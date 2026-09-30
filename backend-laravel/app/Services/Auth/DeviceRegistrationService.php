<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\DeviceStatus;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Auth\Integrity\IntegrityVerdict;
use App\Services\Auth\Integrity\IntegrityVerifier;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;

final class DeviceRegistrationService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly Settings $settings,
        private readonly IntegrityVerifier $integrity,
        private readonly DeviceBindingService $binding,
    ) {}

    /** Keyed hash of ANDROID_ID: detects "one phone, two collectors" without storing the raw id. */
    public static function fingerprint(string $androidId): string
    {
        return hash_hmac('sha256', strtolower(trim($androidId)), (string) config('app.key'));
    }

    /**
     * @param  array{public_key: string, android_id: string, model?: string|null, manufacturer?: string|null, os_version?: string|null, app_version?: string|null, integrity_token?: string|null}  $data
     */
    public function register(User $user, array $data): Device
    {
        DevicePublicKey::assertValidP256($data['public_key']);

        $verdict = $this->checkIntegrity($user, $data['integrity_token'] ?? null);
        $fingerprint = self::fingerprint($data['android_id']);

        $device = DB::transaction(function () use ($user, $data, $fingerprint, $verdict): Device {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $boundElsewhere = Device::query()
                ->where('fingerprint_hash', $fingerprint)
                ->where('status', DeviceStatus::Active->value)
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($boundElsewhere) {
                throw new ApiException('DEVICE_ALREADY_BOUND', 'This phone is already registered to another collector.', 409);
            }

            $superseded = Device::query()
                ->where('user_id', $user->id)
                ->where('status', DeviceStatus::PendingApproval->value)
                ->update(['status' => DeviceStatus::Revoked->value, 'revoked_at' => now(), 'revoke_reason' => 'superseded by a newer registration']);

            $device = Device::query()->create([
                'user_id' => $user->id,
                'platform' => 'android',
                'fingerprint_hash' => $fingerprint,
                'public_key_pem' => $data['public_key'],
                'last_integrity_verdict' => $verdict->toArray(),
                'model' => $data['model'] ?? null,
                'manufacturer' => $data['manufacturer'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'status' => DeviceStatus::PendingApproval,
            ]);

            $this->audit->record('DEVICE.REGISTERED', $device, null, ['status' => $device->status->value, 'model' => $device->model], [
                'integrity' => $verdict->status,
                'superseded_pending' => $superseded,
            ]);

            return $device;
        });

        $neverApproved = Device::query()->where('user_id', $user->id)->whereNotNull('approved_at')->doesntExist();

        if ($this->settings->get('device_auto_approve_first') === true && $neverApproved) {
            return $this->binding->approve($device, null);
        }

        return $device;
    }

    private function checkIntegrity(User $user, ?string $token): IntegrityVerdict
    {
        $mode = $this->settings->get('integrity_enforcement');

        if ($mode === 'OFF') {
            return IntegrityVerdict::unchecked('Integrity checks are switched off.');
        }

        $verdict = $this->integrity->verify($token, $user);

        if ($mode === 'ENFORCE' && ! $verdict->passed()) {
            $this->audit->record('DEVICE.INTEGRITY_REJECTED', $user, null, null, ['verdict' => $verdict->toArray()]);

            throw new ApiException('INTEGRITY_CHECK_FAILED', 'This phone did not pass the security check.', 403);
        }

        return $verdict;
    }
}

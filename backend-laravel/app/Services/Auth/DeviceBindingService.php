<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\DeviceStatus;
use App\Exceptions\ApiException;
use App\Models\Device;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditActor;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/** Approves and revokes collector phones. A collector has at most one active phone. */
final class DeviceBindingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function approve(Device $device, ?User $admin): Device
    {
        return DB::transaction(function () use ($device, $admin): Device {
            User::query()->whereKey($device->user_id)->lockForUpdate()->first();
            $device = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            if ($device->status !== DeviceStatus::PendingApproval) {
                throw new ApiException('INVALID_DEVICE_STATE', 'Only a phone waiting for approval can be approved.', 409);
            }

            $actor = $admin !== null ? AuditActor::user($admin) : AuditActor::system();

            Device::query()
                ->where('user_id', $device->user_id)
                ->where('status', DeviceStatus::Active->value)
                ->get()
                ->each(fn (Device $old) => $this->markRevoked($old, $admin, 'replaced by a newly approved phone', $actor));

            $device->forceFill([
                'status' => DeviceStatus::Active,
                'approved_by_user_id' => $admin?->id,
                'approved_at' => now(),
            ])->save();

            $this->audit->record('DEVICE.APPROVED', $device, ['status' => DeviceStatus::PendingApproval->value], ['status' => DeviceStatus::Active->value], [], $actor);

            return $device;
        });
    }

    public function revoke(Device $device, ?User $admin, string $reason): Device
    {
        return DB::transaction(function () use ($device, $admin, $reason): Device {
            $device = Device::query()->whereKey($device->id)->lockForUpdate()->firstOrFail();

            if ($device->status !== DeviceStatus::Revoked) {
                $this->markRevoked($device, $admin, $reason, $admin !== null ? AuditActor::user($admin) : AuditActor::system());
            }

            return $device;
        });
    }

    private function markRevoked(Device $device, ?User $admin, string $reason, AuditActor $actor): void
    {
        $before = ['status' => $device->status->value];

        $device->forceFill([
            'status' => DeviceStatus::Revoked,
            'revoked_by_user_id' => $admin?->id,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
        ])->save();

        PersonalAccessToken::query()->where('device_id', $device->id)->delete();

        $this->audit->record('DEVICE.REVOKED', $device, $before, ['status' => DeviceStatus::Revoked->value, 'reason' => $reason], [], $actor);
    }
}

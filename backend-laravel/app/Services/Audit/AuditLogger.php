<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

/**
 * Writes one audit row. Call it inside the same DB transaction as the change it describes,
 * so the change and its record commit or roll back together (spec §18.7).
 */
final class AuditLogger
{
    private const REDACTED = '[REDACTED]';

    /** Keys whose values must never reach the audit trail. */
    private const SECRET_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token', 'token', 'plain_text_token',
        'registration_token', 'two_factor_secret', 'secret', 'api_key', 'private_key', 'otp',
        'pan', 'pan_encrypted', 'account_number', 'account_number_encrypted',
    ];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     * @param  array<string, mixed>  $meta
     */
    public function record(
        string $action,
        ?Model $entity = null,
        ?array $before = null,
        ?array $after = null,
        array $meta = [],
        ?AuditActor $actor = null,
    ): AuditLog {
        $actor ??= AuditActor::current();
        $request = request();
        $userAgent = $actor->isHttp() ? Str::limit((string) $request->userAgent(), 255, '') : '';

        return AuditLog::query()->create([
            'occurred_at' => Carbon::now('UTC'),
            'actor_user_id' => $actor->userId,
            'actor_role' => $actor->role,
            'actor_type' => $actor->type->value,
            'action' => $action,
            'entity_type' => $entity !== null ? class_basename($entity) : null,
            'entity_id' => $entity !== null ? $this->entityId($entity) : null,
            'before' => $this->redact($before),
            'after' => $this->redact($after),
            'ip' => $actor->isHttp() ? $request->ip() : null,
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'device_id' => $request->attributes->get('device_id'),
            'request_id' => Context::get('request_id'),
            'meta' => $meta === [] ? null : $this->redact($meta),
        ]);
    }

    private function entityId(Model $entity): string
    {
        $attributes = $entity->getAttributes();

        return (string) (array_key_exists('public_id', $attributes) ? $attributes['public_id'] : $entity->getKey());
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     * @return array<array-key, mixed>|null
     */
    private function redact(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
                $data[$key] = self::REDACTED;
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}

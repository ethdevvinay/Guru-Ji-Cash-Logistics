<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogService
{
    public static function log(
        string $action,
        string $entityType,
        int $entityId,
        ?array $before = null,
        ?array $after = null,
        ?Request $request = null
    ): AuditLog {
        $user = $request?->user();
        $actorId = $user?->id;
        $actorRole = $user?->role ?? 'SYSTEM';

        return AuditLog::create([
            'actor_user_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_state' => $before,
            'after_state' => $after,
            'ip_address' => $request?->ip() ?? request()?->ip(),
            'user_agent' => $request?->userAgent() ?? request()?->userAgent(),
            'device_id' => $request?->header('X-Device-Id') ?? request()?->header('X-Device-Id'),
            'correlation_id' => (string) Str::uuid(),
            'created_at' => now(),
        ]);
    }

    /**
     * Log high-priority cybersecurity and anomaly alerts.
     */
    public static function logSecurityAlert(string $incidentCode, string $description, ?array $context = null): AuditLog
    {
        $request = request();
        $user = $request?->user();

        return AuditLog::create([
            'actor_user_id'  => $user?->id,
            'actor_role'     => $user?->role ?? 'SYSTEM',
            'action'         => 'SECURITY_ALERT:' . $incidentCode,
            'entity_type'    => 'SecurityIncident',
            'entity_id'      => 0,
            'before_state'   => null,
            'after_state'    => array_merge(['description' => $description], $context ?? []),
            'ip_address'     => $request?->ip(),
            'user_agent'     => $request?->userAgent(),
            'device_id'      => $request?->header('X-Device-Id'),
            'correlation_id' => (string) Str::uuid(),
            'created_at'     => now(),
        ]);
    }
}

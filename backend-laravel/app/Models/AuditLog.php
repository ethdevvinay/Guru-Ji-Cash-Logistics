<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Append-only audit record (spec §18.7). Hash-chain columns are written only by AuditSealer
 * through the query builder; the model itself refuses every update and delete.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'occurred_at', 'actor_user_id', 'actor_role', 'actor_type', 'action', 'entity_type', 'entity_id',
        'before', 'after', 'ip', 'user_agent', 'device_id', 'request_id', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'sealed_at' => 'immutable_datetime',
            'before' => 'array',
            'after' => 'array',
            'meta' => 'array',
            'actor_user_id' => 'integer',
            'device_id' => 'integer',
            'seal_seq' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Audit records are append-only.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Audit records are append-only.');
        });
    }
}

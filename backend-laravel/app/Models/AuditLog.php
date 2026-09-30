<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * Enforce strict database immutability for compliance and audit integrity.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \RuntimeException("FINANCIAL AUDIT INTEGRITY VIOLATION: Audit log records are strictly immutable and cannot be modified.");
        });

        static::deleting(function () {
            throw new \RuntimeException("FINANCIAL AUDIT INTEGRITY VIOLATION: Audit log records are permanent and cannot be deleted.");
        });
    }

    protected $fillable = [
        'actor_user_id',
        'actor_role',
        'action',
        'entity_type',
        'entity_id',
        'before_state',
        'after_state',
        'ip_address',
        'user_agent',
        'device_id',
        'correlation_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'before_state' => 'array',
            'after_state' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_user_id',
        'title',
        'message',
        'priority',
        'target_role',
        'is_flash_modal',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'is_flash_modal' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function isActive(): bool
    {
        $now = now();
        return $this->starts_at <= $now && ($this->expires_at === null || $this->expires_at >= $now);
    }
}

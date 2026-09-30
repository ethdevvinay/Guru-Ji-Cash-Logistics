<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PickupRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'request_code',
        'retailer_id',
        'zone_id',
        'requested_amount_paise',
        'status',
        'broadcast_started_at',
        'broadcast_expires_at',
        'shop_lat',
        'shop_lng',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount_paise' => 'integer',
            'broadcast_started_at' => 'datetime',
            'broadcast_expires_at' => 'datetime',
            'shop_lat' => 'decimal:8',
            'shop_lng' => 'decimal:8',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(PickupAssignment::class);
    }

    public function isBroadcasting(): bool
    {
        return $this->status === 'BROADCASTING' &&
            $this->broadcast_expires_at &&
            $this->broadcast_expires_at->isFuture();
    }
}

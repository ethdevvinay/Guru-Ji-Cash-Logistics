<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'collector_id',
        'device_id',
        'latitude',
        'longitude',
        'battery_percent',
        'cash_holding_paise',
        'active_pickup_id',
        'status',
        'acknowledged_by_admin_id',
        'resolved_notes',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'battery_percent' => 'integer',
            'cash_holding_paise' => 'integer',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class, 'active_pickup_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_admin_id');
    }
}

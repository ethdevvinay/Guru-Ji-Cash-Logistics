<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PickupAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_request_id',
        'collector_id',
        'accepted_at',
        'arrived_at',
        'geofence_verified_at',
        'distance_at_arrival_m',
        'status',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'geofence_verified_at' => 'datetime',
            'distance_at_arrival_m' => 'decimal:2',
        ];
    }

    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function cashCollection(): HasOne
    {
        return $this->hasOne(CashCollection::class);
    }
}

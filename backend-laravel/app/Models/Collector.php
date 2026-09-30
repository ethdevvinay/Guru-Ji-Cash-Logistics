<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collector extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'collector_code',
        'bike_number',
        'zone_id',
        'float_limit_paise',
        'current_float_paise',
        'duty_status',
        'battery_percent',
        'current_lat',
        'current_lng',
        'last_location_at',
    ];

    protected function casts(): array
    {
        return [
            'float_limit_paise' => 'integer',
            'current_float_paise' => 'integer',
            'battery_percent' => 'integer',
            'current_lat' => 'decimal:8',
            'current_lng' => 'decimal:8',
            'last_location_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function dutySessions(): HasMany
    {
        return $this->hasMany(DutySession::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CollectorLocation::class);
    }

    public function pickupAssignments(): HasMany
    {
        return $this->hasMany(PickupAssignment::class);
    }

    public function cashCollections(): HasMany
    {
        return $this->hasMany(CashCollection::class);
    }

    public function vaultTransactions(): HasMany
    {
        return $this->hasMany(VaultTransaction::class);
    }

    public function sosAlerts(): HasMany
    {
        return $this->hasMany(SosAlert::class);
    }

    public function hasFloatCapacity(int $amountPaise): bool
    {
        return ($this->current_float_paise + $amountPaise) <= $this->float_limit_paise;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Retailer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'shop_name',
        'owner_name',
        'zone_id',
        'address',
        'latitude',
        'longitude',
        'outstanding_paise',
        'gstin',
        'pan_number',
        'is_kyc_verified',
        'is_blocked',
        'last_outstanding_alert_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'outstanding_paise' => 'integer',
            'is_kyc_verified' => 'boolean',
            'is_blocked' => 'boolean',
            'last_outstanding_alert_at' => 'datetime',
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

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function pickupRequests(): HasMany
    {
        return $this->hasMany(PickupRequest::class);
    }

    public function cashCollections(): HasMany
    {
        return $this->hasMany(CashCollection::class);
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function rechargeTransactions(): HasMany
    {
        return $this->hasMany(RechargeTransaction::class);
    }

    public function bbpsTransactions(): HasMany
    {
        return $this->hasMany(BbpsTransaction::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(Penalty::class);
    }

    public function hasActivePickupRequest(): bool
    {
        return $this->pickupRequests()
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED', 'EXPIRED', 'FAILED'])
            ->exists();
    }
}

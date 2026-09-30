<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KycStatus;
use App\Enums\RecordStatus;
use App\Enums\RetailerStatus;
use App\Enums\ShopRole;
use App\Models\Concerns\HasPublicId;
use Database\Factories\RetailerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Retailer extends Model
{
    /** @use HasFactory<RetailerFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'retailer_code', 'shop_name', 'owner_name', 'mobile', 'alt_mobile', 'address', 'landmark', 'city', 'pincode',
        'lat', 'lng', 'zone_id', 'territory_id', 'default_collector_id', 'gstin', 'pan_encrypted', 'kyc_status', 'status',
        'penalty_amount_paise', 'penalty_collector_share_paise',
    ];

    protected $hidden = ['pan_encrypted'];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'pan_encrypted' => 'encrypted',
            'kyc_status' => KycStatus::class,
            'status' => RetailerStatus::class,
            'penalty_amount_paise' => 'integer',
            'penalty_collector_share_paise' => 'integer',
        ];
    }

    /** @return HasMany<RetailerUser, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(RetailerUser::class);
    }

    /** @return HasOne<RetailerUser, $this> */
    public function owner(): HasOne
    {
        return $this->hasOne(RetailerUser::class)
            ->where('shop_role', ShopRole::Owner->value)
            ->where('status', RecordStatus::Active->value);
    }

    /** @return BelongsTo<Zone, $this> */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** @return BelongsTo<Territory, $this> */
    public function territory(): BelongsTo
    {
        return $this->belongsTo(Territory::class);
    }

    /** @return BelongsTo<Collector, $this> */
    public function defaultCollector(): BelongsTo
    {
        return $this->belongsTo(Collector::class, 'default_collector_id');
    }

    /** @return HasMany<RetailerLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(RetailerLocation::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CashCollection extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection_code',
        'pickup_assignment_id',
        'collector_id',
        'retailer_id',
        'requested_amount_paise',
        'collected_amount_paise',
        'shortfall_amount_paise',
        'is_partial',
        'collector_approved_at',
        'retailer_confirmed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount_paise' => 'integer',
            'collected_amount_paise' => 'integer',
            'shortfall_amount_paise' => 'integer',
            'is_partial' => 'boolean',
            'collector_approved_at' => 'datetime',
            'retailer_confirmed_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(PickupAssignment::class, 'pickup_assignment_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function denominations(): HasOne
    {
        return $this->hasOne(CashDenomination::class);
    }

    public function receipt(): HasOne
    {
        return $this->hasOne(Receipt::class);
    }
}

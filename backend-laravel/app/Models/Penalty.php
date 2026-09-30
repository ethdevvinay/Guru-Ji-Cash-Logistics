<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Penalty extends Model
{
    use HasFactory;

    protected $fillable = [
        'retailer_id',
        'pickup_request_id',
        'amount_paise',
        'collector_share_paise',
        'admin_share_paise',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'collector_share_paise' => 'integer',
            'admin_share_paise' => 'integer',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
    }

    public function waiver(): HasOne
    {
        return $this->hasOne(PenaltyWaiver::class);
    }
}

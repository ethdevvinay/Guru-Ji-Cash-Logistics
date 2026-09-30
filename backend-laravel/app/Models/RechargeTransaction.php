<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RechargeTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'retailer_id',
        'operator',
        'mobile_number',
        'circle',
        'amount_paise',
        'operator_ref_id',
        'status',
        'response_payload',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'response_payload' => 'array',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }
}

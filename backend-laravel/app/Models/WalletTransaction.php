<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_ref',
        'wallet_id',
        'retailer_id',
        'type',
        'amount_paise',
        'opening_balance_paise',
        'closing_balance_paise',
        'status',
        'source_id',
        'source_type',
        'idempotency_key',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'amount_paise' => 'integer',
            'opening_balance_paise' => 'integer',
            'closing_balance_paise' => 'integer',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}

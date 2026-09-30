<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'retailer_id',
        'balance_paise',
        'locked_balance_paise',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'balance_paise' => 'integer',
            'locked_balance_paise' => 'integer',
        ];
    }

    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function getBalanceInRupeesAttribute(): float
    {
        return $this->balance_paise / 100;
    }
}

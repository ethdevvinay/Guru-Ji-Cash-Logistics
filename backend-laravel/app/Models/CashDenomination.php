<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDenomination extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_collection_id',
        'count_500',
        'count_200',
        'count_100',
        'count_50',
        'count_20',
        'count_10',
        'count_coins',
        'total_notes',
        'calculated_amount_paise',
    ];

    protected function casts(): array
    {
        return [
            'count_500' => 'integer',
            'count_200' => 'integer',
            'count_100' => 'integer',
            'count_50' => 'integer',
            'count_20' => 'integer',
            'count_10' => 'integer',
            'count_coins' => 'integer',
            'total_notes' => 'integer',
            'calculated_amount_paise' => 'integer',
        ];
    }

    public function cashCollection(): BelongsTo
    {
        return $this->belongsTo(CashCollection::class);
    }
}

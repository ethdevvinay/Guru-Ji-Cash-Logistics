<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VaultBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_code',
        'admin_user_id',
        'total_cash_counted_paise',
        'total_collectors_settled',
        'status',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_cash_counted_paise' => 'integer',
            'total_collectors_settled' => 'integer',
            'closed_at' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VaultTransaction::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(BankDeposit::class);
    }
}

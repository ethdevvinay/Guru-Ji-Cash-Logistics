<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankDeposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'vault_batch_id',
        'bank_account_id',
        'allocated_amount_paise',
        'deposit_date',
        'utr_number',
        'deposit_slip_url',
        'reconciliation_status',
        'verified_by_admin_id',
    ];

    protected function casts(): array
    {
        return [
            'allocated_amount_paise' => 'integer',
            'deposit_date' => 'date',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(VaultBatch::class, 'vault_batch_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_admin_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'vault_batch_id',
        'collector_id',
        'amount_handed_over_paise',
        'denomination_breakdown',
        'collector_float_before_paise',
        'collector_float_after_paise',
        'digital_signoff_by_admin_id',
        'verified_by_cash_machine',
    ];

    protected function casts(): array
    {
        return [
            'amount_handed_over_paise' => 'integer',
            'denomination_breakdown' => 'array',
            'collector_float_before_paise' => 'integer',
            'collector_float_after_paise' => 'integer',
            'verified_by_cash_machine' => 'boolean',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(VaultBatch::class, 'vault_batch_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'digital_signoff_by_admin_id');
    }
}

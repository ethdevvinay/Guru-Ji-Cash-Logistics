<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_number',
        'cash_collection_id',
        'qr_verification_token',
        'thermal_payload_escpos',
        'pdf_storage_path',
        'whatsapp_delivered_at',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'whatsapp_delivered_at' => 'datetime',
            'printed_at' => 'datetime',
        ];
    }

    public function cashCollection(): BelongsTo
    {
        return $this->belongsTo(CashCollection::class);
    }
}

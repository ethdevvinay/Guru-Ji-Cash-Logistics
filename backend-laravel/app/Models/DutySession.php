<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DutySession extends Model
{
    use HasFactory;

    protected $fillable = [
        'collector_id',
        'punch_in_at',
        'punch_in_lat',
        'punch_in_lng',
        'punch_in_odometer_km',
        'punch_out_at',
        'punch_out_lat',
        'punch_out_lng',
        'punch_out_odometer_km',
        'total_collected_paise',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'punch_in_at' => 'datetime',
            'punch_out_at' => 'datetime',
            'punch_in_lat' => 'decimal:8',
            'punch_in_lng' => 'decimal:8',
            'punch_out_lat' => 'decimal:8',
            'punch_out_lng' => 'decimal:8',
            'punch_in_odometer_km' => 'decimal:2',
            'punch_out_odometer_km' => 'decimal:2',
            'total_collected_paise' => 'integer',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }
}

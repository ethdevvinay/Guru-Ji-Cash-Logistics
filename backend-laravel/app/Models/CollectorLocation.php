<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'collector_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'battery_percent',
        'accuracy_meters',
        'is_mock_detected',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'speed_kmh' => 'decimal:2',
            'battery_percent' => 'integer',
            'accuracy_meters' => 'decimal:2',
            'is_mock_detected' => 'boolean',
            'recorded_at' => 'datetime',
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(Collector::class);
    }
}

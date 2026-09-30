<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CollectorStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\CollectorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Collector extends Model
{
    /** @use HasFactory<CollectorFactory> */
    use HasFactory, HasPublicId, SoftDeletes;

    protected $fillable = [
        'user_id', 'collector_code', 'employee_id', 'vehicle_type', 'vehicle_number', 'float_limit_paise',
        'status', 'emergency_contact_name', 'emergency_contact_mobile',
    ];

    protected function casts(): array
    {
        return ['status' => CollectorStatus::class, 'float_limit_paise' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Zone, $this> */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class, 'collector_zones')->withPivot('is_primary');
    }
}

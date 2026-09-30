<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Enums\TerritoryType;
use Database\Factories\TerritoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Territory extends Model
{
    /** @use HasFactory<TerritoryFactory> */
    use HasFactory;

    protected $fillable = ['parent_id', 'type', 'name', 'code', 'status'];

    protected function casts(): array
    {
        return ['type' => TerritoryType::class, 'status' => RecordStatus::class];
    }

    /** @return BelongsTo<Territory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Territory::class, 'parent_id');
    }

    /** @return HasMany<Territory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Territory::class, 'parent_id');
    }

    /** @return HasMany<Zone, $this> */
    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}

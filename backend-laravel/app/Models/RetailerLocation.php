<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LocationSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only history of a shop's GPS pin. */
class RetailerLocation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['retailer_id', 'lat', 'lng', 'source', 'reason', 'changed_by_user_id', 'effective_from'];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'source' => LocationSource::class,
            'effective_from' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Retailer, $this> */
    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }
}

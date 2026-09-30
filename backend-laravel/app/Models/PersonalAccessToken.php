<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/** Sanctum token that remembers the phone it is bound to (collector sessions). */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected function casts(): array
    {
        return array_merge(parent::casts(), ['device_id' => 'integer']);
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}

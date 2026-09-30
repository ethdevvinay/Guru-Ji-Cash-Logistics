<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecordStatus;
use App\Enums\ShopRole;
use Database\Factories\RetailerUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A login attached to a shop as OWNER or STAFF (decision D4). Both have the RETAILER role. */
class RetailerUser extends Model
{
    /** @use HasFactory<RetailerUserFactory> */
    use HasFactory;

    protected $fillable = [
        'retailer_id', 'user_id', 'shop_role', 'can_request', 'can_confirm', 'can_spend', 'can_manage_staff',
        'status', 'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'shop_role' => ShopRole::class,
            'status' => RecordStatus::class,
            'can_request' => 'boolean',
            'can_confirm' => 'boolean',
            'can_spend' => 'boolean',
            'can_manage_staff' => 'boolean',
        ];
    }

    /** @return BelongsTo<Retailer, $this> */
    public function retailer(): BelongsTo
    {
        return $this->belongsTo(Retailer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

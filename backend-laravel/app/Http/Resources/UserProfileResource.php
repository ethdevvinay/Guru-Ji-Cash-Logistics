<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class UserProfileResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource;
        $user->loadMissing(['collector', 'shopMembership.retailer']);
        $collector = $user->collector;
        $membership = $user->shopMembership;

        return [
            'id' => $user->public_id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $user->email,
            'role' => $user->role->value,
            'must_change_password' => $user->must_change_password,
            'collector' => $collector === null ? null : [
                'id' => $collector->public_id,
                'code' => $collector->collector_code,
                'vehicle_number' => $collector->vehicle_number,
                'float_limit_paise' => $collector->float_limit_paise,
                'status' => $collector->status->value,
            ],
            'shop' => $membership === null ? null : [
                'id' => $membership->retailer->public_id,
                'name' => $membership->retailer->shop_name,
                'shop_role' => $membership->shop_role->value,
                'can_request' => $membership->can_request,
                'can_confirm' => $membership->can_confirm,
                'can_spend' => $membership->can_spend,
                'can_manage_staff' => $membership->can_manage_staff,
            ],
        ];
    }
}

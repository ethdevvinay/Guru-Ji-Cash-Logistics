<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\RecordStatus;
use App\Enums\RetailerStatus;
use App\Exceptions\ApiException;
use App\Models\RetailerUser;
use App\Models\User;

final class RetailerMembershipGuard
{
    /**
     * The login's active shop membership, with its retailer loaded.
     *
     * @throws ApiException FORBIDDEN when there is no active membership, RETAILER_BLOCKED when the shop is not active
     */
    public function activeMembershipFor(User $user): RetailerUser
    {
        $membership = RetailerUser::query()->with('retailer')->where('user_id', $user->id)->first();

        if ($membership === null || $membership->status !== RecordStatus::Active) {
            throw new ApiException('FORBIDDEN', 'This login is not linked to an active shop.', 403);
        }

        if ($membership->retailer->status !== RetailerStatus::Active) {
            throw new ApiException('RETAILER_BLOCKED', 'This shop is blocked. Contact the operations team.', 403);
        }

        return $membership;
    }
}

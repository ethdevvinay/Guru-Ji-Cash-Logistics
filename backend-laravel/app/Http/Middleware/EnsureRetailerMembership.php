<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Auth\RetailerMembershipGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Every shop endpoint runs behind this: it attaches `retailer_membership` to the request. */
final class EnsureRetailerMembership
{
    public function __construct(private readonly RetailerMembershipGuard $memberships) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->role !== UserRole::Retailer) {
            throw new ApiException('FORBIDDEN', 'You are not allowed to do this.', 403);
        }

        $request->attributes->set('retailer_membership', $this->memberships->activeMembershipFor($user));

        return $next($request);
    }
}

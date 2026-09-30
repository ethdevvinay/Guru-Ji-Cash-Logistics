<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `role:admin` or `role:collector,retailer`. The role comes from the user row, never the request. */
final class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if (! $user->isActive()) {
            throw new ApiException('ACCOUNT_BLOCKED', 'This account is not active. Contact the operations team.', 403);
        }

        if (! in_array($user->role->value, $roles, true)) {
            throw new ApiException('FORBIDDEN', 'You are not allowed to do this.', 403);
        }

        return $next($request);
    }
}

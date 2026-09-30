<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** While a password change is pending, only profile, logout and the change itself are allowed. */
final class EnsurePasswordChanged
{
    private const ALLOWED_ROUTES = ['api.v1.auth.me', 'api.v1.auth.logout', 'api.v1.auth.password.change'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password && ! in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            throw new ApiException('PASSWORD_CHANGE_REQUIRED', 'Please set a new password to continue.', 403);
        }

        return $next($request);
    }
}

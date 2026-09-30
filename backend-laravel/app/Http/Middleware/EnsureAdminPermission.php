<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: `permission:devices.manage`. Super admins pass through Gate::before. */
final class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        if ($user->role !== UserRole::Admin || ! $user->can($permission)) {
            throw new ApiException('FORBIDDEN', 'You are not allowed to do this.', 403);
        }

        return $next($request);
    }
}

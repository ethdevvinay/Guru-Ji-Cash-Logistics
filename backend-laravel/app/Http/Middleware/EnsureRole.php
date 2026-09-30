<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated access.',
                'data' => null,
                'errors' => ['auth' => 'Authentication token required.'],
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userRole = strtoupper($user->role);
        $allowedRoles = array_map('strtoupper', $roles);

        if (!in_array($userRole, $allowedRoles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action for role: ' . $userRole,
                'data' => null,
                'errors' => ['role' => 'Access denied.'],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}

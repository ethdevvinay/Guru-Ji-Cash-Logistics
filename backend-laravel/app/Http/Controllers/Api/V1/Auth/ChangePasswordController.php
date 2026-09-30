<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Api\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class ChangePasswordController
{
    public function __invoke(ChangePasswordRequest $request, AuditLogger $audit): JsonResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        if (! Hash::check($request->string('current_password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }

        DB::transaction(function () use ($user, $request, $audit): void {
            $user->forceFill(['password' => $request->string('password')->toString(), 'must_change_password' => false])->save();

            $current = $user->currentAccessToken();
            $user->tokens()
                ->when($current instanceof PersonalAccessToken, fn ($query) => $query->whereKeyNot($current->getKey()))
                ->delete();

            $audit->record('AUTH.PASSWORD_CHANGED', $user, null, null, ['other_sessions_revoked' => true]);
        });

        return ApiResponse::success(null, 'Password changed.');
    }
}

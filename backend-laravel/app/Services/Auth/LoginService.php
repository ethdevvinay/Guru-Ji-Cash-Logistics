<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\AppClient;
use App\Enums\UserRole;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\Audit\AuditActor;
use App\Services\Audit\AuditLogger;
use App\Support\MobileNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Mobile-app login (spec §18.2). Failures are audited; ten wrong passwords within
 * fifteen minutes lock the account for fifteen minutes.
 */
final class LoginService
{
    public const MAX_FAILURES = 10;

    public const FAILURE_WINDOW_SECONDS = 900;

    public const LOCK_MINUTES = 15;

    private static ?string $dummyHash = null;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TokenIssuer $tokens,
        private readonly RetailerMembershipGuard $memberships,
    ) {}

    public function login(string $mobile, string $password, AppClient $app, Request $request): IssuedToken
    {
        $normalized = MobileNumber::normalize($mobile);
        $user = $normalized === null ? null : User::query()->where('mobile', $normalized)->first();

        if ($user === null) {
            Hash::check($password, self::dummyHash()); // similar response time whether or not the mobile exists
            $this->reject(null, $app, 'unknown_mobile', self::invalidCredentials(), ['mobile' => MobileNumber::mask($normalized ?? $mobile)]);
        }

        if ($user->locked_until !== null && $user->locked_until->isFuture()) {
            $until = $user->locked_until->setTimezone('Asia/Kolkata')->format('h:i A');
            $this->reject($user, $app, 'locked', new ApiException('ACCOUNT_LOCKED', "Too many failed attempts. Try again after {$until} IST.", 401));
        }

        if (! Hash::check($password, $user->password)) {
            $this->recordFailure($user, $app);

            throw self::invalidCredentials();
        }

        if (! $user->isActive()) {
            $this->reject($user, $app, 'inactive', new ApiException('ACCOUNT_BLOCKED', 'This account is not active. Contact the operations team.', 403));
        }

        $this->clearFailures($user);

        $issued = match ($app) {
            AppClient::Retailer => $this->retailerLogin($user, $app),
        };

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->audit->record('AUTH.LOGIN', $user, null, null, ['app' => $app->value], AuditActor::user($user));

        return $issued;
    }

    private function retailerLogin(User $user, AppClient $app): IssuedToken
    {
        if ($user->role !== UserRole::Retailer) {
            $this->reject($user, $app, 'wrong_app', new ApiException('FORBIDDEN', 'This account cannot use the retailer app.', 403));
        }

        try {
            $this->memberships->activeMembershipFor($user);
        } catch (ApiException $e) {
            $this->reject($user, $app, strtolower($e->errorCode), $e);
        }

        return $this->tokens->issueRetailerToken($user);
    }

    private function recordFailure(User $user, AppClient $app): void
    {
        $key = self::failureKey($user);
        RateLimiter::hit($key, self::FAILURE_WINDOW_SECONDS);
        User::query()->whereKey($user->id)->increment('failed_login_count');
        $this->audit->record('AUTH.LOGIN_FAILED', $user, null, null, ['reason' => 'wrong_password', 'app' => $app->value], AuditActor::anonymous());

        if (RateLimiter::attempts($key) >= self::MAX_FAILURES) {
            User::query()->whereKey($user->id)->update(['locked_until' => now()->addMinutes(self::LOCK_MINUTES), 'failed_login_count' => 0]);
            RateLimiter::clear($key);
            $this->audit->record('AUTH.ACCOUNT_LOCKED', $user, null, ['locked_minutes' => self::LOCK_MINUTES], [], AuditActor::system());
        }
    }

    private function clearFailures(User $user): void
    {
        RateLimiter::clear(self::failureKey($user));

        if ($user->failed_login_count !== 0) {
            User::query()->whereKey($user->id)->update(['failed_login_count' => 0]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function reject(?User $user, AppClient $app, string $reason, ApiException $exception, array $meta = []): never
    {
        $this->audit->record('AUTH.LOGIN_FAILED', $user, null, null, array_merge(['reason' => $reason, 'app' => $app->value], $meta), AuditActor::anonymous());

        throw $exception;
    }

    private static function invalidCredentials(): ApiException
    {
        return new ApiException('INVALID_CREDENTIALS', 'Mobile number or password is incorrect.', 401);
    }

    private static function failureKey(User $user): string
    {
        return 'login-failures:'.$user->id;
    }

    private static function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(40));
    }
}

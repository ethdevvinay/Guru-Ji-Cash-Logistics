<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Services\Auth\Integrity\IntegrityVerifier;
use App\Services\Auth\Integrity\UncheckedIntegrityVerifier;
use App\Support\MobileNumber;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IntegrityVerifier::class, UncheckedIntegrityVerifier::class);
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        Role::creating(static function (Role $role): void {
            if (! in_array($role->name, UserRole::values(), true)) {
                throw new LogicException("Only the three platform roles may exist; refusing to create role [{$role->name}].");
            }
        });

        Gate::before(static fn (User $user): ?bool => $user->role === UserRole::Admin && $user->is_super_admin && $user->isActive() ? true : null);

        RateLimiter::for('auth-login', static function (Request $request): Limit {
            $mobile = (string) $request->input('mobile');

            return Limit::perMinute(5)->by((MobileNumber::normalize($mobile) ?? Str::lower($mobile)).'|'.$request->ip());
        });

        RateLimiter::for('device-register', static fn (Request $request): Limit => Limit::perHour(10)->by((string) $request->user()?->getAuthIdentifier()));
    }
}

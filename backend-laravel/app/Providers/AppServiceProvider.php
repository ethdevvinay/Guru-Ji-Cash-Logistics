<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use LogicException;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        Role::creating(static function (Role $role): void {
            if (! in_array($role->name, UserRole::values(), true)) {
                throw new LogicException("Only the three platform roles may exist; refusing to create role [{$role->name}].");
            }
        });

        Gate::before(static fn (User $user): ?bool => $user->role === UserRole::Admin && $user->is_super_admin && $user->isActive() ? true : null);
    }
}

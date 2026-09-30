<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Concerns\HasPublicId;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LogicException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasPublicId, HasRoles, Notifiable, SoftDeletes;

    /** Admin permissions always resolve against the web guard, whichever guard authenticated the request. */
    protected $guard_name = 'web';

    protected $fillable = ['name', 'mobile', 'email', 'password', 'role', 'status', 'must_change_password'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret'];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'must_change_password' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'failed_login_count' => 'integer',
            'locked_until' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(static function (User $user): void {
            $user->assignRole(Role::findOrCreate($user->role->value, 'web'));
        });

        static::updating(static function (User $user): void {
            if ($user->isDirty('role')) {
                throw new LogicException("A user's role cannot be changed after creation.");
            }
        });
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /** @return HasOne<Collector, $this> */
    public function collector(): HasOne
    {
        return $this->hasOne(Collector::class);
    }

    /** @return HasOne<RetailerUser, $this> */
    public function shopMembership(): HasOne
    {
        return $this->hasOne(RetailerUser::class);
    }
}

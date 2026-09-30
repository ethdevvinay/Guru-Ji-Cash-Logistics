<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'mobile' => '+91'.fake()->unique()->numerify('9#########'),
            'email' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role' => UserRole::Retailer,
            'status' => UserStatus::Active,
            'must_change_password' => false,
        ];
    }

    public function admin(bool $super = false): static
    {
        return $this->state(fn (): array => [
            'role' => UserRole::Admin,
            'email' => fake()->unique()->safeEmail(),
            'is_super_admin' => $super,
        ]);
    }

    public function collector(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Collector]);
    }

    public function retailer(): static
    {
        return $this->state(fn (): array => ['role' => UserRole::Retailer]);
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Blocked]);
    }

    public function mustChangePassword(): static
    {
        return $this->state(fn (): array => ['must_change_password' => true]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeviceStatus;
use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /** A valid P-256 public key for tests that never sign; signing tests pass their own via withPublicKey(). */
    private const FIXTURE_PUBLIC_KEY = <<<'PEM'
        -----BEGIN PUBLIC KEY-----
        MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEh8UQ+Env3Wg7jpXgk9VCoEgBGe8+
        3G/6/JG7k2VNIB/L9bS/UTz3F1MvM06U+iGYwi61YwSr8EWvtxFxn8TY6w==
        -----END PUBLIC KEY-----
        PEM;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->collector(),
            'platform' => 'android',
            'fingerprint_hash' => hash('sha256', fake()->unique()->uuid()),
            'public_key_pem' => self::FIXTURE_PUBLIC_KEY,
            'model' => 'Redmi Note 13',
            'manufacturer' => 'Xiaomi',
            'os_version' => '14',
            'app_version' => '1.0.0',
            'status' => DeviceStatus::PendingApproval,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => DeviceStatus::Active, 'approved_at' => now()]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => DeviceStatus::PendingApproval]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['status' => DeviceStatus::Revoked, 'revoked_at' => now(), 'revoke_reason' => 'test']);
    }

    public function withPublicKey(string $pem): static
    {
        return $this->state(fn (): array => ['public_key_pem' => $pem]);
    }
}

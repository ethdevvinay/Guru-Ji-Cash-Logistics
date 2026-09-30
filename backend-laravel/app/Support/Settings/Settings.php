<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class Settings
{
    private const CACHE_KEY = 'settings.values';

    public function __construct(private readonly AuditLogger $audit) {}

    public function get(string $key): mixed
    {
        $definition = SettingsCatalog::get($key);
        $stored = $this->stored();

        return array_key_exists($key, $stored) ? $stored[$key] : $definition->default;
    }

    public function set(string $key, mixed $value, ?User $by = null): void
    {
        $definition = SettingsCatalog::get($key);
        $definition->validate($value);
        $this->assertConsistent($key, $value);

        DB::transaction(function () use ($key, $value, $definition, $by): void {
            $before = $this->get($key);

            Setting::query()->updateOrCreate(['key' => $key], [
                'value' => $value,
                'type' => $definition->type->value,
                'group' => $definition->group,
                'description' => $definition->description,
                'updated_by_user_id' => $by?->id,
            ]);

            $this->audit->record('SETTINGS.UPDATED', null, ['key' => $key, 'value' => $before], ['key' => $key, 'value' => $value]);
        });

        Cache::forget(self::CACHE_KEY);
        DB::afterCommit(static fn () => Cache::forget(self::CACHE_KEY));
    }

    /** Rules that span two settings. */
    private function assertConsistent(string $key, mixed $value): void
    {
        if ($key === 'penalty_collector_share_paise' && $value > $this->get('penalty_amount_paise')) {
            throw new InvalidArgumentException("Setting [{$key}] cannot exceed penalty_amount_paise.");
        }

        if ($key === 'penalty_amount_paise' && $value < $this->get('penalty_collector_share_paise')) {
            throw new InvalidArgumentException("Setting [{$key}] cannot be below penalty_collector_share_paise.");
        }
    }

    /**
     * Values are cached only outside transactions, so a rolled-back change can never be cached.
     *
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        $load = static fn (): array => Setting::query()->pluck('value', 'key')->all();

        return DB::transactionLevel() > 0 ? $load() : Cache::rememberForever(self::CACHE_KEY, $load);
    }
}

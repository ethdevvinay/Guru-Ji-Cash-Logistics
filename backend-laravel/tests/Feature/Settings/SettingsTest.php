<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings\Settings;
use App\Support\Settings\SettingsCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): Settings
    {
        return app(Settings::class);
    }

    public function test_every_key_from_spec_appendix_a_has_a_definition(): void
    {
        $expected = [
            'broadcast_window_seconds', 'broadcast_rounds', 'broadcast_mode', 'broadcast_radius_km',
            'default_collector_head_start_seconds', 'pending_max_minutes', 'max_concurrent_jobs', 'approach_radius_m',
            'max_gps_accuracy_m', 'max_fix_age_seconds', 'max_implied_speed_kmh', 'offer_location_fresh_seconds',
            'unlock_token_minutes', 'offline_authorization_minutes', 'integrity_enforcement', 'float_default_limit_paise',
            'float_cap_mode', 'float_alert_percent', 'block_punch_in_with_carried_float', 'punch_in_geofence_mode',
            'free_cancel_seconds', 'penalty_amount_paise', 'penalty_collector_share_paise', 'penalty_requires_dispatch',
            'penalty_when_wallet_short', 'allow_over_collection', 'enabled_denominations_paise', 'min_request_paise',
            'confirmation_reminder_minutes', 'confirmation_admin_alert_minutes', 'outstanding_reminder_time',
            'outstanding_reminder_antispam_hours', 'sos_nearby_radius_km', 'gps_retention_days', 'deposit_slip_required',
            'deposit_unverified_alert_days', 'maker_checker_threshold_paise', 'device_auto_approve_first',
            'collector_token_hours', 'retailer_token_days', 'admin_idle_minutes', 'min_app_version_collector',
            'min_app_version_retailer',
        ];

        $this->assertEqualsCanonicalizing($expected, array_keys(SettingsCatalog::all()));
    }

    public function test_unset_settings_return_the_catalog_default(): void
    {
        $this->assertSame(90, $this->settings()->get('broadcast_window_seconds'));
        $this->assertSame('PROJECTED', $this->settings()->get('float_cap_mode'));
        $this->assertSame([50000, 20000, 10000, 5000, 2000, 1000], $this->settings()->get('enabled_denominations_paise'));
        $this->assertFalse($this->settings()->get('device_auto_approve_first'));
    }

    public function test_a_change_is_stored_returned_and_audited_with_before_and_after(): void
    {
        $admin = User::factory()->admin()->create();

        $this->settings()->set('broadcast_window_seconds', 120, $admin);

        $this->assertSame(120, $this->settings()->get('broadcast_window_seconds'));
        $log = AuditLog::query()->where('action', 'SETTINGS.UPDATED')->sole();
        $this->assertSame(['key' => 'broadcast_window_seconds', 'value' => 90], $log->before);
        $this->assertSame(['key' => 'broadcast_window_seconds', 'value' => 120], $log->after);
        $this->assertDatabaseHas('settings', ['key' => 'broadcast_window_seconds', 'updated_by_user_id' => $admin->id]);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidValues(): array
    {
        return [
            'text for a number' => ['broadcast_window_seconds', '90'],
            'number below minimum' => ['broadcast_window_seconds', 5],
            'number above maximum' => ['broadcast_window_seconds', 3600],
            'unknown enum value' => ['float_cap_mode', 'SOMETIMES'],
            'string for a boolean' => ['device_auto_approve_first', 'yes'],
            'note not allowed' => ['enabled_denominations_paise', [50000, 30000]],
            'duplicate notes' => ['enabled_denominations_paise', [50000, 50000]],
            'empty note list' => ['enabled_denominations_paise', []],
            'bad time' => ['outstanding_reminder_time', '9am'],
            'bad version' => ['min_app_version_collector', 'v2'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected(string $key, mixed $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->set($key, $value);
    }

    public function test_unknown_keys_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings()->get('distributor_commission');
    }

    public function test_the_collector_share_can_never_exceed_the_penalty(): void
    {
        $this->settings()->set('penalty_amount_paise', 10000);
        $this->settings()->set('penalty_collector_share_paise', 7000);

        try {
            $this->settings()->set('penalty_collector_share_paise', 12000);
            $this->fail('A share above the penalty was accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame(7000, $this->settings()->get('penalty_collector_share_paise'));
        }

        $this->expectException(InvalidArgumentException::class);
        $this->settings()->set('penalty_amount_paise', 6000);
    }

    public function test_valid_time_and_version_values_are_accepted(): void
    {
        $this->settings()->set('outstanding_reminder_time', '21:30');
        $this->settings()->set('min_app_version_retailer', '1.4.0');

        $this->assertSame('21:30', $this->settings()->get('outstanding_reminder_time'));
        $this->assertSame('1.4.0', $this->settings()->get('min_app_version_retailer'));
    }
}

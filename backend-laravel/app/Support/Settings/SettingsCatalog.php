<?php

declare(strict_types=1);

namespace App\Support\Settings;

use App\Enums\SettingType;
use InvalidArgumentException;

/** Every runtime setting with its default and bounds (spec Appendix A). The geofence radius is deliberately absent. */
final class SettingsCatalog
{
    /** @var array<string, SettingDefinition>|null */
    private static ?array $definitions = null;

    public static function get(string $key): SettingDefinition
    {
        return self::all()[$key] ?? throw new InvalidArgumentException("Unknown setting [{$key}].");
    }

    /** @return array<string, SettingDefinition> */
    public static function all(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        $int = SettingType::Int;
        $bool = SettingType::Bool;
        $enum = SettingType::Enum;

        $definitions = [
            new SettingDefinition('broadcast_window_seconds', $int, 90, 'operations', 'Seconds each pickup offer stays open for collectors.', 30, 300),
            new SettingDefinition('broadcast_rounds', $int, 2, 'operations', 'Broadcast rounds before a pickup waits for an admin.', 1, 5),
            new SettingDefinition('broadcast_mode', $enum, 'ZONE_OR_RADIUS', 'operations', 'Which collectors receive an offer.', options: ['ZONE', 'RADIUS', 'ZONE_OR_RADIUS', 'ZONE_AND_RADIUS']),
            new SettingDefinition('broadcast_radius_km', $int, 5, 'operations', 'First-round offer radius around the shop, in km.', 1, 50),
            new SettingDefinition('default_collector_head_start_seconds', $int, 0, 'operations', 'Seconds the default collector sees an offer before others.', 0, 60),
            new SettingDefinition('pending_max_minutes', $int, 60, 'operations', 'Minutes an unassigned pickup waits before it expires.', 5, 720),
            new SettingDefinition('max_concurrent_jobs', $int, 1, 'operations', 'Active jobs one collector may hold at a time.', 1, 5),
            new SettingDefinition('punch_in_geofence_mode', $enum, 'ANY_ASSIGNED_ZONE', 'operations', 'Where a collector may punch in.', options: ['NONE', 'ANY_ASSIGNED_ZONE', 'HUB']),
            new SettingDefinition('allow_over_collection', $bool, false, 'operations', 'Allow collecting more than the requested amount.'),
            new SettingDefinition('enabled_denominations_paise', SettingType::IntList, [50000, 20000, 10000, 5000, 2000, 1000], 'operations', 'Notes offered by the denomination calculator, in paise.', 1000, 200000, [200000, 50000, 20000, 10000, 5000, 2000, 1000]),
            new SettingDefinition('min_request_paise', $int, 100000, 'operations', 'Smallest pickup a retailer may request, in paise.', 10000, 10000000),
            new SettingDefinition('approach_radius_m', $int, 250, 'geofence', 'Distance at which the retailer is told the collector is arriving, in metres.', 120, 2000),
            new SettingDefinition('max_gps_accuracy_m', $int, 50, 'geofence', 'Worst GPS accuracy accepted for the cash-desk unlock, in metres.', 5, 100),
            new SettingDefinition('max_fix_age_seconds', $int, 30, 'geofence', 'Oldest GPS fix accepted for the cash-desk unlock, in seconds.', 5, 120),
            new SettingDefinition('max_implied_speed_kmh', $int, 150, 'geofence', 'Speed between fixes above which a jump is treated as spoofing, in km/h.', 60, 300),
            new SettingDefinition('offer_location_fresh_seconds', $int, 120, 'geofence', "A collector's last fix must be this recent to receive offers, in seconds.", 30, 600),
            new SettingDefinition('unlock_token_minutes', $int, 15, 'geofence', 'Minutes a cash-desk unlock stays valid.', 1, 60),
            new SettingDefinition('offline_authorization_minutes', $int, 60, 'geofence', 'Minutes an offline collection authorization stays valid.', 5, 240),
            new SettingDefinition('float_default_limit_paise', $int, 10000000, 'float', 'Default cash limit for new collectors, in paise.', 100000, 100000000),
            new SettingDefinition('float_cap_mode', $enum, 'PROJECTED', 'float', 'How the float cap is applied.', options: ['PROJECTED', 'CURRENT']),
            new SettingDefinition('float_alert_percent', $int, 90, 'float', 'Float level that raises a high-cash alert, in percent.', 50, 100),
            new SettingDefinition('block_punch_in_with_carried_float', $bool, false, 'float', 'Refuse punch-in while cash from a previous day is still held.'),
            new SettingDefinition('free_cancel_seconds', $int, 180, 'penalty', 'Seconds after creation during which a retailer can cancel free.', 0, 1800),
            new SettingDefinition('penalty_amount_paise', $int, 5000, 'penalty', 'Default late-cancellation charge, in paise.', 5000, 20000),
            new SettingDefinition('penalty_collector_share_paise', $int, 3500, 'penalty', "Collector's share of the charge, in paise.", 0, 20000),
            new SettingDefinition('penalty_requires_dispatch', $bool, true, 'penalty', 'Charge only if a collector had accepted the pickup.'),
            new SettingDefinition('penalty_when_wallet_short', $enum, 'CHARGE_TO_UDHAR', 'penalty', 'What happens when the wallet cannot cover the charge.', options: ['CHARGE_TO_UDHAR', 'WAIVE']),
            new SettingDefinition('confirmation_reminder_minutes', SettingType::IntList, [5, 30], 'notifications', 'Minutes after submission when the retailer is reminded to confirm.', 1, 240),
            new SettingDefinition('confirmation_admin_alert_minutes', $int, 60, 'notifications', 'Minutes after submission when admins are alerted.', 5, 1440),
            new SettingDefinition('outstanding_reminder_time', SettingType::Time, '09:00', 'notifications', 'Daily outstanding reminder time (IST).'),
            new SettingDefinition('outstanding_reminder_antispam_hours', $int, 24, 'notifications', 'Minimum hours between outstanding reminders to one retailer.', 1, 168),
            new SettingDefinition('sos_nearby_radius_km', $int, 3, 'notifications', 'Colleagues within this distance are alerted on SOS, in km.', 1, 20),
            new SettingDefinition('gps_retention_days', $int, 90, 'retention', 'Days raw GPS points are kept.', 7, 365),
            new SettingDefinition('deposit_slip_required', $bool, true, 'finance', 'A deposit slip upload is required to record a bank deposit.'),
            new SettingDefinition('deposit_unverified_alert_days', $int, 2, 'finance', 'Days after which an unverified deposit is flagged.', 1, 30),
            new SettingDefinition('maker_checker_threshold_paise', $int, 1000000, 'finance', 'Manual adjustments above this need a second admin, in paise.', 0, 100000000),
            new SettingDefinition('integrity_enforcement', $enum, 'LOG', 'security', 'Play Integrity handling.', options: ['OFF', 'LOG', 'ENFORCE']),
            new SettingDefinition('device_auto_approve_first', $bool, false, 'security', "Approve a collector's first phone automatically."),
            new SettingDefinition('collector_token_hours', $int, 14, 'security', 'Hours a collector session lasts.', 1, 24),
            new SettingDefinition('retailer_token_days', $int, 30, 'security', 'Days a retailer session lasts.', 1, 90),
            new SettingDefinition('admin_idle_minutes', $int, 30, 'security', 'Minutes of inactivity before an admin is logged out.', 5, 240),
            new SettingDefinition('min_app_version_collector', SettingType::Version, '1.0.0', 'app', 'Oldest collector app version allowed.'),
            new SettingDefinition('min_app_version_retailer', SettingType::Version, '1.0.0', 'app', 'Oldest retailer app version allowed.'),
        ];

        $map = [];
        foreach ($definitions as $definition) {
            $map[$definition->key] = $definition;
        }

        return self::$definitions = $map;
    }
}

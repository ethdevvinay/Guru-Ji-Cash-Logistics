<?php

namespace App\Services;

use App\Models\Collector;
use App\Models\Retailer;
use InvalidArgumentException;

class GeofenceVerificationService
{
    public const MAX_ALLOWED_DISTANCE_METERS = 100.0;
    public const MAX_GPS_AGE_SECONDS = 30;
    public const MAX_PLAUSIBLE_SPEED_KMH = 120.0;
    public const MAX_ACCEPTABLE_ACCURACY_METERS = 30.0;

    /**
     * Compute Haversine distance in meters between two lat/lng pairs.
     */
    public static function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // in meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }

    /**
     * Verify if collector is currently within 100m geofence of retailer and passes anti-mock checks.
     */
    public function verifyGeofence(
        Collector $collector,
        Retailer $retailer,
        float $currentLat,
        float $currentLng,
        bool $isMockReported = false,
        ?int $timestamp = null,
        ?float $accuracyMeters = null
    ): array {
        // 1. Anti-Mock provider detection
        if ($isMockReported) {
            AuditLogService::logSecurityAlert(
                'MOCK_GPS_DETECTED',
                "Collector {$collector->collector_code} attempted geofence unlock with active mock location provider.",
                ['lat' => $currentLat, 'lng' => $currentLng]
            );

            return [
                'is_valid' => false,
                'distance_meters' => null,
                'error' => 'Security Violation: Mock GPS provider active. Please disable fake location apps.',
            ];
        }

        // 2. Hardware GPS Accuracy Sanitization
        if ($accuracyMeters !== null) {
            if ($accuracyMeters <= 0.0 || $accuracyMeters > self::MAX_ACCEPTABLE_ACCURACY_METERS) {
                return [
                    'is_valid' => false,
                    'distance_meters' => null,
                    'error' => "GPS accuracy is insufficient (±{$accuracyMeters}m). Accuracy must be under 30m with a clear sky view.",
                ];
            }
        }

        // 3. Timestamp freshness check (Replay attack prevention)
        if ($timestamp !== null) {
            $age = abs(time() - $timestamp);
            if ($age > self::MAX_GPS_AGE_SECONDS) {
                return [
                    'is_valid' => false,
                    'distance_meters' => null,
                    'error' => "Stale GPS reading received ({$age}s old). Re-acquire live GPS telemetry.",
                ];
            }
        }

        // 4. Teleportation & Impossible Speed Anomaly Detection
        if ($collector->current_lat && $collector->current_lng && $collector->last_location_at) {
            $secondsElapsed = max(1, now()->diffInSeconds($collector->last_location_at));
            if ($secondsElapsed < 300) { // Only check if last ping was within 5 minutes
                $metersTraveled = self::calculateDistanceMeters(
                    (float) $collector->current_lat,
                    (float) $collector->current_lng,
                    $currentLat,
                    $currentLng
                );

                // Speed in km/h: (meters / seconds) * 3.6
                $inferredSpeedKmh = ($metersTraveled / $secondsElapsed) * 3.6;

                if ($inferredSpeedKmh > self::MAX_PLAUSIBLE_SPEED_KMH && $metersTraveled > 200) {
                    AuditLogService::logSecurityAlert(
                        'GPS_TELEPORTATION_ANOMALY',
                        "Collector {$collector->collector_code} moved {$metersTraveled}m in {$secondsElapsed}s ({$inferredSpeedKmh} km/h).",
                        [
                            'from_lat' => $collector->current_lat,
                            'from_lng' => $collector->current_lng,
                            'to_lat'   => $currentLat,
                            'to_lng'   => $currentLng,
                            'speed_kmh'=> round($inferredSpeedKmh, 1),
                        ]
                    );

                    return [
                        'is_valid' => false,
                        'distance_meters' => null,
                        'error' => 'Security Violation: Teleportation detected. Impossible movement velocity detected by server.',
                    ];
                }
            }
        }

        // 5. Compute authoritative distance to shop
        $distance = self::calculateDistanceMeters(
            $currentLat,
            $currentLng,
            (float) $retailer->latitude,
            (float) $retailer->longitude
        );

        // 6. Threshold verification <= 100m
        $isWithin = $distance <= self::MAX_ALLOWED_DISTANCE_METERS;

        return [
            'is_valid' => $isWithin,
            'distance_meters' => $distance,
            'threshold_meters' => self::MAX_ALLOWED_DISTANCE_METERS,
            'error' => $isWithin
                ? null
                : "Geofence Locked: You are {$distance}m from shop. Cash desk unlocks only within " . self::MAX_ALLOWED_DISTANCE_METERS . "m.",
        ];
    }
}

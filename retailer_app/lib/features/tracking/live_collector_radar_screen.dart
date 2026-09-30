import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/theme.dart';

class LiveCollectorRadarScreen extends StatefulWidget {
  final String collectorName;
  final String pickupCode;

  const LiveCollectorRadarScreen({
    super.key,
    required this.collectorName,
    required this.pickupCode,
  });

  @override
  State<LiveCollectorRadarScreen> createState() => _LiveCollectorRadarScreenState();
}

class _LiveCollectorRadarScreenState extends State<LiveCollectorRadarScreen> {
  int _distanceMeters = 380;
  int _etaMinutes = 3;
  Timer? _trackerTimer;

  @override
  void initState() {
    super.initState();
    // Simulate live telemetry updates every 4 seconds
    _trackerTimer = Timer.periodic(const Duration(seconds: 4), (timer) {
      if (_distanceMeters > 45) {
        setState(() {
          _distanceMeters -= 45;
          _etaMinutes = (_distanceMeters / 120).ceil();
        });
      } else {
        _trackerTimer?.cancel();
      }
    });
  }

  @override
  void dispose() {
    _trackerTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isInsideGeofence = _distanceMeters <= 100;

    return Scaffold(
      appBar: AppBar(
        title: const Text('Live Collector Radar'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            // Proximity Radar Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    Stack(
                      alignment: Alignment.center,
                      children: [
                        Container(
                          width: 140,
                          height: 140,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: isInsideGeofence
                                ? AppTheme.success.withOpacity(0.08)
                                : AppTheme.primary.withOpacity(0.08),
                            border: Border.all(
                              color: isInsideGeofence ? AppTheme.success : AppTheme.primary,
                              width: 1.5,
                            ),
                          ),
                        ),
                        Container(
                          width: 90,
                          height: 90,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: isInsideGeofence
                                ? AppTheme.success.withOpacity(0.15)
                                : AppTheme.primary.withOpacity(0.15),
                          ),
                        ),
                        const Icon(
                          Icons.two_wheeler_rounded,
                          size: 38,
                          color: AppTheme.primary,
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    Text(
                      '$_distanceMeters meters away',
                      style: TextStyle(
                        fontSize: 26,
                        fontWeight: FontWeight.extrabold,
                        color: isInsideGeofence ? AppTheme.success : const Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      isInsideGeofence
                          ? '🎯 Collector has arrived inside your 100m geofence'
                          : 'Approaching via Subhash Chowk • ETA ~$_etaMinutes mins',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: isInsideGeofence ? FontWeight.bold : FontWeight.normal,
                        color: isInsideGeofence ? AppTheme.success : const Color(0xFF64748B),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 18),

            // Collector Profile Details
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'ASSIGNED EXECUTIVE',
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: AppTheme.success.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: const Text(
                            'VERIFIED FLEET',
                            style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.success),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      widget.collectorName,
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                    ),
                    const SizedBox(height: 4),
                    const Text('Vehicle: Hero Splendor (HR-12-AE-4421) • ID: COL-104', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Calling Rahul Kumar (+919812000001)...')),
                              );
                            },
                            icon: const Icon(Icons.phone_rounded, size: 18),
                            label: const Text('Call Collector'),
                            style: OutlinedButton.styleFrom(
                              minimumSize: const Size.fromHeight(44),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () {
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Opening Google Maps location share...')),
                              );
                            },
                            icon: const Icon(Icons.share_location_rounded, size: 18),
                            label: const Text('Share Shop Pin'),
                            style: OutlinedButton.styleFrom(
                              minimumSize: const Size.fromHeight(44),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';
import '../cash_collection/cash_collection_screen.dart';

class NavigationScreen extends StatefulWidget {
  final Map<String, dynamic> pickupJob;

  const NavigationScreen({super.key, required this.pickupJob});

  @override
  State<NavigationScreen> createState() => _NavigationScreenState();
}

class _NavigationScreenState extends State<NavigationScreen> {
  bool _isCheckingGeofence = false;
  // Simulated collector coordinates for demo:
  // Retailer is at 28.8955, 76.6066
  // Distance 1 = 145m away (28.8967, 76.6066)
  // Distance 2 = 38m away (28.8958, 76.6066)
  double _currentLat = 28.8958;
  double _currentLng = 76.6066;
  int _simulatedDistanceMeters = 38;

  Future<void> _handleVerifyArrival() async {
    setState(() => _isCheckingGeofence = true);

    try {
      final res = await ApiService.verifyGeofence(
        widget.pickupJob['id'],
        _currentLat,
        _currentLng,
      );

      setState(() => _isCheckingGeofence = false);

      if (res['success'] == true && res['data']?['is_within_geofence'] == true) {
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('🎯 100m Geofence Verified (${res['data']['distance_meters']}m)! Cash collection unlocked.'),
            backgroundColor: AppTheme.success,
          ),
        );
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => CashCollectionScreen(
              pickupJob: widget.pickupJob,
              verifiedDistanceMeters: res['data']['distance_meters'] ?? _simulatedDistanceMeters,
            ),
          ),
        );
      } else {
        if (!mounted) return;
        final distance = res['data']?['distance_meters'] ?? _simulatedDistanceMeters;
        showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.shield_outlined, color: AppTheme.danger),
                SizedBox(width: 8),
                Text('Geofence Violation', style: TextStyle(fontSize: 16)),
              ],
            ),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '⛔ Physical Cash Collection is locked. You are currently $distance meters away from the registered retailer coordinates.',
                  style: const TextStyle(fontSize: 14),
                ),
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppTheme.danger.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Row(
                    children: [
                      Icon(Icons.lock_rounded, size: 18, color: AppTheme.danger),
                      SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          'Server-Side Rule: Proximity must be <= 100 meters.',
                          style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.danger),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(ctx).pop(),
                child: const Text('Understand'),
              ),
            ],
          ),
        );
      }
    } catch (e) {
      setState(() => _isCheckingGeofence = false);
      // Fallback local logic for prototype simulation
      if (_simulatedDistanceMeters <= 100) {
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => CashCollectionScreen(
              pickupJob: widget.pickupJob,
              verifiedDistanceMeters: _simulatedDistanceMeters,
            ),
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Geofence Locked: $_simulatedDistanceMeters meters away (Limit: 100m)'), backgroundColor: AppTheme.danger),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('En Route to Retailer'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Proximity & Radar Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: _simulatedDistanceMeters <= 100
                                    ? AppTheme.success.withOpacity(0.1)
                                    : AppTheme.warning.withOpacity(0.1),
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                _simulatedDistanceMeters <= 100 ? Icons.check_circle_rounded : Icons.navigation_rounded,
                                color: _simulatedDistanceMeters <= 100 ? AppTheme.success : AppTheme.warning,
                                size: 24,
                              ),
                            ),
                            const SizedBox(width: 12),
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  'Proximity to Shop',
                                  style: TextStyle(fontSize: 12, color: Colors.grey.shade600),
                                ),
                                Text(
                                  '$_simulatedDistanceMeters meters',
                                  style: TextStyle(
                                    fontSize: 22,
                                    fontWeight: FontWeight.extrabold,
                                    color: _simulatedDistanceMeters <= 100 ? AppTheme.success : const Color(0xFF0F172A),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: _simulatedDistanceMeters <= 100
                                ? AppTheme.success.withOpacity(0.1)
                                : const Color(0xFFF1F5F9),
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            _simulatedDistanceMeters <= 100 ? 'GEOFENCE UNLOCKED' : '100m LOCK ACTIVE',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                              color: _simulatedDistanceMeters <= 100 ? AppTheme.success : const Color(0xFF64748B),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    LinearProgressIndicator(
                      value: ((200 - _simulatedDistanceMeters) / 200).clamp(0.0, 1.0),
                      backgroundColor: const Color(0xFFE2E8F0),
                      color: _simulatedDistanceMeters <= 100 ? AppTheme.success : AppTheme.warning,
                      minHeight: 8,
                      borderRadius: BorderRadius.circular(4),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Demo Distance Toggle Selector
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                children: [
                  const Icon(Icons.tune_rounded, size: 18, color: Color(0xFF64748B)),
                  const SizedBox(width: 8),
                  const Text('GPS Position Test:', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF475569))),
                  const Spacer(),
                  ChoiceChip(
                    label: const Text('145m (Outside)'),
                    selected: _simulatedDistanceMeters == 145,
                    onSelected: (val) {
                      if (val) {
                        setState(() {
                          _simulatedDistanceMeters = 145;
                          _currentLat = 28.8968;
                        });
                      }
                    },
                  ),
                  const SizedBox(width: 8),
                  ChoiceChip(
                    label: const Text('38m (Inside)'),
                    selected: _simulatedDistanceMeters == 38,
                    onSelected: (val) {
                      if (val) {
                        setState(() {
                          _simulatedDistanceMeters = 38;
                          _currentLat = 28.8958;
                        });
                      }
                    },
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Retailer Details
            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      widget.pickupJob['retailer_name'] ?? 'Radhe Digital Store',
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      widget.pickupJob['address'] ?? 'Shop 14, Main Market, Subhash Chowk, Rohtak',
                      style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Cash Request:', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
                        Text(
                          '₹${(widget.pickupJob['amount_rupees'] as num).toStringAsFixed(0)}',
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                        ),
                      ],
                    ),
                    const Divider(height: 24),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () {
                              ScaffoldMessenger.of(context).showSnackBar(
                                SnackBar(content: Text('Dialing ${widget.pickupJob['mobile'] ?? '+919812000004'}...')),
                              );
                            },
                            icon: const Icon(Icons.phone_rounded, size: 18),
                            label: const Text('Call Retailer'),
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
                                const SnackBar(content: Text('Launching Google Maps turn-by-turn navigation...')),
                              );
                            },
                            icon: const Icon(Icons.map_rounded, size: 18),
                            label: const Text('Google Maps'),
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

            const SizedBox(height: 24),

            // Arrival Trigger Action
            ElevatedButton.icon(
              onPressed: _isCheckingGeofence ? null : _handleVerifyArrival,
              icon: _isCheckingGeofence
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.verified_rounded),
              label: Text(_isCheckingGeofence ? 'Verifying Coordinates...' : 'I HAVE ARRIVED (CHECK GEOFENCE)'),
              style: ElevatedButton.styleFrom(
                backgroundColor: _simulatedDistanceMeters <= 100 ? AppTheme.primary : const Color(0xFF64748B),
                minimumSize: const Size.fromHeight(54),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

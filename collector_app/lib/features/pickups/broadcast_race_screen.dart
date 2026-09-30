import 'dart:async';
import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';
import 'navigation_screen.dart';

class BroadcastRaceScreen extends StatefulWidget {
  const BroadcastRaceScreen({super.key});

  @override
  State<BroadcastRaceScreen> createState() => _BroadcastRaceScreenState();
}

class _BroadcastRaceScreenState extends State<BroadcastRaceScreen> with SingleTickerProviderStateMixin {
  int _secondsRemaining = 90;
  Timer? _timer;
  bool _isLocking = false;

  // Mock initial broadcast list fetched or synced via API
  List<Map<String, dynamic>> _broadcastJobs = [
    {
      'id': 1,
      'code': 'REQ-2026-0001',
      'retailer_name': 'Radhe Digital Store',
      'owner_name': 'Radheshyam Gupta',
      'mobile': '+91 98120 00004',
      'address': 'Shop 14, Main Market, Subhash Chowk, Rohtak',
      'amount_rupees': 40000.0,
      'distance_meters': 380,
      'eta_minutes': 3,
      'lat': 28.8955,
      'lng': 76.6066,
    },
    {
      'id': 2,
      'code': 'REQ-2026-0002',
      'retailer_name': 'Sharma Telecom & CSC',
      'owner_name': 'Vipin Sharma',
      'mobile': '+91 98120 00005',
      'address': 'Bus Stand Road, Model Town, Rohtak',
      'amount_rupees': 25000.0,
      'distance_meters': 1200,
      'eta_minutes': 7,
      'lat': 28.8910,
      'lng': 76.5980,
    },
  ];

  @override
  void initState() {
    super.initState();
    _startCountdown();
    _loadLiveBroadcasts();
  }

  void _startCountdown() {
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (_secondsRemaining > 0) {
        setState(() => _secondsRemaining--);
      } else {
        _timer?.cancel();
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('⏱ Broadcast race window expired (90s). Fetching next queue...'),
              backgroundColor: AppTheme.warning,
            ),
          );
        }
      }
    });
  }

  Future<void> _loadLiveBroadcasts() async {
    try {
      final res = await ApiService.getBroadcasts();
      if (res['success'] == true && res['data'] != null && (res['data'] as List).isNotEmpty) {
        setState(() {
          _broadcastJobs = List<Map<String, dynamic>>.from(res['data']);
        });
      }
    } catch (_) {}
  }

  Future<void> _handleAcceptPickup(Map<String, dynamic> job) async {
    setState(() => _isLocking = true);
    try {
      final res = await ApiService.acceptPickup(job['id']);
      setState(() => _isLocking = false);

      if (res['success'] == true) {
        _timer?.cancel();
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('⚡ Race Lock Acquired! You are assigned to this pickup.'),
            backgroundColor: AppTheme.success,
          ),
        );
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => NavigationScreen(pickupJob: job),
          ),
        );
      } else {
        if (!mounted) return;
        // Race condition: another collector won
        showDialog(
          context: context,
          builder: (ctx) => AlertDialog(
            title: const Row(
              children: [
                Icon(Icons.lock_clock_rounded, color: AppTheme.danger),
                SizedBox(width: 8),
                Text('Pickup Claimed'),
              ],
            ),
            content: Text(
              res['message'] ?? 'Another collector accepted this request milliseconds before you. Atomic race lock was awarded to the fastest responder.',
              style: const TextStyle(fontSize: 14),
            ),
            actions: [
              TextButton(
                onPressed: () {
                  Navigator.of(ctx).pop();
                  _loadLiveBroadcasts();
                },
                child: const Text('Back to Queue'),
              ),
            ],
          ),
        );
      }
    } catch (e) {
      setState(() => _isLocking = false);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Error: $e'), backgroundColor: AppTheme.danger),
      );
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('90-Second Broadcast Race'),
        actions: [
          Center(
            child: Container(
              margin: const EdgeInsets.only(right: 16),
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: _secondsRemaining < 20 ? AppTheme.danger.withOpacity(0.1) : AppTheme.primary.withOpacity(0.1),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: _secondsRemaining < 20 ? AppTheme.danger : AppTheme.primary,
                ),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.timer_outlined,
                    size: 16,
                    color: _secondsRemaining < 20 ? AppTheme.danger : AppTheme.primary,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    '${_secondsRemaining}s',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      fontSize: 13,
                      color: _secondsRemaining < 20 ? AppTheme.danger : AppTheme.primary,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
      body: _broadcastJobs.isEmpty
          ? Center(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Icon(Icons.radar_rounded, size: 64, color: Color(0xFF94A3B8)),
                  const SizedBox(height: 16),
                  const Text(
                    'Listening for live pickups...',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                  ),
                  const SizedBox(height: 6),
                  const Text('New retailer requests will buzz here instantly.', style: TextStyle(color: Color(0xFF64748B), fontSize: 13)),
                  const SizedBox(height: 24),
                  OutlinedButton.icon(
                    onPressed: _loadLiveBroadcasts,
                    icon: const Icon(Icons.refresh),
                    label: const Text('Refresh Radar'),
                  ),
                ],
              ),
            )
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: _broadcastJobs.length,
              itemBuilder: (context, index) {
                final job = _broadcastJobs[index];
                return Card(
                  margin: const EdgeInsets.only(bottom: 16),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: const BorderSide(color: Color(0xFFE2E8F0)),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.between,
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                job['code'] ?? 'REQ-LIVE',
                                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                              ),
                            ),
                            Text(
                              '${job['distance_meters']}m • ~${job['eta_minutes']} mins',
                              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.primary),
                            ),
                          ],
                        ),
                        const SizedBox(height: 12),
                        Text(
                          job['retailer_name'] ?? 'Retailer Store',
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          '${job['owner_name']} • ${job['address']}',
                          style: const TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                        ),
                        const SizedBox(height: 16),
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: const Color(0xFFF8FAFC),
                            borderRadius: BorderRadius.circular(10),
                            border: Border.all(color: const Color(0xFFE2E8F0)),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.between,
                            children: [
                              const Text('Cash to Collect:', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
                              Text(
                                '₹${(job['amount_rupees'] as num).toStringAsFixed(0)}',
                                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.extrabold, color: Color(0xFF0F172A)),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 18),
                        Row(
                          children: [
                            Expanded(
                              flex: 1,
                              child: OutlinedButton(
                                onPressed: () {
                                  setState(() => _broadcastJobs.removeAt(index));
                                },
                                style: OutlinedButton.styleFrom(
                                  minimumSize: const Size.fromHeight(48),
                                  foregroundColor: const Color(0xFF64748B),
                                  side: const BorderSide(color: Color(0xFFCBD5E1)),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                                child: const Text('Pass'),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              flex: 2,
                              child: ElevatedButton.icon(
                                onPressed: _isLocking ? null : () => _handleAcceptPickup(job),
                                icon: _isLocking
                                    ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                                    : const Icon(Icons.flash_on_rounded),
                                label: Text(_isLocking ? 'Locking...' : 'ACCEPT & LOCK'),
                                style: ElevatedButton.styleFrom(
                                  backgroundColor: AppTheme.success,
                                  foregroundColor: Colors.white,
                                  minimumSize: const Size.fromHeight(48),
                                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
    );
  }
}

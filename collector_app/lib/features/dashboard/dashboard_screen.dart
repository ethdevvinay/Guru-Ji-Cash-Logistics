import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';
import '../duty/duty_screen.dart';
import '../pickups/broadcast_race_screen.dart';

class CollectorDashboardScreen extends StatefulWidget {
  final Map<String, dynamic> userData;

  const CollectorDashboardScreen({super.key, required this.userData});

  @override
  State<CollectorDashboardScreen> createState() => _CollectorDashboardScreenState();
}

class _CollectorDashboardScreenState extends State<CollectorDashboardScreen> {
  double _currentFloat = 40000.0;
  final double _floatLimit = 100000.0;
  bool _isOnDuty = true;
  int _sosTapCount = 0;

  @override
  void initState() {
    super.initState();
    _refreshFloatMeter();
  }

  Future<void> _refreshFloatMeter() async {
    try {
      final res = await ApiService.getFloatMeter();
      if (res['success'] == true) {
        setState(() {
          _currentFloat = (res['data']['current_float_rupees'] as num).toDouble();
        });
      }
    } catch (_) {}
  }

  void _handleSosTap() {
    _sosTapCount++;
    if (_sosTapCount >= 3) {
      _sosTapCount = 0;
      ApiService.triggerSos(28.8955, 76.6066);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('🚨 SILENT SOS TRIGGERED! Admin & Supervisors alerted with live GPS.'),
          backgroundColor: AppTheme.danger,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final floatPercent = (_currentFloat / _floatLimit).clamp(0.0, 1.0);
    final isLocked = _currentFloat >= _floatLimit;

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(widget.userData['name'] ?? 'Rahul Kumar', style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
            Text('ID: ${widget.userData['collector']?['collector_code'] ?? 'COL-104'} • Rohtak Urban',
                style: const TextStyle(fontSize: 11, color: Color(0xFF64748B))),
          ],
        ),
        actions: [
          // Silent SOS Shield Icon
          IconButton(
            onPressed: _handleSosTap,
            icon: const Icon(Icons.shield_rounded, color: AppTheme.danger),
            tooltip: 'Tap 3 times for Silent SOS',
          ),
          IconButton(
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => DutyScreen(isOnDuty: _isOnDuty)),
              );
            },
            icon: const Icon(Icons.two_wheeler_rounded, color: AppTheme.primary),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refreshFloatMeter,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Live Float Meter Card (Spec PDF Page 3 Section A)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(18),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.between,
                        children: [
                          const Text(
                            'LIVE CASH FLOAT METER (बैग मीटर)',
                            style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: isLocked ? AppTheme.danger.withOpacity(0.1) : AppTheme.success.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              isLocked ? 'CAP LOCKED' : 'CAPACITY ACTIVE',
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.bold,
                                color: isLocked ? AppTheme.danger : AppTheme.success,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.baseline,
                        textBaseline: TextBaseline.alphabetic,
                        children: [
                          Text(
                            '₹${_currentFloat.toStringAsFixed(0)}',
                            style: const TextStyle(fontSize: 28, fontWeight: FontWeight.extrabold, color: Color(0xFF0F172A)),
                          ),
                          const SizedBox(width: 6),
                          Text(
                            '/ ₹${_floatLimit.toStringAsFixed(0)} Limit',
                            style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: Color(0xFF64748B)),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      LinearProgressIndicator(
                        value: floatPercent,
                        backgroundColor: const Color(0xFFE2E8F0),
                        color: isLocked ? AppTheme.danger : (floatPercent > 0.8 ? AppTheme.warning : AppTheme.success),
                        minHeight: 8,
                        borderRadius: BorderRadius.circular(4),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        isLocked
                            ? '⚠ Safety limit of ₹1,00,000 reached. Handover cash to Central Vault to unlock new pickups.'
                            : 'Available bag capacity: ₹${(_floatLimit - _currentFloat).toStringAsFixed(0)}',
                        style: TextStyle(
                          fontSize: 12,
                          color: isLocked ? AppTheme.danger : const Color(0xFF64748B),
                          fontWeight: isLocked ? FontWeight.bold : FontWeight.normal,
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 18),

              // Available Jobs Broadcast Banner
              Card(
                color: AppTheme.primary,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Row(
                    children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.2),
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: const Center(
                          child: Icon(Icons.sensors_rounded, color: Colors.white, size: 28),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              '90s Broadcast Race Feed',
                              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white),
                            ),
                            Text(
                              isLocked ? 'Locked until Vault deposit' : 'View live incoming requests',
                              style: TextStyle(fontSize: 12, color: Colors.white.withOpacity(0.8)),
                            ),
                          ],
                        ),
                      ),
                      ElevatedButton(
                        onPressed: isLocked
                            ? null
                            : () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) => const BroadcastRaceScreen(),
                                  ),
                                );
                              },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: AppTheme.primary,
                          minimumSize: const Size(80, 42),
                          elevation: 0,
                        ),
                        child: const Text('Open Desk'),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 24),
              const Text(
                'QUICK ACTIONS',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B), letterSpacing: 0.5),
              ),
              const SizedBox(height: 12),

              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => DutyScreen(isOnDuty: _isOnDuty)),
                        );
                      },
                      icon: const Icon(Icons.speed_rounded, size: 20),
                      label: const Text('Duty Punch'),
                      style: OutlinedButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          const SnackBar(content: Text('Proceed to Rohtak Central Vault Desk for evening closing.')),
                        );
                      },
                      icon: const Icon(Icons.account_balance_rounded, size: 20),
                      label: const Text('Vault Handover'),
                      style: OutlinedButton.styleFrom(
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
      ),
    );
  }
}

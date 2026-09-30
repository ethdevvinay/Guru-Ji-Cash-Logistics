import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';
import '../tracking/live_collector_radar_screen.dart';
import '../confirmation/cash_confirmation_screen.dart';
import '../utilities/recharge_bbps_screen.dart';
import '../passbook/passbook_screen.dart';

class RetailerDashboardScreen extends StatefulWidget {
  final Map<String, dynamic> userData;

  const RetailerDashboardScreen({super.key, required this.userData});

  @override
  State<RetailerDashboardScreen> createState() => _RetailerDashboardScreenState();
}

class _RetailerDashboardScreenState extends State<RetailerDashboardScreen> {
  double _walletBalance = 45200.0;
  double _outstandingBalance = 12500.0;
  bool _hasActivePickup = true;
  String _activePickupCode = 'REQ-2026-0001';
  double _activePickupAmount = 40000.0;
  String _activePickupStatus = 'RETAILER_PENDING_CONFIRMATION'; // Or EN_ROUTE, ACCEPTED
  String _assignedCollector = 'Rahul Kumar (COL-104)';

  @override
  void initState() {
    super.initState();
    _refreshDashboard();
  }

  Future<void> _refreshDashboard() async {
    try {
      final res = await ApiService.getDashboard();
      if (res['success'] == true && res['data'] != null) {
        setState(() {
          _walletBalance = (res['data']['wallet_balance_rupees'] as num?)?.toDouble() ?? _walletBalance;
          _outstandingBalance = (res['data']['outstanding_rupees'] as num?)?.toDouble() ?? _outstandingBalance;
          if (res['data']['active_pickup'] != null) {
            _hasActivePickup = true;
            _activePickupCode = res['data']['active_pickup']['pickup_code'] ?? _activePickupCode;
            _activePickupAmount = (res['data']['active_pickup']['requested_amount_rupees'] as num?)?.toDouble() ?? _activePickupAmount;
            _activePickupStatus = res['data']['active_pickup']['status'] ?? _activePickupStatus;
          }
        });
      }
    } catch (_) {}
  }

  void _openPickupRequestModal() {
    if (_hasActivePickup && _activePickupStatus != 'COMPLETED') {
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Row(
            children: [
              Icon(Icons.lock_rounded, color: AppTheme.danger),
              SizedBox(width: 8),
              Text('Request Locked', style: TextStyle(fontSize: 16)),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '⛔ Active Pickup ($_activePickupCode) is currently in progress for ₹${_activePickupAmount.toStringAsFixed(0)}.',
                style: const TextStyle(fontSize: 14),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppTheme.danger.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text(
                  'Anti-Duplicate Rule: Retailers cannot initiate a new cash pickup until the previous collection cycle is fully confirmed and settled.',
                  style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.danger),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.of(ctx).pop(), child: const Text('OK')),
          ],
        ),
      );
      return;
    }

    // Open Bottom Sheet to Create Pickup
    final amountCtrl = TextEditingController(text: '40000');
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => Padding(
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 24,
          bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Request Cash Pickup',
                  style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                ),
                IconButton(onPressed: () => Navigator.of(ctx).pop(), icon: const Icon(Icons.close)),
              ],
            ),
            const SizedBox(height: 4),
            const Text(
              'Nearest active collector will receive 90s broadcast alert.',
              style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
            ),
            const SizedBox(height: 18),
            const Text('SELECT AMOUNT (रुपये)', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF475569))),
            const SizedBox(height: 10),
            Wrap(
              spacing: 8,
              children: [10000, 25000, 40000, 50000].map((amt) {
                return ChoiceChip(
                  label: Text('₹$amt'),
                  selected: amountCtrl.text == amt.toString(),
                  onSelected: (val) {
                    if (val) amountCtrl.text = amt.toString();
                  },
                );
              }).toList(),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: amountCtrl,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                prefixText: '₹ ',
                labelText: 'Enter Cash Amount',
                filled: true,
                fillColor: const Color(0xFFF8FAFC),
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),
            const SizedBox(height: 20),
            ElevatedButton.icon(
              onPressed: () async {
                final amt = double.tryParse(amountCtrl.text) ?? 40000.0;
                Navigator.of(ctx).pop();
                try {
                  final res = await ApiService.createPickupRequest(
                    amountRupees: amt,
                    lat: 28.8955,
                    lng: 76.6066,
                  );
                  if (res['success'] == true) {
                    setState(() {
                      _hasActivePickup = true;
                      _activePickupCode = res['data']?['pickup_code'] ?? 'REQ-2026-NEW';
                      _activePickupAmount = amt;
                      _activePickupStatus = 'BROADCASTING';
                    });
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('⚡ Pickup Broadcast Initiated! 90-second race active for nearby collectors.'),
                        backgroundColor: AppTheme.success,
                      ),
                    );
                  }
                } catch (_) {
                  setState(() {
                    _hasActivePickup = true;
                    _activePickupCode = 'REQ-2026-LIVE';
                    _activePickupAmount = amt;
                    _activePickupStatus = 'BROADCASTING';
                  });
                }
              },
              icon: const Icon(Icons.flash_on_rounded),
              label: const Text('DISPATCH 90s BROADCAST'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                minimumSize: const Size.fromHeight(50),
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              widget.userData['retailer']?['store_name'] ?? 'Radhe Digital Store',
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            Text(
              'ID: ${widget.userData['retailer']?['retailer_code'] ?? 'RET-201'} • Subhash Chowk, Rohtak',
              style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
            ),
          ],
        ),
        actions: [
          IconButton(
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const PassbookScreen()),
              );
            },
            icon: const Icon(Icons.receipt_long_rounded, color: AppTheme.primary),
            tooltip: 'Passbook Ledger',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refreshDashboard,
        child: SingleChildScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.all(18),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 09:00 AM Outstanding Notice (Anti-Spam 24h cron)
              if (_outstandingBalance > 0)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFFBEB),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: const Color(0xFFFDE68A)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.alarm_on_rounded, color: Color(0xFFD97706), size: 22),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'DAILY OUTSTANDING NOTICE (09:00 AM)',
                              style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF92400E)),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Market Float Udhar balance: ₹${_outstandingBalance.toStringAsFixed(0)}. Settle via cash pickup today.',
                              style: const TextStyle(fontSize: 12, color: Color(0xFF78350F)),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),

              // Wallet & Float Dual Card
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'LIVE WALLET BALANCE (वॉलेट राशि)',
                            style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: AppTheme.success.withOpacity(0.1),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text(
                              '100% SETTLED',
                              style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: AppTheme.success),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Text(
                        '₹${_walletBalance.toStringAsFixed(2)}',
                        style: const TextStyle(fontSize: 32, fontWeight: FontWeight.extrabold, color: Color(0xFF0F172A)),
                      ),
                      const Divider(height: 24),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text('Market Float (Udhar)', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                              Text('₹${_outstandingBalance.toStringAsFixed(0)}',
                                  style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Color(0xFFDC2626))),
                            ],
                          ),
                          OutlinedButton.icon(
                            onPressed: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(builder: (_) => const PassbookScreen()),
                              );
                            },
                            icon: const Icon(Icons.history_rounded, size: 16),
                            label: const Text('View Passbook'),
                            style: OutlinedButton.styleFrom(
                              minimumSize: const Size(120, 36),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 18),

              // Active Pickup Job Radar Card
              if (_hasActivePickup)
                Card(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                    side: BorderSide(
                      color: _activePickupStatus == 'RETAILER_PENDING_CONFIRMATION'
                          ? AppTheme.warning
                          : const Color(0xFFE2E8F0),
                      width: _activePickupStatus == 'RETAILER_PENDING_CONFIRMATION' ? 2 : 1,
                    ),
                  ),
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              _activePickupCode,
                              style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                            ),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: _activePickupStatus == 'RETAILER_PENDING_CONFIRMATION'
                                    ? AppTheme.warning.withOpacity(0.12)
                                    : AppTheme.primary.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: Text(
                                _activePickupStatus.replaceAll('_', ' '),
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: _activePickupStatus == 'RETAILER_PENDING_CONFIRMATION'
                                      ? AppTheme.warning
                                      : AppTheme.primary,
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),
                        Row(
                          children: [
                            Container(
                              width: 44,
                              height: 44,
                              decoration: BoxDecoration(
                                color: const Color(0xFFF1F5F9),
                                borderRadius: BorderRadius.circular(12),
                              ),
                              child: const Icon(Icons.two_wheeler_rounded, color: AppTheme.primary, size: 24),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    _assignedCollector,
                                    style: const TextStyle(fontSize: 15, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                                  ),
                                  const Text('Hero Splendor HR-12-AE-4421', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                ],
                              ),
                            ),
                            Text(
                              '₹${_activePickupAmount.toStringAsFixed(0)}',
                              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.extrabold, color: Color(0xFF0F172A)),
                            ),
                          ],
                        ),
                        const SizedBox(height: 16),

                        // Action depending on state
                        if (_activePickupStatus == 'RETAILER_PENDING_CONFIRMATION') ...[
                          ElevatedButton.icon(
                            onPressed: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => CashConfirmationScreen(
                                    pickupCode: _activePickupCode,
                                    amountRupees: _activePickupAmount,
                                    collectorName: _assignedCollector,
                                    onConfirmed: () {
                                      setState(() {
                                        _walletBalance += _activePickupAmount;
                                        _hasActivePickup = false;
                                        _activePickupStatus = 'COMPLETED';
                                      });
                                    },
                                  ),
                                ),
                              );
                            },
                            icon: const Icon(Icons.check_circle_rounded),
                            label: const Text('⚡ 1-TAP ACCEPT & CONFIRM CASH'),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppTheme.success,
                              minimumSize: const Size.fromHeight(48),
                            ),
                          ),
                        ] else ...[
                          OutlinedButton.icon(
                            onPressed: () {
                              Navigator.of(context).push(
                                MaterialPageRoute(
                                  builder: (_) => LiveCollectorRadarScreen(
                                    collectorName: _assignedCollector,
                                    pickupCode: _activePickupCode,
                                  ),
                                ),
                              );
                            },
                            icon: const Icon(Icons.radar_rounded),
                            label: const Text('Track Collector Live Radar'),
                            style: OutlinedButton.styleFrom(
                              minimumSize: const Size.fromHeight(48),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),

              const SizedBox(height: 20),

              // Request Pickup Big Button
              ElevatedButton.icon(
                onPressed: _openPickupRequestModal,
                icon: const Icon(Icons.add_shopping_cart_rounded),
                label: Text(
                  _hasActivePickup && _activePickupStatus != 'COMPLETED'
                      ? 'PICKUP IN PROGRESS (LOCKED)'
                      : 'REQUEST CASH PICKUP (कैश पिकअप अनुरोध)',
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _hasActivePickup && _activePickupStatus != 'COMPLETED'
                      ? const Color(0xFF94A3B8)
                      : AppTheme.primary,
                  minimumSize: const Size.fromHeight(56),
                ),
              ),

              const SizedBox(height: 24),
              const Text(
                'MERCHANT UTILITIES (RELOADED BALANCE)',
                style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B), letterSpacing: 0.5),
              ),
              const SizedBox(height: 12),

              // Recharge & BBPS Grid
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => RechargeBbpsScreen(serviceType: 'RECHARGE', walletBalance: _walletBalance)),
                        );
                      },
                      borderRadius: BorderRadius.circular(14),
                      child: Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: AppTheme.secondary.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Icon(Icons.phone_android_rounded, color: AppTheme.secondary, size: 22),
                            ),
                            const SizedBox(height: 12),
                            const Text('Mobile Recharge', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                            const SizedBox(height: 2),
                            const Text('Jio, Airtel, Vi, BSNL', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: InkWell(
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => RechargeBbpsScreen(serviceType: 'BBPS', walletBalance: _walletBalance)),
                        );
                      },
                      borderRadius: BorderRadius.circular(14),
                      child: Container(
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFE2E8F0)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: AppTheme.primary.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(10),
                              ),
                              child: const Icon(Icons.bolt_rounded, color: AppTheme.primary, size: 22),
                            ),
                            const SizedBox(height: 12),
                            const Text('BBPS Bill Pay', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                            const SizedBox(height: 2),
                            const Text('Electricity, Water, Gas', style: TextStyle(fontSize: 11, color: Color(0xFF64748B))),
                          ],
                        ),
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

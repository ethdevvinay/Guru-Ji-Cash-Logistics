import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';
import '../printer/thermal_receipt_screen.dart';

class CashCollectionScreen extends StatefulWidget {
  final Map<String, dynamic> pickupJob;
  final int verifiedDistanceMeters;

  const CashCollectionScreen({
    super.key,
    required this.pickupJob,
    required this.verifiedDistanceMeters,
  });

  @override
  State<CashCollectionScreen> createState() => _CashCollectionScreenState();
}

class _CashCollectionScreenState extends State<CashCollectionScreen> {
  // Denominations count map
  final Map<int, int> _denominations = {
    500: 70, // 70 * 500 = 35,000
    200: 20, // 20 * 200 = 4,000
    100: 10, // 10 * 100 = 1,000
    50: 0,
    20: 0,
    10: 0,
    1: 0, // coins
  };

  bool _isPartialCollection = false;
  bool _isSubmitting = false;

  int get _totalNotesCount {
    int count = 0;
    _denominations.forEach((val, qty) {
      if (val > 1) count += qty;
    });
    return count;
  }

  double get _calculatedTotalRupees {
    double total = 0;
    _denominations.forEach((val, qty) {
      total += (val * qty);
    });
    return total;
  }

  double get _requestedAmountRupees {
    return (widget.pickupJob['amount_rupees'] as num?)?.toDouble() ?? 40000.0;
  }

  double get _shortfallRupees {
    final diff = _requestedAmountRupees - _calculatedTotalRupees;
    return diff > 0 ? diff : 0.0;
  }

  void _updateQuantity(int denom, int delta) {
    setState(() {
      final current = _denominations[denom] ?? 0;
      final updated = (current + delta).clamp(0, 9999);
      _denominations[denom] = updated;
    });
  }

  Future<void> _handleSubmitCash() async {
    final collected = _calculatedTotalRupees;
    if (collected <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter at least one currency denomination.'), backgroundColor: AppTheme.danger),
      );
      return;
    }

    setState(() => _isSubmitting = true);

    try {
      final denomPayload = <String, int>{};
      _denominations.forEach((denom, qty) {
        if (denom == 1) {
          denomPayload['coins'] = qty;
        } else {
          denomPayload['n$denom'] = qty;
        }
      });

      final res = await ApiService.submitCash(
        pickupId: widget.pickupJob['id'],
        amountRupees: collected,
        denominations: denomPayload,
        lat: 28.8955,
        lng: 76.6066,
      );

      setState(() => _isSubmitting = false);

      final receiptData = {
        'slip_no': 'REC-${DateTime.now().millisecondsSinceEpoch.toString().substring(5)}',
        'retailer_name': widget.pickupJob['retailer_name'] ?? 'Radhe Digital Store',
        'collector_name': 'Rahul Kumar (COL-104)',
        'requested_amount': _requestedAmountRupees,
        'collected_amount': collected,
        'shortfall_amount': _shortfallRupees,
        'denominations': Map<int, int>.from(_denominations),
        'timestamp': DateTime.now().toLocal().toString().split('.')[0],
        'status': 'PENDING_RETAILER_CONFIRMATION',
      };

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('✅ Cash Count Recorded! Wallet approval sent to Retailer for 1-Tap signoff.'),
          backgroundColor: AppTheme.success,
        ),
      );

      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => ThermalReceiptScreen(receiptData: receiptData),
        ),
      );
    } catch (e) {
      setState(() => _isSubmitting = false);
      // Fallback preview
      final receiptData = {
        'slip_no': 'REC-2026-9901',
        'retailer_name': widget.pickupJob['retailer_name'] ?? 'Radhe Digital Store',
        'collector_name': 'Rahul Kumar (COL-104)',
        'requested_amount': _requestedAmountRupees,
        'collected_amount': collected,
        'shortfall_amount': _shortfallRupees,
        'denominations': Map<int, int>.from(_denominations),
        'timestamp': DateTime.now().toLocal().toString().split('.')[0],
        'status': 'PENDING_RETAILER_CONFIRMATION',
      };
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => ThermalReceiptScreen(receiptData: receiptData),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Cash Counting & Denominations'),
      ),
      body: Column(
        children: [
          // Geofence Verified Banner
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: AppTheme.success.withOpacity(0.08),
            child: Row(
              children: [
                const Icon(Icons.verified_user_rounded, color: AppTheme.success, size: 20),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Geofence Verified (${widget.verifiedDistanceMeters}m). Collection desk physically authorized.',
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.success),
                  ),
                ),
              ],
            ),
          ),

          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  // Requested vs Collected Comparison Card
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        children: [
                          Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('Requested Target', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                  const SizedBox(height: 2),
                                  Text(
                                    '₹${_requestedAmountRupees.toStringAsFixed(0)}',
                                    style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                                  ),
                                ],
                              ),
                              const Icon(Icons.arrow_forward_rounded, color: Color(0xFFCBD5E1)),
                              Column(
                                crossAxisAlignment: CrossAxisAlignment.end,
                                children: [
                                  const Text('Counted Physical Cash', style: TextStyle(fontSize: 12, color: Color(0xFF64748B))),
                                  const SizedBox(height: 2),
                                  Text(
                                    '₹${_calculatedTotalRupees.toStringAsFixed(0)}',
                                    style: TextStyle(
                                      fontSize: 22,
                                      fontWeight: FontWeight.extrabold,
                                      color: _calculatedTotalRupees == _requestedAmountRupees ? AppTheme.success : AppTheme.primary,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                          if (_shortfallRupees > 0) ...[
                            const SizedBox(height: 12),
                            Container(
                              padding: const EdgeInsets.all(8),
                              decoration: BoxDecoration(
                                color: AppTheme.warning.withOpacity(0.1),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.warning_amber_rounded, color: AppTheme.warning, size: 18),
                                  const SizedBox(width: 8),
                                  Text(
                                    'Partial Shortfall: ₹${_shortfallRupees.toStringAsFixed(0)} (Requires Retailer confirmation)',
                                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.warning),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'CURRENCY DENOMINATIONS (नोट गिनती)',
                        style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B), letterSpacing: 0.5),
                      ),
                      Text(
                        'Total: $_totalNotesCount notes',
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600, color: AppTheme.primary),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),

                  // Denomination rows
                  ...[500, 200, 100, 50, 20, 10, 1].map((denom) {
                    final qty = _denominations[denom] ?? 0;
                    final subtotal = denom * qty;
                    final label = denom == 1 ? 'Coins' : '₹$denom';

                    return Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFE2E8F0)),
                      ),
                      child: Row(
                        children: [
                          Container(
                            width: 60,
                            padding: const EdgeInsets.symmetric(vertical: 4),
                            decoration: BoxDecoration(
                              color: const Color(0xFFF1F5F9),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Center(
                              child: Text(
                                label,
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1E293B)),
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          IconButton(
                            onPressed: () => _updateQuantity(denom, -5),
                            icon: const Icon(Icons.remove_circle_outline, size: 22, color: Color(0xFF94A3B8)),
                            constraints: const BoxConstraints(),
                            padding: EdgeInsets.zero,
                          ),
                          const SizedBox(width: 6),
                          Container(
                            width: 50,
                            height: 36,
                            decoration: BoxDecoration(
                              border: Border.all(color: const Color(0xFFCBD5E1)),
                              borderRadius: BorderRadius.circular(8),
                            ),
                            child: Center(
                              child: Text(
                                '$qty',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                          IconButton(
                            onPressed: () => _updateQuantity(denom, 5),
                            icon: const Icon(Icons.add_circle_outline, size: 22, color: AppTheme.primary),
                            constraints: const BoxConstraints(),
                            padding: EdgeInsets.zero,
                          ),
                          const Spacer(),
                          Text(
                            '= ₹$subtotal',
                            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                          ),
                        ],
                      ),
                    );
                  }),
                ],
              ),
            ),
          ),

          // Bottom Submission Bar
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.05),
                  blurRadius: 10,
                  offset: const Offset(0, -4),
                ),
              ],
            ),
            child: ElevatedButton.icon(
              onPressed: _isSubmitting ? null : _handleSubmitCash,
              icon: _isSubmitting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.account_balance_wallet_rounded),
              label: Text(
                _isSubmitting
                    ? 'Submitting to Wallet Ledger...'
                    : 'ADD WALLET & APPROVE (₹${_calculatedTotalRupees.toStringAsFixed(0)})',
              ),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                minimumSize: const Size.fromHeight(52),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

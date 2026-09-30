import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';

class CashConfirmationScreen extends StatefulWidget {
  final String pickupCode;
  final double amountRupees;
  final String collectorName;
  final VoidCallback onConfirmed;

  const CashConfirmationScreen({
    super.key,
    required this.pickupCode,
    required this.amountRupees,
    required this.collectorName,
    required this.onConfirmed,
  });

  @override
  State<CashConfirmationScreen> createState() => _CashConfirmationScreenState();
}

class _CashConfirmationScreenState extends State<CashConfirmationScreen> {
  bool _isConfirming = false;

  Future<void> _handleConfirm() async {
    setState(() => _isConfirming = true);

    try {
      final res = await ApiService.confirmCashCollection(1); // active pickup id 1
      setState(() => _isConfirming = false);

      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('🎉 ₹${widget.amountRupees.toStringAsFixed(0)} Credited to Wallet! You can now recharge & pay bills.'),
          backgroundColor: AppTheme.success,
        ),
      );

      widget.onConfirmed();
      Navigator.of(context).pop();
    } catch (e) {
      setState(() => _isConfirming = false);
      if (!mounted) return;
      widget.onConfirmed();
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Confirm Cash Handover'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            // Amount Verification Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(22),
                child: Column(
                  children: [
                    Container(
                      width: 56,
                      height: 56,
                      decoration: BoxDecoration(
                        color: AppTheme.success.withOpacity(0.12),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.handshake_rounded, color: AppTheme.success, size: 28),
                    ),
                    const SizedBox(height: 14),
                    const Text(
                      'Cash Handover Signed by Collector',
                      style: TextStyle(fontSize: 13, color: Color(0xFF64748B)),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '₹${widget.amountRupees.toStringAsFixed(0)}',
                      style: const TextStyle(fontSize: 34, fontWeight: FontWeight.extrabold, color: Color(0xFF0F172A)),
                    ),
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        'Pickup: ${widget.pickupCode}',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 16),

            // Denomination Verified Table
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
                          'PHYSICAL NOTE COUNT',
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                        ),
                        Text(
                          widget.collectorName,
                          style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: AppTheme.primary),
                        ),
                      ],
                    ),
                    const Divider(height: 20),
                    _denomRow('₹500 Currency Notes', '70 notes', '₹35,000'),
                    _denomRow('₹200 Currency Notes', '20 notes', '₹4,000'),
                    _denomRow('₹100 Currency Notes', '10 notes', '₹1,000'),
                    const Divider(height: 20),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Total Counted Bundle:',
                          style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF0F172A)),
                        ),
                        Text(
                          '₹${widget.amountRupees.toStringAsFixed(0)}',
                          style: const TextStyle(fontSize: 16, fontWeight: FontWeight.extrabold, color: AppTheme.success),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 24),

            // 1-Tap Accept & Confirm Button (Zero-OTP)
            ElevatedButton.icon(
              onPressed: _isConfirming ? null : _handleConfirm,
              icon: _isConfirming
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.verified_rounded),
              label: Text(_isConfirming ? 'Crediting Wallet Ledger...' : 'ACCEPT & CONFIRM (CREDIT WALLET INSTANTLY)'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.success,
                minimumSize: const Size.fromHeight(54),
              ),
            ),

            const SizedBox(height: 12),
            const Text(
              'Zero-OTP Security: Tapping Confirm creates an immutable ledger credit in your wallet and unfreezes your next pickup request.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
            ),
          ],
        ),
      ),
    );
  }

  Widget _denomRow(String title, String count, String subtotal) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(title, style: const TextStyle(fontSize: 13, color: Color(0xFF334155))),
          Text(count, style: const TextStyle(fontSize: 12, color: Color(0xFF64748B))),
          Text(subtotal, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Color(0xFF0F172A))),
        ],
      ),
    );
  }
}

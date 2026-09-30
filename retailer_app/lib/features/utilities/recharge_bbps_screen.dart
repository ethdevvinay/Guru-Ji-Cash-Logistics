import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';

class RechargeBbpsScreen extends StatefulWidget {
  final String serviceType; // 'RECHARGE' or 'BBPS'
  final double walletBalance;

  const RechargeBbpsScreen({
    super.key,
    required this.serviceType,
    required this.walletBalance,
  });

  @override
  State<RechargeBbpsScreen> createState() => _RechargeBbpsScreenState();
}

class _RechargeBbpsScreenState extends State<RechargeBbpsScreen> {
  final _targetNumberCtrl = TextEditingController(text: '9812044556');
  final _amountCtrl = TextEditingController(text: '299');
  String _selectedOperator = 'JIO';
  bool _isProcessing = false;

  final List<Map<String, String>> _operators = [
    {'code': 'JIO', 'name': 'Reliance Jio 5G'},
    {'code': 'AIRTEL', 'name': 'Bharti Airtel'},
    {'code': 'VI', 'name': 'Vodafone Idea'},
    {'code': 'BSNL', 'name': 'BSNL GSM'},
  ];

  final List<Map<String, String>> _bbpsBillers = [
    {'code': 'DHBVN', 'name': 'Dakshin Haryana Bijli Vitran Nigam'},
    {'code': 'UHBVN', 'name': 'Uttar Haryana Bijli Vitran Nigam'},
    {'code': 'IGL', 'name': 'Indraprastha Gas Limited'},
    {'code': 'FASTAG', 'name': 'NHAI NETC FASTag'},
  ];

  Future<void> _handleProcessPayment() async {
    final amount = double.tryParse(_amountCtrl.text) ?? 0.0;
    if (amount <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter a valid payment amount.'), backgroundColor: AppTheme.danger),
      );
      return;
    }

    if (amount > widget.walletBalance) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Insufficient wallet balance. Request cash pickup first.'), backgroundColor: AppTheme.danger),
      );
      return;
    }

    setState(() => _isProcessing = true);

    try {
      final res = await ApiService.executeRecharge(
        operatorCode: _selectedOperator,
        mobileNumber: _targetNumberCtrl.text.trim(),
        amountRupees: amount,
      );

      setState(() => _isProcessing = false);

      if (!mounted) return;
      showDialog(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Row(
            children: [
              Icon(Icons.check_circle_rounded, color: AppTheme.success),
              SizedBox(width: 8),
              Text('Payment Successful'),
            ],
          ),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                '₹${amount.toStringAsFixed(2)} deducted from your wallet ledger.',
                style: const TextStyle(fontSize: 14),
              ),
              const SizedBox(height: 8),
              Text(
                'Reference: TXN-BBPS-${DateTime.now().millisecondsSinceEpoch.toString().substring(5)}',
                style: const TextStyle(fontSize: 12, fontFamily: 'monospace', color: Color(0xFF64748B)),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: AppTheme.success.withOpacity(0.08),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Text(
                  'Operator Status: SUCCESS (Simulated Sandbox Bridge)',
                  style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.success),
                ),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(ctx).pop();
                Navigator.of(context).pop();
              },
              child: const Text('Done'),
            ),
          ],
        ),
      );
    } catch (e) {
      setState(() => _isProcessing = false);
      if (!mounted) return;
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final isRecharge = widget.serviceType == 'RECHARGE';
    final items = isRecharge ? _operators : _bbpsBillers;

    return Scaffold(
      appBar: AppBar(
        title: Text(isRecharge ? 'Prepaid Mobile Recharge' : 'BBPS Utility Bill Payment'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(18),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Available Wallet Balance Pill
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text('Available Wallet Balance:', style: TextStyle(fontSize: 13, color: Color(0xFF64748B))),
                  Text(
                    '₹${widget.walletBalance.toStringAsFixed(2)}',
                    style: const TextStyle(fontSize: 16, fontWeight: FontWeight.extrabold, color: Color(0xFF0F172A)),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 18),

            Card(
              child: Padding(
                padding: const EdgeInsets.all(18),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isRecharge ? 'Select Telecom Operator' : 'Select Biller Board',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                    ),
                    const SizedBox(height: 8),
                    DropdownButtonFormField<String>(
                      value: _selectedOperator,
                      decoration: InputDecoration(
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                      items: items.map((item) {
                        return DropdownMenuItem(
                          value: item['code'],
                          child: Text(item['name']!),
                        );
                      }).toList(),
                      onChanged: (val) {
                        if (val != null) setState(() => _selectedOperator = val);
                      },
                    ),

                    const SizedBox(height: 16),

                    Text(
                      isRecharge ? 'Consumer 10-Digit Mobile Number' : 'Consumer Account / CA Number',
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _targetNumberCtrl,
                      keyboardType: TextInputType.phone,
                      decoration: InputDecoration(
                        prefixIcon: Icon(isRecharge ? Icons.phone_android_rounded : Icons.numbers_rounded, size: 20),
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),

                    const SizedBox(height: 16),

                    const Text(
                      'Recharge / Bill Amount (रुपये)',
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                    ),
                    const SizedBox(height: 8),
                    TextField(
                      controller: _amountCtrl,
                      keyboardType: TextInputType.number,
                      decoration: InputDecoration(
                        prefixText: '₹ ',
                        filled: true,
                        fillColor: const Color(0xFFF8FAFC),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      ),
                    ),

                    const SizedBox(height: 24),

                    ElevatedButton.icon(
                      onPressed: _isProcessing ? null : _handleProcessPayment,
                      icon: _isProcessing
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                          : const Icon(Icons.flash_on_rounded),
                      label: Text(_isProcessing ? 'Processing Transaction...' : 'PAY VIA WALLET LEDGER'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppTheme.primary,
                        minimumSize: const Size.fromHeight(50),
                      ),
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

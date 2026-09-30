import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';

class PassbookScreen extends StatefulWidget {
  const PassbookScreen({super.key});

  @override
  State<PassbookScreen> createState() => _PassbookScreenState();
}

class _PassbookScreenState extends State<PassbookScreen> {
  bool _isLoading = false;

  final List<Map<String, dynamic>> _ledgerEntries = [
    {
      'id': 101,
      'code': 'TXN-2026-9041',
      'type': 'CASH_COLLECTION_CREDIT',
      'title': 'Cash Pickup Handover Credit',
      'description': 'Verified by Collector Rahul Kumar (COL-104) • 100 notes',
      'amount_rupees': 40000.0,
      'is_credit': true,
      'balance_after': 45200.0,
      'date': '30 Sep 2026, 22:45',
    },
    {
      'id': 102,
      'code': 'TXN-2026-8890',
      'type': 'RECHARGE_DEBIT',
      'title': 'Prepaid Recharge - Jio 5G',
      'description': 'Mobile: +91 98120 44556 • Plan ₹299 (28 Days)',
      'amount_rupees': 299.0,
      'is_credit': false,
      'balance_after': 5200.0,
      'date': '30 Sep 2026, 18:20',
    },
    {
      'id': 103,
      'code': 'TXN-2026-8712',
      'type': 'BBPS_DEBIT',
      'title': 'Electricity Bill - DHBVN',
      'description': 'CA: 0984421142 • Consumer: Radheshyam Gupta',
      'amount_rupees': 1850.0,
      'is_credit': false,
      'balance_after': 5499.0,
      'date': '30 Sep 2026, 14:15',
    },
    {
      'id': 104,
      'code': 'TXN-2026-8400',
      'type': 'CASH_COLLECTION_CREDIT',
      'title': 'Cash Pickup Handover Credit',
      'description': 'Verified by Collector Amit Singh (COL-102)',
      'amount_rupees': 25000.0,
      'is_credit': true,
      'balance_after': 7349.0,
      'date': '29 Sep 2026, 19:30',
    },
  ];

  @override
  void initState() {
    super.initState();
    _fetchPassbook();
  }

  Future<void> _fetchPassbook() async {
    try {
      final res = await ApiService.getPassbook();
      if (res['success'] == true && res['data'] != null && (res['data'] as List).isNotEmpty) {
        setState(() {
          // If server returns entries, update
        });
      }
    } catch (_) {}
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Wallet Passbook & Ledger'),
      ),
      body: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: _ledgerEntries.length,
        itemBuilder: (context, index) {
          final item = _ledgerEntries[index];
          final isCredit = item['is_credit'] as bool;

          return Card(
            margin: const EdgeInsets.only(bottom: 12),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        item['code'],
                        style: const TextStyle(fontSize: 11, fontFamily: 'monospace', color: Color(0xFF64748B)),
                      ),
                      Text(
                        item['date'],
                        style: const TextStyle(fontSize: 11, color: Color(0xFF94A3B8)),
                      ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: isCredit ? AppTheme.success.withOpacity(0.1) : AppTheme.danger.withOpacity(0.1),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          isCredit ? Icons.arrow_downward_rounded : Icons.arrow_upward_rounded,
                          color: isCredit ? AppTheme.success : AppTheme.danger,
                          size: 18,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item['title'],
                              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF0F172A)),
                            ),
                            Text(
                              item['description'],
                              style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        '${isCredit ? '+' : '-'}₹${(item['amount_rupees'] as num).toStringAsFixed(0)}',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.extrabold,
                          color: isCredit ? AppTheme.success : AppTheme.danger,
                        ),
                      ),
                    ],
                  ),
                  const Divider(height: 20),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'Closing Balance: ₹${(item['balance_after'] as num).toStringAsFixed(2)}',
                        style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                      ),
                      InkWell(
                        onTap: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(content: Text('Downloading Digital Receipt for ${item['code']}...')),
                          );
                        },
                        child: const Row(
                          children: [
                            Icon(Icons.download_rounded, size: 14, color: AppTheme.primary),
                            SizedBox(width: 4),
                            Text('Receipt PDF', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppTheme.primary)),
                          ],
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

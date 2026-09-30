import 'package:flutter/material.dart';
import '../../core/theme.dart';
import '../dashboard/dashboard_screen.dart';

class ThermalReceiptScreen extends StatefulWidget {
  final Map<String, dynamic> receiptData;

  const ThermalReceiptScreen({super.key, required this.receiptData});

  @override
  State<ThermalReceiptScreen> createState() => _ThermalReceiptScreenState();
}

class _ThermalReceiptScreenState extends State<ThermalReceiptScreen> {
  bool _isPrinting = false;
  String _printerStatus = 'Ready (SPP Bluetooth 58mm)';

  Future<void> _handlePrintBluetooth() async {
    setState(() {
      _isPrinting = true;
      _printerStatus = 'Connecting to BT-PRINTER-01...';
    });

    await Future.delayed(const Duration(seconds: 2));

    setState(() {
      _isPrinting = false;
      _printerStatus = 'Printed successfully! (ESC/POS 384 dots)';
    });

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('🖨 ESC/POS Slip Printed! Hand receipt to retailer.'),
        backgroundColor: AppTheme.success,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final Map<int, int> denoms = widget.receiptData['denominations'] as Map<int, int>? ?? {};

    return Scaffold(
      appBar: AppBar(
        title: const Text('Thermal Receipt Preview'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () {
            Navigator.of(context).pushAndRemoveUntil(
              MaterialPageRoute(builder: (_) => const CollectorDashboardScreen(userData: {})),
              (route) => false,
            );
          },
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            // Thermal Receipt Paper Card (Realistic 58mm/80mm preview)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(8),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.08),
                    blurRadius: 12,
                    offset: const Offset(0, 4),
                  ),
                ],
                border: Border.all(color: const Color(0xFFE2E8F0)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  const Text(
                    '================================',
                    style: TextStyle(fontFamily: 'monospace', fontSize: 11, color: Color(0xFF94A3B8)),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'GURUJI OPERATIONS DESK',
                    style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16, letterSpacing: 0.5),
                  ),
                  const Text(
                    'Cash Logistics & Retailer Settlement',
                    style: TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                  ),
                  const Text(
                    'Rohtak Urban Division • Fleet CMS',
                    style: TextStyle(fontSize: 10, color: Color(0xFF94A3B8)),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    '================================',
                    style: TextStyle(fontFamily: 'monospace', fontSize: 11, color: Color(0xFF94A3B8)),
                  ),
                  const SizedBox(height: 12),

                  // Metadata table
                  _receiptRow('Slip Number:', widget.receiptData['slip_no'] ?? 'REC-2026-9901'),
                  _receiptRow('Date & Time:', widget.receiptData['timestamp'] ?? '2026-09-30 22:45'),
                  _receiptRow('Collector:', widget.receiptData['collector_name'] ?? 'Rahul Kumar (COL-104)'),
                  _receiptRow('Retailer Store:', widget.receiptData['retailer_name'] ?? 'Radhe Digital Store'),
                  const SizedBox(height: 8),
                  const Text(
                    '--------------------------------',
                    style: TextStyle(fontFamily: 'monospace', fontSize: 11, color: Color(0xFFCBD5E1)),
                  ),
                  const SizedBox(height: 6),

                  // Denominations breakdown
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'DENOMINATIONS BREAKDOWN:',
                      style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold, color: Color(0xFF475569)),
                    ),
                  ),
                  const SizedBox(height: 6),
                  ...denoms.entries.where((e) => e.value > 0).map((entry) {
                    final label = entry.key == 1 ? 'Coins' : '₹${entry.key}';
                    return _receiptRow(
                      '$label  x  ${entry.value}',
                      '₹${entry.key * entry.value}',
                    );
                  }),

                  const SizedBox(height: 8),
                  const Text(
                    '================================',
                    style: TextStyle(fontFamily: 'monospace', fontSize: 11, color: Color(0xFF94A3B8)),
                  ),
                  const SizedBox(height: 8),

                  _receiptRow(
                    'TOTAL COLLECTED:',
                    '₹${(widget.receiptData['collected_amount'] as num?)?.toStringAsFixed(2) ?? '40,000.00'}',
                    isBold: true,
                    fontSize: 14,
                  ),

                  if ((widget.receiptData['shortfall_amount'] as num? ?? 0) > 0) ...[
                    const SizedBox(height: 4),
                    _receiptRow(
                      'Shortfall Amount:',
                      '₹${(widget.receiptData['shortfall_amount'] as num).toStringAsFixed(2)}',
                      color: AppTheme.danger,
                    ),
                  ],

                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                    decoration: BoxDecoration(
                      color: AppTheme.warning.withOpacity(0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Text(
                      'STATUS: ${widget.receiptData['status'] ?? 'PENDING CONFIRMATION'}',
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                        color: AppTheme.warning,
                      ),
                    ),
                  ),

                  const SizedBox(height: 16),
                  const Text(
                    '* 1-Tap Retailer Confirmation Required *',
                    style: TextStyle(fontSize: 10, fontStyle: FontStyle.italic, color: Color(0xFF94A3B8)),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Authorized Digital Signoff by Guruji CMS',
                    style: TextStyle(fontSize: 9, color: Color(0xFF94A3B8)),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // Printer Connection Status Card
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: const Color(0xFFF1F5F9),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Row(
                children: [
                  Icon(
                    Icons.bluetooth_connected_rounded,
                    size: 20,
                    color: _printerStatus.contains('Printed') ? AppTheme.success : AppTheme.primary,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      _printerStatus,
                      style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 20),

            // Print Thermal Receipt Button
            ElevatedButton.icon(
              onPressed: _isPrinting ? null : _handlePrintBluetooth,
              icon: _isPrinting
                  ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                  : const Icon(Icons.print_rounded),
              label: Text(_isPrinting ? 'Printing ESC/POS Slip...' : 'PRINT THERMAL RECEIPT (BLUETOOTH)'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppTheme.primary,
                minimumSize: const Size.fromHeight(52),
              ),
            ),

            const SizedBox(height: 12),

            // Share Digital PDF Fallback
            OutlinedButton.icon(
              onPressed: () {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Generating Digital PDF Receipt to WhatsApp & SMS...')),
                );
              },
              icon: const Icon(Icons.share_rounded, size: 18),
              label: const Text('Share Digital PDF Receipt'),
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
            ),

            const SizedBox(height: 12),

            TextButton(
              onPressed: () {
                Navigator.of(context).pushAndRemoveUntil(
                  MaterialPageRoute(builder: (_) => const CollectorDashboardScreen(userData: {})),
                  (route) => false,
                );
              },
              child: const Text('Return to Home Dashboard'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _receiptRow(String label, String value, {bool isBold = false, double fontSize = 11, Color? color}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2.5),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: TextStyle(
              fontSize: fontSize,
              fontWeight: isBold ? FontWeight.bold : FontWeight.w500,
              color: color ?? const Color(0xFF475569),
              fontFamily: 'monospace',
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: fontSize,
              fontWeight: isBold ? FontWeight.bold : FontWeight.w600,
              color: color ?? const Color(0xFF0F172A),
              fontFamily: 'monospace',
            ),
          ),
        ],
      ),
    );
  }
}

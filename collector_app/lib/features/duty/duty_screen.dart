import 'package:flutter/material.dart';
import '../../core/api_service.dart';
import '../../core/theme.dart';

class DutyScreen extends StatefulWidget {
  final bool isOnDuty;

  const DutyScreen({super.key, required this.isOnDuty});

  @override
  State<DutyScreen> createState() => _DutyScreenState();
}

class _DutyScreenState extends State<DutyScreen> {
  final _odometerController = TextEditingController(text: '14285.5');
  bool _isLoading = false;
  late bool _currentDuty;

  @override
  void initState() {
    super.initState();
    _currentDuty = widget.isOnDuty;
  }

  Future<void> _toggleDuty() async {
    setState(() => _isLoading = true);

    try {
      final odo = double.tryParse(_odometerController.text);
      if (_currentDuty) {
        // Punch Out
        final res = await ApiService.punchOut(28.8955, 76.6066, odo);
        if (res['success'] == true) {
          setState(() => _currentDuty = false);
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Duty ended successfully.')),
            );
          }
        } else {
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(res['message'] ?? 'Punch-out failed.'), backgroundColor: AppTheme.danger),
            );
          }
        }
      } else {
        // Punch In
        final res = await ApiService.punchIn(28.8955, 76.6066, odo);
        if (res['success'] == true) {
          setState(() => _currentDuty = true);
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Duty started successfully. GPS active.')),
            );
          }
        }
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Network error.'), backgroundColor: AppTheme.danger),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Duty Punch Desk')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(22),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.between,
                      children: [
                        const Text(
                          'DUTY ATTENDANCE STATUS',
                          style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF64748B)),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                          decoration: BoxDecoration(
                            color: _currentDuty ? AppTheme.success.withOpacity(0.1) : Colors.grey.withOpacity(0.1),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            _currentDuty ? '● ON DUTY' : '○ OFF DUTY',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.bold,
                              color: _currentDuty ? AppTheme.success : Colors.grey,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Odometer Reading
                    TextField(
                      controller: _odometerController,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(
                        labelText: 'Current Bike Odometer (KM)',
                        prefixIcon: const Icon(Icons.speed_rounded, size: 20),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // GPS Auto-Tag
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.location_on_rounded, size: 18, color: AppTheme.primary),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'GPS Auto-Tag: 28.8955° N, 76.6066° E\nAccuracy: 12m • Rohtak Urban',
                              style: TextStyle(fontSize: 11, color: Color(0xFF475569)),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),

                    ElevatedButton(
                      onPressed: _isLoading ? null : _toggleDuty,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _currentDuty ? AppTheme.danger : AppTheme.success,
                      ),
                      child: _isLoading
                          ? const CircularProgressIndicator(color: Colors.white)
                          : Text(_currentDuty ? 'Punch-Out Duty' : 'Punch-In Duty'),
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

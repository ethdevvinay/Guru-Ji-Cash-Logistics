import 'package:flutter/material.dart';
import 'core/theme.dart';
import 'features/auth/login_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const CollectorApp());
}

class CollectorApp extends StatelessWidget {
  const CollectorApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Guruji Collector',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: const CollectorLoginScreen(),
    );
  }
}

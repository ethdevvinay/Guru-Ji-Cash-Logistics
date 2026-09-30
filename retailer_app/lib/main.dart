import 'package:flutter/material.dart';
import 'core/theme.dart';
import 'features/auth/login_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const RetailerApp());
}

class RetailerApp extends StatelessWidget {
  const RetailerApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Guruji Retailer',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      home: const RetailerLoginScreen(),
    );
  }
}

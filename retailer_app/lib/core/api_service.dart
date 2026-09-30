import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiService {
  // Use 10.0.2.2 for Android Emulator, or 127.0.0.1 for local dev
  static String baseUrl = 'http://10.0.2.2:8000/api/v1';
  static String? authToken;
  static String deviceUuid = 'DEV-RET-ANDROID-2026';

  static Map<String, String> get headers => {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Device-Id': deviceUuid,
    if (authToken != null) 'Authorization': 'Bearer $authToken',
  };

  static Future<Map<String, dynamic>> login(String login, String password) async {
    final response = await http.post(
      Uri.parse('$baseUrl/auth/login'),
      headers: headers,
      body: jsonEncode({
        'login': login,
        'password': password,
        'device_uuid': deviceUuid,
        'device_model': 'Redmi Note 13 Pro 5G',
      }),
    );

    final data = jsonDecode(response.body);
    if (response.statusCode == 200 && data['success'] == true) {
      authToken = data['data']['token'];
    }
    return data;
  }

  static Future<Map<String, dynamic>> getDashboard() async {
    final response = await http.get(
      Uri.parse('$baseUrl/retailer/dashboard'),
      headers: headers,
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> getWallet() async {
    final response = await http.get(
      Uri.parse('$baseUrl/retailer/wallet'),
      headers: headers,
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> createPickupRequest({
    required double amountRupees,
    required double lat,
    required double lng,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/retailer/pickups/create'),
      headers: headers,
      body: jsonEncode({
        'requested_amount_rupees': amountRupees,
        'latitude': lat,
        'longitude': lng,
      }),
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> getCollectorLocation(int pickupId) async {
    final response = await http.get(
      Uri.parse('$baseUrl/retailer/pickups/$pickupId/collector-location'),
      headers: headers,
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> confirmCashCollection(int pickupId) async {
    final response = await http.post(
      Uri.parse('$baseUrl/retailer/pickups/$pickupId/confirm'),
      headers: headers,
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> executeRecharge({
    required String operatorCode,
    required String mobileNumber,
    required double amountRupees,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/retailer/recharge'),
      headers: headers,
      body: jsonEncode({
        'operator_code': operatorCode,
        'mobile_number': mobileNumber,
        'amount_rupees': amountRupees,
      }),
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> getPassbook() async {
    final response = await http.get(
      Uri.parse('$baseUrl/retailer/wallet/passbook'),
      headers: headers,
    );
    return jsonDecode(response.body);
  }
}

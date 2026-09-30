import 'dart:convert';
import 'package:http/http.dart' as http;

class ApiService {
  // Use 10.0.2.2 for Android Emulator, or 127.0.0.1 / custom IP for local dev
  static String baseUrl = 'http://10.0.2.2:8000/api/v1';
  static String? authToken;
  static String deviceUuid = 'DEV-COL-ANDROID-2026';

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
        'device_model': 'Samsung Galaxy M34 5G',
      }),
    );

    final data = jsonDecode(response.body);
    if (response.statusCode == 200 && data['success'] == true) {
      authToken = data['data']['token'];
    }
    return data;
  }

  static Future<Map<String, dynamic>> punchIn(double lat, double lng, double? odometer) async {
    final response = await http.post(
      Uri.parse('$baseUrl/collector/duty/punch-in'),
      headers: headers,
      body: jsonEncode({
        'latitude': lat,
        'longitude': lng,
        'odometer_km': odometer,
      }),
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> punchOut(double lat, double lng, double? odometer) async {
    final response = await http.post(
      Uri.parse('$baseUrl/collector/duty/punch-out'),
      headers: headers,
      body: jsonEncode({
        'latitude': lat,
        'longitude': lng,
        'odometer_km': odometer,
      }),
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> getBroadcasts() async {
    final response = await http.get(
      Uri.parse('$baseUrl/collector/pickups/broadcasts'),
      headers: headers,
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> acceptPickup(int pickupId) async {
    final response = await http.post(
      Uri.parse('$baseUrl/collector/pickups/$pickupId/accept'),
      headers: headers,
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> verifyGeofence(int pickupId, double lat, double lng) async {
    final response = await http.post(
      Uri.parse('$baseUrl/collector/pickups/$pickupId/verify-geofence'),
      headers: headers,
      body: jsonEncode({
        'latitude': lat,
        'longitude': lng,
        'timestamp': DateTime.now().millisecondsSinceEpoch ~/ 1000,
      }),
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> submitCash({
    required int pickupId,
    required double amountRupees,
    required Map<String, int> denominations,
    required double lat,
    required double lng,
  }) async {
    final response = await http.post(
      Uri.parse('$baseUrl/collector/pickups/$pickupId/submit-cash'),
      headers: headers,
      body: jsonEncode({
        'collected_amount_rupees': amountRupees,
        'denominations': denominations,
        'latitude': lat,
        'longitude': lng,
      }),
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> getFloatMeter() async {
    final response = await http.get(
      Uri.parse('$baseUrl/collector/float/meter'),
      headers: headers,
    );
    return jsonDecode(response.body);
  }

  static Future<Map<String, dynamic>> triggerSos(double lat, double lng) async {
    final response = await http.post(
      Uri.parse('$baseUrl/collector/sos/trigger'),
      headers: headers,
      body: jsonEncode({
        'latitude': lat,
        'longitude': lng,
        'battery_percent': 85,
      }),
    );
    return jsonDecode(response.body);
  }
}

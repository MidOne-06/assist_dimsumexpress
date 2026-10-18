import 'package:dio/dio.dart';

import '../auth/mobile_session.dart';

class MobileApiClient {
  MobileApiClient()
    : _dio = Dio(
        BaseOptions(
          baseUrl: const String.fromEnvironment(
            'API_BASE_URL',
            defaultValue: 'https://assist.dimsumexpress.cloud/api/mobile/v1',
          ),
          connectTimeout: const Duration(seconds: 15),
          receiveTimeout: const Duration(seconds: 20),
          headers: const {'Accept': 'application/json'},
        ),
      );

  final Dio _dio;
  String? _token;

  void authenticate(String? token) {
    _token = token;
  }

  Future<MobileSession> login({
    required String email,
    required String password,
    required String device,
  }) async {
    final response = await _dio.post<Map<String, dynamic>>(
      '/sesion',
      data: {'email': email, 'password': password, 'dispositivo': device},
    );
    return MobileSession.fromLogin(response.data!);
  }

  Future<Map<String, dynamic>> post(
    String path,
    Map<String, dynamic> data,
  ) async {
    final response = await _dio.post<Map<String, dynamic>>(
      path,
      data: data,
      options: _options(),
    );
    return response.data!;
  }

  Future<Map<String, dynamic>> get(String path) async {
    final response = await _dio.get<Map<String, dynamic>>(
      path,
      options: _options(),
    );
    return response.data!;
  }

  Future<void> logout() async {
    await _dio.delete<void>('/sesion', options: _options());
  }

  Options _options() =>
      Options(headers: {if (_token != null) 'Authorization': 'Bearer $_token'});

  static String messageFor(Object error) {
    if (error is DioException) {
      final data = error.response?.data;
      if (data is Map<String, dynamic>) {
        final errors = data['errors'];
        if (errors is Map && errors.isNotEmpty) {
          final first = errors.values.first;
          if (first is List && first.isNotEmpty) return first.first.toString();
        }
        if (data['message'] is String) return data['message'] as String;
      }
      if (error.type == DioExceptionType.connectionTimeout ||
          error.type == DioExceptionType.connectionError) {
        return 'No pudimos conectarnos. Revisa tu conexión e inténtalo nuevamente.';
      }
    }
    return 'No se pudo completar la operación. Inténtalo nuevamente.';
  }
}

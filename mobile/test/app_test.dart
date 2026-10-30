import 'package:dio/dio.dart';
import 'package:dimsum_asistencia/core/api/mobile_api_client.dart';
import 'package:dimsum_asistencia/core/auth/mobile_session.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('MobileSession', () {
    test('parses login response for operator correctly', () {
      final payload = {
        'token': '1|test_token',
        'usuario': {
          'id': 10,
          'nombre': 'Juan Pérez',
          'accesos': {
            'marcacion': true,
            'visitas': false,
          },
        },
      };

      final session = MobileSession.fromLogin(payload);
      expect(session.token, '1|test_token');
      expect(session.name, 'Juan Pérez');
      expect(session.canMark, isTrue);
      expect(session.canVisit, isFalse);
    });

    test('parses login response for supervisor correctly', () {
      final payload = {
        'token': '2|super_token',
        'usuario': {
          'id': 5,
          'nombre': 'Rosa Chávez',
          'accesos': {
            'marcacion': true,
            'visitas': true,
          },
        },
      };

      final session = MobileSession.fromLogin(payload);
      expect(session.token, '2|super_token');
      expect(session.name, 'Rosa Chávez');
      expect(session.canMark, isTrue);
      expect(session.canVisit, isTrue);
    });
  });

  group('MobileApiClient error mapping', () {
    test('extracts first validation error from nested map', () {
      final error = DioException(
        requestOptions: RequestOptions(path: '/sesion'),
        response: Response(
          requestOptions: RequestOptions(path: '/sesion'),
          data: {
            'message': 'Error de validación',
            'errors': {
              'email': ['El correo ingresado no es válido.'],
            },
          },
        ),
      );

      expect(MobileApiClient.messageFor(error), 'El correo ingresado no es válido.');
    });

    test('extracts top-level message when errors list is absent', () {
      final error = DioException(
        requestOptions: RequestOptions(path: '/sesion'),
        response: Response(
          requestOptions: RequestOptions(path: '/sesion'),
          data: {
            'message': 'Esta cuenta no tiene una operación móvil habilitada.',
          },
        ),
      );

      expect(MobileApiClient.messageFor(error), 'Esta cuenta no tiene una operación móvil habilitada.');
    });

    test('returns network message on connection error', () {
      final error = DioException(
        requestOptions: RequestOptions(path: '/sesion'),
        type: DioExceptionType.connectionError,
      );

      expect(
        MobileApiClient.messageFor(error),
        'No pudimos conectarnos. Revisa tu conexión e inténtalo nuevamente.',
      );
    });
  });
}

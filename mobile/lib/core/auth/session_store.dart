import 'dart:math';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'mobile_session.dart';

class SessionStore {
  static const _tokenKey = 'mobile_token';
  static const _nameKey = 'mobile_name';
  static const _markKey = 'mobile_can_mark';
  static const _visitKey = 'mobile_can_visit';
  static const _installationKey = 'mobile_installation_id';

  const SessionStore(this._storage);

  final FlutterSecureStorage _storage;

  Future<MobileSession?> read() async {
    final token = await _storage.read(key: _tokenKey);
    final name = await _storage.read(key: _nameKey);
    if (token == null || name == null) return null;

    return MobileSession(
      token: token,
      name: name,
      canMark: await _storage.read(key: _markKey) == 'true',
      canVisit: await _storage.read(key: _visitKey) == 'true',
    );
  }

  Future<void> write(MobileSession session) async {
    await Future.wait([
      _storage.write(key: _tokenKey, value: session.token),
      _storage.write(key: _nameKey, value: session.name),
      _storage.write(key: _markKey, value: session.canMark.toString()),
      _storage.write(key: _visitKey, value: session.canVisit.toString()),
    ]);
  }

  /// Identifica una instalación, no al teléfono ni a la persona.
  /// Se conserva al cerrar sesión para que cada celular tenga su propio token.
  Future<String> installationId() async {
    final existing = await _storage.read(key: _installationKey);
    if (existing != null && existing.isNotEmpty) return existing;

    final random = Random.secure();
    final value =
        'dimsum-${List.generate(24, (_) => random.nextInt(256)).map((byte) => byte.toRadixString(16).padLeft(2, '0')).join()}';
    await _storage.write(key: _installationKey, value: value);
    return value;
  }

  Future<void> clear() => Future.wait([
    _storage.delete(key: _tokenKey),
    _storage.delete(key: _nameKey),
    _storage.delete(key: _markKey),
    _storage.delete(key: _visitKey),
  ]);
}

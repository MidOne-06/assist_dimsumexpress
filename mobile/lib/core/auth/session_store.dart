import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'mobile_session.dart';

class SessionStore {
  static const _tokenKey = 'mobile_token';
  static const _nameKey = 'mobile_name';
  static const _markKey = 'mobile_can_mark';
  static const _visitKey = 'mobile_can_visit';

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

  Future<void> clear() => _storage.deleteAll();
}

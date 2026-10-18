import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'core/api/mobile_api_client.dart';
import 'core/auth/mobile_session.dart';
import 'core/auth/session_store.dart';
import 'features/auth/login_page.dart';
import 'features/home/home_page.dart';

void main() => runApp(const DimSumAttendanceApp());

class DimSumAttendanceApp extends StatefulWidget {
  const DimSumAttendanceApp({super.key});

  @override
  State<DimSumAttendanceApp> createState() => _DimSumAttendanceAppState();
}

class _DimSumAttendanceAppState extends State<DimSumAttendanceApp> {
  final _client = MobileApiClient();
  final _store = const SessionStore(FlutterSecureStorage());
  MobileSession? _session;
  String? _deviceId;
  bool _restoring = true;

  @override
  void initState() {
    super.initState();
    _restore();
  }

  Future<void> _restore() async {
    final values = await Future.wait([_store.read(), _store.installationId()]);
    final session = values[0] as MobileSession?;
    final deviceId = values[1] as String;
    _client.authenticate(session?.token);
    if (mounted) {
      setState(() {
        _session = session;
        _deviceId = deviceId;
        _restoring = false;
      });
    }
  }

  Future<void> _login(MobileSession session) async {
    await _store.write(session);
    _client.authenticate(session.token);
    if (mounted) {
      setState(() => _session = session);
    }
  }

  Future<void> _logout() async {
    try {
      await _client.logout();
    } catch (_) {
      // La salida local debe funcionar aun cuando no haya señal.
    }
    await _store.clear();
    _client.authenticate(null);
    if (mounted) {
      setState(() => _session = null);
    }
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'DIM SUM Asistencia',
    debugShowCheckedModeBanner: false,
    theme: ThemeData(
      brightness: Brightness.dark,
      colorScheme: ColorScheme.fromSeed(
        seedColor: const Color(0xff1677ff),
        brightness: Brightness.dark,
      ),
      inputDecorationTheme: const InputDecorationTheme(
        border: OutlineInputBorder(),
      ),
    ),
    home: _restoring
        ? const Scaffold(body: Center(child: CircularProgressIndicator()))
        : _session == null
        ? LoginPage(client: _client, deviceId: _deviceId!, onLoggedIn: _login)
        : HomePage(client: _client, session: _session!, onLogout: _logout),
  );
}

import 'package:flutter/material.dart';

import '../../core/api/mobile_api_client.dart';
import '../../core/auth/mobile_session.dart';
import '../scan/qr_scan_page.dart';

class HomePage extends StatelessWidget {
  const HomePage({
    super.key,
    required this.client,
    required this.session,
    required this.onLogout,
  });

  final MobileApiClient client;
  final MobileSession session;
  final Future<void> Function() onLogout;

  Future<void> _openScanner(BuildContext context, ScanMode mode) =>
      Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => QrScanPage(client: client, mode: mode),
        ),
      );

  Future<void> _showToday(BuildContext context) async {
    try {
      final data = await client.get('/marcaciones/hoy');
      if (!context.mounted) return;
      final records = (data['marcaciones'] as List<dynamic>)
          .cast<Map<String, dynamic>>();
      await showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (_) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 0, 20, 24),
            child: records.isEmpty
                ? const Text('Aún no tienes marcaciones registradas hoy.')
                : Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Mi marcación de hoy',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 12),
                      ...records.map(
                        (record) => ListTile(
                          leading: const Icon(Icons.access_time_rounded),
                          title: Text(record['etiqueta'] as String),
                          trailing: Text(record['hora'] as String),
                        ),
                      ),
                    ],
                  ),
          ),
        ),
      );
    } catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(MobileApiClient.messageFor(error))),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('DIM SUM · Marcación'),
      actions: [
        IconButton(
          onPressed: onLogout,
          icon: const Icon(Icons.logout),
          tooltip: 'Cerrar sesión',
        ),
      ],
    ),
    body: SafeArea(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Hola,', style: Theme.of(context).textTheme.headlineMedium),
            Text(
              session.name,
              style: Theme.of(context).textTheme.headlineLarge
                  ?.copyWith(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 28),
            if (session.canMark) ...[
              FilledButton.icon(
                onPressed: () => _openScanner(context, ScanMode.marcacion),
                icon: const Icon(Icons.qr_code_scanner_rounded),
                label: const Padding(
                  padding: EdgeInsets.all(16),
                  child: Text('Escanear QR de asistencia'),
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () => _showToday(context),
                icon: const Icon(Icons.schedule_rounded),
                label: const Padding(
                  padding: EdgeInsets.all(14),
                  child: Text('Mi marcación de hoy'),
                ),
              ),
            ],
            if (session.canVisit) ...[
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: () => _openScanner(context, ScanMode.visita),
                icon: const Icon(Icons.location_on_outlined),
                label: const Padding(
                  padding: EdgeInsets.all(16),
                  child: Text('Escanear QR de visita'),
                ),
              ),
            ],
          ],
        ),
      ),
    ),
  );
}

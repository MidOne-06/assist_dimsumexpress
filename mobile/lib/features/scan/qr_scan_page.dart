import 'dart:typed_data';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/api/mobile_api_client.dart';

enum ScanMode { marcacion, visita }

class QrScanPage extends StatefulWidget {
  const QrScanPage({super.key, required this.client, required this.mode});

  final MobileApiClient client;
  final ScanMode mode;

  @override
  State<QrScanPage> createState() => _QrScanPageState();
}

class _QrScanPageState extends State<QrScanPage> {
  final _controller = MobileScannerController();
  bool _processing = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onDetect(BarcodeCapture capture) async {
    if (_processing || capture.barcodes.isEmpty) return;
    final raw = capture.barcodes.first.rawValue;
    if (raw == null || raw.isEmpty) return;
    final token = Uri.tryParse(raw)?.queryParameters['token'] ?? raw;
    setState(() => _processing = true);
    await _controller.stop();

    try {
      final path = widget.mode == ScanMode.marcacion
          ? '/marcaciones/validar-qr'
          : '/visitas/validar-qr';
      final response = await widget.client.post(path, {'token': token});
      if (response['confirmado'] != true) throw StateError(response['mensaje']);
      if (!mounted) return;
      await _chooseAction(token, response);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              error is StateError
                  ? error.message.toString()
                  : MobileApiClient.messageFor(error),
            ),
          ),
        );
        setState(() => _processing = false);
        await _controller.start();
      }
    }
  }

  Future<void> _chooseAction(
    String token,
    Map<String, dynamic> response,
  ) async {
    final action = await showModalBottomSheet<String>(
      context: context,
      isDismissible: false,
      enableDrag: false,
      builder: (context) {
        if (widget.mode == ScanMode.visita) {
          final action = response['accion'] as String;
          final label = action == 'ingreso'
              ? 'Registrar ingreso de visita'
              : 'Registrar salida de visita';
          return SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    response['local'] as String,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  const SizedBox(height: 20),
                  FilledButton(
                    onPressed: () => Navigator.pop(context, action),
                    child: Text(label),
                  ),
                  TextButton(
                    onPressed: () => Navigator.pop(context),
                    child: const Text('Cancelar'),
                  ),
                ],
              ),
            ),
          );
        }

        final options = (response['acciones'] as List<dynamic>)
            .cast<Map<String, dynamic>>();
        return SafeArea(
          child: ListView(
            shrinkWrap: true,
            padding: const EdgeInsets.all(20),
            children: [
              const Text(
                'Elige tu marcación',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              ...options.map(
                (item) => ListTile(
                  enabled: item['habilitada'] == true,
                  title: Text(item['etiqueta'] as String),
                  subtitle: item['motivo'] == null
                      ? null
                      : Text(item['motivo'] as String),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.pop(context, item['tipo'] as String),
                ),
              ),
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Cancelar'),
              ),
            ],
          ),
        );
      },
    );

    if (action == null) {
      if (mounted) {
        setState(() => _processing = false);
        await _controller.start();
      }
      return;
    }

    try {
      final path = widget.mode == ScanMode.marcacion
          ? '/marcaciones'
          : '/visitas';
      final response = await widget.client.post(path, {
        'token': token,
        'accion': action,
      });
      if (!mounted) return;
      final data = widget.mode == ScanMode.marcacion
          ? response['marcacion'] as Map<String, dynamic>
          : response['visita'] as Map<String, dynamic>;
      await _showConfirmation(data);
      if (mounted) Navigator.pop(context);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(MobileApiClient.messageFor(error))),
        );
        setState(() => _processing = false);
        await _controller.start();
      }
    }
  }

  Future<void> _showConfirmation(Map<String, dynamic> data) async {
    final captureKey = GlobalKey();
    final title = widget.mode == ScanMode.marcacion
        ? (data['confirmacion'] as Map<String, dynamic>)['evento'] as String
        : data['mensaje'] as String;

    await showDialog<void>(
      context: context,
      builder: (context) => RepaintBoundary(
        key: captureKey,
        child: AlertDialog(
          icon: const Icon(Icons.check_circle, color: Colors.green, size: 48),
          title: Text(title),
          content: Text(
            'Registrado a las ${data['hora']}.\nLa operación fue guardada correctamente.',
          ),
          actions: [
            TextButton.icon(
              onPressed: () => _shareCapture(captureKey, title),
              icon: const Icon(Icons.share_outlined),
              label: const Text('Compartir'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('Listo'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _shareCapture(GlobalKey captureKey, String title) async {
    try {
      final boundary =
          captureKey.currentContext?.findRenderObject()
              as RenderRepaintBoundary?;
      if (boundary == null) return;

      final image = await boundary.toImage(pixelRatio: 3);
      final byteData = await image.toByteData(format: ui.ImageByteFormat.png);
      final bytes = byteData?.buffer.asUint8List();
      if (bytes == null) return;

      await SharePlus.instance.share(
        ShareParams(
          text: 'DIM SUM EXPRESS · $title',
          files: [
            XFile.fromData(Uint8List.fromList(bytes), mimeType: 'image/png'),
          ],
          fileNameOverrides: ['marcacion-dimsum.png'],
        ),
      );
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('No se pudo preparar la imagen para compartir.'),
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        widget.mode == ScanMode.marcacion
            ? 'Escanear asistencia'
            : 'Escanear visita',
      ),
    ),
    body: Stack(
      fit: StackFit.expand,
      children: [
        MobileScanner(controller: _controller, onDetect: _onDetect),
        const Center(
          child: Icon(
            Icons.qr_code_scanner_rounded,
            color: Colors.white70,
            size: 220,
          ),
        ),
        if (_processing)
          const ColoredBox(
            color: Colors.black54,
            child: Center(child: CircularProgressIndicator()),
          ),
      ],
    ),
  );
}

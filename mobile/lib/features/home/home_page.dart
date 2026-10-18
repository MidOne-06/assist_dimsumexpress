import 'package:flutter/material.dart';

import '../../core/api/mobile_api_client.dart';
import '../../core/auth/mobile_session.dart';
import '../../core/ui/brand_glyph.dart';
import '../scan/qr_scan_page.dart';

class HomePage extends StatefulWidget {
  const HomePage({
    super.key,
    required this.client,
    required this.session,
    required this.onLogout,
  });

  final MobileApiClient client;
  final MobileSession session;
  final Future<void> Function() onLogout;

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  static const _surface = Color(0xff0b1c37);
  static const _blue = Color(0xff1677ff);
  static const _line = Color(0xff5480af);
  static const _mint = Color(0xff7ef0ae);

  int _tab = 0;
  bool _loadingToday = false;
  String? _todayError;
  List<_AttendanceRecord> _today = const [];

  @override
  void initState() {
    super.initState();
    _loadToday();
  }

  Future<void> _loadToday() async {
    if (!widget.session.canMark) return;
    setState(() {
      _loadingToday = true;
      _todayError = null;
    });

    try {
      final data = await widget.client.get('/marcaciones/hoy');
      final rawRecords = data['marcaciones'];
      final records = rawRecords is List
          ? rawRecords
                .whereType<Map>()
                .map(
                  (record) => _AttendanceRecord(
                    label: record['etiqueta']?.toString() ?? 'Marcación',
                    time: record['hora']?.toString() ?? '--:--',
                  ),
                )
                .toList(growable: false)
          : const <_AttendanceRecord>[];
      if (!mounted) return;
      setState(() => _today = records);
    } catch (error) {
      if (!mounted) return;
      setState(() => _todayError = MobileApiClient.messageFor(error));
    } finally {
      if (mounted) setState(() => _loadingToday = false);
    }
  }

  Future<void> _openScanner(ScanMode mode) async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (_) => QrScanPage(client: widget.client, mode: mode),
      ),
    );
    if (result == true && mounted) {
      await _loadToday();
      if (mode == ScanMode.marcacion && mounted) setState(() => _tab = 1);
    }
  }

  Future<void> _beginScan() async {
    final canMark = widget.session.canMark;
    final canVisit = widget.session.canVisit;
    if (!canMark && !canVisit) {
      _showMessage('Tu perfil no tiene una acción de marcación habilitada.');
      return;
    }
    if (canMark && canVisit) {
      final mode = await showModalBottomSheet<ScanMode>(
        context: context,
        backgroundColor: _surface,
        showDragHandle: true,
        builder: (sheetContext) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  '¿Qué deseas registrar?',
                  style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Escanea el código QR correspondiente a tu acción.',
                  style: TextStyle(color: Color(0xffa9bfda)),
                ),
                const SizedBox(height: 18),
                _ModeChoice(
                  glyph: BrandGlyphKind.attendance,
                  title: 'Mi asistencia',
                  detail: 'Ingreso, refrigerio o salida de turno',
                  onTap: () => Navigator.pop(sheetContext, ScanMode.marcacion),
                ),
                const SizedBox(height: 10),
                _ModeChoice(
                  glyph: BrandGlyphKind.visit,
                  title: 'Visita de supervisor',
                  detail: 'Ingreso o salida del local visitado',
                  onTap: () => Navigator.pop(sheetContext, ScanMode.visita),
                ),
              ],
            ),
          ),
        ),
      );
      if (mode != null && mounted) await _openScanner(mode);
      return;
    }
    await _openScanner(canMark ? ScanMode.marcacion : ScanMode.visita);
  }

  void _showMessage(String message) =>
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xff07172e),
    body: SafeArea(
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 220),
        child: switch (_tab) {
          0 => _homeView(),
          1 => _dayView(),
          _ => _profileView(),
        },
      ),
    ),
    bottomNavigationBar: _NavigationDock(
      activeTab: _tab,
      canScan: widget.session.canMark || widget.session.canVisit,
      onTabChanged: (tab) => setState(() => _tab = tab),
      onScan: _beginScan,
    ),
  );

  Widget _homeView() => _PageScroll(
    key: const ValueKey('home'),
    onRefresh: _loadToday,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _BrandHeader(section: 'ASISTENCIA MÓVIL'),
        const SizedBox(height: 28),
        Text(
          'Hola,',
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
            color: const Color(0xffc3d4eb),
            fontWeight: FontWeight.w400,
          ),
        ),
        Text(
          _firstName(widget.session.name),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.displaySmall
              ?.copyWith(fontWeight: FontWeight.w800, color: Colors.white),
        ),
        const SizedBox(height: 24),
        if (widget.session.canMark || widget.session.canVisit)
          _PrimaryScanCard(
            isSupervisor: widget.session.canVisit,
            onTap: _beginScan,
          )
        else
          const _AccessNotice(),
        if (widget.session.canMark) ...[
          const SizedBox(height: 18),
          _TodaySummary(
            records: _today,
            loading: _loadingToday,
            onTap: () => setState(() => _tab = 1),
          ),
        ],
        if (widget.session.canVisit) ...[
          SizedBox(height: widget.session.canMark ? 16 : 18),
          const _SectionLabel('ACCESO DE SUPERVISIÓN'),
          const SizedBox(height: 10),
          _VisitCard(onTap: () => _openScanner(ScanMode.visita)),
        ],
      ],
    ),
  );

  Widget _dayView() => _PageScroll(
    key: const ValueKey('day'),
    onRefresh: _loadToday,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _BrandHeader(section: 'MI DÍA'),
        const SizedBox(height: 28),
        Text(
          'Mi marcación',
          style: Theme.of(context).textTheme.headlineMedium
              ?.copyWith(fontWeight: FontWeight.w800, color: Colors.white),
        ),
        const SizedBox(height: 6),
        Text(
          _todayLabel(),
          style: const TextStyle(color: Color(0xffa9bfda), fontSize: 16),
        ),
        const SizedBox(height: 24),
        if (!widget.session.canMark)
          const _EmptyState(
            glyph: BrandGlyphKind.day,
            title: 'No hay marcaciones para mostrar',
            detail: 'Este acceso está configurado para visitas de supervisor.',
          )
        else if (_loadingToday && _today.isEmpty)
          const Padding(
            padding: EdgeInsets.only(top: 64),
            child: Center(child: CircularProgressIndicator()),
          )
        else if (_todayError != null)
          _RetryState(message: _todayError!, onRetry: _loadToday)
        else if (_today.isEmpty)
          const _EmptyState(
            glyph: BrandGlyphKind.attendance,
            title: 'Aún no registras asistencia',
            detail: 'Cuando realices tu primera marcación, aparecerá aquí.',
          )
        else
          _AttendanceTimeline(records: _today),
      ],
    ),
  );

  Widget _profileView() => _PageScroll(
    key: const ValueKey('profile'),
    onRefresh: () async {},
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const _BrandHeader(section: 'MI PERFIL'),
        const SizedBox(height: 32),
        Center(
          child: Container(
            height: 82,
            width: 82,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: _blue,
              border: Border.all(color: const Color(0xff9dc4ff), width: 2),
              boxShadow: const [
                BoxShadow(color: Color(0x551677ff), blurRadius: 24),
              ],
            ),
            child: Text(
              _initials(widget.session.name),
              style: const TextStyle(
                fontSize: 25,
                fontWeight: FontWeight.w800,
                color: Colors.white,
              ),
            ),
          ),
        ),
        const SizedBox(height: 14),
        Center(
          child: Text(
            widget.session.name,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
          ),
        ),
        const SizedBox(height: 4),
        Center(
          child: Text(
            _roleLabel(),
            style: const TextStyle(color: Color(0xffa9bfda)),
          ),
        ),
        const SizedBox(height: 30),
        const _SectionLabel('PERMISOS ACTIVOS'),
        const SizedBox(height: 12),
        _AccessRow(
          glyph: BrandGlyphKind.attendance,
          title: 'Marcación de asistencia',
          enabled: widget.session.canMark,
        ),
        const SizedBox(height: 10),
        _AccessRow(
          glyph: BrandGlyphKind.visit,
          title: 'Marcación de visitas',
          enabled: widget.session.canVisit,
        ),
        const SizedBox(height: 28),
        _OutlinedAction(
          glyph: BrandGlyphKind.exit,
          label: 'Cerrar sesión',
          onTap: widget.onLogout,
        ),
      ],
    ),
  );

  String _roleLabel() {
    if (widget.session.canMark && widget.session.canVisit) {
      return 'Colaborador y supervisor';
    }
    if (widget.session.canVisit) {
      return 'Supervisor';
    }
    if (widget.session.canMark) {
      return 'Colaborador';
    }
    return 'Usuario';
  }

  String _todayLabel() {
    const weekdays = [
      'lunes',
      'martes',
      'miércoles',
      'jueves',
      'viernes',
      'sábado',
      'domingo',
    ];
    const months = [
      'enero',
      'febrero',
      'marzo',
      'abril',
      'mayo',
      'junio',
      'julio',
      'agosto',
      'septiembre',
      'octubre',
      'noviembre',
      'diciembre',
    ];
    final now = DateTime.now();
    return '${weekdays[now.weekday - 1]} ${now.day} de ${months[now.month - 1]}';
  }

  String _firstName(String value) {
    final words = value.trim().split(RegExp(r'\s+'));
    return words.isEmpty || words.first.isEmpty ? value : words.first;
  }

  String _initials(String value) {
    final words = value
        .trim()
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty);
    return words.take(2).map((word) => word[0].toUpperCase()).join();
  }
}

class _PageScroll extends StatelessWidget {
  const _PageScroll({super.key, required this.child, required this.onRefresh});

  final Widget child;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) => RefreshIndicator(
    onRefresh: onRefresh,
    color: _HomePageState._blue,
    backgroundColor: _HomePageState._surface,
    child: SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(24, 20, 24, 132),
      child: child,
    ),
  );
}

class _BrandHeader extends StatelessWidget {
  const _BrandHeader({required this.section});

  final String section;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Container(
        height: 38,
        width: 58,
        alignment: Alignment.center,
        child: Image.asset(
          'assets/branding/dim_sum_express_logo.png',
          fit: BoxFit.contain,
          semanticLabel: 'DIM SUM EXPRESS',
        ),
      ),
      const SizedBox(width: 10),
      Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'DIM SUM EXPRESS',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.3,
            ),
          ),
          Text(
            section,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              letterSpacing: 1.5,
              color: Color(0xff94b4da),
            ),
          ),
        ],
      ),
    ],
  );
}

class _PrimaryScanCard extends StatelessWidget {
  const _PrimaryScanCard({required this.isSupervisor, required this.onTap});

  final bool isSupervisor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        colors: [Color(0xff126bfb), Color(0xff1c86ff)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      borderRadius: BorderRadius.circular(26),
      boxShadow: const [
        BoxShadow(
          color: Color(0x661677ff),
          blurRadius: 28,
          offset: Offset(0, 12),
        ),
      ],
    ),
    child: Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(26),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(22),
          child: Row(
            children: [
              const BrandGlyph(
                BrandGlyphKind.scan,
                size: 38,
                color: Colors.white,
                strokeWidth: 2.1,
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Escanear QR',
                      style: TextStyle(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      isSupervisor
                          ? 'Asistencia o visita'
                          : 'Registra tu asistencia',
                      style: const TextStyle(color: Color(0xffdceaff)),
                    ),
                  ],
                ),
              ),
              const BrandGlyph(
                BrandGlyphKind.arrow,
                color: Colors.white,
                size: 28,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _TodaySummary extends StatelessWidget {
  const _TodaySummary({
    required this.records,
    required this.loading,
    required this.onTap,
  });

  final List<_AttendanceRecord> records;
  final bool loading;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => _SurfaceCard(
    child: Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              Container(
                height: 46,
                width: 46,
                alignment: Alignment.center,
                decoration: const BoxDecoration(
                  color: Color(0xff183559),
                  shape: BoxShape.circle,
                ),
                child: const BrandGlyph(
                  BrandGlyphKind.day,
                  color: Color(0xffa9c7f0),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Mi día',
                      style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      loading
                          ? 'Actualizando marcaciones…'
                          : records.isEmpty
                          ? 'Sin marcaciones todavía'
                          : '${records.length} marcación${records.length == 1 ? '' : 'es'} registrada${records.length == 1 ? '' : 's'}',
                      style: const TextStyle(color: Color(0xffa9bfda)),
                    ),
                  ],
                ),
              ),
              const BrandGlyph(
                BrandGlyphKind.arrow,
                color: Color(0xffa9c7f0),
                size: 22,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _VisitCard extends StatelessWidget {
  const _VisitCard({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => _SurfaceCard(
    child: Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: const Padding(
          padding: EdgeInsets.all(18),
          child: Row(
            children: [
              BrandGlyph(
                BrandGlyphKind.visit,
                color: Color(0xff82edb0),
                size: 27,
              ),
              SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Registrar visita',
                      style: TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 17,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Escanea el QR del local',
                      style: TextStyle(color: Color(0xffa9bfda)),
                    ),
                  ],
                ),
              ),
              BrandGlyph(
                BrandGlyphKind.arrow,
                color: Color(0xffa9c7f0),
                size: 22,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _AccessNotice extends StatelessWidget {
  const _AccessNotice();

  @override
  Widget build(BuildContext context) => const _SurfaceCard(
    child: Padding(
      padding: EdgeInsets.all(20),
      child: Text(
        'Tu cuenta no tiene una modalidad de registro habilitada. Comunícate con tu administrador.',
        style: TextStyle(color: Color(0xffc8d7eb), height: 1.45),
      ),
    ),
  );
}

class _AttendanceTimeline extends StatelessWidget {
  const _AttendanceTimeline({required this.records});

  final List<_AttendanceRecord> records;

  @override
  Widget build(BuildContext context) => _SurfaceCard(
    child: Padding(
      padding: const EdgeInsets.fromLTRB(20, 18, 20, 10),
      child: Column(
        children: [
          for (var index = 0; index < records.length; index++)
            _TimelineRow(
              record: records[index],
              isLast: index == records.length - 1,
            ),
        ],
      ),
    ),
  );
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.record, required this.isLast});

  final _AttendanceRecord record;
  final bool isLast;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 76,
    child: Row(
      children: [
        SizedBox(
          width: 24,
          child: Stack(
            alignment: Alignment.topCenter,
            children: [
              Container(
                margin: const EdgeInsets.only(top: 5),
                height: 14,
                width: 14,
                decoration: const BoxDecoration(
                  color: _HomePageState._mint,
                  shape: BoxShape.circle,
                ),
              ),
              if (!isLast)
                Positioned(
                  top: 24,
                  bottom: 0,
                  child: Container(width: 2, color: const Color(0xff30557f)),
                ),
            ],
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            record.label,
            style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16),
          ),
        ),
        Text(
          record.time,
          style: const TextStyle(
            color: _HomePageState._mint,
            fontSize: 18,
            fontWeight: FontWeight.w800,
          ),
        ),
      ],
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({
    required this.glyph,
    required this.title,
    required this.detail,
  });

  final BrandGlyphKind glyph;
  final String title;
  final String detail;

  @override
  Widget build(BuildContext context) => _SurfaceCard(
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 34),
      child: Column(
        children: [
          BrandGlyph(glyph, color: const Color(0xff8fb9e9), size: 42),
          const SizedBox(height: 18),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Text(
            detail,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xffa9bfda), height: 1.4),
          ),
        ],
      ),
    ),
  );
}

class _RetryState extends StatelessWidget {
  const _RetryState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => _SurfaceCard(
    child: Padding(
      padding: const EdgeInsets.all(22),
      child: Column(
        children: [
          const BrandGlyph(
            BrandGlyphKind.refresh,
            color: Color(0xfff0b06f),
            size: 38,
          ),
          const SizedBox(height: 14),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: Color(0xffe4c2ad)),
          ),
          const SizedBox(height: 14),
          TextButton(onPressed: onRetry, child: const Text('Reintentar')),
        ],
      ),
    ),
  );
}

class _AccessRow extends StatelessWidget {
  const _AccessRow({
    required this.glyph,
    required this.title,
    required this.enabled,
  });

  final BrandGlyphKind glyph;
  final String title;
  final bool enabled;

  @override
  Widget build(BuildContext context) => _SurfaceCard(
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          BrandGlyph(
            glyph,
            color: enabled ? const Color(0xff82edb0) : const Color(0xff71829a),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              title,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
          Text(
            enabled ? 'Activo' : 'No activo',
            style: TextStyle(
              color: enabled
                  ? const Color(0xff82edb0)
                  : const Color(0xff71829a),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    ),
  );
}

class _OutlinedAction extends StatelessWidget {
  const _OutlinedAction({
    required this.glyph,
    required this.label,
    required this.onTap,
  });

  final BrandGlyphKind glyph;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity,
    child: OutlinedButton(
      onPressed: onTap,
      style: OutlinedButton.styleFrom(
        foregroundColor: const Color(0xffffadad),
        side: const BorderSide(color: Color(0xff8f4d59)),
        padding: const EdgeInsets.symmetric(vertical: 16),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          BrandGlyph(glyph, color: const Color(0xffffadad), size: 21),
          const SizedBox(width: 10),
          Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
        ],
      ),
    ),
  );
}

class _ModeChoice extends StatelessWidget {
  const _ModeChoice({
    required this.glyph,
    required this.title,
    required this.detail,
    required this.onTap,
  });

  final BrandGlyphKind glyph;
  final String title;
  final String detail;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => _SurfaceCard(
    child: Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              BrandGlyph(glyph, color: const Color(0xff91c3ff), size: 27),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      detail,
                      style: const TextStyle(
                        color: Color(0xffa9bfda),
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
              const BrandGlyph(
                BrandGlyphKind.arrow,
                color: Color(0xffa9c7f0),
                size: 20,
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _SurfaceCard extends StatelessWidget {
  const _SurfaceCard({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
    decoration: BoxDecoration(
      color: _HomePageState._surface,
      borderRadius: BorderRadius.circular(20),
      border: Border.all(color: _HomePageState._line),
    ),
    child: child,
  );
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text,
    style: const TextStyle(
      color: Color(0xff8fb4dd),
      fontSize: 11,
      fontWeight: FontWeight.w800,
      letterSpacing: 1.3,
    ),
  );
}

class _NavigationDock extends StatelessWidget {
  const _NavigationDock({
    required this.activeTab,
    required this.canScan,
    required this.onTabChanged,
    required this.onScan,
  });

  final int activeTab;
  final bool canScan;
  final ValueChanged<int> onTabChanged;
  final VoidCallback onScan;

  @override
  Widget build(BuildContext context) => SafeArea(
    top: false,
    child: Container(
      height: 82,
      decoration: const BoxDecoration(
        color: Color(0xff091a33),
        border: Border(top: BorderSide(color: Color(0xff31557c))),
      ),
      child: Stack(
        alignment: Alignment.topCenter,
        children: [
          Row(
            children: [
              Expanded(
                child: _NavItem(
                  glyph: BrandGlyphKind.home,
                  label: 'Inicio',
                  active: activeTab == 0,
                  onTap: () => onTabChanged(0),
                ),
              ),
              Expanded(
                child: _NavItem(
                  glyph: BrandGlyphKind.day,
                  label: 'Mi día',
                  active: activeTab == 1,
                  onTap: () => onTabChanged(1),
                ),
              ),
              const SizedBox(width: 84),
              Expanded(
                child: _NavItem(
                  glyph: BrandGlyphKind.profile,
                  label: 'Perfil',
                  active: activeTab == 2,
                  onTap: () => onTabChanged(2),
                ),
              ),
            ],
          ),
          Transform.translate(
            offset: const Offset(0, -25),
            child: Semantics(
              button: true,
              label: 'Escanear código QR',
              child: Material(
                color: Colors.transparent,
                child: InkResponse(
                  radius: 40,
                  onTap: canScan ? onScan : null,
                  child: Container(
                    height: 68,
                    width: 68,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: canScan
                          ? _HomePageState._blue
                          : const Color(0xff4d5e72),
                      border: Border.all(
                        color: const Color(0xffa9cbff),
                        width: 3,
                      ),
                      boxShadow: const [
                        BoxShadow(color: Color(0x771677ff), blurRadius: 22),
                      ],
                    ),
                    child: const BrandGlyph(
                      BrandGlyphKind.scan,
                      color: Colors.white,
                      size: 31,
                      strokeWidth: 2.1,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.glyph,
    required this.label,
    required this.active,
    required this.onTap,
  });

  final BrandGlyphKind glyph;
  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = active ? const Color(0xff8fc2ff) : const Color(0xff9aacc1);
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.only(top: 12),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              BrandGlyph(glyph, color: color, size: 24),
              const SizedBox(height: 5),
              Text(
                label,
                style: TextStyle(
                  fontSize: 11,
                  color: color,
                  fontWeight: active ? FontWeight.w800 : FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _AttendanceRecord {
  const _AttendanceRecord({required this.label, required this.time});

  final String label;
  final String time;
}

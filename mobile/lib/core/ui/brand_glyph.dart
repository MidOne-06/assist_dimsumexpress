import 'dart:math' as math;

import 'package:flutter/material.dart';

/// Iconografía vectorial propia de la aplicación móvil.
///
/// Evita que la navegación dependa de un set genérico y mantiene un lenguaje
/// visual consistente en Android e iOS sin cargar fuentes ni imágenes extras.
enum BrandGlyphKind {
  home,
  scan,
  day,
  profile,
  attendance,
  visit,
  exit,
  refresh,
  arrow,
  check,
  share,
}

class BrandGlyph extends StatelessWidget {
  const BrandGlyph(
    this.kind, {
    super.key,
    this.size = 24,
    required this.color,
    this.strokeWidth = 1.9,
  });

  final BrandGlyphKind kind;
  final double size;
  final Color color;
  final double strokeWidth;

  @override
  Widget build(BuildContext context) => Semantics(
    image: true,
    child: CustomPaint(
      size: Size.square(size),
      painter: _BrandGlyphPainter(
        kind: kind,
        color: color,
        strokeWidth: strokeWidth,
      ),
    ),
  );
}

class _BrandGlyphPainter extends CustomPainter {
  const _BrandGlyphPainter({
    required this.kind,
    required this.color,
    required this.strokeWidth,
  });

  final BrandGlyphKind kind;
  final Color color;
  final double strokeWidth;

  @override
  void paint(Canvas canvas, Size size) {
    final unit = size.width / 24;
    canvas.scale(unit);
    final line = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    final fill = Paint()
      ..color = color
      ..style = PaintingStyle.fill;

    switch (kind) {
      case BrandGlyphKind.home:
        _home(canvas, line);
      case BrandGlyphKind.scan:
        _scan(canvas, line, fill);
      case BrandGlyphKind.day:
        _day(canvas, line);
      case BrandGlyphKind.profile:
        _profile(canvas, line);
      case BrandGlyphKind.attendance:
        _attendance(canvas, line, fill);
      case BrandGlyphKind.visit:
        _visit(canvas, line, fill);
      case BrandGlyphKind.exit:
        _exit(canvas, line);
      case BrandGlyphKind.refresh:
        _refresh(canvas, line, fill);
      case BrandGlyphKind.arrow:
        _arrow(canvas, line);
      case BrandGlyphKind.check:
        _check(canvas, fill);
      case BrandGlyphKind.share:
        _share(canvas, line, fill);
    }
  }

  void _home(Canvas canvas, Paint line) {
    final roof = Path()
      ..moveTo(3.4, 10.8)
      ..lineTo(12, 3.8)
      ..lineTo(20.6, 10.8);
    canvas.drawPath(roof, line);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(5.4, 9.8, 13.2, 10.2),
        const Radius.circular(2),
      ),
      line,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(10, 14.3, 4, 5.7),
        const Radius.circular(1),
      ),
      line,
    );
  }

  void _scan(Canvas canvas, Paint line, Paint fill) {
    canvas.drawLine(const Offset(3.6, 8.8), const Offset(3.6, 3.6), line);
    canvas.drawLine(const Offset(3.6, 3.6), const Offset(8.8, 3.6), line);
    canvas.drawLine(const Offset(15.2, 3.6), const Offset(20.4, 3.6), line);
    canvas.drawLine(const Offset(20.4, 3.6), const Offset(20.4, 8.8), line);
    canvas.drawLine(const Offset(3.6, 15.2), const Offset(3.6, 20.4), line);
    canvas.drawLine(const Offset(3.6, 20.4), const Offset(8.8, 20.4), line);
    canvas.drawLine(const Offset(15.2, 20.4), const Offset(20.4, 20.4), line);
    canvas.drawLine(const Offset(20.4, 20.4), const Offset(20.4, 15.2), line);
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(8.5, 8.5, 7, 7),
        const Radius.circular(2),
      ),
      line,
    );
    canvas.drawCircle(const Offset(12, 12), 1.2, fill);
  }

  void _day(Canvas canvas, Paint line) {
    canvas.drawCircle(const Offset(12, 12), 8.1, line);
    canvas.drawLine(const Offset(12, 7.5), const Offset(12, 12.2), line);
    canvas.drawLine(const Offset(12, 12.2), const Offset(15.6, 14.3), line);
    canvas.drawCircle(const Offset(12, 12.2), 0.65, line);
  }

  void _profile(Canvas canvas, Paint line) {
    canvas.drawCircle(const Offset(12, 8.2), 3.35, line);
    final body = Path()
      ..moveTo(5.1, 20)
      ..cubicTo(5.8, 15.8, 8.3, 13.8, 12, 13.8)
      ..cubicTo(15.7, 13.8, 18.2, 15.8, 18.9, 20);
    canvas.drawPath(body, line);
  }

  void _attendance(Canvas canvas, Paint line, Paint fill) {
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(4.1, 3.7, 15.8, 16.6),
        const Radius.circular(4),
      ),
      line,
    );
    for (final y in [8.2, 12.0, 15.8]) {
      canvas.drawCircle(Offset(8, y), 1.05, fill);
      canvas.drawLine(Offset(11, y), Offset(16.5, y), line);
    }
  }

  void _visit(Canvas canvas, Paint line, Paint fill) {
    final pin = Path()
      ..moveTo(12, 21)
      ..cubicTo(8.2, 16.4, 6.4, 13.5, 6.4, 10.1)
      ..arcToPoint(
        const Offset(17.6, 10.1),
        radius: const Radius.circular(5.6),
        clockwise: true,
      )
      ..cubicTo(17.6, 13.5, 15.8, 16.4, 12, 21);
    canvas.drawPath(pin, line);
    canvas.drawCircle(const Offset(12, 10.1), 1.9, fill);
  }

  void _exit(Canvas canvas, Paint line) {
    canvas.drawRRect(
      RRect.fromRectAndRadius(
        const Rect.fromLTWH(4, 4, 10.2, 16),
        const Radius.circular(2),
      ),
      line,
    );
    canvas.drawLine(const Offset(11.4, 12), const Offset(21, 12), line);
    canvas.drawLine(const Offset(17.6, 8.6), const Offset(21, 12), line);
    canvas.drawLine(const Offset(21, 12), const Offset(17.6, 15.4), line);
  }

  void _refresh(Canvas canvas, Paint line, Paint fill) {
    canvas.drawArc(
      const Rect.fromLTWH(4.1, 4.1, 15.8, 15.8),
      -math.pi * .95,
      math.pi * 1.48,
      false,
      line,
    );
    final tip = Path()
      ..moveTo(18.3, 4.8)
      ..lineTo(20.5, 9)
      ..lineTo(16, 8.1)
      ..close();
    canvas.drawPath(tip, fill);
  }

  void _arrow(Canvas canvas, Paint line) {
    canvas.drawLine(const Offset(4, 12), const Offset(20, 12), line);
    canvas.drawLine(const Offset(15.4, 7.4), const Offset(20, 12), line);
    canvas.drawLine(const Offset(20, 12), const Offset(15.4, 16.6), line);
  }

  void _check(Canvas canvas, Paint fill) {
    canvas.drawCircle(const Offset(12, 12), 8.4, fill);
    final contrast = Paint()
      ..color = const Color(0xff06162f)
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth + .35
      ..strokeCap = StrokeCap.round
      ..strokeJoin = StrokeJoin.round;
    canvas.drawLine(const Offset(8, 12), const Offset(10.8, 14.8), contrast);
    canvas.drawLine(
      const Offset(10.8, 14.8),
      const Offset(16.6, 8.9),
      contrast,
    );
  }

  void _share(Canvas canvas, Paint line, Paint fill) {
    const points = [Offset(7, 12), Offset(16.9, 6.8), Offset(16.9, 17.2)];
    canvas.drawLine(points[0], points[1], line);
    canvas.drawLine(points[0], points[2], line);
    for (final point in points) {
      canvas.drawCircle(point, 2.15, fill);
    }
  }

  @override
  bool shouldRepaint(covariant _BrandGlyphPainter oldDelegate) =>
      oldDelegate.kind != kind ||
      oldDelegate.color != color ||
      oldDelegate.strokeWidth != strokeWidth;
}

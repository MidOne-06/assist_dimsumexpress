import 'dart:async';

import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

class SplashPage extends StatefulWidget {
  const SplashPage({super.key, required this.onFinished});

  final VoidCallback onFinished;

  @override
  State<SplashPage> createState() => _SplashPageState();
}

class _SplashPageState extends State<SplashPage> {
  static const _splashVideo = 'assets/branding/dim_sum_express_splash.mp4';
  static const _brandLogo = 'assets/branding/dim_sum_express_logo.png';

  late final VideoPlayerController _controller;
  Timer? _fallbackTimer;
  bool _isReady = false;
  bool _hasFinished = false;

  @override
  void initState() {
    super.initState();
    _controller = VideoPlayerController.asset(_splashVideo)
      ..addListener(_onTick);
    _prepareVideo();
    _fallbackTimer = Timer(const Duration(seconds: 5), _finish);
  }

  Future<void> _prepareVideo() async {
    try {
      await _controller.initialize();
      if (!mounted) return;
      setState(() => _isReady = true);
      await _controller.play();
    } catch (_) {
      _finish();
    }
  }

  void _onTick() {
    final value = _controller.value;
    if (!value.isInitialized || value.duration == Duration.zero) return;
    final finalFrame = value.duration - const Duration(milliseconds: 80);
    if (value.position >= finalFrame) _finish();
  }

  void _finish() {
    if (_hasFinished || !mounted) return;
    _hasFinished = true;
    _fallbackTimer?.cancel();
    widget.onFinished();
  }

  @override
  void dispose() {
    _fallbackTimer?.cancel();
    _controller
      ..removeListener(_onTick)
      ..dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    backgroundColor: const Color(0xff06142a),
    body: Center(
      child: _isReady
          ? AspectRatio(
              aspectRatio: _controller.value.aspectRatio,
              child: VideoPlayer(_controller),
            )
          : Padding(
              padding: const EdgeInsets.all(48),
              child: Image.asset(_brandLogo, fit: BoxFit.contain),
            ),
    ),
  );
}

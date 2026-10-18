import 'package:flutter/material.dart';

import '../../core/api/mobile_api_client.dart';
import '../../core/auth/mobile_session.dart';
import '../../core/ui/brand_glyph.dart';

class LoginPage extends StatefulWidget {
  const LoginPage({
    super.key,
    required this.client,
    required this.deviceId,
    required this.onLoggedIn,
  });

  final MobileApiClient client;
  final String deviceId;
  final ValueChanged<MobileSession> onLoggedIn;

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final _formKey = GlobalKey<FormState>();
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _loading = false;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _loading = true);
    try {
      final session = await widget.client.login(
        email: _email.text.trim(),
        password: _password.text,
        device: widget.deviceId,
      );
      widget.onLoggedIn(session);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(MobileApiClient.messageFor(error))),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(28),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const BrandGlyph(
                  BrandGlyphKind.scan,
                  size: 72,
                  color: Color(0xffe21b2d),
                  strokeWidth: 1.6,
                ),
                const SizedBox(height: 16),
                Text(
                  'DIM SUM EXPRESS',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 6),
                const Text('Asistencia y visitas', textAlign: TextAlign.center),
                const SizedBox(height: 38),
                TextFormField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'Correo'),
                  validator: (value) => value == null || !value.contains('@')
                      ? 'Ingresa un correo válido.'
                      : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(labelText: 'Contraseña'),
                  validator: (value) => value == null || value.isEmpty
                      ? 'Ingresa tu contraseña.'
                      : null,
                ),
                const SizedBox(height: 28),
                FilledButton(
                  onPressed: _loading ? null : _submit,
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: _loading
                        ? const SizedBox(
                            height: 22,
                            width: 22,
                            child: CircularProgressIndicator(),
                          )
                        : const Text('Ingresar'),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}

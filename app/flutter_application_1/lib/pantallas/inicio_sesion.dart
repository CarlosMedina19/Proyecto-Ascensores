import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/cliente_api.dart';
import '../estado/sesion.dart';
import '../widgets/comunes.dart';

/// RF-001: inicio de sesión con el correo y la contraseña de la cuenta.
class PantallaInicioSesion extends StatefulWidget {
  const PantallaInicioSesion({super.key});

  @override
  State<PantallaInicioSesion> createState() => _PantallaInicioSesionState();
}

class _PantallaInicioSesionState extends State<PantallaInicioSesion> {
  final _formulario = GlobalKey<FormState>();
  final _correo = TextEditingController();
  final _password = TextEditingController();
  bool _verPassword = false;
  bool _enviando = false;
  String? _error;
  Map<String, String> _erroresCampo = {};

  @override
  void dispose() {
    _correo.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _ingresar() async {
    if (!_formulario.currentState!.validate()) return;
    setState(() {
      _enviando = true;
      _error = null;
      _erroresCampo = {};
    });
    try {
      await context.read<Sesion>().iniciarSesion(_correo.text, _password.text);
    } on ErrorApi catch (e) {
      setState(() {
        _error = e.mensaje;
        _erroresCampo = {
          for (final campo in ['correo', 'password'])
            if (e.errorDe(campo) != null) campo: e.errorDe(campo)!,
        };
      });
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final motivo = context.select<Sesion, String?>((s) => s.motivoCierre);
    final esquema = Theme.of(context).colorScheme;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _formulario,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const _Presentacion(),
                    const SizedBox(height: 28),
                    const Etiqueta('Bienvenido de nuevo'),
                    const SizedBox(height: 4),
                    const Text('Ingresar', style: TextStyle(fontSize: 32, fontWeight: FontWeight.w800, letterSpacing: -0.8)),
                    const SizedBox(height: 18),
                    if (motivo != null && _error == null) _Aviso(texto: motivo, color: esquema.primary),
                    if (_error != null) _Aviso(texto: _error!, color: esquema.error),
                    TextFormField(
                      key: const Key('campo-correo'),
                      controller: _correo,
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.username],
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        labelText: 'Correo electrónico',
                        prefixIcon: const Icon(Icons.mail_outline),
                        errorText: _erroresCampo['correo'],
                      ),
                      validator: (valor) {
                        final texto = valor?.trim() ?? '';
                        if (texto.isEmpty) return 'Escriba su correo';
                        if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(texto)) return 'El correo no es válido';
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      key: const Key('campo-password'),
                      controller: _password,
                      obscureText: !_verPassword,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: (_) => _ingresar(),
                      decoration: InputDecoration(
                        labelText: 'Contraseña',
                        prefixIcon: const Icon(Icons.lock_outline),
                        errorText: _erroresCampo['password'],
                        suffixIcon: IconButton(
                          tooltip: _verPassword ? 'Ocultar contraseña' : 'Mostrar contraseña',
                          icon: Icon(_verPassword ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                          onPressed: () => setState(() => _verPassword = !_verPassword),
                        ),
                      ),
                      validator: (valor) => (valor ?? '').isEmpty ? 'Escriba su contraseña' : null,
                    ),
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PantallaRecuperarContrasena())),
                        child: const Text('¿Olvidó su contraseña?'),
                      ),
                    ),
                    const SizedBox(height: 6),
                    FilledButton.icon(
                      key: const Key('boton-ingresar'),
                      onPressed: _enviando ? null : _ingresar,
                      icon: _enviando
                          ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Icon(Icons.arrow_forward),
                      label: Text(_enviando ? 'Ingresando…' : 'Ingresar'),
                    ),
                    const SizedBox(height: 18),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.shield_outlined, size: 14, color: esquema.onSurfaceVariant),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'La sesión se cierra tras 10 minutos sin actividad. Cinco intentos fallidos bloquean la cuenta durante 15 minutos.',
                            style: TextStyle(fontSize: 12, color: esquema.onSurfaceVariant),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Presentacion extends StatelessWidget {
  const _Presentacion();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: const RadialGradient(center: Alignment(0.8, -0.9), radius: 1.4, colors: [Color(0xFF5A2FC2), Color(0xFF0E0A17)]),
      ),
      child: const Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              LogoSigma(tamano: 40),
              SizedBox(width: 10),
              Text(
                'SIGMA',
                style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800, letterSpacing: 0.5),
              ),
            ],
          ),
          SizedBox(height: 20),
          Text(
            'Cada ascensor, cada piso, bajo control.',
            style: TextStyle(color: Colors.white, fontSize: 24, height: 1.1, fontWeight: FontWeight.w700, letterSpacing: -0.5),
          ),
          SizedBox(height: 8),
          Text('Sistema Integral de Gestión y Mantenimiento de Ascensores', style: TextStyle(color: Color(0xFFB6AEC6), fontSize: 13)),
        ],
      ),
    );
  }
}

class _Aviso extends StatelessWidget {
  const _Aviso({required this.texto, required this.color});

  final String texto;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 14),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color.withValues(alpha: 0.25)),
      ),
      child: Text(texto, style: TextStyle(color: color)),
    );
  }
}

/// RF-001: recuperación de contraseña. El enlace llega por correo y vence en 60 minutos.
class PantallaRecuperarContrasena extends StatefulWidget {
  const PantallaRecuperarContrasena({super.key});

  @override
  State<PantallaRecuperarContrasena> createState() => _PantallaRecuperarContrasenaState();
}

class _PantallaRecuperarContrasenaState extends State<PantallaRecuperarContrasena> {
  final _correo = TextEditingController();
  String? _mensaje;
  String? _error;
  bool _enviando = false;

  @override
  void dispose() {
    _correo.dispose();
    super.dispose();
  }

  Future<void> _enviar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final mensaje = await context.read<Sesion>().repositorio.recuperarContrasena(_correo.text.trim());
      setState(() => _mensaje = mensaje);
    } catch (e) {
      setState(() => _error = mensajeError(e));
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Recuperar contraseña')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Text('Le enviaremos un enlace para crear una nueva contraseña. El enlace vence en 60 minutos y solo puede usarse una vez.'),
          const SizedBox(height: 20),
          if (_mensaje != null)
            _Aviso(texto: _mensaje!, color: Theme.of(context).colorScheme.primary)
          else ...[
            if (_error != null) _Aviso(texto: _error!, color: Theme.of(context).colorScheme.error),
            TextField(
              controller: _correo,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(labelText: 'Correo electrónico', prefixIcon: Icon(Icons.mail_outline)),
            ),
            const SizedBox(height: 16),
            FilledButton(onPressed: _enviando ? null : _enviar, child: Text(_enviando ? 'Enviando…' : 'Enviar enlace')),
          ],
        ],
      ),
    );
  }
}

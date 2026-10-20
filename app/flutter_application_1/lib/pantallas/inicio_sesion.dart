import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/cliente_api.dart';
import '../estado/sesion.dart';
import '../tema.dart';
import '../widgets/comunes.dart';
import '../widgets/puertas.dart';

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
      await context.read<Sesion>().iniciarSesion(_correo.text, _password.text, antesDeEntrar: PuertasSigma.cerrar);
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
                    const SizedBox(height: 18),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(18, 20, 18, 18),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            const Etiqueta('Bienvenido de nuevo'),
                            const SizedBox(height: 4),
                            Text('Ingresa a SIGMA', style: Theme.of(context).textTheme.headlineMedium),
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
                                onPressed: () =>
                                    Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PantallaRecuperarContrasena())),
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
                            const SizedBox(height: 16),
                            const Divider(),
                            const SizedBox(height: 12),
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
                    const SizedBox(height: 18),
                    Text(
                      'SENA · Proyecto formativo SIGMA',
                      textAlign: TextAlign.center,
                      style: estiloEtiqueta(esquema.outline, tamano: 10.5),
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

/// Presentación del prototipo: fondo tinta con cuadrícula, marca SIGMA y lema con degradado.
class _Presentacion extends StatelessWidget {
  const _Presentacion();

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: Container(
        decoration: const BoxDecoration(
          gradient: RadialGradient(center: Alignment(1, -1), radius: 1.5, colors: [Color(0x805A30C5), Color(0x005A30C5)]),
          color: SigmaColores.tinta,
        ),
        child: CustomPaint(
          painter: _Cuadricula(),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 20, 20, 22),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const MarcaSigma(tamanoLogo: 40, sobreOscuro: true),
                const SizedBox(height: 22),
                Text.rich(
                  TextSpan(
                    children: [
                      const TextSpan(text: 'Cada ascensor, '),
                      WidgetSpan(
                        alignment: PlaceholderAlignment.baseline,
                        baseline: TextBaseline.alphabetic,
                        child: ShaderMask(
                          blendMode: BlendMode.srcIn,
                          shaderCallback: (r) => const LinearGradient(colors: [Color(0xFFCDC0ED), SigmaColores.lima]).createShader(r),
                          child: const Text('cada piso', style: _estiloLema),
                        ),
                      ),
                      const TextSpan(text: ', bajo control.'),
                    ],
                  ),
                  style: _estiloLema,
                ),
                const SizedBox(height: 10),
                const Text(
                  'Programación de mantenimientos, checklist digital en campo e informes firmados en PDF.',
                  style: TextStyle(color: Color(0xFFCDC0ED), fontSize: 13.5),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

const _estiloLema = TextStyle(
  fontFamily: SigmaFuentes.titulos,
  color: SigmaColores.tintaTexto,
  fontSize: 27,
  height: 1.08,
  fontWeight: FontWeight.w700,
  letterSpacing: -0.8,
);

/// Cuadrícula tenue del fondo de la presentación.
class _Cuadricula extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final pincel = Paint()
      ..color = Colors.white.withValues(alpha: 0.04)
      ..strokeWidth = 1;
    for (var x = 0.0; x < size.width; x += 32) {
      canvas.drawLine(Offset(x, 0), Offset(x, size.height), pincel);
    }
    for (var y = 0.0; y < size.height; y += 32) {
      canvas.drawLine(Offset(0, y), Offset(size.width, y), pincel);
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
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

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import '../configuracion.dart';

import '../datos/cliente_api.dart';
import '../estado/preferencias.dart';
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
                    DecoratedBox(
                      decoration: ShapeDecoration(
                        color: esquema.surface,
                        shape: BordeLuminoso(degradado: degradadoBorde(Theme.of(context).brightness), radio: 20),
                        shadows: const [BoxShadow(color: Color(0x335B34C4), blurRadius: 28, offset: Offset(0, 14), spreadRadius: -16)],
                      ),
                      child: Padding(
                        padding: const EdgeInsets.fromLTRB(20, 22, 20, 18),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Text('Ingrese a SIGMA', style: Theme.of(context).textTheme.headlineMedium),
                            const SizedBox(height: 4),
                            Text('Use el correo y la contraseña de su cuenta.', style: TextStyle(color: esquema.onSurfaceVariant)),
                            const SizedBox(height: 20),
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
                      context.watch<Preferencias>().demostracion
                          ? 'SENA · Proyecto formativo SIGMA · Modo demostración'
                          : 'SIGMA · Gestión y mantenimiento de ascensores',
                      textAlign: TextAlign.center,
                      style: estiloEtiqueta(esquema.outline, tamano: 10.5),
                    ),
                    const SizedBox(height: 6),
                    Center(
                      child: TextButton.icon(
                        key: const Key('boton-servidor'),
                        onPressed: () => showDialog<void>(context: context, builder: (_) => const _DialogoServidor()),
                        icon: const Icon(Icons.dns_outlined, size: 16),
                        label: Text('Servidor: ${Uri.tryParse(context.read<Sesion>().repositorio.api.urlBase)?.authority ?? ''}'),
                      ),
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

/// Presentación del prototipo: fondo tinta con resplandor morado, marca SIGMA y lema.
class _Presentacion extends StatelessWidget {
  const _Presentacion();

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(20),
      child: Container(
        decoration: const BoxDecoration(
          gradient: RadialGradient(center: Alignment(1, -1), radius: 1.5, colors: [Color(0x40A58BFF), Color(0x00A58BFF)]),
          color: SigmaColores.tinta,
        ),
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
                    const TextSpan(
                      text: 'cada piso',
                      style: TextStyle(color: SigmaColores.lima),
                    ),
                    const TextSpan(text: ', bajo control.'),
                  ],
                ),
                style: _estiloLema,
              ),
              const SizedBox(height: 10),
              const Text(
                'Programación de mantenimientos, checklist digital en campo e informes firmados en PDF.',
                style: TextStyle(color: SigmaColores.escenaTextoSuave, fontSize: 13.5),
              ),
            ],
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
  fontWeight: FontWeight.w600,
  letterSpacing: -0.5,
);

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

/// Dirección del servidor de SIGMA. En un celular real se escribe la IP del computador en la red
/// Wi-Fi (por ejemplo 192.168.1.100:8000); se prueba antes de guardarla.
class _DialogoServidor extends StatefulWidget {
  const _DialogoServidor();

  @override
  State<_DialogoServidor> createState() => _DialogoServidorState();
}

class _DialogoServidorState extends State<_DialogoServidor> {
  late final _campo = TextEditingController(
    text: Uri.tryParse(context.read<Sesion>().repositorio.api.urlBase)?.authority ?? '',
  );
  bool _probando = false;
  String? _error;

  @override
  void dispose() {
    _campo.dispose();
    super.dispose();
  }

  Future<void> _guardar() async {
    final url = Configuracion.normalizarServidor(_campo.text);
    if (url.isEmpty) {
      setState(() => _error = 'Escriba la dirección del servidor.');
      return;
    }
    setState(() {
      _probando = true;
      _error = null;
    });
    try {
      // Cualquier respuesta (aunque sea "sin sesión") confirma que el servidor está al otro lado.
      await http.get(Uri.parse('$url/perfil'), headers: {'Accept': 'application/json'}).timeout(const Duration(seconds: 8));
    } catch (_) {
      if (mounted) {
        setState(() {
          _probando = false;
          _error = 'No hay respuesta en esa dirección. Revise que el celular esté en la misma red Wi-Fi que el computador y que SIGMA esté encendido.';
        });
      }
      return;
    }
    if (!mounted) return;
    context.read<Sesion>().repositorio.api.urlBase = url;
    await context.read<Preferencias>().cambiarServidor(url);
    if (mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Servidor de SIGMA'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Escriba la dirección del computador donde corre SIGMA, con el puerto.'),
          const SizedBox(height: 12),
          TextField(
            key: const Key('campo-servidor'),
            controller: _campo,
            keyboardType: TextInputType.url,
            autocorrect: false,
            decoration: InputDecoration(labelText: 'Dirección', hintText: '192.168.1.100:8000', errorText: _error, errorMaxLines: 4),
            onSubmitted: (_) => _guardar(),
          ),
        ],
      ),
      actions: [
        TextButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Cancelar')),
        FilledButton(onPressed: _probando ? null : _guardar, child: Text(_probando ? 'Probando…' : 'Probar y guardar')),
      ],
    );
  }
}

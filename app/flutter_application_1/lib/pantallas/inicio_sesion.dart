import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:http/http.dart' as http;
import 'package:provider/provider.dart';

import '../configuracion.dart';

import '../datos/cliente_api.dart';
import '../datos/modelos.dart';
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

  /// Con la verificación en dos pasos, el desafío del código que llegó al correo.
  DesafioVerificacion? _verificacion;

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
      final verificacion = await context.read<Sesion>().iniciarSesion(_correo.text, _password.text, antesDeEntrar: PuertasSigma.cerrar);
      if (verificacion != null && mounted) {
        setState(() {
          _verificacion = verificacion;
          _password.clear();
        });
      }
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
                            if (_verificacion != null)
                              _PasoCodigo(verificacion: _verificacion!, alVolver: () => setState(() => _verificacion = null))
                            else ...[
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
                            ],
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

/// Campo de 6 dígitos para el código de verificación (el teclado del celular lo propone desde el SMS o el correo).
class _CampoCodigo extends StatelessWidget {
  const _CampoCodigo({required this.controlador, this.etiqueta = 'Código de verificación', this.error, this.alEnviar});

  final TextEditingController controlador;
  final String etiqueta;
  final String? error;
  final VoidCallback? alEnviar;

  @override
  Widget build(BuildContext context) {
    return TextField(
      key: const Key('campo-codigo'),
      controller: controlador,
      keyboardType: TextInputType.number,
      maxLength: 6,
      autofocus: true,
      autofillHints: const [AutofillHints.oneTimeCode],
      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
      textAlign: TextAlign.center,
      style: const TextStyle(fontSize: 24, letterSpacing: 8, fontFeatures: [FontFeature.tabularFigures()]),
      onSubmitted: (_) => alEnviar?.call(),
      decoration: InputDecoration(labelText: etiqueta, counterText: '', errorText: error, errorMaxLines: 3),
    );
  }
}

/// Botón «Reenviar código» con la espera de 60 segundos que también exige el servidor.
class _BotonReenviar extends StatefulWidget {
  const _BotonReenviar({required this.alReenviar});

  final Future<bool> Function() alReenviar;

  @override
  State<_BotonReenviar> createState() => _BotonReenviarState();
}

class _BotonReenviarState extends State<_BotonReenviar> {
  int _espera = 60;
  Timer? _temporizador;

  @override
  void initState() {
    super.initState();
    _contar();
  }

  void _contar() {
    _temporizador?.cancel();
    _espera = 60;
    _temporizador = Timer.periodic(const Duration(seconds: 1), (temporizador) {
      if (!mounted) return;
      setState(() => _espera--);
      if (_espera <= 0) temporizador.cancel();
    });
  }

  @override
  void dispose() {
    _temporizador?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return TextButton.icon(
      onPressed: _espera > 0
          ? null
          : () async {
              if (await widget.alReenviar() && mounted) setState(_contar);
            },
      icon: const Icon(Icons.refresh, size: 18),
      label: Text(_espera > 0 ? 'Reenviar en $_espera s' : 'Reenviar código'),
    );
  }
}

/// Segundo paso del ingreso: el código de 6 dígitos enviado al correo.
class _PasoCodigo extends StatefulWidget {
  const _PasoCodigo({required this.verificacion, required this.alVolver});

  final DesafioVerificacion verificacion;
  final VoidCallback alVolver;

  @override
  State<_PasoCodigo> createState() => _PasoCodigoState();
}

class _PasoCodigoState extends State<_PasoCodigo> {
  final _codigo = TextEditingController();
  late String _aviso = widget.verificacion.mensaje;
  String? _error;
  bool _enviando = false;

  @override
  void dispose() {
    _codigo.dispose();
    super.dispose();
  }

  Future<void> _verificar() async {
    if (_codigo.text.length != 6) {
      setState(() => _error = 'Escriba los 6 dígitos del código.');
      return;
    }
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      await context.read<Sesion>().verificarCodigo(widget.verificacion.desafio, _codigo.text, antesDeEntrar: PuertasSigma.cerrar);
    } on ErrorApi catch (e) {
      if (mounted) {
        setState(() {
          _error = e.errorDe('codigo') ?? e.mensaje;
          _codigo.clear();
        });
      }
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  Future<bool> _reenviar() async {
    try {
      final nuevo = await context.read<Sesion>().repositorio.reenviarCodigo(widget.verificacion.desafio);
      if (mounted) {
        setState(() {
          _aviso = nuevo.mensaje;
          _error = null;
        });
      }
      return true;
    } on ErrorApi catch (e) {
      if (mounted) setState(() => _error = e.errorDe('codigo') ?? e.mensaje);
      return false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text('Verificación en dos pasos', style: Theme.of(context).textTheme.headlineMedium),
        const SizedBox(height: 4),
        Text(
          'Escriba el código de 6 dígitos que enviamos a ${widget.verificacion.destino}. Vence en ${widget.verificacion.minutos} minutos.',
          style: TextStyle(color: esquema.onSurfaceVariant),
        ),
        const SizedBox(height: 16),
        if (_error == null && _aviso.isNotEmpty) _Aviso(texto: _aviso, color: esquema.primary),
        _CampoCodigo(controlador: _codigo, error: _error, alEnviar: _verificar),
        const SizedBox(height: 4),
        Text('Si no lo ve, revise la carpeta de spam o «Promociones».', style: TextStyle(fontSize: 12, color: esquema.onSurfaceVariant)),
        const SizedBox(height: 14),
        FilledButton.icon(
          key: const Key('boton-verificar'),
          onPressed: _enviando ? null : _verificar,
          icon: _enviando ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.verified_user_outlined),
          label: Text(_enviando ? 'Verificando…' : 'Verificar e ingresar'),
        ),
        const SizedBox(height: 6),
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            TextButton.icon(onPressed: widget.alVolver, icon: const Icon(Icons.arrow_back, size: 18), label: const Text('Volver')),
            Flexible(child: _BotonReenviar(alReenviar: _reenviar)),
          ],
        ),
      ],
    );
  }
}

/// RF-001: recuperación de contraseña por correo (enlace de 60 minutos) o con el celular (código por SMS).
class PantallaRecuperarContrasena extends StatefulWidget {
  const PantallaRecuperarContrasena({super.key});

  @override
  State<PantallaRecuperarContrasena> createState() => _PantallaRecuperarContrasenaState();
}

class _PantallaRecuperarContrasenaState extends State<PantallaRecuperarContrasena> {
  final _correo = TextEditingController();
  final _telefono = TextEditingController();
  final _codigo = TextEditingController();
  final _nueva = TextEditingController();
  final _confirmacion = TextEditingController();
  String _metodo = 'correo';
  DesafioVerificacion? _solicitud;
  String? _mensaje;
  String? _error;
  Map<String, String> _erroresCampo = {};
  bool _enviando = false;

  @override
  void dispose() {
    for (final c in [_correo, _telefono, _codigo, _nueva, _confirmacion]) {
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _ejecutar(Future<void> Function() accion) async {
    setState(() {
      _enviando = true;
      _error = null;
      _erroresCampo = {};
    });
    try {
      await accion();
    } on ErrorApi catch (e) {
      setState(() {
        _error = e.mensaje;
        _erroresCampo = {
          for (final campo in ['telefono', 'codigo', 'password'])
            if (e.errorDe(campo) != null) campo: e.errorDe(campo)!,
        };
        if (_erroresCampo.isNotEmpty) _error = null;
      });
    } catch (e) {
      setState(() => _error = mensajeError(e));
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  Future<void> _enviarCorreo() => _ejecutar(() async {
    final mensaje = await context.read<Sesion>().repositorio.recuperarContrasena(_correo.text.trim());
    setState(() => _mensaje = mensaje);
  });

  Future<void> _pedirCodigo() => _ejecutar(() async {
    final solicitud = await context.read<Sesion>().repositorio.recuperarConTelefono(_telefono.text.trim());
    setState(() => _solicitud = solicitud);
  });

  Future<void> _restablecer() => _ejecutar(() async {
    final mensaje = await context.read<Sesion>().repositorio.restablecerConTelefono(
      desafio: _solicitud!.desafio,
      codigo: _codigo.text,
      nueva: _nueva.text,
      confirmacion: _confirmacion.text,
    );
    setState(() {
      _mensaje = mensaje;
      _solicitud = null;
    });
  });

  Future<bool> _reenviarSms() async {
    try {
      final nuevo = await context.read<Sesion>().repositorio.reenviarCodigo(_solicitud!.desafio, proposito: 'recuperacion');
      if (mounted) setState(() => _solicitud = nuevo);
      return true;
    } on ErrorApi catch (e) {
      if (mounted) setState(() => _error = e.errorDe('codigo') ?? e.mensaje);
      return false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Recuperar contraseña')),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (_mensaje != null) ...[
            _Aviso(texto: _mensaje!, color: esquema.primary),
            OutlinedButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Volver al inicio de sesión')),
          ] else ...[
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'correo', label: Text('Por correo'), icon: Icon(Icons.mail_outline)),
                ButtonSegment(value: 'celular', label: Text('Por celular'), icon: Icon(Icons.smartphone)),
              ],
              selected: {_metodo},
              onSelectionChanged: (s) => setState(() {
                _metodo = s.first;
                _error = null;
                _erroresCampo = {};
              }),
            ),
            const SizedBox(height: 20),
            if (_error != null) _Aviso(texto: _error!, color: esquema.error),
            if (_metodo == 'correo') ...[
              const Text('Le enviaremos un enlace para crear una nueva contraseña. El enlace vence en 60 minutos y solo puede usarse una vez.'),
              const SizedBox(height: 16),
              TextField(
                controller: _correo,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(labelText: 'Correo electrónico', prefixIcon: Icon(Icons.mail_outline)),
              ),
              const SizedBox(height: 16),
              FilledButton(onPressed: _enviando ? null : _enviarCorreo, child: Text(_enviando ? 'Enviando…' : 'Enviar enlace')),
            ] else if (_solicitud == null) ...[
              const Text('Escriba el celular registrado en su cuenta: le enviaremos un SMS con un código de 6 dígitos que vence en 10 minutos.'),
              const SizedBox(height: 16),
              TextField(
                key: const Key('campo-telefono'),
                controller: _telefono,
                keyboardType: TextInputType.phone,
                autofillHints: const [AutofillHints.telephoneNumberNational],
                decoration: InputDecoration(labelText: 'Número de celular', hintText: '300 123 4567', prefixIcon: const Icon(Icons.smartphone), errorText: _erroresCampo['telefono']),
              ),
              const SizedBox(height: 16),
              FilledButton(
                key: const Key('boton-enviar-sms'),
                onPressed: _enviando ? null : _pedirCodigo,
                child: Text(_enviando ? 'Enviando…' : 'Enviar código por SMS'),
              ),
            ] else ...[
              _Aviso(texto: _solicitud!.mensaje, color: esquema.primary),
              if (_solicitud!.codigoDemo != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 14),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: esquema.primaryContainer.withValues(alpha: 0.5),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: esquema.primary),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('SMS simulado para ${_solicitud!.destino} (modo de demostración, sin operador):', style: const TextStyle(fontSize: 12.5)),
                      const SizedBox(height: 4),
                      Text(_solicitud!.codigoDemo!, style: TextStyle(fontSize: 24, letterSpacing: 6, fontWeight: FontWeight.w600, color: esquema.primary)),
                    ],
                  ),
                ),
              if (_solicitud!.nota != null) _Aviso(texto: _solicitud!.nota!, color: esquema.primary),
              _CampoCodigo(controlador: _codigo, etiqueta: 'Código del SMS', error: _erroresCampo['codigo']),
              const SizedBox(height: 12),
              TextField(
                key: const Key('campo-nueva'),
                controller: _nueva,
                obscureText: true,
                decoration: InputDecoration(labelText: 'Nueva contraseña', helperText: 'Mínimo 8 caracteres, con letras y números.', errorText: _erroresCampo['password'], errorMaxLines: 3),
              ),
              const SizedBox(height: 12),
              TextField(
                key: const Key('campo-confirmacion'),
                controller: _confirmacion,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Confirmar nueva contraseña'),
              ),
              const SizedBox(height: 16),
              FilledButton(
                key: const Key('boton-restablecer'),
                onPressed: _enviando ? null : _restablecer,
                child: Text(_enviando ? 'Guardando…' : 'Restablecer contraseña'),
              ),
              const SizedBox(height: 6),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  TextButton(onPressed: () => setState(() => _solicitud = null), child: const Text('Cambiar número')),
                  Flexible(child: _BotonReenviar(alReenviar: _reenviarSms)),
                ],
              ),
            ],
          ],
        ],
      ),
    );
  }
}

/// Dirección del servidor de SIGMA: el dominio de producción (sigma.proyectoscampus.top) o, en desarrollo,
/// la IP del computador en la red Wi-Fi (por ejemplo 192.168.1.100:8000); se prueba antes de guardarla.
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
            decoration: InputDecoration(labelText: 'Dirección', hintText: 'sigma.proyectoscampus.top', errorText: _error, errorMaxLines: 4),
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

import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../datos/cliente_api.dart';

/// Archivo de un envío pendiente (por ejemplo, una foto de la planilla), en base64.
class ArchivoPendiente {
  ArchivoPendiente({required this.campo, required this.nombre, required this.base64, this.campos = const {}});

  factory ArchivoPendiente.desdeJson(Map<String, dynamic> json) => ArchivoPendiente(
    campo: json['campo'] as String,
    nombre: json['nombre'] as String,
    base64: json['base64'] as String,
    campos: ((json['campos'] as Map?) ?? const {}).map((k, v) => MapEntry('$k', '$v')),
  );

  final String campo;
  final String nombre;
  final String base64;
  final Map<String, String> campos;

  Map<String, dynamic> aJson() => {'campo': campo, 'nombre': nombre, 'base64': base64, 'campos': campos};
}

/// Un cambio que el técnico hizo sin conexión y que espera para llegar al servidor.
class EnvioPendiente {
  EnvioPendiente({
    String? clave,
    required this.usuarioId,
    required this.metodo,
    required this.ruta,
    this.cuerpo,
    this.archivo,
    required this.descripcion,
    required this.grupo,
    DateTime? creadoEn,
    this.error,
    this.rechazado = false,
    this.intentos = 0,
  }) : clave = clave ?? nuevaClave(),
       creadoEn = creadoEn ?? DateTime.now();

  factory EnvioPendiente.desdeJson(Map<String, dynamic> json) => EnvioPendiente(
    clave: json['clave'] as String,
    usuarioId: (json['usuario_id'] as num).toInt(),
    metodo: json['metodo'] as String,
    ruta: json['ruta'] as String,
    cuerpo: (json['cuerpo'] as Map?)?.cast<String, Object?>(),
    archivo: json['archivo'] == null ? null : ArchivoPendiente.desdeJson(json['archivo'] as Map<String, dynamic>),
    descripcion: json['descripcion'] as String? ?? '',
    grupo: json['grupo'] as String? ?? '',
    creadoEn: DateTime.tryParse(json['creado_en'] as String? ?? ''),
    error: json['error'] as String?,
    rechazado: json['rechazado'] as bool? ?? false,
    intentos: (json['intentos'] as num?)?.toInt() ?? 0,
  );

  /// Clave única: el servidor la usa para no aplicar dos veces el mismo cambio (X-Clave-Envio).
  final String clave;
  final int usuarioId;
  final String metodo;
  final String ruta;
  final Map<String, Object?>? cuerpo;
  final ArchivoPendiente? archivo;

  /// Texto para el técnico, por ejemplo «OT-2026-00362 · Iniciar trabajo».
  final String descripcion;

  /// A qué pertenece (`orden:12`, `emergencia:3`, `visita:5`): la pantalla muestra si tiene cambios por enviar.
  final String grupo;
  final DateTime creadoEn;
  String? error;

  /// El servidor lo revisó y no lo aceptó (regla de negocio): no se reintenta; el técnico lo ve y lo descarta.
  bool rechazado;
  int intentos;

  static String nuevaClave() {
    final azar = Random.secure();
    return List.generate(16, (_) => azar.nextInt(256).toRadixString(16).padLeft(2, '0')).join();
  }

  Map<String, dynamic> aJson() => {
    'clave': clave,
    'usuario_id': usuarioId,
    'metodo': metodo,
    'ruta': ruta,
    'cuerpo': cuerpo,
    'archivo': archivo?.aJson(),
    'descripcion': descripcion,
    'grupo': grupo,
    'creado_en': creadoEn.toIso8601String(),
    'error': error,
    'rechazado': rechazado,
    'intentos': intentos,
  };
}

/// Cola de cambios guardados en el celular. Se envían en el orden en que se hicieron apenas hay conexión: al
/// guardar, cada [intervalo] mientras quede algo, al volver a la app y en cuanto cualquier consulta responde.
/// Si el servidor rechaza un cambio (por ejemplo, la orden ya no admite ese paso), queda marcado para que el
/// técnico lo vea; los demás siguen.
class ColaEnvios extends ChangeNotifier {
  ColaEnvios(this.api, {this.intervalo = const Duration(seconds: 20)}) {
    api.alCambiarConexion = _alCambiarConexion;
  }

  static const _llave = 'sigma.cola_envios';

  /// Tras estos intentos con error del servidor (5xx) el cambio queda marcado como rechazado.
  static const maximoIntentosServidor = 5;

  final ClienteApi api;
  final Duration intervalo;

  /// Usuario de la sesión: solo se envían sus cambios (otro técnico en el mismo celular no los ve).
  int? _usuarioId;
  final List<EnvioPendiente> _todos = [];
  Future<void>? _carga;
  Future<void>? _enCurso;
  Timer? _reloj;
  bool _desechada = false;
  bool enviando = false;
  bool sinConexion = false;
  DateTime? ultimoEnvio;

  /// Cambios enviados en la última sincronización (para el aviso «Se enviaron N cambios»).
  int enviadosUltimaVez = 0;

  int? get usuarioId => _usuarioId;
  set usuarioId(int? valor) {
    if (valor == _usuarioId) return;
    _usuarioId = valor;
    if (valor == null) _detener();
    _avisar();
  }

  List<EnvioPendiente> get envios => [
    for (final e in _todos)
      if (e.usuarioId == _usuarioId) e,
  ];
  int get pendientes => envios.where((e) => !e.rechazado).length;
  int get rechazados => envios.where((e) => e.rechazado).length;
  bool tienePendientes(String grupo) => envios.any((e) => e.grupo == grupo && !e.rechazado);
  List<EnvioPendiente> delGrupo(String grupo) => [
    for (final e in envios)
      if (e.grupo == grupo) e,
  ];

  Future<void> cargar() => _carga ??= _leer();

  Future<void> _leer() async {
    try {
      final texto = (await SharedPreferences.getInstance().timeout(const Duration(seconds: 3))).getString(_llave);
      if (texto != null) {
        for (final e in jsonDecode(texto) as List) {
          try {
            _todos.add(EnvioPendiente.desdeJson(e as Map<String, dynamic>));
          } catch (_) {
            // Un envío dañado no impide leer los demás.
          }
        }
      }
    } catch (_) {}
    _avisar();
  }

  Future<void> _guardar() async {
    try {
      final preferencias = await SharedPreferences.getInstance().timeout(const Duration(seconds: 3));
      await preferencias.setString(_llave, jsonEncode([for (final e in _todos) e.aJson()]));
    } catch (_) {
      // Sin almacenamiento la cola dura mientras la app esté abierta.
    }
  }

  /// Envía un cambio ahora (con su clave para no duplicarlo). Lanza [ErrorApi] si no se pudo.
  Future<Map<String, dynamic>> enviar(EnvioPendiente envio) {
    final cabeceras = {'X-Clave-Envio': envio.clave};
    final archivo = envio.archivo;
    if (archivo != null) {
      return api.subirArchivo(
        envio.ruta,
        campo: archivo.campo,
        bytes: base64Decode(archivo.base64),
        nombreArchivo: archivo.nombre,
        campos: archivo.campos,
        cabeceras: cabeceras,
      );
    }
    return switch (envio.metodo) {
      'PATCH' => api.actualizar(envio.ruta, envio.cuerpo, cabeceras),
      'PUT' => api.reemplazar(envio.ruta, envio.cuerpo, cabeceras),
      'DELETE' => api.eliminar(envio.ruta, cabeceras),
      _ => api.crear(envio.ruta, envio.cuerpo, cabeceras),
    };
  }

  Future<void> encolar(EnvioPendiente envio) async {
    await cargar();
    _todos.add(envio);
    await _guardar();
    _avisar();
    _programar();
  }

  /// Descarta un cambio (solo los rechazados se ofrecen para descartar en la pantalla).
  Future<void> descartar(EnvioPendiente envio) async {
    _todos.removeWhere((e) => e.clave == envio.clave);
    await _guardar();
    _avisar();
  }

  /// Envía en orden los cambios pendientes del usuario. Se detiene sin conexión o si el servidor falla, y lo
  /// reintenta después. Nunca hay dos envíos a la vez: si hay uno en curso, espera a que termine y hace otra pasada.
  Future<void> sincronizar() async {
    while (_enCurso != null) {
      await _enCurso;
    }
    final pasada = _enCurso = _sincronizar().whenComplete(() => _enCurso = null);
    await pasada;
  }

  Future<void> _sincronizar() async {
    await cargar();
    if (_desechada || _usuarioId == null || api.token == null) return;
    final porEnviar = [
      for (final e in envios)
        if (!e.rechazado) e,
    ];
    if (porEnviar.isEmpty) {
      _detener();
      return;
    }
    enviando = true;
    enviadosUltimaVez = 0;
    _avisar();
    try {
      for (final envio in porEnviar) {
        try {
          await enviar(envio);
          _todos.removeWhere((e) => e.clave == envio.clave);
          enviadosUltimaVez++;
          ultimoEnvio = DateTime.now();
        } on ErrorApi catch (error) {
          final reintentable = error.estado == 0 || error.estado == 401 || error.estado == 419 || error.estado == 429;
          if (reintentable) break;
          if (error.estado >= 500) {
            envio.intentos++;
            if (envio.intentos < maximoIntentosServidor) break;
          }
          envio.rechazado = true;
          envio.error = error.mensaje;
        }
        await _guardar();
        _avisar();
      }
    } finally {
      enviando = false;
      await _guardar();
      _avisar();
      _programar();
    }
  }

  void _alCambiarConexion(bool conectado) {
    if (sinConexion == !conectado) return;
    sinConexion = !conectado;
    _avisar();
    if (conectado && pendientes > 0) sincronizar();
  }

  void _programar() {
    if (pendientes > 0 && _usuarioId != null) {
      _reloj ??= Timer.periodic(intervalo, (_) => sincronizar());
    } else {
      _detener();
    }
  }

  void _detener() {
    _reloj?.cancel();
    _reloj = null;
  }

  void _avisar() {
    if (!_desechada) notifyListeners();
  }

  @override
  void dispose() {
    _desechada = true;
    _detener();
    super.dispose();
  }
}

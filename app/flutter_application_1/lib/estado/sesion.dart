import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../configuracion.dart';
import '../datos/cliente_api.dart';
import '../datos/modelos.dart';
import '../datos/repositorio.dart';

/// Dónde se guarda el token. En el dispositivo se usa almacenamiento cifrado.
abstract class AlmacenToken {
  Future<String?> leer();
  Future<void> guardar(String token);
  Future<void> borrar();
}

class AlmacenTokenSeguro implements AlmacenToken {
  static const _clave = 'sigma.token';
  final _almacen = const FlutterSecureStorage();

  // Si el almacenamiento cifrado del teléfono falla (llaves dañadas tras restaurar una copia,
  // fabricantes con Keystore defectuoso), la app no se bloquea: se pide iniciar sesión de nuevo.
  @override
  Future<String?> leer() async {
    try {
      return await _almacen.read(key: _clave).timeout(const Duration(seconds: 5));
    } catch (_) {
      try {
        await _almacen.deleteAll();
      } catch (_) {}
      return null;
    }
  }

  @override
  Future<void> guardar(String token) async {
    try {
      await _almacen.write(key: _clave, value: token);
    } catch (_) {
      // Sin almacenamiento cifrado la sesión dura mientras la app esté abierta.
    }
  }

  @override
  Future<void> borrar() async {
    try {
      await _almacen.delete(key: _clave);
    } catch (_) {}
  }
}

class AlmacenTokenMemoria implements AlmacenToken {
  String? _token;

  @override
  Future<String?> leer() async => _token;

  @override
  Future<void> guardar(String token) async => _token = token;

  @override
  Future<void> borrar() async => _token = null;
}

/// Estado de la sesión del usuario (RF-001, RF-002).
class Sesion extends ChangeNotifier {
  Sesion({required this.repositorio, AlmacenToken? almacen, this.intervaloRevision = const Duration(seconds: 1)})
    : _almacen = almacen ?? AlmacenTokenSeguro() {
    repositorio.api.alExpirarSesion = () => _terminar('Su sesión expiró. Inicie sesión nuevamente.', avisarServidor: false);
  }

  final Repositorio repositorio;
  final AlmacenToken _almacen;
  final Duration intervaloRevision;

  Usuario? usuario;
  bool cargando = true;
  String? motivoCierre;
  int minutosInactividad = Configuracion.minutosInactividadPorDefecto;

  /// Segundos que faltan para cerrar la sesión; no es nulo durante el último minuto.
  int? segundosParaCierre;

  Timer? _reloj;
  DateTime _ultimaActividad = DateTime.now();
  DateTime _ultimoPing = DateTime.now();

  bool get autenticado => usuario != null;
  bool tienePermiso(String permiso) => usuario?.tienePermiso(permiso) ?? false;

  /// Recupera la sesión guardada en el dispositivo, si el token sigue vigente.
  Future<void> iniciar() async {
    // Pase lo que pase, la pantalla de carga termina: nunca queda la app en negro esperando.
    try {
      final token = await _almacen.leer();
      if (token != null) {
        repositorio.api.token = token;
        try {
          usuario = await repositorio.perfil();
          _iniciarReloj();
        } catch (_) {
          repositorio.api.token = null;
          await _almacen.borrar();
        }
      }
    } finally {
      cargando = false;
      notifyListeners();
    }
  }

  /// `antesDeEntrar` corre con las credenciales ya validadas y antes de mostrar la app (transición).
  Future<void> iniciarSesion(String correo, String password, {Future<void> Function()? antesDeEntrar}) async {
    final resultado = await repositorio.iniciarSesion(correo.trim(), password);
    repositorio.api.token = resultado.token;
    if (antesDeEntrar != null) await antesDeEntrar();
    await _almacen.guardar(resultado.token);
    minutosInactividad = resultado.minutosInactividad;
    usuario = resultado.usuario;
    motivoCierre = null;
    _iniciarReloj();
    notifyListeners();
  }

  Future<void> cerrarSesion([String? motivo]) => _terminar(motivo, avisarServidor: true);

  /// Cualquier toque del usuario reinicia el contador de inactividad.
  void registrarActividad() {
    if (usuario == null || segundosParaCierre != null) return;
    _ultimaActividad = DateTime.now();
    // El servidor cuenta la inactividad por solicitudes: se le avisa cada 2 minutos de uso.
    if (DateTime.now().difference(_ultimoPing) > const Duration(minutes: 2)) {
      _ultimoPing = DateTime.now();
      repositorio.perfil().ignore();
    }
  }

  /// "Mantener sesión activa" en el aviso del último minuto.
  void mantenerSesion() {
    segundosParaCierre = null;
    _ultimaActividad = DateTime.now();
    _ultimoPing = DateTime.now();
    repositorio.perfil().ignore();
    notifyListeners();
  }

  void _iniciarReloj() {
    _reloj?.cancel();
    _ultimaActividad = DateTime.now();
    _ultimoPing = DateTime.now();
    segundosParaCierre = null;
    _reloj = Timer.periodic(intervaloRevision, (_) => revisarInactividad(DateTime.now()));
  }

  @visibleForTesting
  void revisarInactividad(DateTime ahora) {
    final limite = Duration(minutes: minutosInactividad);
    final inactivo = ahora.difference(_ultimaActividad);
    if (inactivo >= limite) {
      cerrarSesion('Su sesión se cerró por inactividad.');
    } else if (inactivo >= limite - const Duration(minutes: 1)) {
      segundosParaCierre = (limite - inactivo).inSeconds.clamp(1, 60);
      notifyListeners();
    }
  }

  Future<void> _terminar(String? motivo, {required bool avisarServidor}) async {
    _reloj?.cancel();
    if (avisarServidor && repositorio.api.token != null) {
      try {
        await repositorio.cerrarSesion();
      } catch (_) {
        // Si el token ya no es válido no hay nada que cerrar en el servidor.
      }
    }
    repositorio.api.token = null;
    await _almacen.borrar();
    usuario = null;
    segundosParaCierre = null;
    motivoCierre = motivo;
    // Los borradores de visitas se conservan en el dispositivo (RF-001).
    notifyListeners();
  }

  @override
  void dispose() {
    _reloj?.cancel();
    super.dispose();
  }
}

/// Mensaje legible para mostrar en pantalla a partir de cualquier error.
String mensajeError(Object error) => error is ErrorApi ? error.mensaje : 'Ocurrió un error inesperado.';

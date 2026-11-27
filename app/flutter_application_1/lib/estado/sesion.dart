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

  /// Llave de dispositivo de confianza de una cuenta (verificación en dos pasos ya hecha en este celular).
  Future<String?> leerConfianza(String correo);
  Future<void> guardarConfianza(String correo, String llave);
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

  static String _claveConfianza(String correo) => 'sigma.confianza.${correo.trim().toLowerCase()}';

  @override
  Future<String?> leerConfianza(String correo) async {
    try {
      return await _almacen.read(key: _claveConfianza(correo)).timeout(const Duration(seconds: 5));
    } catch (_) {
      return null;
    }
  }

  @override
  Future<void> guardarConfianza(String correo, String llave) async {
    try {
      await _almacen.write(key: _claveConfianza(correo), value: llave);
    } catch (_) {
      // Sin almacenamiento cifrado se volverá a pedir el código, nada más.
    }
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

  final _confianza = <String, String>{};

  @override
  Future<String?> leerConfianza(String correo) async => _confianza[correo.trim().toLowerCase()];

  @override
  Future<void> guardarConfianza(String correo, String llave) async => _confianza[correo.trim().toLowerCase()] = llave;
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
          // Sin conexión se usa el último perfil guardado: el técnico sigue trabajando y sus cambios quedan en cola.
          usuario = await repositorio.perfil(permitirCopia: true);
          _activarCola();
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
  /// Con la verificación en dos pasos activa no entra: devuelve el desafío para pedir el código del correo.
  Future<DesafioVerificacion?> iniciarSesion(String correo, String password, {Future<void> Function()? antesDeEntrar}) async {
    final resultado = await repositorio.iniciarSesion(correo.trim(), password, confianza: await _almacen.leerConfianza(correo));
    if (resultado.verificacion != null) return resultado.verificacion;
    await _entrar(resultado.token!, resultado.usuario!, resultado.minutosInactividad, antesDeEntrar);
    return null;
  }

  /// Segundo paso del ingreso con el código que llegó al correo.
  Future<void> verificarCodigo(String desafio, String codigo, {Future<void> Function()? antesDeEntrar}) async {
    final resultado = await repositorio.verificarInicioSesion(desafio, codigo);
    // Este celular queda como dispositivo de confianza: el próximo ingreso no pedirá el código.
    if (resultado.confianza != null) await _almacen.guardarConfianza(resultado.usuario!.correo, resultado.confianza!);
    await _entrar(resultado.token!, resultado.usuario!, resultado.minutosInactividad, antesDeEntrar);
  }

  Future<void> _entrar(String token, Usuario nuevo, int minutos, Future<void> Function()? antesDeEntrar) async {
    repositorio.api.token = token;
    if (antesDeEntrar != null) await antesDeEntrar();
    await _almacen.guardar(token);
    minutosInactividad = minutos;
    usuario = nuevo;
    motivoCierre = null;
    _activarCola();
    _iniciarReloj();
    notifyListeners();
  }

  /// Los cambios guardados sin conexión son de cada usuario: se envían los suyos al entrar.
  void _activarCola() {
    repositorio.cola.usuarioId = usuario?.id;
    repositorio.cola.sincronizar();
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
    // Los cambios sin enviar quedan en el celular y se envían cuando ese usuario vuelva a entrar.
    repositorio.cola.usuarioId = null;
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

/// Aviso cuando un cambio quedó guardado sin conexión.
const textoGuardadoSinConexion = 'Guardado en el celular: se enviará solo al tener conexión.';

/// Mensaje legible para mostrar en pantalla a partir de cualquier error.
String mensajeError(Object error) => error is ErrorApi ? error.mensaje : 'Ocurrió un error inesperado.';

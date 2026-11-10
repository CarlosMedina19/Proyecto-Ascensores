import 'dart:async';
import 'dart:io' show Platform;

import 'package:geolocator/geolocator.dart';

import '../datos/repositorio.dart';

/// Estado del envío de la ubicación, para mostrarlo en el detalle de la orden.
enum EstadoUbicacion { inactivo, compartiendo, sinPermiso, gpsApagado, error }

/// Mientras el técnico tiene una orden en proceso, la app envía su posición cada 30 s
/// para el mapa de Auditoría del panel. Se detiene al salir de la orden o al cambiar su estado.
class SeguimientoUbicacion {
  SeguimientoUbicacion(this._repositorio, {this.alCambiar});

  static const intervalo = Duration(seconds: 30);

  /// En las pruebas automáticas no hay GPS ni temporizadores reales.
  static bool get habilitado => !Platform.environment.containsKey('FLUTTER_TEST');

  final Repositorio _repositorio;
  final void Function(EstadoUbicacion estado)? alCambiar;
  Timer? _temporizador;
  int? _ordenId;

  bool get activo => _temporizador != null;

  Future<void> iniciar(int ordenId) async {
    if (!habilitado || (_ordenId == ordenId && activo)) return;
    detener();
    _ordenId = ordenId;
    if (!await _permisoConcedido()) return;
    await _enviar();
    _temporizador = Timer.periodic(intervalo, (_) => _enviar());
  }

  void detener() {
    _temporizador?.cancel();
    _temporizador = null;
    _ordenId = null;
  }

  Future<bool> _permisoConcedido() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        alCambiar?.call(EstadoUbicacion.gpsApagado);
        return false;
      }
      var permiso = await Geolocator.checkPermission();
      if (permiso == LocationPermission.denied) permiso = await Geolocator.requestPermission();
      if (permiso == LocationPermission.denied || permiso == LocationPermission.deniedForever) {
        alCambiar?.call(EstadoUbicacion.sinPermiso);
        return false;
      }
      return true;
    } catch (_) {
      alCambiar?.call(EstadoUbicacion.error);
      return false;
    }
  }

  Future<void> _enviar() async {
    final ordenId = _ordenId;
    if (ordenId == null) return;
    try {
      final posicion = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 20)),
      );
      await _repositorio.enviarUbicacion(ordenId, posicion.latitude, posicion.longitude, posicion.accuracy);
      alCambiar?.call(EstadoUbicacion.compartiendo);
    } catch (_) {
      // Sin señal o sin conexión: se reintenta en el siguiente intervalo.
      alCambiar?.call(EstadoUbicacion.error);
    }
  }
}

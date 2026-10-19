import 'package:flutter/foundation.dart';

/// Configuración general de la app SIGMA.
class Configuracion {
  Configuracion._();

  /// URL base de la API de Laravel. Se puede cambiar al compilar:
  /// `flutter run --dart-define=API_URL=http://192.168.1.10:8000/api/v1`
  static String get urlApi {
    const definida = String.fromEnvironment('API_URL');
    if (definida.isNotEmpty) return definida;

    // El emulador de Android llega al computador por 10.0.2.2.
    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8000/api/v1';
    }
    return 'http://localhost:8000/api/v1';
  }

  /// RF-001: minutos sin actividad antes de cerrar la sesión (la API lo confirma al iniciar sesión).
  static const int minutosInactividadPorDefecto = 10;

  static const Duration tiempoEsperaSolicitud = Duration(seconds: 20);
}

import 'package:flutter/foundation.dart';

/// Configuración general de la app SIGMA.
class Configuracion {
  Configuracion._();

  /// URL base de la API de Laravel. Se puede cambiar al compilar:
  /// `flutter run --dart-define=API_URL=http://192.168.1.10:8000/api/v1`
  /// Dirección elegida en la app (Configurar servidor). Tiene prioridad sobre la de compilación.
  static String? urlPersonalizada;

  static String get urlApi {
    if (urlPersonalizada != null && urlPersonalizada!.isNotEmpty) return urlPersonalizada!;
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

  /// Normaliza lo que escribe el usuario: "192.168.1.100:8000" → "http://192.168.1.100:8000/api/v1"
  /// y "sigma.proyectoscampus.top" → "https://sigma.proyectoscampus.top/api/v1" (los dominios van por HTTPS).
  static String normalizarServidor(String texto) {
    var url = texto.trim();
    if (url.isEmpty) return url;
    if (!url.startsWith('http://') && !url.startsWith('https://')) {
      final local = RegExp(r'^(localhost|\d{1,3}(\.\d{1,3}){3}|10\.0\.2\.2)(:\d+)?(/|$)').hasMatch(url);
      url = local ? 'http://$url' : 'https://$url';
    }
    url = url.replaceAll(RegExp(r'/+$'), '');
    if (!url.endsWith('/api/v1')) url = '$url/api/v1';
    return url;
  }
}

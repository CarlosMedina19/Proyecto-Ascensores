import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../configuracion.dart';

/// Error devuelto por la API con el mensaje y los errores de validación en español.
class ErrorApi implements Exception {
  ErrorApi(this.mensaje, {this.estado = 0, this.errores = const {}});

  final String mensaje;
  final int estado;
  final Map<String, List<String>> errores;

  /// Primer error de validación de un campo, si existe.
  String? errorDe(String campo) => errores[campo]?.first;

  @override
  String toString() => mensaje;
}

/// Cliente HTTP de la API REST de SIGMA (/api/v1) con token Bearer de Sanctum.
class ClienteApi {
  ClienteApi({http.Client? http, String? urlBase}) : _http = http ?? _clientePorDefecto(), urlBase = urlBase ?? Configuracion.urlApi;

  static http.Client _clientePorDefecto() => http.Client();

  final http.Client _http;
  /// Se puede cambiar en caliente desde Configurar servidor.
  String urlBase;
  String? token;

  /// Se llama cuando la API responde 401 con un token: sesión vencida o usuario desactivado.
  void Function()? alExpirarSesion;

  Future<Map<String, dynamic>> obtener(String ruta, {Map<String, Object?> consulta = const {}}) => _enviar('GET', ruta, consulta: consulta);

  Future<Map<String, dynamic>> crear(String ruta, [Map<String, Object?>? cuerpo]) => _enviar('POST', ruta, cuerpo: cuerpo);

  Future<Map<String, dynamic>> actualizar(String ruta, [Map<String, Object?>? cuerpo]) => _enviar('PATCH', ruta, cuerpo: cuerpo);

  Future<Map<String, dynamic>> reemplazar(String ruta, [Map<String, Object?>? cuerpo]) => _enviar('PUT', ruta, cuerpo: cuerpo);

  Future<Map<String, dynamic>> _enviar(
    String metodo,
    String ruta, {
    Map<String, Object?>? cuerpo,
    Map<String, Object?> consulta = const {},
  }) async {
    final parametros = <String, String>{
      for (final entrada in consulta.entries)
        if (entrada.value != null && '${entrada.value}'.isNotEmpty) entrada.key: '${entrada.value}',
    };
    final uri = Uri.parse('$urlBase$ruta').replace(queryParameters: parametros.isEmpty ? null : parametros);

    final solicitud = http.Request(metodo, uri)
      ..headers['Accept'] = 'application/json'
      ..headers['Content-Type'] = 'application/json';
    if (cuerpo != null) solicitud.body = jsonEncode(cuerpo);

    return _ejecutar(solicitud);
  }

  /// Fase 4: sube un archivo (multipart), por ejemplo una foto de la planilla.
  Future<Map<String, dynamic>> subirArchivo(
    String ruta, {
    required String campo,
    required List<int> bytes,
    required String nombreArchivo,
    Map<String, String> campos = const {},
  }) {
    final solicitud = http.MultipartRequest('POST', Uri.parse('$urlBase$ruta'))
      ..headers['Accept'] = 'application/json'
      ..fields.addAll(campos)
      ..files.add(http.MultipartFile.fromBytes(campo, bytes, filename: nombreArchivo));

    return _ejecutar(solicitud);
  }

  Future<Map<String, dynamic>> _ejecutar(http.BaseRequest solicitud) async {
    if (token != null) solicitud.headers['Authorization'] = 'Bearer $token';

    http.Response respuesta;
    try {
      respuesta = await http.Response.fromStream(await _http.send(solicitud).timeout(Configuracion.tiempoEsperaSolicitud));
    } on TimeoutException {
      throw ErrorApi('El servidor tardó demasiado en responder. Intente de nuevo.');
    } catch (_) {
      throw ErrorApi('No fue posible conectar con el servidor. Verifique su conexión.');
    }

    Map<String, dynamic> datos = {};
    if (respuesta.body.isNotEmpty) {
      try {
        final decodificado = jsonDecode(utf8.decode(respuesta.bodyBytes));
        if (decodificado is Map<String, dynamic>) datos = decodificado;
      } catch (_) {
        // Respuesta sin JSON: se usa el mensaje genérico.
      }
    }

    if (respuesta.statusCode >= 400) {
      if (respuesta.statusCode == 401 && token != null) {
        token = null;
        alExpirarSesion?.call();
      }
      throw ErrorApi(
        datos['mensaje'] as String? ?? 'Ocurrió un error inesperado. Intente de nuevo.',
        estado: respuesta.statusCode,
        errores: _leerErrores(datos['errores']),
      );
    }

    return datos;
  }

  static Map<String, List<String>> _leerErrores(Object? errores) {
    if (errores is! Map) return const {};
    return {for (final entrada in errores.entries) '${entrada.key}': (entrada.value as List).map((e) => '$e').toList()};
  }
}

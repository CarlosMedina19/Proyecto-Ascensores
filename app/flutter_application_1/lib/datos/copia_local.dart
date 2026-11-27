import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Copia en el celular de lo último que se consultó al servidor (órdenes, planillas, emergencias, ascensores…).
///
/// Sin conexión, la app muestra esta copia; los cambios que el técnico hace sin conexión también se aplican aquí
/// para que la pantalla los refleje mientras llegan al servidor. Cada usuario tiene sus propias copias.
class CopiaLocal {
  static const _prefijo = 'sigma.copia.';

  /// Clave de una consulta: la ruta y los parámetros en orden (los vacíos no cuentan).
  static String claveDe(String ruta, [Map<String, Object?> consulta = const {}]) {
    final parametros = [
      for (final e in consulta.entries.toList()..sort((a, b) => a.key.compareTo(b.key)))
        if (e.value != null && '${e.value}'.isNotEmpty) '${e.key}=${e.value}',
    ];
    return parametros.isEmpty ? ruta : '$ruta?${parametros.join('&')}';
  }

  /// Si el almacenamiento del celular no responde, la app sigue en línea sin copia (no se queda esperando).
  static Future<SharedPreferences> _preferencias() => SharedPreferences.getInstance().timeout(const Duration(seconds: 3));

  static String _llave(int? usuarioId, String clave) => '$_prefijo${usuarioId ?? 0}.$clave';

  Future<Map<String, dynamic>?> leer(int? usuarioId, String clave) async {
    try {
      final texto = (await _preferencias()).getString(_llave(usuarioId, clave));
      return texto == null ? null : jsonDecode(texto) as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  Future<void> guardar(int? usuarioId, String clave, Map<String, dynamic> respuesta) async {
    try {
      final copia = Map<String, dynamic>.of(respuesta)..['_guardada_en'] = DateTime.now().toIso8601String();
      await (await _preferencias()).setString(_llave(usuarioId, clave), jsonEncode(copia));
    } catch (_) {
      // Sin espacio o sin almacenamiento: la app sigue funcionando en línea.
    }
  }

  /// Aplica un cambio a todas las copias cuya clave cumpla la condición (por ejemplo, la orden y las listas).
  Future<void> modificar(int? usuarioId, bool Function(String clave) coincide, void Function(Map<String, dynamic> respuesta) cambio) async {
    final SharedPreferences preferencias;
    try {
      preferencias = await _preferencias();
    } catch (_) {
      return;
    }
    final inicio = '$_prefijo${usuarioId ?? 0}.';
    for (final llave in preferencias.getKeys().where((k) => k.startsWith(inicio)).toList()) {
      if (!coincide(llave.substring(inicio.length))) continue;
      final texto = preferencias.getString(llave);
      if (texto == null) continue;
      try {
        final respuesta = jsonDecode(texto) as Map<String, dynamic>;
        cambio(respuesta);
        await preferencias.setString(llave, jsonEncode(respuesta));
      } catch (_) {
        // Una copia dañada no impide actualizar las demás.
      }
    }
  }

  /// Aplica el cambio al registro con ese id dentro de las copias de un recurso: el detalle (`/ordenes/12`) y las
  /// listas (`/ordenes?…`). No toca las subrutas (`/ordenes/12/planilla`).
  Future<void> modificarRegistro(int? usuarioId, String recurso, int id, void Function(Map<String, dynamic> registro) cambio) =>
      modificar(usuarioId, (c) => c == '$recurso/$id' || c == recurso || c.startsWith('$recurso?'), (respuesta) {
        final datos = respuesta['datos'];
        if (datos is Map<String, dynamic> && datos['id'] == id) cambio(datos);
        if (datos is List) {
          for (final registro in datos) {
            if (registro is Map<String, dynamic> && registro['id'] == id) cambio(registro);
          }
        }
      });
}

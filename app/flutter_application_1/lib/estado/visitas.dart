import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Zonas del ascensor que se revisan en cada visita (RF-031 a RF-036).
const zonasAscensor = ['Cuarto de máquinas', 'Cabina', 'Contrapeso', 'Puertas de cabina y de piso', 'Hueco y recorrido', 'Foso'];

/// Actividades frecuentes de la visita (se conservan las de la versión anterior de la app).
const actividadesVisita = ['Limpieza del equipo', 'Lubricación del equipo', 'Ajustes necesarios'];

const nombresEstado = {'B': 'Bueno', 'R': 'Regular', 'M': 'Malo'};

class RevisionZona {
  RevisionZona({this.estado = '', this.observacion = ''});

  factory RevisionZona.desdeJson(Map<String, dynamic> json) =>
      RevisionZona(estado: json['estado'] as String? ?? '', observacion: json['observacion'] as String? ?? '');

  String estado;
  String observacion;

  /// RF-037: un estado Regular o Malo exige observación.
  bool get faltaObservacion => (estado == 'R' || estado == 'M') && observacion.trim().isEmpty;

  Map<String, dynamic> aJson() => {'estado': estado, 'observacion': observacion};
}

class Repuesto {
  Repuesto(this.nombre, this.cantidad);

  factory Repuesto.desdeJson(Map<String, dynamic> json) => Repuesto(json['nombre'] as String, (json['cantidad'] as num).toInt());

  final String nombre;
  final int cantidad;

  Map<String, dynamic> aJson() => {'nombre': nombre, 'cantidad': cantidad};
}

/// Borrador de una visita de mantenimiento guardado en el dispositivo.
///
/// Se guarda sin conexión y no se pierde al cerrar la sesión. Al enviarlo,
/// el resumen queda en el historial del equipo (RF-009). Las fotos y la firma
/// permanecen en el dispositivo hasta que la API de adjuntos esté disponible (fase 4).
class VisitaBorrador {
  VisitaBorrador({
    required this.equipoId,
    required this.equipoCodigo,
    Map<String, RevisionZona>? zonas,
    Set<String>? actividades,
    this.diagnostico = '',
    this.observaciones = '',
    List<Repuesto>? repuestos,
    List<String>? fotos,
    this.firmaBase64,
    DateTime? inicio,
  }) : zonas = zonas ?? {for (final zona in zonasAscensor) zona: RevisionZona()},
       actividades = actividades ?? <String>{},
       repuestos = repuestos ?? [],
       fotos = fotos ?? [],
       inicio = inicio ?? DateTime.now();

  factory VisitaBorrador.desdeJson(Map<String, dynamic> json) => VisitaBorrador(
    equipoId: (json['equipo_id'] as num).toInt(),
    equipoCodigo: json['equipo_codigo'] as String,
    zonas: (json['zonas'] as Map<String, dynamic>).map((k, v) => MapEntry(k, RevisionZona.desdeJson(v as Map<String, dynamic>))),
    actividades: ((json['actividades'] as List?) ?? const []).map((e) => '$e').toSet(),
    diagnostico: json['diagnostico'] as String? ?? '',
    observaciones: json['observaciones'] as String? ?? '',
    repuestos: ((json['repuestos'] as List?) ?? const []).map((e) => Repuesto.desdeJson(e as Map<String, dynamic>)).toList(),
    fotos: ((json['fotos'] as List?) ?? const []).map((e) => '$e').toList(),
    firmaBase64: json['firma'] as String?,
    inicio: DateTime.tryParse(json['inicio'] as String? ?? ''),
  );

  final int equipoId;
  final String equipoCodigo;
  final Map<String, RevisionZona> zonas;
  final Set<String> actividades;
  String diagnostico;
  String observaciones;
  final List<Repuesto> repuestos;

  /// Fotos en base64 (funciona igual en Android, iOS y web).
  final List<String> fotos;
  String? firmaBase64;
  final DateTime inicio;

  int get zonasRevisadas => zonas.values.where((z) => z.estado.isNotEmpty).length;
  int get zonasConObservacionPendiente => zonas.values.where((z) => z.faltaObservacion).length;
  bool get tieneFirma => firmaBase64 != null && firmaBase64!.isNotEmpty;

  /// Lo que falta para poder enviar la visita.
  List<String> get pendientes => [
    if (zonasRevisadas < zonas.length) 'Faltan ${zonas.length - zonasRevisadas} zona(s) por revisar',
    if (zonasConObservacionPendiente > 0) '$zonasConObservacionPendiente hallazgo(s) sin observación',
    if (diagnostico.trim().isEmpty) 'Falta el diagnóstico',
    if (!tieneFirma) 'Falta la firma del técnico',
  ];

  bool get listaParaEnviar => pendientes.isEmpty;

  /// Estado más grave de la visita: M > R > B.
  String get estadoGeneral {
    final estados = zonas.values.map((z) => z.estado);
    if (estados.contains('M')) return 'M';
    if (estados.contains('R')) return 'R';
    return 'B';
  }

  /// Texto que se registra en el historial del equipo.
  String resumen({DateTime? fin}) {
    final hasta = fin ?? DateTime.now();
    final minutos = hasta.difference(inicio).inMinutes;
    final lineas = <String>[
      'Visita de mantenimiento · duración ${minutos ~/ 60} h ${minutos % 60} min.',
      'Resultado general: ${nombresEstado[estadoGeneral]}.',
      for (final entrada in zonas.entries)
        '- ${entrada.key}: ${nombresEstado[entrada.value.estado] ?? 'Sin revisar'}'
            '${entrada.value.observacion.trim().isNotEmpty ? ' — ${entrada.value.observacion.trim()}' : ''}',
      if (actividades.isNotEmpty) 'Actividades: ${actividades.join(', ')}.',
      'Diagnóstico: ${diagnostico.trim()}',
      if (observaciones.trim().isNotEmpty) 'Observaciones: ${observaciones.trim()}',
      if (repuestos.isNotEmpty) 'Repuestos: ${repuestos.map((r) => '${r.nombre} (${r.cantidad})').join(', ')}.',
      if (fotos.isNotEmpty) 'Fotografías tomadas: ${fotos.length} (guardadas en el dispositivo).',
      'Firmado por el técnico en el dispositivo.',
    ];
    final texto = lineas.join('\n');
    // La API admite descripciones de hasta 2000 caracteres.
    return texto.length > 2000 ? '${texto.substring(0, 1997)}...' : texto;
  }

  Map<String, dynamic> aJson() => {
    'equipo_id': equipoId,
    'equipo_codigo': equipoCodigo,
    'zonas': zonas.map((k, v) => MapEntry(k, v.aJson())),
    'actividades': actividades.toList(),
    'diagnostico': diagnostico,
    'observaciones': observaciones,
    'repuestos': repuestos.map((r) => r.aJson()).toList(),
    'fotos': fotos,
    'firma': firmaBase64,
    'inicio': inicio.toIso8601String(),
  };
}

/// Guarda los borradores de visitas en el dispositivo (modo sin conexión).
class AlmacenVisitas {
  static String _clave(int equipoId) => 'sigma.visita.$equipoId';

  Future<VisitaBorrador?> leer(int equipoId) async {
    final preferencias = await SharedPreferences.getInstance();
    final texto = preferencias.getString(_clave(equipoId));
    if (texto == null) return null;
    try {
      return VisitaBorrador.desdeJson(jsonDecode(texto) as Map<String, dynamic>);
    } catch (_) {
      return null;
    }
  }

  Future<void> guardar(VisitaBorrador visita) async {
    final preferencias = await SharedPreferences.getInstance();
    await preferencias.setString(_clave(visita.equipoId), jsonEncode(visita.aJson()));
  }

  Future<void> borrar(int equipoId) async {
    final preferencias = await SharedPreferences.getInstance();
    await preferencias.remove(_clave(equipoId));
  }

  /// Equipos con una visita sin enviar.
  Future<List<VisitaBorrador>> pendientes() async {
    final preferencias = await SharedPreferences.getInstance();
    final lista = <VisitaBorrador>[];
    for (final clave in preferencias.getKeys().where((k) => k.startsWith('sigma.visita.'))) {
      final texto = preferencias.getString(clave);
      if (texto == null) continue;
      try {
        lista.add(VisitaBorrador.desdeJson(jsonDecode(texto) as Map<String, dynamic>));
      } catch (_) {
        // Un borrador dañado no impide listar los demás.
      }
    }
    return lista;
  }
}

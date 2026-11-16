import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

const nombresEstado = {'B': 'Bueno', 'R': 'Regular', 'M': 'Malo'};

/// Tipos de servicio de la planilla de asistencia técnica.
const tiposServicio = {'preventivo': 'Preventivo', 'correctivo': 'Correctivo', 'reparacion': 'Reparación'};

/// Actividad de la planilla (la plantilla la edita el administrador en el panel).
class ItemPlanilla {
  ItemPlanilla({required this.id, required this.codigo, required this.nombre, this.critico = false});

  factory ItemPlanilla.desdeJson(Map<String, dynamic> json) => ItemPlanilla(
    id: (json['id'] as num).toInt(),
    codigo: json['codigo'] as String? ?? '',
    nombre: json['nombre'] as String? ?? '',
    critico: json['critico'] as bool? ?? false,
  );

  final int id;
  final String codigo;
  final String nombre;
  final bool critico;

  Map<String, dynamic> aJson() => {'id': id, 'codigo': codigo, 'nombre': nombre, 'critico': critico};
}

class SeccionPlanilla {
  SeccionPlanilla({required this.clave, required this.nombre, required this.items});

  factory SeccionPlanilla.desdeJson(Map<String, dynamic> json) => SeccionPlanilla(
    clave: json['clave'] as String? ?? '',
    nombre: json['nombre'] as String? ?? '',
    items: ((json['items'] as List?) ?? const []).map((i) => ItemPlanilla.desdeJson(i as Map<String, dynamic>)).toList(),
  );

  final String clave;
  final String nombre;
  final List<ItemPlanilla> items;

  Map<String, dynamic> aJson() => {'clave': clave, 'nombre': nombre, 'items': items.map((i) => i.aJson()).toList()};
}

/// Lo marcado en una actividad: estado B, R o M (inspeccionada) o N.A. (no aplica).
class RespuestaItem {
  RespuestaItem({this.estado = '', this.na = false, this.observacion = ''});

  factory RespuestaItem.desdeJson(Map<String, dynamic> json) => RespuestaItem(
    estado: json['estado'] as String? ?? '',
    na: json['na'] as bool? ?? false,
    observacion: json['observacion'] as String? ?? '',
  );

  String estado;
  bool na;
  String observacion;

  bool get respondida => na || estado.isNotEmpty;

  /// Regular o Malo exigen observación.
  bool get faltaObservacion => !na && (estado == 'R' || estado == 'M') && observacion.trim().isEmpty;

  Map<String, dynamic> aJson() => {'estado': estado, 'na': na, 'observacion': observacion};
}

class Repuesto {
  Repuesto(this.nombre, this.cantidad);

  factory Repuesto.desdeJson(Map<String, dynamic> json) => Repuesto(json['nombre'] as String, (json['cantidad'] as num).toInt());

  final String nombre;
  final int cantidad;

  Map<String, dynamic> aJson() => {'nombre': nombre, 'cantidad': cantidad};
}

/// Planilla de asistencia técnica de una visita, guardada en el dispositivo mientras se diligencia.
///
/// Funciona sin conexión (lleva su copia de la plantilla) y no se pierde al cerrar la sesión. Al enviarla,
/// el servidor genera el PDF con el formato de la planilla y la deja en la ficha del ascensor.
class VisitaBorrador {
  VisitaBorrador({
    required this.equipoId,
    required this.equipoCodigo,
    required List<SeccionPlanilla> plantilla,
    Map<int, RespuestaItem>? respuestas,
    this.tipoServicio = 'preventivo',
    this.observaciones = '',
    List<Repuesto>? repuestos,
    List<String>? fotos,
    this.firmaBase64,
    this.firmaRecibeBase64,
    this.nombreRecibe = '',
    this.documentoRecibe = '',
    this.documentoTecnico = '',
    DateTime? inicio,
  }) : plantilla = List.of(plantilla),
       respuestas = respuestas ?? {},
       repuestos = repuestos ?? [],
       fotos = fotos ?? [],
       inicio = inicio ?? DateTime.now();

  factory VisitaBorrador.desdeJson(Map<String, dynamic> json) => VisitaBorrador(
    equipoId: (json['equipo_id'] as num).toInt(),
    equipoCodigo: json['equipo_codigo'] as String,
    // Los borradores de la versión anterior (sin plantilla) no se pueden recuperar.
    plantilla: (json['plantilla'] as List).map((s) => SeccionPlanilla.desdeJson(s as Map<String, dynamic>)).toList(),
    respuestas: ((json['respuestas'] as Map?) ?? const {}).map(
      (k, v) => MapEntry(int.parse('$k'), RespuestaItem.desdeJson(v as Map<String, dynamic>)),
    ),
    tipoServicio: json['tipo_servicio'] as String? ?? 'preventivo',
    observaciones: json['observaciones'] as String? ?? '',
    repuestos: ((json['repuestos'] as List?) ?? const []).map((e) => Repuesto.desdeJson(e as Map<String, dynamic>)).toList(),
    fotos: ((json['fotos'] as List?) ?? const []).map((e) => '$e').toList(),
    firmaBase64: json['firma'] as String?,
    firmaRecibeBase64: json['firma_recibe'] as String?,
    nombreRecibe: json['nombre_recibe'] as String? ?? '',
    documentoRecibe: json['documento_recibe'] as String? ?? '',
    documentoTecnico: json['documento_tecnico'] as String? ?? '',
    inicio: DateTime.tryParse(json['inicio'] as String? ?? ''),
  );

  final int equipoId;
  final String equipoCodigo;
  List<SeccionPlanilla> plantilla;
  final Map<int, RespuestaItem> respuestas;
  String tipoServicio;
  String observaciones;
  final List<Repuesto> repuestos;

  /// Fotos en base64: quedan en el dispositivo como apoyo del técnico.
  final List<String> fotos;
  String? firmaBase64;
  String? firmaRecibeBase64;
  String nombreRecibe;
  String documentoRecibe;
  String documentoTecnico;

  /// Hora de entrada (inicio de la visita).
  final DateTime inicio;

  Iterable<ItemPlanilla> get items => plantilla.expand((s) => s.items);
  int get totalItems => items.length;
  RespuestaItem respuesta(int itemId) => respuestas.putIfAbsent(itemId, RespuestaItem.new);
  int get respondidos => items.where((i) => respuestas[i.id]?.respondida ?? false).length;
  int respondidosEn(SeccionPlanilla seccion) => seccion.items.where((i) => respuestas[i.id]?.respondida ?? false).length;
  int get sinObservacion => items.where((i) => respuestas[i.id]?.faltaObservacion ?? false).length;
  bool get tieneFirma => firmaBase64 != null && firmaBase64!.isNotEmpty;
  bool get tieneFirmaRecibe => firmaRecibeBase64 != null && firmaRecibeBase64!.isNotEmpty;

  /// Marca en Bueno las actividades de la sección que aún no tienen respuesta.
  void todoBueno(SeccionPlanilla seccion) {
    for (final item in seccion.items) {
      final r = respuesta(item.id);
      if (!r.respondida) r.estado = 'B';
    }
  }

  /// Cambia a una plantilla más reciente conservando lo ya marcado en las actividades que siguen.
  void actualizarPlantilla(List<SeccionPlanilla> nueva) {
    final vigentes = nueva.expand((s) => s.items).map((i) => i.id).toSet();
    respuestas.removeWhere((id, _) => !vigentes.contains(id));
    plantilla = List.of(nueva);
  }

  /// Lo que falta para poder enviar la planilla.
  List<String> get pendientes => [
    if (respondidos < totalItems) 'Faltan ${totalItems - respondidos} actividad(es) por marcar',
    if (sinObservacion > 0) '$sinObservacion actividad(es) en R o M sin observación',
    if (!tieneFirma) 'Falta la firma del técnico',
    if (nombreRecibe.trim().length < 3) 'Falta el nombre de quien recibe',
    if (documentoRecibe.trim().length < 5) 'Falta la C.C. de quien recibe',
    if (!tieneFirmaRecibe) 'Falta la firma de quien recibe',
  ];

  bool get listaParaEnviar => totalItems > 0 && pendientes.isEmpty;

  Map<String, int> get conteo {
    final c = {'B': 0, 'R': 0, 'M': 0, 'NA': 0};
    for (final item in items) {
      final r = respuestas[item.id];
      if (r == null || !r.respondida) continue;
      if (r.na) {
        c['NA'] = c['NA']! + 1;
      } else {
        c[r.estado] = (c[r.estado] ?? 0) + 1;
      }
    }
    return c;
  }

  /// Datos que se envían a la API (POST /equipos/{id}/visitas).
  Map<String, Object?> paraEnviar({DateTime? salida}) => {
    'tipo_servicio': tipoServicio,
    'entrada_en': inicio.toUtc().toIso8601String(),
    'salida_en': (salida ?? DateTime.now()).toUtc().toIso8601String(),
    'resultados': [
      for (final item in items)
        {
          'item_id': item.id,
          'ins': !(respuestas[item.id]?.na ?? false),
          'estado': (respuestas[item.id]?.na ?? false) ? null : respuestas[item.id]?.estado,
          'na': respuestas[item.id]?.na ?? false,
          'observacion': (respuestas[item.id]?.observacion.trim().isEmpty ?? true) ? null : respuestas[item.id]!.observacion.trim(),
        },
    ],
    'observaciones': observaciones.trim().isEmpty ? null : observaciones.trim(),
    'repuestos': repuestos.map((r) => r.aJson()).toList(),
    'recibe_nombre': nombreRecibe.trim(),
    'recibe_documento': documentoRecibe.trim(),
    'tecnico_documento': documentoTecnico.trim().isEmpty ? null : documentoTecnico.trim(),
    'firma_tecnico': 'data:image/png;base64,$firmaBase64',
    'firma_recibe': 'data:image/png;base64,$firmaRecibeBase64',
  };

  Map<String, dynamic> aJson() => {
    'version': 2,
    'equipo_id': equipoId,
    'equipo_codigo': equipoCodigo,
    'plantilla': plantilla.map((s) => s.aJson()).toList(),
    'respuestas': respuestas.map((k, v) => MapEntry('$k', v.aJson())),
    'tipo_servicio': tipoServicio,
    'observaciones': observaciones,
    'repuestos': repuestos.map((r) => r.aJson()).toList(),
    'fotos': fotos,
    'firma': firmaBase64,
    'firma_recibe': firmaRecibeBase64,
    'nombre_recibe': nombreRecibe,
    'documento_recibe': documentoRecibe,
    'documento_tecnico': documentoTecnico,
    'inicio': inicio.toIso8601String(),
  };
}

/// Guarda en el dispositivo los borradores de las visitas y la última plantilla descargada (modo sin conexión).
class AlmacenVisitas {
  static String _clave(int equipoId) => 'sigma.visita.$equipoId';
  static const _clavePlantilla = 'sigma.plantilla_planilla';

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

  Future<void> guardarPlantilla(List<SeccionPlanilla> plantilla) async {
    final preferencias = await SharedPreferences.getInstance();
    await preferencias.setString(_clavePlantilla, jsonEncode(plantilla.map((s) => s.aJson()).toList()));
  }

  Future<List<SeccionPlanilla>?> leerPlantilla() async {
    final preferencias = await SharedPreferences.getInstance();
    final texto = preferencias.getString(_clavePlantilla);
    if (texto == null) return null;
    try {
      return (jsonDecode(texto) as List).map((s) => SeccionPlanilla.desdeJson(s as Map<String, dynamic>)).toList();
    } catch (_) {
      return null;
    }
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
        // Un borrador dañado o de la versión anterior no impide listar los demás.
      }
    }
    return lista;
  }
}

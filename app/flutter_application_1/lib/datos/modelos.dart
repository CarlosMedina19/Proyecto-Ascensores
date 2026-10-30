// Modelos de la fase 1, construidos a partir de las respuestas JSON de la API.

int? _entero(Object? valor) => valor == null ? null : num.tryParse('$valor')?.toInt();
double? _decimal(Object? valor) => valor == null ? null : num.tryParse('$valor')?.toDouble();
DateTime? _fecha(Object? valor) => valor == null ? null : DateTime.tryParse('$valor')?.toLocal();

class Usuario {
  Usuario({
    required this.id,
    required this.nombre,
    required this.correo,
    required this.rol,
    required this.permisos,
    this.clienteId,
    this.clienteNombre,
    this.ultimoAcceso,
  });

  factory Usuario.desdeJson(Map<String, dynamic> json) {
    final rol = json['rol'] as Map<String, dynamic>?;
    final cliente = json['cliente'] as Map<String, dynamic>?;
    return Usuario(
      id: _entero(json['id'])!,
      nombre: json['nombre'] as String? ?? '',
      correo: json['correo'] as String? ?? '',
      rol: rol?['nombre'] as String? ?? '',
      permisos: ((rol?['permisos'] as List?) ?? const []).map((p) => '$p').toSet(),
      clienteId: _entero(json['cliente_id']),
      clienteNombre: cliente?['nombre'] as String?,
      ultimoAcceso: _fecha(json['ultimo_acceso_en']),
    );
  }

  final int id;
  final String nombre;
  final String correo;
  final String rol;
  final Set<String> permisos;
  final int? clienteId;
  final String? clienteNombre;
  final DateTime? ultimoAcceso;

  bool tienePermiso(String permiso) => permisos.contains(permiso);
  bool get esCliente => rol == 'cliente';
  bool get esTecnico => rol == 'tecnico';
  String get primerNombre => nombre.split(' ').first;

  String get nombreRol =>
      const {
        'administrador': 'Administrador',
        'coordinador': 'Coordinador',
        'tecnico': 'Técnico',
        'cliente': 'Cliente',
        'contador': 'Contador',
        'supervisor': 'Supervisor',
      }[rol] ??
      rol;
}

/// Tipo de ascensor según su uso (mismos valores que la API).
const nombresTipoAscensor = {
  'pasajeros': 'Pasajeros',
  'carga': 'Carga (montacargas)',
  'camillero': 'Camillero (hospitalario)',
  'panoramico': 'Panorámico',
  'vehicular': 'Vehicular',
  'plataforma': 'Plataforma para movilidad reducida',
  'montaplatos': 'Montaplatos',
};

class Ascensor {
  Ascensor({
    this.tipo,
    this.capacidadKg,
    this.velocidadMpm,
    this.paradas,
    this.accionamiento,
    this.motor,
    this.controlador,
    this.tipoPuerta,
    this.puertasCabina,
    this.puertasPiso,
    this.operadorPuertas,
    this.especificaciones,
  });

  factory Ascensor.desdeJson(Map<String, dynamic> json) => Ascensor(
    tipo: json['tipo_ascensor'] as String?,
    capacidadKg: _entero(json['capacidad_kg']),
    velocidadMpm: _decimal(json['velocidad_mpm']),
    paradas: _entero(json['paradas']),
    accionamiento: json['tipo_traccion'] as String?,
    motor: json['motor'] as String?,
    controlador: json['controlador'] as String?,
    tipoPuerta: json['tipo_puerta'] as String?,
    puertasCabina: _entero(json['puertas_cabina']),
    puertasPiso: _entero(json['puertas_piso']),
    operadorPuertas: json['operador_puertas'] as String?,
    especificaciones: json['especificaciones_tecnicas'] as String?,
  );

  final String? tipo;
  final int? capacidadKg;
  final double? velocidadMpm;
  final int? paradas;
  final String? accionamiento;
  final String? motor;
  final String? controlador;
  final String? tipoPuerta;
  final int? puertasCabina;
  final int? puertasPiso;
  final String? operadorPuertas;
  final String? especificaciones;

  String? get nombreTipo => tipo == null ? null : nombresTipoAscensor[tipo] ?? tipo;
}

class Edificio {
  Edificio({
    required this.id,
    required this.nombre,
    this.clienteId,
    this.clienteNombre,
    this.direccion,
    this.ciudad,
    this.departamento,
    this.pisos,
    this.nombreContacto,
    this.telefonoContacto,
    this.correoContacto,
    this.observaciones,
    this.activo = true,
    this.cantidadEquipos,
    this.equipos = const [],
  });

  factory Edificio.desdeJson(Map<String, dynamic> json) {
    final cliente = json['cliente'] as Map<String, dynamic>?;
    return Edificio(
      id: _entero(json['id'])!,
      nombre: json['nombre'] as String? ?? '',
      clienteId: _entero(json['cliente_id']),
      clienteNombre: cliente?['nombre'] as String?,
      direccion: json['direccion'] as String?,
      ciudad: json['ciudad'] as String?,
      departamento: json['departamento'] as String?,
      pisos: _entero(json['pisos']),
      nombreContacto: json['nombre_contacto'] as String?,
      telefonoContacto: json['telefono_contacto'] as String?,
      correoContacto: json['correo_contacto'] as String?,
      observaciones: json['observaciones'] as String?,
      activo: json['activo'] as bool? ?? true,
      cantidadEquipos: _entero(json['equipos_count']),
      equipos: ((json['equipos'] as List?) ?? const []).map((e) => Equipo.desdeJson(e as Map<String, dynamic>)).toList(),
    );
  }

  final int id;
  final String nombre;
  final int? clienteId;
  final String? clienteNombre;
  final String? direccion;
  final String? ciudad;
  final String? departamento;
  final int? pisos;
  final String? nombreContacto;
  final String? telefonoContacto;
  final String? correoContacto;
  final String? observaciones;
  final bool activo;
  final int? cantidadEquipos;
  final List<Equipo> equipos;
}

class Equipo {
  Equipo({
    required this.id,
    required this.codigo,
    required this.estado,
    this.edificioId,
    this.edificioNombre,
    this.clienteNombre,
    this.marca,
    this.modelo,
    this.numeroSerie,
    this.ubicacion,
    this.fechaInstalacion,
    this.observaciones,
    this.ascensor,
    this.asignacionActiva,
  });

  factory Equipo.desdeJson(Map<String, dynamic> json) {
    final edificio = json['edificio'] as Map<String, dynamic>?;
    final cliente = edificio?['cliente'] as Map<String, dynamic>?;
    final ascensor = json['ascensor'] as Map<String, dynamic>?;
    final asignacion = json['asignacion_activa'] as Map<String, dynamic>?;
    return Equipo(
      id: _entero(json['id'])!,
      codigo: json['codigo'] as String? ?? '',
      estado: json['estado'] as String? ?? 'activo',
      edificioId: _entero(json['edificio_id']),
      edificioNombre: edificio?['nombre'] as String?,
      clienteNombre: cliente?['nombre'] as String?,
      marca: json['marca'] as String?,
      modelo: json['modelo'] as String?,
      numeroSerie: json['numero_serie'] as String?,
      ubicacion: json['ubicacion'] as String?,
      fechaInstalacion: _fecha(json['fecha_instalacion']),
      observaciones: json['observaciones'] as String?,
      ascensor: ascensor == null ? null : Ascensor.desdeJson(ascensor),
      asignacionActiva: asignacion == null ? null : AsignacionPlan.desdeJson(asignacion),
    );
  }

  final int id;
  final String codigo;
  final String estado;
  final int? edificioId;
  final String? edificioNombre;
  final String? clienteNombre;
  final String? marca;
  final String? modelo;
  final String? numeroSerie;
  final String? ubicacion;
  final DateTime? fechaInstalacion;
  final String? observaciones;
  final Ascensor? ascensor;

  /// Fase 2: plan de mantenimiento vigente (solo llega en el detalle del equipo).
  final AsignacionPlan? asignacionActiva;

  String get marcaModelo => [marca, modelo].whereType<String>().where((t) => t.isNotEmpty).join(' · ');
}

class EventoHistorial {
  EventoHistorial({required this.id, required this.evento, this.descripcion, this.usuarioNombre, this.fecha});

  factory EventoHistorial.desdeJson(Map<String, dynamic> json) => EventoHistorial(
    id: _entero(json['id'])!,
    evento: json['evento'] as String? ?? '',
    descripcion: json['descripcion'] as String?,
    usuarioNombre: (json['usuario'] as Map<String, dynamic>?)?['nombre'] as String?,
    fecha: _fecha(json['created_at']),
  );

  final int id;
  final String evento;
  final String? descripcion;
  final String? usuarioNombre;
  final DateTime? fecha;
}

class Cliente {
  Cliente({
    required this.id,
    required this.nombre,
    this.tipo,
    this.nit,
    this.numeroDocumento,
    this.telefono,
    this.correo,
    this.direccion,
    this.activo = true,
  });

  factory Cliente.desdeJson(Map<String, dynamic> json) => Cliente(
    id: _entero(json['id'])!,
    nombre: json['nombre'] as String? ?? '',
    tipo: json['tipo'] as String?,
    nit: json['nit'] as String?,
    numeroDocumento: json['numero_documento'] as String?,
    telefono: json['telefono'] as String?,
    correo: json['correo'] as String?,
    direccion: json['direccion'] as String?,
    activo: json['estado'] as bool? ?? true,
  );

  final int id;
  final String nombre;
  final String? tipo;
  final String? nit;
  final String? numeroDocumento;
  final String? telefono;
  final String? correo;
  final String? direccion;
  final bool activo;
}

/// Página de resultados con los metadatos de paginación de la API.
class Pagina<T> {
  Pagina({required this.elementos, required this.paginaActual, required this.ultimaPagina, required this.total});

  factory Pagina.desdeJson(Map<String, dynamic> json, T Function(Map<String, dynamic>) convertir) {
    final metadatos = json['metadatos'] as Map<String, dynamic>? ?? const {};
    final elementos = ((json['datos'] as List?) ?? const []).map((e) => convertir(e as Map<String, dynamic>)).toList();
    return Pagina(
      elementos: elementos,
      paginaActual: _entero(metadatos['pagina_actual']) ?? 1,
      ultimaPagina: _entero(metadatos['ultima_pagina']) ?? 1,
      total: _entero(metadatos['total']) ?? elementos.length,
    );
  }

  final List<T> elementos;
  final int paginaActual;
  final int ultimaPagina;
  final int total;

  bool get hayMas => paginaActual < ultimaPagina;
}

/// Resumen del panel (GET /resumen).
class Resumen {
  Resumen({
    required this.equiposTotal,
    required this.equiposActivos,
    required this.equiposMantenimiento,
    required this.equiposInactivos,
    required this.edificios,
    required this.clientes,
    required this.ultimosEventos,
  });

  factory Resumen.desdeJson(Map<String, dynamic> json) {
    final datos = json['datos'] as Map<String, dynamic>;
    final equipos = datos['equipos'] as Map<String, dynamic>;
    return Resumen(
      equiposTotal: _entero(equipos['total']) ?? 0,
      equiposActivos: _entero(equipos['activo']) ?? 0,
      equiposMantenimiento: _entero(equipos['mantenimiento']) ?? 0,
      equiposInactivos: _entero(equipos['inactivo']) ?? 0,
      edificios: _entero((datos['edificios'] as Map)['total']) ?? 0,
      clientes: _entero((datos['clientes'] as Map)['total']) ?? 0,
      ultimosEventos: ((datos['ultimos_eventos'] as List?) ?? const [])
          .map((e) => (EventoHistorial.desdeJson(e as Map<String, dynamic>), e['equipo_codigo'] as String?, _entero(e['equipo_id'])))
          .toList(),
    );
  }

  final int equiposTotal;
  final int equiposActivos;
  final int equiposMantenimiento;
  final int equiposInactivos;
  final int edificios;
  final int clientes;
  final List<(EventoHistorial, String?, int?)> ultimosEventos;
}

// ---------------------------------------------------------------- Fase 2

/// Periodicidades de 1 a 6 meses (RF-011): "Trimestral (3 meses)".
String nombrePeriodicidad(int? meses) {
  if (meses == null) return '—';
  const nombres = {1: 'Mensual', 2: 'Bimestral', 3: 'Trimestral', 4: 'Cuatrimestral', 6: 'Semestral'};
  final duracion = meses == 1 ? '1 mes' : '$meses meses';
  final nombre = nombres[meses];
  return nombre == null ? 'Cada $duracion' : '$nombre ($duracion)';
}

/// Plan asignado a un ascensor (RF-013 a RF-016).
class AsignacionPlan {
  AsignacionPlan({
    required this.id,
    required this.estado,
    this.planNombre,
    this.meses,
    this.precio,
    this.precioConIva,
    this.aplicaIva = false,
    this.porcentajeIva,
    this.fechaInicio,
    this.fechaFin,
    this.ultimoMantenimiento,
    this.proximoMantenimiento,
    this.contratoNumero,
    this.horaPreferida,
  });

  factory AsignacionPlan.desdeJson(Map<String, dynamic> json) => AsignacionPlan(
    id: _entero(json['id'])!,
    estado: json['estado'] as String? ?? 'activa',
    planNombre: (json['plan'] as Map<String, dynamic>?)?['nombre'] as String?,
    meses: _entero((json['periodicidad'] as Map<String, dynamic>?)?['meses']),
    precio: _decimal(json['precio']),
    precioConIva: _decimal(json['precio_con_iva']),
    aplicaIva: json['aplica_iva'] as bool? ?? false,
    porcentajeIva: _decimal(json['porcentaje_iva']),
    fechaInicio: _fecha(json['fecha_inicio']),
    fechaFin: _fecha(json['fecha_fin']),
    ultimoMantenimiento: _fecha(json['ultimo_mantenimiento']),
    proximoMantenimiento: _fecha(json['proximo_mantenimiento']),
    contratoNumero: (json['contrato'] as Map<String, dynamic>?)?['numero'] as String?,
    horaPreferida: (json['hora_preferida'] as String?)?.substring(0, 5),
  );

  final int id;
  final String estado;
  final String? planNombre;
  final int? meses;
  final double? precio;
  final double? precioConIva;
  final bool aplicaIva;
  final double? porcentajeIva;
  final DateTime? fechaInicio;
  final DateTime? fechaFin;
  final DateTime? ultimoMantenimiento;
  final DateTime? proximoMantenimiento;
  final String? contratoNumero;
  final String? horaPreferida;

  String get periodicidad => nombrePeriodicidad(meses);
}

/// Visita preventiva programada (RF-017 y RF-018).
class MantenimientoProgramado {
  MantenimientoProgramado({
    required this.id,
    required this.estado,
    required this.fechaProgramada,
    this.equipoId,
    this.equipoCodigo,
    this.edificioNombre,
    this.planNombre,
    this.fechaTeorica,
    this.precio,
    this.motivo,
    this.hora,
  });

  factory MantenimientoProgramado.desdeJson(Map<String, dynamic> json) {
    final equipo = json['equipo'] as Map<String, dynamic>?;
    return MantenimientoProgramado(
      id: _entero(json['id'])!,
      estado: json['estado'] as String? ?? 'programado',
      fechaProgramada: _fecha(json['fecha_programada'])!,
      equipoId: _entero(json['equipo_id']),
      equipoCodigo: equipo?['codigo'] as String?,
      edificioNombre: (equipo?['edificio'] as Map<String, dynamic>?)?['nombre'] as String?,
      planNombre: (json['plan'] as Map<String, dynamic>?)?['nombre'] as String?,
      fechaTeorica: _fecha(json['fecha_teorica']),
      precio: _decimal(json['precio']),
      motivo: json['motivo'] as String?,
      hora: (json['hora'] as String?)?.substring(0, 5),
    );
  }

  final int id;
  final String estado;
  final DateTime fechaProgramada;
  final int? equipoId;
  final String? equipoCodigo;
  final String? edificioNombre;
  final String? planNombre;
  final DateTime? fechaTeorica;
  final double? precio;
  final String? motivo;
  final String? hora;

  bool get pendiente => estado == 'programado' || estado == 'reprogramado';

  /// Días que faltan desde [hoy] (negativo si está atrasado).
  int diasRestantes([DateTime? hoy]) {
    final referencia = hoy ?? DateTime.now();
    final inicioHoy = DateTime(referencia.year, referencia.month, referencia.day);
    final dia = DateTime(fechaProgramada.year, fechaProgramada.month, fechaProgramada.day);
    return dia.difference(inicioHoy).inDays;
  }
}

// ---------------------------------------------------------------- Fase 3

const nombresServicio = {'preventivo': 'Mantenimiento preventivo', 'correctivo': 'Mantenimiento correctivo', 'inspeccion': 'Inspección'};
const nombresPrioridad = {'baja': 'Baja', 'media': 'Media', 'alta': 'Alta', 'critica': 'Crítica'};
const nombresEstadoOrden = {
  'programada': 'Programada',
  'asignada': 'Asignada',
  'en_camino': 'En camino',
  'en_proceso': 'En proceso',
  'pendiente': 'Pendiente',
  'finalizada': 'Finalizada',
  'cancelada': 'Cancelada',
};
const nombresAccionOrden = {
  'creada': 'Orden creada',
  'tecnico_asignado': 'Técnico asignado',
  'tecnico_retirado': 'Técnico retirado',
  'estado_cambiado': 'Cambio de estado',
  'prioridad_cambiada': 'Cambio de prioridad',
  'reprogramada': 'Reprogramada',
  'inicio_registrado': 'Inicio registrado',
  'fin_registrado': 'Fin registrado',
  'mantenimiento_ejecutado': 'Visita del plan ejecutada',
  'informe_generado': 'Informe PDF generado',
};

/// Orden de trabajo (RF-025 a RF-029).
class OrdenTrabajo {
  OrdenTrabajo({
    required this.id,
    required this.numero,
    required this.servicio,
    required this.estado,
    required this.prioridad,
    required this.fecha,
    required this.hora,
    this.finEstimado,
    this.duracionEstimada,
    this.equipoId,
    this.equipoCodigo,
    this.edificioId,
    this.edificioNombre,
    this.direccion,
    this.clienteNombre,
    this.descripcion,
    this.observaciones,
    this.informe,
    this.motivoCancelacion,
    this.inicioEn,
    this.finEn,
    this.duracionMinutos,
    this.tecnicos = const [],
    this.puedeEjecutar = false,
    this.trazabilidad = const [],
    this.llevaPlanilla = false,
    this.planillaRegistrados = 0,
    this.planillaTotal = 0,
    this.firmas = const {},
    this.informes = 0,
  });

  factory OrdenTrabajo.desdeJson(Map<String, dynamic> json) {
    final edificio = json['edificio'] as Map<String, dynamic>?;
    final equipo = json['equipo'] as Map<String, dynamic>?;
    final permisos = json['permisos'] as Map<String, dynamic>?;
    return OrdenTrabajo(
      id: _entero(json['id'])!,
      numero: json['numero'] as String? ?? '',
      servicio: json['servicio'] as String? ?? 'correctivo',
      estado: json['estado'] as String? ?? 'programada',
      prioridad: json['prioridad'] as String? ?? 'media',
      fecha: _fecha(json['fecha'])!,
      hora: json['hora'] as String? ?? '',
      finEstimado: json['fin_estimado'] as String?,
      duracionEstimada: _entero(json['duracion_estimada']),
      equipoId: _entero(json['equipo_id']),
      equipoCodigo: equipo?['codigo'] as String?,
      edificioId: _entero(json['edificio_id']),
      edificioNombre: edificio?['nombre'] as String?,
      direccion: [edificio?['direccion'], edificio?['ciudad']].whereType<String>().where((t) => t.isNotEmpty).join(', '),
      clienteNombre: (json['cliente'] as Map<String, dynamic>?)?['nombre'] as String?,
      descripcion: json['descripcion'] as String?,
      observaciones: json['observaciones'] as String?,
      informe: json['informe'] as String?,
      motivoCancelacion: json['motivo_cancelacion'] as String?,
      inicioEn: _fecha(json['inicio_en']),
      finEn: _fecha(json['fin_en']),
      duracionMinutos: _entero(json['duracion_minutos']),
      tecnicos: ((json['tecnicos'] as List?) ?? const []).map((t) => '${(t as Map)['nombre']}').toList(),
      puedeEjecutar: permisos?['ejecutar'] as bool? ?? false,
      trazabilidad: ((json['trazabilidad'] as List?) ?? const []).map((t) => _traza(t as Map<String, dynamic>)).toList(),
      // Fase 4: avance de la planilla, firmas registradas e informes emitidos.
      llevaPlanilla: json['lleva_planilla'] as bool? ?? false,
      planillaRegistrados: _entero((json['planilla'] as Map<String, dynamic>?)?['registrados']) ?? 0,
      planillaTotal: _entero((json['planilla'] as Map<String, dynamic>?)?['total_items']) ?? 0,
      firmas: {for (final f in (json['firmas'] as List?) ?? const []) '${(f as Map)['tipo']}': '${f['nombre']}'},
      informes: ((json['informes'] as List?) ?? const []).length,
    );
  }

  /// RF-029: cada registro de la trazabilidad como evento de la línea de tiempo.
  static EventoHistorial _traza(Map<String, dynamic> traza) {
    final campo = traza['campo'] as String?;
    String? legible(Object? valor) => valor == null ? null : (campo == 'estado' ? nombresEstadoOrden['$valor'] ?? '$valor' : '$valor');
    final anterior = legible(traza['valor_anterior']);
    final nuevo = legible(traza['valor_nuevo']);
    final cambio = campo == null || campo == 'inicio_en' || campo == 'fin_en'
        ? null
        : (anterior != null ? '$anterior → ${nuevo ?? '—'}' : nuevo);
    return EventoHistorial(
      id: _entero(traza['id'])!,
      evento: nombresAccionOrden[traza['accion']] ?? '${traza['accion']}',
      descripcion: [cambio, traza['detalle'] as String?].whereType<String>().where((t) => t.isNotEmpty).join('. '),
      usuarioNombre: (traza['usuario'] as Map<String, dynamic>?)?['nombre'] as String?,
      fecha: _fecha(traza['created_at']),
    );
  }

  final int id;
  final String numero;
  final String servicio;
  final String estado;
  final String prioridad;
  final DateTime fecha;
  final String hora;
  final String? finEstimado;
  final int? duracionEstimada;
  final int? equipoId;
  final String? equipoCodigo;
  final int? edificioId;
  final String? edificioNombre;
  final String? direccion;
  final String? clienteNombre;
  final String? descripcion;
  final String? observaciones;
  final String? informe;
  final String? motivoCancelacion;
  final DateTime? inicioEn;
  final DateTime? finEn;
  final int? duracionMinutos;
  final List<String> tecnicos;
  final bool puedeEjecutar;
  final List<EventoHistorial> trazabilidad;
  final bool llevaPlanilla;
  final int planillaRegistrados;
  final int planillaTotal;

  /// Tipo de firma ('tecnico' o 'cliente') → nombre de quien firmó.
  final Map<String, String> firmas;
  final int informes;

  bool get planillaCompleta => planillaTotal > 0 && planillaRegistrados >= planillaTotal;

  bool get esFinal => estado == 'finalizada' || estado == 'cancelada';
  String get nombreServicio => nombresServicio[servicio] ?? servicio;
  String get nombrePrioridad => nombresPrioridad[prioridad] ?? prioridad;
  String get nombreEstado => nombresEstadoOrden[estado] ?? estado;

  /// RF-026: siguientes estados que el técnico asignado puede elegir.
  List<String> get siguientesEstados => switch (estado) {
    'asignada' => const ['en_camino'],
    'en_camino' => const ['en_proceso'],
    'en_proceso' => const ['pendiente', 'finalizada'],
    'pendiente' => const ['en_proceso'],
    _ => const [],
  };
}

// ---------------------------------------------------------------- Fase 4

const nombresSeccion = {
  'cuarto_maquinas': 'Cuarto de máquinas',
  'cabina': 'Cabina',
  'contrapeso': 'Contrapeso',
  'puertas': 'Puertas',
  'hueco': 'Hueco y recorrido',
  'foso': 'Foso',
};

/// RF-031 a RF-036: componente que se revisa en la planilla.
class ItemChecklist {
  ItemChecklist({required this.id, required this.seccion, required this.codigo, required this.nombre, this.critico = false});

  factory ItemChecklist.desdeJson(Map<String, dynamic> json) => ItemChecklist(
    id: _entero(json['id'])!,
    seccion: json['seccion'] as String? ?? '',
    codigo: json['codigo'] as String? ?? '',
    nombre: json['nombre'] as String? ?? '',
    critico: json['critico'] as bool? ?? false,
  );

  final int id;
  final String seccion;
  final String codigo;
  final String nombre;
  final bool critico;
}

/// RF-037: resultado (INS, EST o NA) y estado (B, R o M) de un ítem.
class ResultadoItem {
  ResultadoItem({this.id, this.resultado = '', this.estado = '', this.observacion = '', this.correctivoNumero, this.correctivoId});

  factory ResultadoItem.desdeJson(Map<String, dynamic> json) {
    final correctivo = json['correctivo_abierto'] as Map<String, dynamic>?;
    return ResultadoItem(
      id: _entero(json['id']),
      resultado: json['resultado'] as String? ?? '',
      estado: json['estado'] as String? ?? '',
      observacion: json['observacion'] as String? ?? '',
      correctivoNumero: correctivo?['numero'] as String?,
      correctivoId: _entero(correctivo?['id']),
    );
  }

  final int? id;
  String resultado;
  String estado;
  String observacion;
  final String? correctivoNumero;
  final int? correctivoId;

  bool get completo => resultado.isNotEmpty && estado.isNotEmpty;

  /// RF-037: R o M exigen observación.
  bool get faltaObservacion => (estado == 'R' || estado == 'M') && observacion.trim().isEmpty;

  ResultadoItem copia() => ResultadoItem(
    id: id,
    resultado: resultado,
    estado: estado,
    observacion: observacion,
    correctivoNumero: correctivoNumero,
    correctivoId: correctivoId,
  );
}

/// RF-030: planilla de una orden preventiva.
class Planilla {
  Planilla({this.resultados = const {}, this.recomendaciones = '', this.fotos = 0, this.faltantes = 0});

  factory Planilla.desdeRespuesta(Map<String, dynamic> respuesta) {
    final datos = respuesta['datos'] as Map<String, dynamic>?;
    return Planilla(
      resultados: {
        for (final r in (datos?['resultados'] as List?) ?? const [])
          _entero((r as Map)['item_id'])!: ResultadoItem.desdeJson(r as Map<String, dynamic>),
      },
      recomendaciones: datos?['recomendaciones'] as String? ?? '',
      fotos: ((datos?['fotos'] as List?) ?? const []).length,
      faltantes: _entero(respuesta['faltantes']) ?? 0,
    );
  }

  final Map<int, ResultadoItem> resultados;
  final String recomendaciones;
  final int fotos;
  final int faltantes;
}

/// Emergencias 24/7: atrapamientos y fallas con tiempo objetivo de llegada.
const nombresTipoEmergencia = {
  'atrapamiento': 'Personas atrapadas',
  'falla': 'Ascensor detenido o con falla',
  'ruido': 'Ruido o movimiento anormal',
  'otro': 'Otro',
};

const nombresEstadoEmergencia = {
  'reportada': 'Reportada',
  'asignada': 'Técnico en camino',
  'en_sitio': 'Técnico en sitio',
  'resuelta': 'Resuelta',
  'cancelada': 'Cancelada',
};

class SeguimientoEmergencia {
  SeguimientoEmergencia({required this.estado, this.nota, this.usuario, this.fecha});

  factory SeguimientoEmergencia.desdeJson(Map<String, dynamic> json) => SeguimientoEmergencia(
    estado: json['estado'] as String? ?? '',
    nota: json['nota'] as String?,
    usuario: json['usuario'] as String?,
    fecha: _fecha(json['fecha']),
  );

  final String estado;
  final String? nota;
  final String? usuario;
  final DateTime? fecha;
}

class Emergencia {
  Emergencia({
    required this.id,
    required this.numero,
    required this.tipo,
    required this.estado,
    required this.personasAtrapadas,
    required this.minutosObjetivo,
    this.equipoId,
    this.equipoCodigo,
    this.ubicacion,
    this.edificioNombre,
    this.clienteNombre,
    this.descripcion,
    this.reportanteNombre,
    this.reportanteTelefono,
    this.tecnicoNombre,
    this.minutosRespuesta,
    this.dentroObjetivo,
    this.reportadaEn,
    this.llegadaEn,
    this.resueltaEn,
    this.solucion,
    this.ordenNumero,
    this.siguientes = const [],
    this.seguimiento = const [],
  });

  factory Emergencia.desdeJson(Map<String, dynamic> json) {
    final equipo = json['equipo'] as Map<String, dynamic>?;
    final tecnico = json['tecnico'] as Map<String, dynamic>?;
    return Emergencia(
      id: _entero(json['id'])!,
      numero: json['numero'] as String? ?? '',
      tipo: json['tipo'] as String? ?? 'otro',
      estado: json['estado'] as String? ?? 'reportada',
      personasAtrapadas: json['personas_atrapadas'] == true,
      minutosObjetivo: _entero(json['minutos_objetivo']) ?? 0,
      equipoId: _entero(equipo?['id']),
      equipoCodigo: equipo?['codigo'] as String?,
      ubicacion: equipo?['ubicacion'] as String?,
      edificioNombre: (equipo?['edificio'] as Map?)?['nombre'] as String?,
      clienteNombre: (equipo?['cliente'] as Map?)?['nombre'] as String?,
      descripcion: json['descripcion'] as String?,
      reportanteNombre: json['reportante_nombre'] as String?,
      reportanteTelefono: json['reportante_telefono'] as String?,
      tecnicoNombre: tecnico?['nombre'] as String?,
      minutosRespuesta: _entero(json['minutos_respuesta']),
      dentroObjetivo: json['dentro_objetivo'] as bool?,
      reportadaEn: _fecha(json['reportada_en']),
      llegadaEn: _fecha(json['llegada_en']),
      resueltaEn: _fecha(json['resuelta_en']),
      solucion: json['solucion'] as String?,
      ordenNumero: (json['orden'] as Map?)?['numero'] as String?,
      siguientes: ((json['siguientes'] as List?) ?? const []).map((s) => '$s').toList(),
      seguimiento: ((json['seguimiento'] as List?) ?? const [])
          .map((s) => SeguimientoEmergencia.desdeJson(s as Map<String, dynamic>))
          .toList(),
    );
  }

  final int id;
  final String numero;
  final String tipo;
  final String estado;
  final bool personasAtrapadas;
  final int minutosObjetivo;
  final int? equipoId;
  final String? equipoCodigo;
  final String? ubicacion;
  final String? edificioNombre;
  final String? clienteNombre;
  final String? descripcion;
  final String? reportanteNombre;
  final String? reportanteTelefono;
  final String? tecnicoNombre;
  final int? minutosRespuesta;
  final bool? dentroObjetivo;
  final DateTime? reportadaEn;
  final DateTime? llegadaEn;
  final DateTime? resueltaEn;
  final String? solucion;
  final String? ordenNumero;
  final List<String> siguientes;
  final List<SeguimientoEmergencia> seguimiento;

  bool get abierta => estado == 'reportada' || estado == 'asignada' || estado == 'en_sitio';
  String get nombreTipo => nombresTipoEmergencia[tipo] ?? tipo;
  String get nombreEstado => nombresEstadoEmergencia[estado] ?? estado;
}

/// RF-040: repuesto del inventario (lo que el técnico necesita para elegirlo en una orden).
class RepuestoInventario {
  RepuestoInventario({required this.id, required this.codigo, required this.nombre, required this.unidad, required this.stock});

  factory RepuestoInventario.desdeJson(Map<String, dynamic> json) => RepuestoInventario(
    id: _entero(json['id'])!,
    codigo: json['codigo'] as String? ?? '',
    nombre: json['nombre'] as String? ?? '',
    unidad: json['unidad'] as String? ?? 'unidad',
    stock: _decimal(json['stock']) ?? 0,
  );

  final int id;
  final String codigo;
  final String nombre;
  final String unidad;
  final double stock;
}

/// RF-042: repuesto usado en una orden; se descuenta del inventario al finalizarla.
class RepuestoOrden {
  RepuestoOrden({required this.id, required this.repuesto, required this.cantidad, this.faltante = 0, this.descontada = 0});

  factory RepuestoOrden.desdeJson(Map<String, dynamic> json) => RepuestoOrden(
    id: _entero(json['id'])!,
    repuesto: RepuestoInventario.desdeJson(json['repuesto'] as Map<String, dynamic>),
    cantidad: _decimal(json['cantidad']) ?? 0,
    descontada: _decimal(json['cantidad_descontada']) ?? 0,
    faltante: _decimal(json['faltante']) ?? 0,
  );

  final int id;
  final RepuestoInventario repuesto;
  final double cantidad;
  final double descontada;
  final double faltante;
}

/// "2", "2,5": cantidad sin ceros de más.
String cantidadTexto(double valor) => valor == valor.roundToDouble() ? valor.toStringAsFixed(0) : valor.toStringAsFixed(2).replaceAll(RegExp(r'0+$'), '').replaceAll('.', ',');

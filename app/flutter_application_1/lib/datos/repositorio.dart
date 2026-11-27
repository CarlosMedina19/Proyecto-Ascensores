import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'cliente_api.dart';
import 'copia_local.dart';
import '../estado/cola_envios.dart';
import '../estado/visitas.dart';
import 'modelos.dart';

/// Resultado de un ingreso: la sesión, o el desafío del código de dos pasos. `confianza` es la llave de
/// este celular como dispositivo de confianza (llega al verificar el código).
typedef ResultadoIngreso = ({Usuario? usuario, String? token, int minutosInactividad, DesafioVerificacion? verificacion, String? confianza});

/// Resultado de un cambio del técnico. `enCola`: no había conexión y quedó guardado en el celular; se enviará
/// solo al volver la conexión (el valor ya refleja el cambio).
typedef Guardado<T> = ({T valor, bool enCola});

/// Acceso a los recursos de la API usados por la app móvil.
///
/// Trabajo sin conexión: lo que el técnico consulta queda copiado en el celular ([CopiaLocal]) y se muestra si
/// no hay internet; sus cambios (estados, planilla, fotos, firmas, repuestos, emergencias y visitas) pasan por
/// la [ColaEnvios], que los envía en orden cuando vuelve la conexión.
class Repositorio {
  Repositorio(this.api, {CopiaLocal? copia, ColaEnvios? cola}) : copia = copia ?? CopiaLocal(), cola = cola ?? ColaEnvios(api);

  final ClienteApi api;
  final CopiaLocal copia;
  final ColaEnvios cola;

  int? get _usuario => cola.usuarioId;

  static String _ahora() => DateTime.now().toIso8601String();

  /// Consulta con copia en el celular: sin conexión devuelve lo último que se consultó.
  Future<Map<String, dynamic>> _consultar(String ruta, {Map<String, Object?> consulta = const {}}) async {
    final clave = CopiaLocal.claveDe(ruta, consulta);
    try {
      final respuesta = await api.obtener(ruta, consulta: consulta);
      // La copia se guarda sin hacer esperar a la pantalla.
      unawaited(copia.guardar(_usuario, clave, respuesta));
      return respuesta;
    } on ErrorApi catch (e) {
      if (e.estado != 0) rethrow;
      final guardada = await copia.leer(_usuario, clave);
      if (guardada == null) {
        throw ErrorApi('Sin conexión. Esta información aún no está en el celular: ábrala una vez con internet para tenerla disponible.');
      }
      return guardada;
    }
  }

  /// Envía un cambio. Sin conexión, o si aún hay cambios anteriores por enviar (para respetar el orden), lo guarda
  /// en la cola. `conHora`: el servidor registra la hora real del cambio (`ocurrido_en`) aunque llegue después.
  Future<({Map<String, dynamic>? respuesta, bool enCola})> _cambiar(EnvioPendiente envio, {bool conHora = false}) async {
    await cola.cargar();
    if (cola.pendientes == 0) {
      try {
        return (respuesta: await cola.enviar(envio), enCola: false);
      } on ErrorApi catch (e) {
        if (e.estado != 0) rethrow;
      }
    }
    final guardado = conHora
        ? EnvioPendiente(
            clave: envio.clave,
            usuarioId: envio.usuarioId,
            metodo: envio.metodo,
            ruta: envio.ruta,
            cuerpo: {...?envio.cuerpo, 'ocurrido_en': envio.creadoEn.toUtc().toIso8601String()},
            descripcion: envio.descripcion,
            grupo: envio.grupo,
            creadoEn: envio.creadoEn,
          )
        : envio;
    await cola.encolar(guardado);
    unawaited(cola.sincronizar());
    return (respuesta: null, enCola: true);
  }

  EnvioPendiente _envio(
    String metodo,
    String ruta, {
    required String descripcion,
    required String grupo,
    Map<String, Object?>? cuerpo,
    ArchivoPendiente? archivo,
  }) => EnvioPendiente(usuarioId: _usuario ?? 0, metodo: metodo, ruta: ruta, cuerpo: cuerpo, archivo: archivo, descripcion: descripcion, grupo: grupo);

  /// Lee una copia, la cambia y la guarda (la crea si no existía).
  Future<Map<String, dynamic>> _actualizarCopia(String clave, void Function(Map<String, dynamic> respuesta) cambio) async {
    final respuesta = await copia.leer(_usuario, clave) ?? <String, dynamic>{};
    cambio(respuesta);
    await copia.guardar(_usuario, clave, respuesta);
    return respuesta;
  }

  Future<Map<String, dynamic>> _copiaObligatoria(String clave) async =>
      await copia.leer(_usuario, clave) ?? (throw ErrorApi('Sin conexión. Abra esta información una vez con internet para poder trabajar sin conexión.'));

  // RF-001 Autenticación
  /// Con la verificación en dos pasos activa no llega el token: llega el desafío para pedir el código.
  /// `confianza`: la llave guardada si este celular ya verificó el código; así no lo vuelve a pedir.
  Future<ResultadoIngreso> iniciarSesion(String correo, String password, {String? confianza}) async {
    final respuesta = await api.crear('/iniciar-sesion', {
      'correo': correo,
      'password': password,
      'dispositivo': 'app-movil',
      'dispositivo_confianza': ?confianza,
    });
    if (respuesta['requiere_verificacion'] == true) {
      return (usuario: null, token: null, minutosInactividad: 10, verificacion: DesafioVerificacion.desdeJson(respuesta), confianza: null);
    }
    return _sesionDesde(respuesta);
  }

  /// Segundo paso del ingreso: el código de 6 dígitos que llegó al correo.
  Future<ResultadoIngreso> verificarInicioSesion(String desafio, String codigo) async =>
      _sesionDesde(await api.crear('/iniciar-sesion/verificar', {'desafio': desafio, 'codigo': codigo, 'dispositivo': 'app-movil'}));

  ResultadoIngreso _sesionDesde(Map<String, dynamic> respuesta) => (
    usuario: Usuario.desdeJson(respuesta['usuario'] as Map<String, dynamic>),
    token: respuesta['token_acceso'] as String,
    minutosInactividad: (respuesta['minutos_inactividad'] as num?)?.toInt() ?? 10,
    verificacion: null,
    confianza: respuesta['dispositivo_confianza'] as String?,
  );

  /// Reenvía el código (correo del ingreso o SMS de la recuperación); el servidor exige 60 s entre envíos.
  Future<DesafioVerificacion> reenviarCodigo(String desafio, {String proposito = 'inicio_sesion'}) async =>
      DesafioVerificacion.desdeJson(await api.crear('/codigo-verificacion/reenviar', {'desafio': desafio, 'proposito': proposito}));

  /// Recuperación con el celular, paso 1: envía el código por SMS.
  Future<DesafioVerificacion> recuperarConTelefono(String telefono) async =>
      DesafioVerificacion.desdeJson(await api.crear('/recuperar-contrasena/telefono', {'telefono': telefono}));

  /// Recuperación con el celular, paso 2: código del SMS y contraseña nueva.
  Future<String> restablecerConTelefono({required String desafio, required String codigo, required String nueva, required String confirmacion}) async =>
      (await api.crear('/restablecer-contrasena/telefono', {
            'desafio': desafio,
            'codigo': codigo,
            'password': nueva,
            'password_confirmation': confirmacion,
          }))['mensaje']
          as String? ??
      '';

  Future<void> cerrarSesion() => api.crear('/cerrar-sesion');

  /// Perfil del usuario. `permitirCopia`: al abrir la app sin conexión se usa el último perfil guardado, para que
  /// el técnico pueda seguir trabajando (la sesión sigue siendo la misma).
  Future<Usuario> perfil({bool permitirCopia = false}) async {
    try {
      final respuesta = await api.obtener('/perfil');
      await copia.guardar(null, '/perfil', respuesta);
      return Usuario.desdeJson(respuesta['datos'] as Map<String, dynamic>);
    } on ErrorApi catch (e) {
      if (!permitirCopia || e.estado != 0) rethrow;
      final guardada = await copia.leer(null, '/perfil');
      if (guardada == null) rethrow;
      return Usuario.desdeJson(guardada['datos'] as Map<String, dynamic>);
    }
  }

  Future<String> recuperarContrasena(String correo) async => (await api.crear('/recuperar-contrasena', {'correo': correo}))['mensaje'] as String? ?? '';

  Future<String> cambiarContrasena({required String actual, required String nueva, required String confirmacion}) async =>
      (await api.reemplazar('/perfil/contrasena', {'contrasena_actual': actual, 'password': nueva, 'password_confirmation': confirmacion}))['mensaje']
          as String? ??
      '';

  Future<Resumen> resumen() async => Resumen.desdeJson(await _consultar('/resumen'));

  // RF-007, RF-008 y RF-009 Equipos, ascensores e historial
  Future<Pagina<Equipo>> equipos({String busqueda = '', String? estado, int? edificioId, int pagina = 1}) async => Pagina.desdeJson(
    await _consultar('/equipos', consulta: {'busqueda': busqueda, 'estado': estado, 'edificio_id': edificioId, 'pagina': pagina, 'por_pagina': 20}),
    Equipo.desdeJson,
  );

  Future<Equipo> equipo(int id) async => Equipo.desdeJson((await _consultar('/equipos/$id'))['datos'] as Map<String, dynamic>);

  Future<List<EventoHistorial>> historial(int equipoId) async {
    final respuesta = await _consultar('/equipos/$equipoId/historial');
    return ((respuesta['datos'] as List?) ?? const []).map((e) => EventoHistorial.desdeJson(e as Map<String, dynamic>)).toList();
  }

  Future<EventoHistorial> registrarEvento(int equipoId, {required String evento, required String descripcion}) async {
    final respuesta = await api.crear('/equipos/$equipoId/historial', {'evento': evento, 'descripcion': descripcion});
    return EventoHistorial.desdeJson(respuesta['datos'] as Map<String, dynamic>);
  }

  Future<Equipo> cambiarEstadoEquipo(int equipoId, String estado) async =>
      Equipo.desdeJson((await api.actualizar('/equipos/$equipoId', {'estado': estado}))['datos'] as Map<String, dynamic>);

  // RF-006 Edificios
  Future<Pagina<Edificio>> edificios({String busqueda = '', int pagina = 1}) async =>
      Pagina.desdeJson(await _consultar('/edificios', consulta: {'busqueda': busqueda, 'pagina': pagina, 'por_pagina': 20}), Edificio.desdeJson);

  Future<Edificio> edificio(int id) async => Edificio.desdeJson((await _consultar('/edificios/$id'))['datos'] as Map<String, dynamic>);

  // RF-017 y RF-018 Mantenimientos programados (rango de máximo 100 días)
  Future<List<MantenimientoProgramado>> mantenimientos({required DateTime desde, required DateTime hasta, int? equipoId}) async {
    String dia(DateTime valor) => '${valor.year}-${'${valor.month}'.padLeft(2, '0')}-${'${valor.day}'.padLeft(2, '0')}';
    final respuesta = await _consultar('/mantenimientos', consulta: {'desde': dia(desde), 'hasta': dia(hasta), 'equipo_id': equipoId});
    return ((respuesta['datos'] as List?) ?? const []).map((m) => MantenimientoProgramado.desdeJson(m as Map<String, dynamic>)).toList();
  }

  // RF-023 a RF-026 Órdenes de trabajo (el técnico solo recibe las suyas)
  Future<Pagina<OrdenTrabajo>> ordenes({bool activas = true, int pagina = 1}) async => Pagina.desdeJson(
    await _consultar('/ordenes', consulta: {'activas': activas ? 1 : null, 'estado': activas ? null : 'finalizada', 'pagina': pagina, 'por_pagina': 30}),
    OrdenTrabajo.desdeJson,
  );

  Future<OrdenTrabajo> orden(int id) async => OrdenTrabajo.desdeJson((await _consultar('/ordenes/$id'))['datos'] as Map<String, dynamic>);

  /// Posición del técnico durante una orden en proceso (mapa de Auditoría del panel). Sin conexión no se guarda:
  /// solo interesa la posición actual.
  Future<void> enviarUbicacion(int ordenId, double latitud, double longitud, double? precision) async {
    await api.crear('/ordenes/$ordenId/ubicacion', {'latitud': latitud, 'longitud': longitud, 'precision_metros': ?precision, 'origen': 'app'});
  }

  /// RF-026 y RF-028: en camino, en proceso, pendiente (con motivo) o finalizada (con informe). Sin conexión queda
  /// en la cola con la hora en que se hizo.
  Future<Guardado<OrdenTrabajo>> cambiarEstadoOrden(int id, String estado, {String? motivo, String? informe, String? observaciones, String? referencia}) async {
    final resultado = await _cambiar(
      _envio(
        'PATCH',
        '/ordenes/$id/estado',
        descripcion: '${referencia ?? 'Orden'} · ${nombresEstadoOrden[estado] ?? estado}',
        grupo: 'orden:$id',
        cuerpo: {'estado': estado, 'motivo': ?motivo, 'informe': ?informe, 'observaciones': ?observaciones},
      ),
      conHora: true,
    );
    if (resultado.respuesta != null) {
      await copia.guardar(_usuario, '/ordenes/$id', resultado.respuesta!);
      return (valor: OrdenTrabajo.desdeJson(resultado.respuesta!['datos'] as Map<String, dynamic>), enCola: false);
    }
    final ahora = _ahora();
    await copia.modificarRegistro(_usuario, '/ordenes', id, (o) {
      o['estado'] = estado;
      if (estado == 'en_proceso') o['inicio_en'] ??= ahora;
      if (estado == 'finalizada') {
        o['fin_en'] = ahora;
        o['informe'] = informe;
      }
    });
    final detalle = await _copiaObligatoria('/ordenes/$id');
    return (valor: OrdenTrabajo.desdeJson(detalle['datos'] as Map<String, dynamic>), enCola: true);
  }

  // Planilla de asistencia técnica de la visita (la plantilla la edita el administrador en el panel).
  Future<List<SeccionPlanilla>> plantillaPlanilla() async =>
      (((await _consultar('/planilla/plantilla'))['datos'] as List?) ?? const []).map((s) => SeccionPlanilla.desdeJson(s as Map<String, dynamic>)).toList();

  /// ¿El servidor puede enviar SMS? Sin operador real la recuperación por celular no se ofrece. Ante cualquier
  /// error se asume que sí (el servidor responde con un aviso claro si no lo está).
  Future<bool> smsDisponible() async {
    try {
      final datos = (await api.obtener('/publico/acceso'))['datos'];
      return !(datos is Map && datos['sms_disponible'] == false);
    } catch (_) {
      return true;
    }
  }

  /// Planillas ya enviadas que el usuario puede ver: el técnico, las suyas.
  Future<List<VisitaEnviada>> visitasRecientes() async => [
    for (final v in (await _consultar('/visitas'))['datos'] as List) VisitaEnviada.desdeJson(v as Map<String, dynamic>),
  ];

  /// Pregunta al asistente de SIGMA, con o sin foto. El servidor responde según los permisos del rol.
  Future<RespuestaAsistente> preguntarAsistente(String mensaje, {required String sesion, List<int>? foto, String? nombreFoto}) async {
    final respuesta = foto == null
        ? await api.crear('/asistente', {'mensaje': mensaje, 'sesion': sesion})
        : await api.subirArchivo(
            '/asistente',
            campo: 'imagen',
            bytes: foto,
            nombreArchivo: nombreFoto ?? 'foto.jpg',
            campos: {'sesion': sesion, if (mensaje.isNotEmpty) 'mensaje': mensaje},
          );
    return RespuestaAsistente.desdeJson(respuesta['datos'] as Map<String, dynamic>);
  }

  /// Envía la planilla diligenciada; el servidor genera el PDF y devuelve el número de la planilla. Sin conexión
  /// queda en la cola (con sus horas de entrada y salida) y se envía sola al volver la conexión.
  Future<Guardado<Map<String, dynamic>?>> registrarVisita(int equipoId, Map<String, Object?> datos, {String? codigo}) async {
    final resultado = await _cambiar(
      _envio('POST', '/equipos/$equipoId/visitas', descripcion: 'Planilla de visita · ${codigo ?? 'ascensor'}', grupo: 'visita:$equipoId', cuerpo: datos),
    );
    return (valor: resultado.respuesta?['datos'] as Map<String, dynamic>?, enCola: resultado.enCola);
  }

  // Fase 4 (RF-030 a RF-039): planilla, fotos, firmas y correctivo derivado
  Future<List<ItemChecklist>> catalogoChecklist() async =>
      (((await _consultar('/checklist/items'))['datos'] as List?) ?? const []).map((i) => ItemChecklist.desdeJson(i as Map<String, dynamic>)).toList();

  Future<Planilla> planilla(int ordenId) async => Planilla.desdeRespuesta(await _consultar('/ordenes/$ordenId/planilla'));

  Future<Guardado<Planilla>> guardarPlanilla(int ordenId, Map<int, ResultadoItem> resultados, {String? recomendaciones, String? referencia}) async {
    final filas = [
      for (final entrada in resultados.entries)
        {
          'item_id': entrada.key,
          'resultado': entrada.value.resultado,
          'estado': entrada.value.estado,
          'observacion': entrada.value.observacion.trim().isEmpty ? null : entrada.value.observacion.trim(),
        },
    ];
    final resultado = await _cambiar(
      _envio(
        'PUT',
        '/ordenes/$ordenId/planilla',
        descripcion: '${referencia ?? 'Orden'} · Planilla (${filas.length} ítem(s))',
        grupo: 'orden:$ordenId',
        cuerpo: {'resultados': filas, 'recomendaciones': ?recomendaciones},
      ),
    );
    if (resultado.respuesta != null) {
      await copia.guardar(_usuario, '/ordenes/$ordenId/planilla', resultado.respuesta!);
      return (valor: Planilla.desdeRespuesta(resultado.respuesta!), enCola: false);
    }

    // Sin conexión: la copia de la planilla y el avance de la orden reflejan lo guardado.
    var nuevos = 0;
    final planilla = await _actualizarCopia('/ordenes/$ordenId/planilla', (r) {
      final datos = (r['datos'] as Map<String, dynamic>?) ?? <String, dynamic>{'resultados': [], 'recomendaciones': '', 'fotos': []};
      final lista = ((datos['resultados'] as List?) ?? []).cast<Map<String, dynamic>>();
      for (final fila in filas) {
        final existente = lista.where((x) => x['item_id'] == fila['item_id']).firstOrNull;
        if (existente == null) {
          lista.add({...fila, 'id': null});
          nuevos++;
        } else {
          existente.addAll(fila);
        }
      }
      datos['resultados'] = lista;
      if (recomendaciones != null) datos['recomendaciones'] = recomendaciones;
      r['datos'] = datos;
      final faltantes = (r['faltantes'] as num?)?.toInt();
      if (faltantes != null) r['faltantes'] = max(0, faltantes - nuevos);
    });
    if (nuevos > 0) {
      await copia.modificarRegistro(_usuario, '/ordenes', ordenId, (o) {
        final avance = o['planilla'];
        if (avance is Map<String, dynamic>) avance['registrados'] = ((avance['registrados'] as num?)?.toInt() ?? 0) + nuevos;
      });
    }
    return (valor: Planilla.desdeRespuesta(planilla), enCola: true);
  }

  /// Foto de la planilla. Sin conexión queda en la cola (la foto viaja guardada en el celular).
  Future<bool> subirFotoPlanilla(int ordenId, List<int> bytes, String nombre, {int? itemId, String? referencia}) async {
    final resultado = await _cambiar(
      _envio(
        'POST',
        '/ordenes/$ordenId/planilla/fotos',
        descripcion: '${referencia ?? 'Orden'} · Foto de la planilla',
        grupo: 'orden:$ordenId',
        archivo: ArchivoPendiente(campo: 'foto', nombre: nombre, base64: base64Encode(bytes), campos: {if (itemId != null) 'item_id': '$itemId'}),
      ),
    );
    if (resultado.enCola) {
      await _actualizarCopia('/ordenes/$ordenId/planilla', (r) {
        final datos = (r['datos'] as Map<String, dynamic>?) ?? <String, dynamic>{'resultados': [], 'recomendaciones': '', 'fotos': []};
        datos['fotos'] = [
          ...?(datos['fotos'] as List?),
          {'id': null, 'nombre': nombre},
        ];
        r['datos'] = datos;
      });
    }
    return resultado.enCola;
  }

  /// RF-025: firma del técnico ('tecnico') o de quien recibe ('cliente'), PNG en base64. Devuelve si quedó en cola.
  Future<bool> firmarOrden(
    int ordenId, {
    required String tipo,
    required String nombre,
    required String pngBase64,
    String? documento,
    String? cargo,
    String? referencia,
  }) async {
    final resultado = await _cambiar(
      _envio(
        'POST',
        '/ordenes/$ordenId/firmas',
        descripcion: '${referencia ?? 'Orden'} · Firma ${tipo == 'tecnico' ? 'del técnico' : 'de quien recibe'}',
        grupo: 'orden:$ordenId',
        cuerpo: {'tipo': tipo, 'nombre': nombre, 'imagen': 'data:image/png;base64,$pngBase64', 'documento': ?documento, 'cargo': ?cargo},
      ),
    );
    if (resultado.enCola) {
      await copia.modificarRegistro(_usuario, '/ordenes', ordenId, (o) {
        final firmas = ((o['firmas'] as List?) ?? []).where((f) => f is Map && f['tipo'] != tipo).toList();
        o['firmas'] = [
          ...firmas,
          {'tipo': tipo, 'nombre': nombre},
        ];
      });
    }
    return resultado.enCola;
  }

  /// RF-038: orden correctiva desde un ítem en R o M.
  Future<String> crearCorrectivo(int ordenId, int resultadoId) async =>
      ((await api.crear('/ordenes/$ordenId/planilla/resultados/$resultadoId/correctivo'))['datos'] as Map<String, dynamic>)['numero'] as String;

  // Emergencias 24/7: el técnico ve las asignadas a él y las que aún no tienen técnico.
  Future<Pagina<Emergencia>> emergencias({bool abiertas = true, int pagina = 1}) async => Pagina.desdeJson(
    await _consultar('/emergencias', consulta: {'estado': abiertas ? 'abiertas' : 'resuelta', 'pagina': pagina, 'por_pagina': 30}),
    Emergencia.desdeJson,
  );

  Future<Emergencia> emergencia(int id) async => Emergencia.desdeJson((await _consultar('/emergencias/$id'))['datos'] as Map<String, dynamic>);

  /// Llegada al sitio ('en_sitio') o solución ('resuelta', con lo que se hizo). Sin conexión queda en la cola con
  /// la hora real (el tiempo de respuesta se mide con ella).
  Future<Guardado<Emergencia>> cambiarEstadoEmergencia(int id, String estado, {String? solucion, String? nota, String? referencia}) async {
    final resultado = await _cambiar(
      _envio(
        'POST',
        '/emergencias/$id/estado',
        descripcion: '${referencia ?? 'Emergencia'} · ${estado == 'en_sitio' ? 'Llegada al sitio' : 'Resuelta'}',
        grupo: 'emergencia:$id',
        cuerpo: {'estado': estado, 'solucion': ?solucion, 'nota': ?nota},
      ),
      conHora: true,
    );
    if (resultado.respuesta != null) {
      await copia.guardar(_usuario, '/emergencias/$id', resultado.respuesta!);
      return (valor: Emergencia.desdeJson(resultado.respuesta!['datos'] as Map<String, dynamic>), enCola: false);
    }
    final ahora = _ahora();
    await copia.modificarRegistro(_usuario, '/emergencias', id, (e) {
      e['estado'] = estado;
      if (estado == 'en_sitio') e['llegada_en'] ??= ahora;
      if (estado == 'resuelta') {
        e['resuelta_en'] = ahora;
        e['llegada_en'] ??= ahora;
        e['solucion'] = solucion;
      }
    });
    final detalle = await _copiaObligatoria('/emergencias/$id');
    return (valor: Emergencia.desdeJson(detalle['datos'] as Map<String, dynamic>), enCola: true);
  }

  Future<({Emergencia emergencia, String mensaje})> reportarEmergencia({required int equipoId, required String tipo, String? descripcion}) async {
    final respuesta = await api.crear('/emergencias', {'equipo_id': equipoId, 'tipo': tipo, 'descripcion': ?descripcion});
    return (emergencia: Emergencia.desdeJson(respuesta['datos'] as Map<String, dynamic>), mensaje: respuesta['mensaje'] as String? ?? '');
  }

  // QR del ascensor: estado (lo ve cualquiera) y, para el técnico, el ascensor y su orden abierta.
  Future<Map<String, dynamic>> estadoQr(String codigo) async => (await api.obtener('/publico/ascensores/$codigo'))['datos'] as Map<String, dynamic>;

  Future<Map<String, dynamic>> ascensorQr(String codigo) async => (await api.obtener('/qr/$codigo'))['datos'] as Map<String, dynamic>;

  /// Inicia (o continúa) el mantenimiento del ascensor escaneado; devuelve la orden en proceso.
  Future<({int ordenId, String mensaje})> iniciarMantenimientoQr(String codigo) async {
    final respuesta = await api.crear('/qr/$codigo/mantenimiento');
    return (ordenId: (respuesta['datos'] as Map<String, dynamic>)['id'] as int, mensaje: respuesta['mensaje'] as String? ?? '');
  }

  // RF-040 y RF-042: inventario y repuestos usados en la orden.
  static const _consultaRepuestos = {'estado': 'activos', 'por_pagina': 100};

  Future<List<RepuestoInventario>> repuestosActivos() async => (((await _consultar('/repuestos', consulta: _consultaRepuestos))['datos'] as List?) ?? const [])
      .map((r) => RepuestoInventario.desdeJson(r as Map<String, dynamic>))
      .toList();

  Future<List<RepuestoOrden>> repuestosDeOrden(int ordenId) async => (((await _consultar('/ordenes/$ordenId/repuestos'))['datos'] as List?) ?? const [])
      .map((r) => RepuestoOrden.desdeJson(r as Map<String, dynamic>))
      .toList();

  /// Registra un repuesto usado. Sin conexión queda en la cola y aparece en la lista de la orden.
  Future<Guardado<String>> agregarRepuesto(int ordenId, int repuestoId, double cantidad, {String? nombre, String? referencia}) async {
    final resultado = await _cambiar(
      _envio(
        'POST',
        '/ordenes/$ordenId/repuestos',
        descripcion: '${referencia ?? 'Orden'} · Repuesto ${nombre ?? ''} × ${cantidad % 1 == 0 ? cantidad.toInt() : cantidad}'.trim(),
        grupo: 'orden:$ordenId',
        cuerpo: {'repuesto_id': repuestoId, 'cantidad': cantidad},
      ),
    );
    if (!resultado.enCola) return (valor: resultado.respuesta?['mensaje'] as String? ?? '', enCola: false);

    final inventario = await copia.leer(_usuario, CopiaLocal.claveDe('/repuestos', _consultaRepuestos));
    final repuesto = ((inventario?['datos'] as List?) ?? const []).cast<Map<String, dynamic>>().where((r) => r['id'] == repuestoId).firstOrNull;
    if (repuesto != null) {
      await _actualizarCopia('/ordenes/$ordenId/repuestos', (r) {
        r['datos'] = [
          ...?(r['datos'] as List?),
          // Id negativo: aún no está en el servidor.
          {'id': -DateTime.now().millisecondsSinceEpoch, 'repuesto': repuesto, 'cantidad': cantidad, 'cantidad_descontada': 0, 'faltante': 0},
        ];
      });
    }
    return (valor: 'Repuesto guardado en el celular: se enviará al tener conexión.', enCola: true);
  }

  /// Quita un repuesto ya registrado. Uno que aún no se ha enviado no se puede quitar hasta que llegue al servidor.
  Future<bool> quitarRepuesto(int ordenId, int registroId, {String? referencia}) async {
    if (registroId < 0) throw ErrorApi('Ese repuesto aún no se ha enviado al servidor. Podrá quitarlo cuando se envíe.');
    final resultado = await _cambiar(
      _envio('DELETE', '/ordenes/$ordenId/repuestos/$registroId', descripcion: '${referencia ?? 'Orden'} · Quitar repuesto', grupo: 'orden:$ordenId'),
    );
    if (resultado.enCola) {
      await _actualizarCopia('/ordenes/$ordenId/repuestos', (r) {
        r['datos'] = [...?(r['datos'] as List?)]..removeWhere((x) => x is Map && x['id'] == registroId);
      });
    }
    return resultado.enCola;
  }

  // RF-005 Clientes
  Future<Cliente> cliente(int id) async => Cliente.desdeJson((await _consultar('/clientes/$id'))['datos'] as Map<String, dynamic>);
}

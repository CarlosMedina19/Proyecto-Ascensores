import 'cliente_api.dart';
import 'modelos.dart';

/// Acceso a los recursos de la API usados por la app móvil.
class Repositorio {
  Repositorio(this.api);

  final ClienteApi api;

  // RF-001 Autenticación
  Future<({Usuario usuario, String token, int minutosInactividad})> iniciarSesion(String correo, String password) async {
    final respuesta = await api.crear('/iniciar-sesion', {'correo': correo, 'password': password, 'dispositivo': 'app-movil'});
    return (
      usuario: Usuario.desdeJson(respuesta['usuario'] as Map<String, dynamic>),
      token: respuesta['token_acceso'] as String,
      minutosInactividad: (respuesta['minutos_inactividad'] as num?)?.toInt() ?? 10,
    );
  }

  Future<void> cerrarSesion() => api.crear('/cerrar-sesion');

  Future<Usuario> perfil() async => Usuario.desdeJson((await api.obtener('/perfil'))['datos'] as Map<String, dynamic>);

  Future<String> recuperarContrasena(String correo) async =>
      (await api.crear('/recuperar-contrasena', {'correo': correo}))['mensaje'] as String? ?? '';

  Future<String> cambiarContrasena({required String actual, required String nueva, required String confirmacion}) async =>
      (await api.reemplazar('/perfil/contrasena', {
            'contrasena_actual': actual,
            'password': nueva,
            'password_confirmation': confirmacion,
          }))['mensaje']
          as String? ??
      '';

  Future<Resumen> resumen() async => Resumen.desdeJson(await api.obtener('/resumen'));

  // RF-007, RF-008 y RF-009 Equipos, ascensores e historial
  Future<Pagina<Equipo>> equipos({String busqueda = '', String? estado, int? edificioId, int pagina = 1}) async => Pagina.desdeJson(
    await api.obtener(
      '/equipos',
      consulta: {'busqueda': busqueda, 'estado': estado, 'edificio_id': edificioId, 'pagina': pagina, 'por_pagina': 20},
    ),
    Equipo.desdeJson,
  );

  Future<Equipo> equipo(int id) async => Equipo.desdeJson((await api.obtener('/equipos/$id'))['datos'] as Map<String, dynamic>);

  Future<List<EventoHistorial>> historial(int equipoId) async {
    final respuesta = await api.obtener('/equipos/$equipoId/historial');
    return ((respuesta['datos'] as List?) ?? const []).map((e) => EventoHistorial.desdeJson(e as Map<String, dynamic>)).toList();
  }

  Future<EventoHistorial> registrarEvento(int equipoId, {required String evento, required String descripcion}) async {
    final respuesta = await api.crear('/equipos/$equipoId/historial', {'evento': evento, 'descripcion': descripcion});
    return EventoHistorial.desdeJson(respuesta['datos'] as Map<String, dynamic>);
  }

  Future<Equipo> cambiarEstadoEquipo(int equipoId, String estado) async =>
      Equipo.desdeJson((await api.actualizar('/equipos/$equipoId', {'estado': estado}))['datos'] as Map<String, dynamic>);

  // RF-006 Edificios
  Future<Pagina<Edificio>> edificios({String busqueda = '', int pagina = 1}) async => Pagina.desdeJson(
    await api.obtener('/edificios', consulta: {'busqueda': busqueda, 'pagina': pagina, 'por_pagina': 20}),
    Edificio.desdeJson,
  );

  Future<Edificio> edificio(int id) async => Edificio.desdeJson((await api.obtener('/edificios/$id'))['datos'] as Map<String, dynamic>);

  // RF-017 y RF-018 Mantenimientos programados (rango de máximo 100 días)
  Future<List<MantenimientoProgramado>> mantenimientos({required DateTime desde, required DateTime hasta, int? equipoId}) async {
    String dia(DateTime valor) => '${valor.year}-${'${valor.month}'.padLeft(2, '0')}-${'${valor.day}'.padLeft(2, '0')}';
    final respuesta = await api.obtener('/mantenimientos', consulta: {'desde': dia(desde), 'hasta': dia(hasta), 'equipo_id': equipoId});
    return ((respuesta['datos'] as List?) ?? const []).map((m) => MantenimientoProgramado.desdeJson(m as Map<String, dynamic>)).toList();
  }

  // RF-023 a RF-026 Órdenes de trabajo (el técnico solo recibe las suyas)
  Future<Pagina<OrdenTrabajo>> ordenes({bool activas = true, int pagina = 1}) async => Pagina.desdeJson(
    await api.obtener(
      '/ordenes',
      consulta: {'activas': activas ? 1 : null, 'estado': activas ? null : 'finalizada', 'pagina': pagina, 'por_pagina': 30},
    ),
    OrdenTrabajo.desdeJson,
  );

  Future<OrdenTrabajo> orden(int id) async => OrdenTrabajo.desdeJson((await api.obtener('/ordenes/$id'))['datos'] as Map<String, dynamic>);

  /// RF-026 y RF-028: en camino, en proceso, pendiente (con motivo) o finalizada (con informe).
  Future<OrdenTrabajo> cambiarEstadoOrden(int id, String estado, {String? motivo, String? informe, String? observaciones}) async =>
      OrdenTrabajo.desdeJson(
        (await api.actualizar('/ordenes/$id/estado', {
              'estado': estado,
              'motivo': ?motivo,
              'informe': ?informe,
              'observaciones': ?observaciones,
            }))['datos']
            as Map<String, dynamic>,
      );

  // Fase 4 (RF-030 a RF-039): planilla, fotos, firmas y correctivo derivado
  Future<List<ItemChecklist>> catalogoChecklist() async => (((await api.obtener('/checklist/items'))['datos'] as List?) ?? const [])
      .map((i) => ItemChecklist.desdeJson(i as Map<String, dynamic>))
      .toList();

  Future<Planilla> planilla(int ordenId) async => Planilla.desdeRespuesta(await api.obtener('/ordenes/$ordenId/planilla'));

  Future<Planilla> guardarPlanilla(int ordenId, Map<int, ResultadoItem> resultados, {String? recomendaciones}) async =>
      Planilla.desdeRespuesta(
        await api.reemplazar('/ordenes/$ordenId/planilla', {
          'resultados': [
            for (final entrada in resultados.entries)
              {
                'item_id': entrada.key,
                'resultado': entrada.value.resultado,
                'estado': entrada.value.estado,
                'observacion': entrada.value.observacion.trim().isEmpty ? null : entrada.value.observacion.trim(),
              },
          ],
          'recomendaciones': ?recomendaciones,
        }),
      );

  Future<void> subirFotoPlanilla(int ordenId, List<int> bytes, String nombre, {int? itemId}) => api.subirArchivo(
    '/ordenes/$ordenId/planilla/fotos',
    campo: 'foto',
    bytes: bytes,
    nombreArchivo: nombre,
    campos: {if (itemId != null) 'item_id': '$itemId'},
  );

  /// RF-025: firma del técnico ('tecnico') o de quien recibe ('cliente'), PNG en base64.
  Future<void> firmarOrden(
    int ordenId, {
    required String tipo,
    required String nombre,
    required String pngBase64,
    String? documento,
    String? cargo,
  }) => api.crear('/ordenes/$ordenId/firmas', {
    'tipo': tipo,
    'nombre': nombre,
    'imagen': 'data:image/png;base64,$pngBase64',
    'documento': ?documento,
    'cargo': ?cargo,
  });

  /// RF-038: orden correctiva desde un ítem en R o M.
  Future<String> crearCorrectivo(int ordenId, int resultadoId) async =>
      ((await api.crear('/ordenes/$ordenId/planilla/resultados/$resultadoId/correctivo'))['datos'] as Map<String, dynamic>)['numero']
          as String;

  // Emergencias 24/7: el técnico ve las asignadas a él y las que aún no tienen técnico.
  Future<Pagina<Emergencia>> emergencias({bool abiertas = true, int pagina = 1}) async => Pagina.desdeJson(
    await api.obtener('/emergencias', consulta: {'estado': abiertas ? 'abiertas' : 'resuelta', 'pagina': pagina, 'por_pagina': 30}),
    Emergencia.desdeJson,
  );

  Future<Emergencia> emergencia(int id) async => Emergencia.desdeJson((await api.obtener('/emergencias/$id'))['datos'] as Map<String, dynamic>);

  /// Llegada al sitio ('en_sitio') o solución ('resuelta', con lo que se hizo).
  Future<Emergencia> cambiarEstadoEmergencia(int id, String estado, {String? solucion, String? nota}) async => Emergencia.desdeJson(
    (await api.crear('/emergencias/$id/estado', {'estado': estado, 'solucion': ?solucion, 'nota': ?nota}))['datos'] as Map<String, dynamic>,
  );

  Future<({Emergencia emergencia, String mensaje})> reportarEmergencia({
    required int equipoId,
    required String tipo,
    String? descripcion,
  }) async {
    final respuesta = await api.crear('/emergencias', {'equipo_id': equipoId, 'tipo': tipo, 'descripcion': ?descripcion});
    return (emergencia: Emergencia.desdeJson(respuesta['datos'] as Map<String, dynamic>), mensaje: respuesta['mensaje'] as String? ?? '');
  }

  // RF-040 y RF-042: inventario y repuestos usados en la orden.
  Future<List<RepuestoInventario>> repuestosActivos() async => (((await api.obtener('/repuestos', consulta: {'estado': 'activos', 'por_pagina': 100}))['datos'] as List?) ?? const [])
      .map((r) => RepuestoInventario.desdeJson(r as Map<String, dynamic>))
      .toList();

  Future<List<RepuestoOrden>> repuestosDeOrden(int ordenId) async => (((await api.obtener('/ordenes/$ordenId/repuestos'))['datos'] as List?) ?? const [])
      .map((r) => RepuestoOrden.desdeJson(r as Map<String, dynamic>))
      .toList();

  Future<String> agregarRepuesto(int ordenId, int repuestoId, double cantidad) async =>
      (await api.crear('/ordenes/$ordenId/repuestos', {'repuesto_id': repuestoId, 'cantidad': cantidad}))['mensaje'] as String? ?? '';

  Future<void> quitarRepuesto(int ordenId, int registroId) => api.eliminar('/ordenes/$ordenId/repuestos/$registroId');

  // RF-005 Clientes
  Future<Cliente> cliente(int id) async => Cliente.desdeJson((await api.obtener('/clientes/$id'))['datos'] as Map<String, dynamic>);
}

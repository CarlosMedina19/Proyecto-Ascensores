import 'dart:convert';

import 'package:flutter_application_1/datos/cliente_api.dart';
import 'package:flutter_application_1/datos/modelos.dart';
import 'package:flutter_application_1/datos/repositorio.dart';
import 'package:flutter_application_1/estado/cola_envios.dart';
import 'package:flutter_application_1/estado/sesion.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Trabajo del técnico sin conexión: copia local, cola de envíos en orden y envío al volver el internet.
const _tecnico = {
  'id': 7,
  'nombre': 'Andrés Rojas',
  'correo': 'tecnicosigma@gmail.com',
  'rol': {
    'nombre': 'tecnico',
    'permisos': ['ordenes.ver', 'equipos.ver', 'equipos.historial.crear'],
  },
};

Map<String, dynamic> _orden(String estado) => {
  'id': 5,
  'numero': 'OT-2026-00005',
  'servicio': 'preventivo',
  'estado': estado,
  'prioridad': 'media',
  'fecha': '2026-11-26',
  'hora': '08:00',
  'equipo_id': 27,
  'equipo': {'id': 27, 'codigo': 'ASC-002'},
  'tecnicos': [
    {'id': 1, 'nombre': 'Andrés Rojas'},
  ],
  'permisos': {'ejecutar': true},
  'lleva_planilla': true,
  'planilla': {'total_items': 3, 'registrados': 0},
  'firmas': [],
};

http.Response _json(Object cuerpo, [int estado = 200]) =>
    http.Response(jsonEncode(cuerpo), estado, headers: {'content-type': 'application/json; charset=utf-8'});

/// Servidor simulado: `enLinea` corta o devuelve la conexión; guarda cada envío con su cabecera de clave.
class _Servidor {
  bool enLinea = true;
  final envios = <Map<String, dynamic>>[];
  int? rechazarEstadoCon;

  MockClient get cliente => MockClient((solicitud) async {
    if (!enLinea) throw http.ClientException('Sin red');
    final ruta = solicitud.url.path.replaceFirst('/api/v1', '');
    final cuerpo = solicitud.body.isNotEmpty && solicitud.headers['content-type']?.contains('json') == true
        ? jsonDecode(solicitud.body) as Map<String, dynamic>
        : <String, dynamic>{};
    if (solicitud.method != 'GET') envios.add({'metodo': solicitud.method, 'ruta': ruta, 'clave': solicitud.headers['X-Clave-Envio'], ...cuerpo});
    return switch ((solicitud.method, ruta)) {
      ('GET', '/perfil') => _json({'datos': _tecnico}),
      ('GET', '/ordenes/5') => _json({'datos': _orden('asignada')}),
      ('GET', '/ordenes/5/planilla') => _json({'datos': null, 'faltantes': 3}),
      ('PATCH', '/ordenes/5/estado') when rechazarEstadoCon != null => _json({
        'mensaje': 'Una orden Finalizada no puede pasar a En proceso.',
      }, rechazarEstadoCon!),
      ('PATCH', '/ordenes/5/estado') => _json({'datos': _orden('${cuerpo['estado']}')}),
      ('PUT', '/ordenes/5/planilla') => _json({
        'datos': {'resultados': cuerpo['resultados'], 'recomendaciones': '', 'fotos': []},
        'faltantes': 2,
      }),
      ('POST', '/equipos/27/visitas') => _json({
        'datos': {'id': 40, 'numero': 'PL-0040'},
      }, 201),
      _ => _json({'datos': []}),
    };
  });
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  late _Servidor servidor;
  late Repositorio repositorio;

  setUp(() {
    servidor = _Servidor();
    final api = ClienteApi(http: servidor.cliente, urlBase: 'http://api.test/api/v1')..token = 'token';
    repositorio = Repositorio(api, cola: ColaEnvios(api, intervalo: const Duration(hours: 1)));
    repositorio.cola.usuarioId = 7;
  });

  tearDown(() => repositorio.cola.dispose());

  test('sin conexión muestra la copia guardada y los cambios de estado quedan en cola, en orden y con su hora', () async {
    expect((await repositorio.orden(5)).estado, 'asignada');

    servidor.enLinea = false;
    expect((await repositorio.orden(5)).numero, 'OT-2026-00005', reason: 'sin conexión se lee la copia del celular');

    final enCamino = await repositorio.cambiarEstadoOrden(5, 'en_camino', referencia: 'OT-2026-00005');
    expect(enCamino.enCola, isTrue);
    expect(enCamino.valor.estado, 'en_camino');
    final enProceso = await repositorio.cambiarEstadoOrden(5, 'en_proceso', referencia: 'OT-2026-00005');
    expect(enProceso.valor.estado, 'en_proceso');
    expect(enProceso.valor.inicioEn, isNotNull);
    expect((await repositorio.orden(5)).estado, 'en_proceso', reason: 'la copia refleja el cambio aunque no haya llegado');

    expect(repositorio.cola.pendientes, 2);
    expect(repositorio.cola.tienePendientes('orden:5'), isTrue);
    expect(repositorio.cola.envios.first.descripcion, 'OT-2026-00005 · En camino');
    expect(repositorio.cola.envios.first.cuerpo!['ocurrido_en'], isNotNull);

    // Vuelve la conexión: se envían en el orden en que se hicieron, cada uno con su clave.
    servidor.enLinea = true;
    await repositorio.cola.sincronizar();
    expect(repositorio.cola.pendientes, 0);
    expect([for (final e in servidor.envios) e['estado']], ['en_camino', 'en_proceso']);
    expect(servidor.envios.map((e) => e['clave']).toSet().length, 2);
    expect(servidor.envios.every((e) => e['ocurrido_en'] != null), isTrue);
    expect(repositorio.cola.enviadosUltimaVez, 2);
  });

  test('la cola sobrevive al cierre de la app (queda en el celular)', () async {
    await repositorio.orden(5);
    servidor.enLinea = false;
    await repositorio.cambiarEstadoOrden(5, 'en_camino');
    await repositorio.cola.sincronizar();

    final api = ClienteApi(http: servidor.cliente, urlBase: 'http://api.test/api/v1')..token = 'token';
    final otraVez = ColaEnvios(api, intervalo: const Duration(hours: 1))..usuarioId = 7;
    await otraVez.cargar();
    expect(otraVez.pendientes, 1);
    expect(otraVez.envios.single.ruta, '/ordenes/5/estado');
    otraVez.dispose();
  });

  test('un cambio que el servidor rechaza queda marcado y no detiene los demás', () async {
    await repositorio.orden(5);
    servidor.enLinea = false;
    await repositorio.cambiarEstadoOrden(5, 'en_camino');
    await repositorio.guardarPlanilla(5, {1: ResultadoItem(resultado: 'Ajustado', estado: 'B')});

    servidor
      ..enLinea = true
      ..rechazarEstadoCon = 422;
    await repositorio.cola.sincronizar();
    expect(repositorio.cola.pendientes, 0);
    expect(repositorio.cola.rechazados, 1);
    expect(repositorio.cola.envios.single.error, contains('no puede pasar'));
    expect(servidor.envios.last['ruta'], '/ordenes/5/planilla');

    await repositorio.cola.descartar(repositorio.cola.envios.single);
    expect(repositorio.cola.envios, isEmpty);
  });

  test('la planilla de la orden se guarda sin conexión y avanza en la copia', () async {
    await repositorio.orden(5);
    await repositorio.planilla(5);
    servidor.enLinea = false;

    final guardado = await repositorio.guardarPlanilla(5, {
      1: ResultadoItem(resultado: 'Limpieza', estado: 'B'),
      2: ResultadoItem(resultado: 'Ajuste', estado: 'R', observacion: 'Desgaste'),
    });
    expect(guardado.enCola, isTrue);
    expect(guardado.valor.resultados.keys, containsAll([1, 2]));
    expect(guardado.valor.faltantes, 1);
    expect((await repositorio.orden(5)).planillaRegistrados, 2);
  });

  test('la planilla de visita terminada sin conexión se envía sola después', () async {
    servidor.enLinea = false;
    final guardado = await repositorio.registrarVisita(27, {'tipo_servicio': 'preventivo', 'resultados': []}, codigo: 'ASC-002');
    expect(guardado.enCola, isTrue);
    await repositorio.cola.sincronizar(); // el intento automático, aún sin conexión
    expect(repositorio.cola.envios.single.grupo, 'visita:27');

    servidor.enLinea = true;
    await repositorio.cola.sincronizar();
    expect(servidor.envios.single['ruta'], '/equipos/27/visitas');
    expect(servidor.envios.single['clave'], isNotEmpty);
    expect(repositorio.cola.envios, isEmpty);
  });

  test('con conexión el cambio se envía de una vez (sin cola)', () async {
    await repositorio.orden(5);
    final guardado = await repositorio.cambiarEstadoOrden(5, 'en_camino');
    expect(guardado.enCola, isFalse);
    expect(guardado.valor.estado, 'en_camino');
    expect(servidor.envios.single['ocurrido_en'], isNull, reason: 'en línea manda la hora del servidor');
    expect(repositorio.cola.envios, isEmpty);
  });

  test('al abrir la app sin conexión se conserva la sesión con el último perfil', () async {
    final almacen = AlmacenTokenMemoria();
    await almacen.guardar('token');
    await repositorio.perfil(); // queda la copia del perfil
    servidor.enLinea = false;

    final sesion = Sesion(repositorio: repositorio, almacen: almacen, intervaloRevision: const Duration(hours: 1));
    await sesion.iniciar();
    expect(sesion.autenticado, isTrue);
    expect(sesion.usuario!.nombre, 'Andrés Rojas');
    expect(repositorio.cola.usuarioId, 7);
    sesion.dispose();
  });
}

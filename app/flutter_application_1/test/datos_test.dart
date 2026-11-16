import 'dart:convert';

import 'package:flutter_application_1/datos/cliente_api.dart';
import 'package:flutter_application_1/datos/modelos.dart';
import 'package:flutter_application_1/estado/visitas.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  group('Modelos', () {
    test('la emergencia lee el ascensor, el técnico, la orden y el seguimiento', () {
      final emergencia = Emergencia.desdeJson({
        'id': 4,
        'numero': 'EM-2026-00004',
        'tipo': 'atrapamiento',
        'estado': 'asignada',
        'personas_atrapadas': true,
        'minutos_objetivo': 30,
        'equipo': {
          'id': 2,
          'codigo': 'ASC-002',
          'ubicacion': 'Torre B',
          'edificio': {'id': 1, 'nombre': 'Conjunto Mirador'},
          'cliente': {'id': 3, 'nombre': 'Administración Mirador'},
        },
        'tecnico': {'id': 9, 'nombre': 'Andrés Rojas'},
        'orden': {'id': 15, 'numero': 'OT-2026-00015'},
        'reportada_en': '2026-01-05T10:00:00-05:00',
        'siguientes': ['en_sitio', 'resuelta', 'cancelada'],
        'seguimiento': [
          {'estado': 'reportada', 'nota': 'Reporte desde la app.', 'usuario': 'Ana', 'fecha': '2026-01-05T10:00:00-05:00'},
        ],
      });

      expect(emergencia.abierta, isTrue);
      expect(emergencia.nombreTipo, 'Personas atrapadas');
      expect(emergencia.nombreEstado, 'Técnico en camino');
      expect(emergencia.equipoCodigo, 'ASC-002');
      expect(emergencia.edificioNombre, 'Conjunto Mirador');
      expect(emergencia.tecnicoNombre, 'Andrés Rojas');
      expect(emergencia.ordenNumero, 'OT-2026-00015');
      expect(emergencia.siguientes, contains('en_sitio'));
      expect(emergencia.seguimiento.single.nota, 'Reporte desde la app.');
    });

    test('el detalle del ascensor dice si el técnico puede registrar la visita y por qué', () {
      final cerrada = Equipo.desdeJson({
        'id': 7,
        'codigo': 'ASC-007',
        'visita': {'permitida': false, 'motivo': 'El próximo es el 19/11/2026.'},
      });
      expect(cerrada.visitaPermitida, isFalse);
      expect(cerrada.motivoVisita, contains('19/11/2026'));
      // Sin el dato (listados o roles sin restricción) se permite.
      expect(Equipo.desdeJson({'id': 8, 'codigo': 'ASC-008'}).visitaPermitida, isTrue);
    });

    test('el usuario lee su rol, permisos y cliente', () {
      final usuario = Usuario.desdeJson({
        'id': 7,
        'nombre': 'Ana María Ruiz',
        'correo': 'ana@sigma.test',
        'cliente_id': 3,
        'cliente': {'id': 3, 'nombre': 'Conjunto Mirador'},
        'rol': {
          'nombre': 'cliente',
          'permisos': ['equipos.ver', 'edificios.ver'],
        },
      });

      expect(usuario.esCliente, isTrue);
      expect(usuario.nombreRol, 'Cliente');
      expect(usuario.primerNombre, 'Ana');
      expect(usuario.tienePermiso('equipos.ver'), isTrue);
      expect(usuario.tienePermiso('usuarios.ver'), isFalse);
      expect(usuario.clienteNombre, 'Conjunto Mirador');
    });

    test('la página de equipos lee la ficha técnica y la paginación en español', () {
      final pagina = Pagina.desdeJson({
        'datos': [
          {
            'id': 4,
            'codigo': 'ASC-001',
            'estado': 'mantenimiento',
            'edificio': {
              'nombre': 'Torre A',
              'cliente': {'nombre': 'Conjunto Mirador'},
            },
            'ascensor': {'capacidad_kg': 800, 'velocidad_mpm': '105.00', 'paradas': 18, 'puertas_piso': 18},
          },
        ],
        'metadatos': {'pagina_actual': 1, 'ultima_pagina': 3, 'total': 41},
      }, Equipo.desdeJson);

      final equipo = pagina.elementos.single;
      expect(equipo.codigo, 'ASC-001');
      expect(equipo.edificioNombre, 'Torre A');
      expect(equipo.clienteNombre, 'Conjunto Mirador');
      expect(equipo.ascensor!.velocidadMpm, 105.0);
      expect(equipo.ascensor!.puertasPiso, 18);
      expect(pagina.hayMas, isTrue);
      expect(pagina.total, 41);
    });
  });

  group('ClienteApi', () {
    test('convierte los errores de validación de la API', () async {
      final api = ClienteApi(
        urlBase: 'http://api.test/api/v1',
        http: MockClient(
          (_) async => http.Response(
            jsonEncode({
              'mensaje': 'Los datos enviados no son válidos.',
              'errores': {
                'descripcion': ['El campo descripción es obligatorio.'],
              },
            }),
            422,
            headers: {'content-type': 'application/json; charset=utf-8'},
          ),
        ),
      );

      expect(
        () => api.crear('/equipos/1/historial', {'evento': 'observacion'}),
        throwsA(
          isA<ErrorApi>()
              .having((e) => e.estado, 'estado', 422)
              .having((e) => e.errorDe('descripcion'), 'error', 'El campo descripción es obligatorio.'),
        ),
      );
    });

    test('envía el token y avisa cuando la sesión expira (401)', () async {
      String? autorizacion;
      var expirada = false;
      final api =
          ClienteApi(
              urlBase: 'http://api.test/api/v1',
              http: MockClient((solicitud) async {
                autorizacion = solicitud.headers['Authorization'];
                return http.Response(jsonEncode({'mensaje': 'Sesión no válida o expirada.'}), 401);
              }),
            )
            ..token = 'abc'
            ..alExpirarSesion = () => expirada = true;

      await expectLater(api.obtener('/perfil'), throwsA(isA<ErrorApi>()));
      expect(autorizacion, 'Bearer abc');
      expect(expirada, isTrue);
      expect(api.token, isNull);
    });

    test('envía los filtros como parámetros de consulta y omite los vacíos', () async {
      Uri? solicitada;
      final api = ClienteApi(
        urlBase: 'http://api.test/api/v1',
        http: MockClient((solicitud) async {
          solicitada = solicitud.url;
          return http.Response(jsonEncode({'datos': []}), 200);
        }),
      );

      await api.obtener('/equipos', consulta: {'busqueda': '', 'estado': 'activo', 'pagina': 2});
      expect(solicitada!.queryParameters, {'estado': 'activo', 'pagina': '2'});
    });
  });

  group('Planilla de asistencia técnica', () {
    List<SeccionPlanilla> plantilla() => [
      SeccionPlanilla(clave: 'cabina', nombre: 'Cabina', items: [
        ItemPlanilla(id: 1, codigo: 'CB-01', nombre: 'Verificación de cabina.'),
        ItemPlanilla(id: 2, codigo: 'CB-07', nombre: 'Señales de emergencia.', critico: true),
      ]),
      SeccionPlanilla(clave: 'foso', nombre: 'En foso', items: [ItemPlanilla(id: 3, codigo: 'EF-04', nombre: 'Verificación del buffer.')]),
    ];

    VisitaBorrador visitaCompleta() {
      final visita = VisitaBorrador(equipoId: 4, equipoCodigo: 'ASC-001', plantilla: plantilla(), inicio: DateTime(2026, 10, 16, 8, 12));
      for (final seccion in visita.plantilla) {
        visita.todoBueno(seccion);
      }
      visita
        ..firmaBase64 = 'iVBORw0KGgo='
        ..firmaRecibeBase64 = 'iVBORw0KGgo='
        ..nombreRecibe = 'Marta Díaz'
        ..documentoRecibe = '52123456';
      return visita;
    }

    test('exige marcar todas las actividades, el nombre y la C.C. de quien recibe y las firmas', () {
      final visita = VisitaBorrador(equipoId: 4, equipoCodigo: 'ASC-001', plantilla: plantilla());
      expect(visita.listaParaEnviar, isFalse);
      expect(visita.pendientes, contains('Faltan 3 actividad(es) por marcar'));
      expect(visita.pendientes, contains('Falta la firma del técnico'));
      expect(visita.pendientes, contains('Falta la C.C. de quien recibe'));
      expect(visitaCompleta().listaParaEnviar, isTrue);
    });

    test('Regular o Malo exigen observación y N.A. cuenta como marcada', () {
      final visita = visitaCompleta();
      visita.respuesta(3).estado = 'M';
      expect(visita.listaParaEnviar, isFalse);
      expect(visita.sinObservacion, 1);
      visita.respuesta(3).observacion = 'Fuga leve en el amortiguador.';
      visita.respuesta(1)
        ..na = true
        ..estado = '';
      expect(visita.listaParaEnviar, isTrue);
      expect(visita.conteo, {'B': 1, 'R': 0, 'M': 1, 'NA': 1});
    });

    test('los datos para la API llevan cada actividad, el tipo y las firmas', () {
      final visita = visitaCompleta()
        ..tipoServicio = 'correctivo'
        ..repuestos.add(Repuesto('Rodamiento', 2));
      visita.respuesta(2)
        ..estado = 'R'
        ..observacion = 'Luz de emergencia débil.';
      final datos = visita.paraEnviar(salida: DateTime(2026, 10, 16, 10, 21));
      expect(datos['tipo_servicio'], 'correctivo');
      expect((datos['resultados'] as List).length, 3);
      expect((datos['resultados'] as List)[1], {'item_id': 2, 'ins': true, 'estado': 'R', 'na': false, 'observacion': 'Luz de emergencia débil.'});
      expect(datos['recibe_documento'], '52123456');
      expect((datos['firma_tecnico'] as String).startsWith('data:image/png;base64,'), isTrue);
    });

    test('se guarda y se recupera igual (sin conexión) y conserva lo marcado al cambiar la plantilla', () {
      final visita = visitaCompleta()..repuestos.add(Repuesto('Contacto', 1));
      final recuperada = VisitaBorrador.desdeJson(jsonDecode(jsonEncode(visita.aJson())) as Map<String, dynamic>);
      expect(recuperada.equipoCodigo, 'ASC-001');
      expect(recuperada.respondidos, 3);
      expect(recuperada.repuestos.single.nombre, 'Contacto');
      expect(recuperada.inicio, visita.inicio);
      expect(recuperada.listaParaEnviar, isTrue);

      // El administrador quitó una actividad y agregó otra: lo marcado se conserva y la nueva queda pendiente.
      recuperada.actualizarPlantilla([
        SeccionPlanilla(clave: 'cabina', nombre: 'Cabina', items: [ItemPlanilla(id: 1, codigo: 'CB-01', nombre: 'Verificación de cabina.'), ItemPlanilla(id: 9, codigo: 'CB-10', nombre: 'Nueva.')]),
      ]);
      expect(recuperada.respondidos, 1);
      expect(recuperada.pendientes, contains('Faltan 1 actividad(es) por marcar'));
    });
  });
}

import 'dart:convert';

import 'package:flutter_application_1/datos/cliente_api.dart';
import 'package:flutter_application_1/datos/modelos.dart';
import 'package:flutter_application_1/estado/visitas.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

void main() {
  group('Modelos', () {
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

  group('Visita de mantenimiento', () {
    VisitaBorrador visitaCompleta() {
      final visita = VisitaBorrador(equipoId: 4, equipoCodigo: 'ASC-001', inicio: DateTime(2026, 10, 16, 8, 12));
      for (final zona in visita.zonas.values) {
        zona.estado = 'B';
      }
      visita
        ..diagnostico = 'Equipo operando normalmente.'
        ..firmaBase64 = 'iVBORw0KGgo=';
      return visita;
    }

    test('exige revisar todas las zonas, diagnóstico y firma', () {
      final visita = VisitaBorrador(equipoId: 4, equipoCodigo: 'ASC-001');
      expect(visita.listaParaEnviar, isFalse);
      expect(visita.pendientes, contains('Falta el diagnóstico'));
      expect(visita.pendientes, contains('Falta la firma del técnico'));
      expect(visitaCompleta().listaParaEnviar, isTrue);
    });

    test('un estado Regular o Malo exige observación (RF-037)', () {
      final visita = visitaCompleta();
      visita.zonas['Foso']!.estado = 'M';
      expect(visita.listaParaEnviar, isFalse);
      expect(visita.zonasConObservacionPendiente, 1);

      visita.zonas['Foso']!.observacion = 'Fuga leve en amortiguador izquierdo.';
      expect(visita.listaParaEnviar, isTrue);
      expect(visita.estadoGeneral, 'M');
    });

    test('el resumen para el historial incluye zonas, repuestos y duración', () {
      final visita = visitaCompleta()
        ..repuestos.add(Repuesto('Rodamiento', 2))
        ..actividades.add('Lubricación del equipo');
      visita.zonas['Cabina']!
        ..estado = 'R'
        ..observacion = 'Dos paneles LED parpadean.';

      final resumen = visita.resumen(fin: DateTime(2026, 10, 16, 10, 21));
      expect(resumen, contains('duración 2 h 9 min'));
      expect(resumen, contains('Resultado general: Regular.'));
      expect(resumen, contains('- Cabina: Regular — Dos paneles LED parpadean.'));
      expect(resumen, contains('Rodamiento (2)'));
      expect(resumen.length, lessThanOrEqualTo(2000));
    });

    test('se guarda y se recupera igual (modo sin conexión)', () {
      final visita = visitaCompleta()..repuestos.add(Repuesto('Contacto', 1));
      final recuperada = VisitaBorrador.desdeJson(jsonDecode(jsonEncode(visita.aJson())) as Map<String, dynamic>);
      expect(recuperada.equipoCodigo, 'ASC-001');
      expect(recuperada.zonasRevisadas, 6);
      expect(recuperada.repuestos.single.nombre, 'Contacto');
      expect(recuperada.inicio, visita.inicio);
      expect(recuperada.listaParaEnviar, isTrue);
    });
  });
}

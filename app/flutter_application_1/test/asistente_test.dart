import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_application_1/datos/cliente_api.dart';
import 'package:flutter_application_1/datos/repositorio.dart';
import 'package:flutter_application_1/estado/preferencias.dart';
import 'package:flutter_application_1/estado/sesion.dart';
import 'package:flutter_application_1/main.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Asistente de SIGMA en la app y pestaña Visitas del técnico.
const _tecnico = {
  'id': 7,
  'nombre': 'Andrés Rojas',
  'correo': 'tecnicosigma@gmail.com',
  'rol': {
    'nombre': 'tecnico',
    'permisos': ['ordenes.ver', 'equipos.ver', 'equipos.historial.crear', 'inventario.ver'],
  },
};

http.Response _json(Object cuerpo, [int estado = 200]) =>
    http.Response(jsonEncode(cuerpo), estado, headers: {'content-type': 'application/json; charset=utf-8'});

MockClient _api(List<Map<String, dynamic>> llamadas) => MockClient((solicitud) async {
  final ruta = solicitud.url.path.replaceFirst('/api/v1', '');
  final cuerpo = solicitud.headers['content-type']?.contains('json') == true && solicitud.body.isNotEmpty
      ? jsonDecode(solicitud.body) as Map<String, dynamic>
      : <String, dynamic>{};
  llamadas.add({'ruta': ruta, 'metodo': solicitud.method, ...cuerpo});
  return switch (ruta) {
    '/perfil' => _json({'datos': _tecnico}),
    '/asistente' => _json({
      'datos': {
        'texto': 'Tiene 2 órdenes activas: OT-2026-00010 (en proceso) y OT-2026-00011 (asignada).',
        'acciones': [
          {'tipo': 'enlace', 'texto': 'Abrir OT-2026-00010', 'ruta': '/ordenes/10'},
          {'tipo': 'enlace', 'texto': 'Ver en el panel', 'ruta': '/calendario'},
        ],
        'sugerencias': ['Próximos mantenimientos'],
        'origen': 'local',
      },
    }),
    '/visitas' => _json({
      'datos': [
        {
          'id': 5,
          'numero': '12901',
          'tipo_servicio': 'preventivo',
          'tipo_nombre': 'Preventivo',
          'salida_en': '2026-11-20T10:30:00-05:00',
          'tecnico': 'Andrés Rojas',
          'conteo': {'B': 43, 'R': 0, 'M': 1, 'NA': 1},
          'ruta_pdf': '/visitas/5/pdf',
          'equipo': {'id': 3, 'codigo': 'ASC-201', 'edificio': 'Torre 1'},
        },
      ],
    }),
    _ => _json({
      'datos': [],
      'metadatos': {'pagina_actual': 1, 'ultima_pagina': 1, 'total': 0},
    }),
  };
});

Future<void> _asentar(WidgetTester tester) async {
  for (var i = 0; i < 20; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<Sesion> _sesionTecnico(List<Map<String, dynamic>> llamadas) async {
  final almacen = AlmacenTokenMemoria();
  await almacen.guardar('token-tecnico');
  final sesion = Sesion(
    repositorio: Repositorio(ClienteApi(http: _api(llamadas), urlBase: 'http://api.test/api/v1')),
    almacen: almacen,
    intervaloRevision: const Duration(hours: 1),
  );
  await sesion.iniciar();
  return sesion;
}

void main() {
  setUpAll(() => initializeDateFormatting('es'));
  setUp(() => SharedPreferences.setMockInitialValues({}));

  testWidgets('el técnico consulta al asistente desde cualquier pestaña', (tester) async {
    final llamadas = <Map<String, dynamic>>[];
    final sesion = await _sesionTecnico(llamadas);
    await tester.pumpWidget(SigmaApp(sesion: sesion, preferencias: Preferencias()));
    await _asentar(tester);

    await tester.tap(find.byTooltip('Asistente SIGMA'));
    await _asentar(tester);
    expect(find.textContaining('Hola, Andrés'), findsOneWidget);
    expect(find.textContaining('qué repuestos hay en el inventario'), findsOneWidget);
    expect(find.byTooltip('Adjuntar una foto del ascensor'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'Mis órdenes activas');
    await tester.tap(find.byTooltip('Enviar'));
    await _asentar(tester);

    final consulta = llamadas.lastWhere((l) => l['ruta'] == '/asistente');
    expect(consulta['mensaje'], 'Mis órdenes activas');
    expect(consulta['sesion'], startsWith('app-'));
    expect(find.textContaining('Tiene 2 órdenes activas'), findsOneWidget);
    // Solo se ofrecen los enlaces que la app puede abrir (órdenes y ascensores).
    expect(find.text('Abrir OT-2026-00010'), findsOneWidget);
    expect(find.text('Ver en el panel'), findsNothing);
    expect(find.text('Próximos mantenimientos'), findsOneWidget);

    await sesion.cerrarSesion();
    await _asentar(tester);
  });

  testWidgets('la pestaña Visitas muestra las planillas enviadas y las pendientes', (tester) async {
    final llamadas = <Map<String, dynamic>>[];
    final sesion = await _sesionTecnico(llamadas);
    await tester.pumpWidget(SigmaApp(sesion: sesion, preferencias: Preferencias()));
    await _asentar(tester);

    await tester.tap(find.text('Visitas').last);
    await _asentar(tester);

    expect(find.text('Por enviar'), findsOneWidget);
    expect(find.text('No hay planillas pendientes en este celular.'), findsOneWidget);
    expect(find.text('Planilla 12901 · ASC-201'), findsOneWidget);
    expect(find.textContaining('B 43 · R 0 · M 1'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsNothing);

    await sesion.cerrarSesion();
    await _asentar(tester);
  });

  testWidgets('sin operador de SMS la recuperación solo ofrece el correo', (tester) async {
    final sesion = Sesion(
      repositorio: Repositorio(
        ClienteApi(
          http: MockClient((solicitud) async => solicitud.url.path.endsWith('/publico/acceso')
              ? _json({'datos': {'sms_disponible': false}})
              : _json({'mensaje': 'No autenticado.'}, 401)),
          urlBase: 'http://api.test/api/v1',
        ),
      ),
      almacen: AlmacenTokenMemoria(),
      intervaloRevision: const Duration(hours: 1),
    );
    await sesion.iniciar();
    await tester.pumpWidget(SigmaApp(sesion: sesion, preferencias: Preferencias()));
    await _asentar(tester);

    await tester.ensureVisible(find.text('¿Olvidó su contraseña?'));
    await tester.tap(find.text('¿Olvidó su contraseña?'));
    await _asentar(tester);

    expect(find.text('Por celular'), findsNothing);
    expect(find.textContaining('Le enviaremos un enlace'), findsOneWidget);
  });
}

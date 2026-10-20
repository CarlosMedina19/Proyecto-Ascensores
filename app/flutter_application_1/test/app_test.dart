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

Map<String, dynamic> _usuario(String rol, List<String> permisos) => {
  'id': 1,
  'nombre': 'Andrés Rojas',
  'correo': 'andres@sigma.test',
  'rol': {'nombre': rol, 'permisos': permisos},
};

/// API simulada: responde según la ruta solicitada.
MockClient _apiSimulada({required Map<String, dynamic> usuario, int estadoLogin = 200, String? mensajeLogin}) {
  return MockClient((solicitud) async {
    final ruta = solicitud.url.path.replaceFirst('/api/v1', '');
    final respuestas = <String, (int, Object)>{
      '/iniciar-sesion': estadoLogin == 200
          ? (200, {'usuario': usuario, 'token_acceso': 'token-prueba', 'minutos_inactividad': 10})
          : (estadoLogin, {'mensaje': mensajeLogin}),
      '/perfil': (200, {'datos': usuario}),
      '/equipos': (
        200,
        {
          'datos': [],
          'metadatos': {'pagina_actual': 1, 'ultima_pagina': 1, 'total': 0},
        },
      ),
      '/edificios': (
        200,
        {
          'datos': [],
          'metadatos': {'pagina_actual': 1, 'ultima_pagina': 1, 'total': 0},
        },
      ),
      '/cerrar-sesion': (200, {'mensaje': 'Sesión cerrada correctamente.'}),
    };
    final (estado, cuerpo) = respuestas[ruta] ?? (404, {'mensaje': 'El recurso solicitado no existe.'});
    return http.Response(jsonEncode(cuerpo), estado, headers: {'content-type': 'application/json; charset=utf-8'});
  });
}

Sesion _sesion(MockClient http) => Sesion(
  repositorio: Repositorio(ClienteApi(http: http, urlBase: 'http://api.test/api/v1')),
  almacen: AlmacenTokenMemoria(),
  intervaloRevision: const Duration(hours: 1),
);

Future<void> _montar(WidgetTester tester, Sesion sesion) async {
  await sesion.iniciar();
  await tester.pumpWidget(SigmaApp(sesion: sesion, preferencias: Preferencias()));
  await _asentar(tester);
}

/// Avanza el tiempo sin esperar a que terminen las animaciones continuas (el punto "en vivo" late siempre).
Future<void> _asentar(WidgetTester tester) async {
  for (var i = 0; i < 20; i++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<void> _ingresar(WidgetTester tester) async {
  await tester.enterText(find.byKey(const Key('campo-correo')), 'andres@sigma.test');
  await tester.enterText(find.byKey(const Key('campo-password')), 'Clave2026');
  await tester.ensureVisible(find.byKey(const Key('boton-ingresar')));
  await tester.tap(find.byKey(const Key('boton-ingresar')));
  await _asentar(tester);
}

void main() {
  setUpAll(() => initializeDateFormatting('es'));
  setUp(() => SharedPreferences.setMockInitialValues({}));

  testWidgets('valida el formulario antes de llamar a la API', (tester) async {
    await _montar(tester, _sesion(_apiSimulada(usuario: _usuario('tecnico', []))));

    await tester.ensureVisible(find.byKey(const Key('boton-ingresar')));
    await tester.ensureVisible(find.byKey(const Key('boton-ingresar')));
  await tester.tap(find.byKey(const Key('boton-ingresar')));
    await tester.pump();

    expect(find.text('Escriba su correo'), findsOneWidget);
    expect(find.text('Escriba su contraseña'), findsOneWidget);
  });

  testWidgets('muestra el mensaje de la API si la cuenta está bloqueada', (tester) async {
    final sesion = _sesion(
      _apiSimulada(
        usuario: _usuario('tecnico', []),
        estadoLogin: 423,
        mensajeLogin: 'La cuenta está bloqueada por intentos fallidos. Intente de nuevo en 15 minuto(s).',
      ),
    );
    await _montar(tester, sesion);
    await _ingresar(tester);

    expect(find.textContaining('bloqueada por intentos fallidos'), findsOneWidget);
    expect(sesion.autenticado, isFalse);
  });

  testWidgets('el técnico ve sus pestañas y puede cerrar sesión', (tester) async {
    final sesion = _sesion(
      _apiSimulada(usuario: _usuario('tecnico', ['equipos.ver', 'edificios.ver', 'equipos.historial.ver', 'equipos.historial.crear'])),
    );
    await _montar(tester, sesion);
    await _ingresar(tester);

    expect(sesion.autenticado, isTrue);
    expect(find.text('Ascensores'), findsWidgets);
    expect(find.text('Visitas'), findsOneWidget);

    await tester.tap(find.text('Perfil'));
    await _asentar(tester);
    await tester.scrollUntilVisible(find.text('Cerrar sesión'), 200);
    await tester.tap(find.text('Cerrar sesión'));
    await _asentar(tester);

    expect(sesion.autenticado, isFalse);
    expect(find.byKey(const Key('boton-ingresar')), findsOneWidget);
  });

  testWidgets('el cliente no ve la pestaña de visitas del técnico', (tester) async {
    final sesion = _sesion(
      _apiSimulada(
        usuario: {
          ..._usuario('cliente', ['equipos.ver', 'edificios.ver', 'equipos.historial.ver']),
          'cliente': {'id': 3, 'nombre': 'Conjunto Mirador'},
        },
      ),
    );
    await _montar(tester, sesion);
    await _ingresar(tester);

    expect(find.text('Mis ascensores'), findsWidgets);
    expect(find.text('Visitas'), findsNothing);
    expect(find.textContaining(RegExp('portal del cliente · conjunto mirador', caseSensitive: false)), findsOneWidget);

    // Cierra la sesión para detener el reloj de inactividad antes de terminar la prueba.
    await sesion.cerrarSesion();
    await _asentar(tester);
  });

  test('la sesión avisa en el último minuto y se cierra a los 10 minutos sin actividad', () async {
    final sesion = _sesion(_apiSimulada(usuario: _usuario('tecnico', [])));
    await sesion.iniciarSesion('andres@sigma.test', 'Clave2026');
    final inicio = DateTime.now();

    sesion.revisarInactividad(inicio.add(const Duration(minutes: 8)));
    expect(sesion.segundosParaCierre, isNull);

    sesion.revisarInactividad(inicio.add(const Duration(minutes: 9, seconds: 30)));
    expect(sesion.segundosParaCierre, inInclusiveRange(1, 60));

    sesion.revisarInactividad(inicio.add(const Duration(minutes: 10, seconds: 1)));
    await Future<void>.delayed(Duration.zero);
    expect(sesion.autenticado, isFalse);
    expect(sesion.motivoCierre, 'Su sesión se cerró por inactividad.');
  });
}

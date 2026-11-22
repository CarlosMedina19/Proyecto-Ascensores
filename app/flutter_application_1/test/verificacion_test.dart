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

/// Verificación en dos pasos (código al correo) y recuperación de la contraseña con el celular (SMS).
const _usuario = {
  'id': 1,
  'nombre': 'Admin SIGMA',
  'correo': 'sigma3172409@gmail.com',
  'rol': {'nombre': 'administrador', 'permisos': <String>[]},
};

http.Response _json(Object cuerpo, [int estado = 200]) =>
    http.Response(jsonEncode(cuerpo), estado, headers: {'content-type': 'application/json; charset=utf-8'});

MockClient _api(List<Map<String, dynamic>> llamadas) => MockClient((solicitud) async {
  final ruta = solicitud.url.path.replaceFirst('/api/v1', '');
  final cuerpo = solicitud.body.isEmpty ? <String, dynamic>{} : jsonDecode(solicitud.body) as Map<String, dynamic>;
  llamadas.add({'ruta': ruta, ...cuerpo});
  return switch (ruta) {
    '/iniciar-sesion' => _json({
      'requiere_verificacion': true,
      'desafio': 'desafio-1',
      'canal': 'correo',
      'destino': 'si***09@gmail.com',
      'minutos': 10,
      'mensaje': 'Enviamos un código de 6 dígitos a si***09@gmail.com.',
    }),
    '/iniciar-sesion/verificar' =>
      cuerpo['codigo'] == '123456'
          ? _json({'usuario': _usuario, 'token_acceso': 'token-2fa', 'minutos_inactividad': 10})
          : _json({
              'mensaje': 'Datos inválidos.',
              'errores': {
                'codigo': ['El código no es correcto. Le quedan 4 intento(s).'],
              },
            }, 422),
    '/recuperar-contrasena/telefono' => _json({
      'desafio': 'desafio-sms',
      'canal': 'sms',
      'destino': '300 *** **67',
      'minutos': 10,
      'codigo_demo': '654321',
      'mensaje': 'Si el número 300 *** **67 está registrado, recibirá un SMS con un código de 6 dígitos.',
    }),
    '/restablecer-contrasena/telefono' => _json({'mensaje': 'Contraseña restablecida. Ya puede iniciar sesión.'}),
    '/perfil' => _json({'datos': _usuario}),
    '/cerrar-sesion' => _json({'mensaje': 'Sesión cerrada correctamente.'}),
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

void main() {
  setUpAll(() => initializeDateFormatting('es'));
  setUp(() => SharedPreferences.setMockInitialValues({}));

  testWidgets('con verificación en dos pasos pide el código del correo antes de entrar', (tester) async {
    final llamadas = <Map<String, dynamic>>[];
    final sesion = Sesion(
      repositorio: Repositorio(ClienteApi(http: _api(llamadas), urlBase: 'http://api.test/api/v1')),
      almacen: AlmacenTokenMemoria(),
      intervaloRevision: const Duration(hours: 1),
    );
    await sesion.iniciar();
    await tester.pumpWidget(SigmaApp(sesion: sesion, preferencias: Preferencias()));
    await _asentar(tester);

    await tester.enterText(find.byKey(const Key('campo-correo')), 'sigma3172409@gmail.com');
    await tester.enterText(find.byKey(const Key('campo-password')), 'AdminSigma123');
    await tester.ensureVisible(find.byKey(const Key('boton-ingresar')));
    await tester.tap(find.byKey(const Key('boton-ingresar')));
    await _asentar(tester);

    expect(find.text('Verificación en dos pasos'), findsOneWidget);
    expect(sesion.autenticado, isFalse);

    await tester.enterText(find.byKey(const Key('campo-codigo')), '000000');
    await tester.ensureVisible(find.byKey(const Key('boton-verificar')));
    await tester.tap(find.byKey(const Key('boton-verificar')));
    await _asentar(tester);
    expect(find.text('El código no es correcto. Le quedan 4 intento(s).'), findsOneWidget);

    await tester.enterText(find.byKey(const Key('campo-codigo')), '123456');
    await tester.ensureVisible(find.byKey(const Key('boton-verificar')));
    await tester.tap(find.byKey(const Key('boton-verificar')));
    await _asentar(tester);

    expect(sesion.autenticado, isTrue);
    expect(llamadas.lastWhere((l) => l['ruta'] == '/iniciar-sesion/verificar')['desafio'], 'desafio-1');

    await sesion.cerrarSesion();
    await _asentar(tester);
  });

  testWidgets('recupera la contraseña con el código del SMS', (tester) async {
    final llamadas = <Map<String, dynamic>>[];
    final sesion = Sesion(
      repositorio: Repositorio(ClienteApi(http: _api(llamadas), urlBase: 'http://api.test/api/v1')),
      almacen: AlmacenTokenMemoria(),
      intervaloRevision: const Duration(hours: 1),
    );
    await sesion.iniciar();
    await tester.pumpWidget(SigmaApp(sesion: sesion, preferencias: Preferencias()));
    await _asentar(tester);

    await tester.ensureVisible(find.text('¿Olvidó su contraseña?'));
    await tester.tap(find.text('¿Olvidó su contraseña?'));
    await _asentar(tester);
    await tester.tap(find.text('Por celular'));
    await _asentar(tester);
    await tester.enterText(find.byKey(const Key('campo-telefono')), '300 123 4567');
    await tester.tap(find.byKey(const Key('boton-enviar-sms')));
    await _asentar(tester);

    expect(find.text('654321'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('campo-codigo')), '654321');
    await tester.enterText(find.byKey(const Key('campo-nueva')), 'NuevaClave2026');
    await tester.enterText(find.byKey(const Key('campo-confirmacion')), 'NuevaClave2026');
    await tester.ensureVisible(find.byKey(const Key('boton-restablecer')));
    await tester.pump(const Duration(milliseconds: 300));
    await tester.tap(find.byKey(const Key('boton-restablecer')));
    await _asentar(tester);

    expect(llamadas.map((l) => l['ruta']), contains('/restablecer-contrasena/telefono'));
    expect(find.text('Contraseña restablecida. Ya puede iniciar sesión.'), findsOneWidget);
    expect(llamadas.firstWhere((l) => l['ruta'] == '/restablecer-contrasena/telefono'), {
      'ruta': '/restablecer-contrasena/telefono',
      'desafio': 'desafio-sms',
      'codigo': '654321',
      'password': 'NuevaClave2026',
      'password_confirmation': 'NuevaClave2026',
    });
  });
}

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_application_1/datos/cliente_api.dart';
import 'package:flutter_application_1/datos/modelos.dart';
import 'package:flutter_application_1/datos/repositorio.dart';
import 'package:flutter_application_1/estado/sesion.dart';
import 'package:flutter_application_1/pantallas/planilla.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';

Map<String, dynamic> ordenPreventiva({String estado = 'en_proceso'}) => {
  'id': 4,
  'numero': 'OT-2026-00004',
  'servicio': 'preventivo',
  'estado': estado,
  'prioridad': 'media',
  'fecha': '2026-10-18',
  'hora': '08:00',
  'tecnicos': [
    {'id': 1, 'nombre': 'Andrés Rojas'},
  ],
  'permisos': {'ejecutar': true},
  'lleva_planilla': true,
  'planilla': {'registrados': 47, 'total_items': 48},
  'firmas': [
    {'tipo': 'tecnico', 'nombre': 'Andrés Rojas'},
  ],
  'informes': [],
};

final _items = [
  {'id': 1, 'seccion': 'cuarto_maquinas', 'codigo': 'CM-01', 'nombre': 'Freno de máquina', 'critico': true},
  {'id': 2, 'seccion': 'cuarto_maquinas', 'codigo': 'CM-02', 'nombre': 'Aceite del motor', 'critico': false},
  {'id': 3, 'seccion': 'cabina', 'codigo': 'CA-01', 'nombre': 'Iluminación', 'critico': false},
];

void main() {
  setUpAll(() => initializeDateFormatting('es'));

  group('Planilla (RF-030 a RF-039)', () {
    test('la orden preventiva expone avance de la planilla y firmas', () {
      final orden = OrdenTrabajo.desdeJson(ordenPreventiva());

      expect(orden.llevaPlanilla, isTrue);
      expect(orden.planillaRegistrados, 47);
      expect(orden.planillaTotal, 48);
      expect(orden.planillaCompleta, isFalse);
      expect(orden.firmas, {'tecnico': 'Andrés Rojas'});
      expect(orden.informes, 0);
    });

    test('R o M exigen observación y la copia no altera el original', () {
      final resultado = ResultadoItem(resultado: 'INS', estado: 'M');
      expect(resultado.completo, isTrue);
      expect(resultado.faltaObservacion, isTrue);

      final copia = resultado.copia()..observacion = 'Zapata gastada';
      expect(copia.faltaObservacion, isFalse);
      expect(resultado.observacion, isEmpty);
      expect(ResultadoItem(resultado: 'NA', estado: 'B').faltaObservacion, isFalse);
    });

    test('lee la planilla con su correctivo abierto y los faltantes', () {
      final planilla = Planilla.desdeRespuesta({
        'datos': {
          'recomendaciones': 'Cambiar zapatas',
          'resultados': [
            {
              'id': 10,
              'item_id': 1,
              'resultado': 'INS',
              'estado': 'M',
              'observacion': 'Zapata gastada',
              'correctivo_abierto': {'id': 5, 'numero': 'OT-2026-00005'},
            },
          ],
          'fotos': [
            {'id': 1},
          ],
        },
        'faltantes': 47,
      });

      expect(planilla.resultados[1]!.correctivoNumero, 'OT-2026-00005');
      expect(planilla.recomendaciones, 'Cambiar zapatas');
      expect(planilla.fotos, 1);
      expect(planilla.faltantes, 47);
    });
  });

  testWidgets('el técnico registra un ítem en M con observación y lo guarda', (tester) async {
    Map<String, dynamic>? enviado;
    final api = MockClient((solicitud) async {
      final ruta = solicitud.url.path.replaceFirst('/api/v1', '');
      Object cuerpo = {'mensaje': 'El recurso solicitado no existe.'};
      if (ruta == '/checklist/items') cuerpo = {'datos': _items};
      if (ruta == '/ordenes/4/planilla') {
        if (solicitud.method == 'PUT') enviado = jsonDecode(solicitud.body) as Map<String, dynamic>;
        cuerpo = {
          'datos': {'resultados': [], 'fotos': [], 'recomendaciones': null},
          'faltantes': 2,
        };
      }
      return http.Response(jsonEncode(cuerpo), 200, headers: {'content-type': 'application/json; charset=utf-8'});
    });
    final sesion = Sesion(repositorio: Repositorio(ClienteApi(http: api, urlBase: 'http://api.test/api/v1')));

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: sesion,
        child: MaterialApp(
          locale: const Locale('es'),
          supportedLocales: const [Locale('es')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          home: PantallaPlanilla(orden: OrdenTrabajo.desdeJson(ordenPreventiva())),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Planilla · 0/3'), findsOneWidget);
    expect(find.text('Freno de máquina'), findsOneWidget);
    expect(find.text('Todo guardado'), findsOneWidget);

    await tester.tap(find.widgetWithText(ChoiceChip, 'INS').first);
    await tester.tap(find.widgetWithText(ChoiceChip, 'M').first);
    await tester.pump();

    // Sin observación no se envía.
    expect(find.text('R o M exigen observación'), findsOneWidget);
    await tester.tap(find.text('Guardar (1)'));
    await tester.pump();
    expect(enviado, isNull);
    expect(find.textContaining('sin observación'), findsOneWidget);
    ScaffoldMessenger.of(tester.element(find.byType(TabBarView))).removeCurrentSnackBar();

    await tester.enterText(find.byType(TextFormField).first, 'Zapata gastada');
    await tester.pump();
    await tester.tap(find.text('Guardar (1)'));
    await tester.pumpAndSettle();

    expect(enviado!['resultados'], [
      {'item_id': 1, 'resultado': 'INS', 'estado': 'M', 'observacion': 'Zapata gastada'},
    ]);
    expect(find.text('Planilla guardada. Faltan 2 ítems.'), findsOneWidget);
  });
}

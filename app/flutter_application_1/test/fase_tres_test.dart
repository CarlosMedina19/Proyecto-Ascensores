import 'package:flutter/material.dart';
import 'package:flutter_application_1/datos/modelos.dart';
import 'package:flutter_application_1/pantallas/ordenes.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

Map<String, dynamic> ordenJson({String estado = 'en_proceso', bool ejecutar = true}) => {
  'id': 9,
  'numero': 'OT-2026-00009',
  'servicio': 'correctivo',
  'estado': estado,
  'prioridad': 'critica',
  'fecha': '2026-10-18',
  'hora': '07:30',
  'fin_estimado': '09:30',
  'duracion_estimada': 120,
  'equipo_id': 27,
  'equipo': {'id': 27, 'codigo': 'ASC-002'},
  'edificio_id': 3,
  'edificio': {'id': 3, 'nombre': 'Torre A', 'direccion': 'Cra 15 # 100-20', 'ciudad': 'Bogotá'},
  'cliente': {'id': 1, 'nombre': 'Mirador'},
  'inicio_en': '2026-10-18T07:41:00-05:00',
  'tecnicos': [
    {'id': 1, 'nombre': 'Andrés Rojas'},
  ],
  'permisos': {'ejecutar': ejecutar},
  'trazabilidad': [
    {
      'id': 3,
      'accion': 'estado_cambiado',
      'campo': 'estado',
      'valor_anterior': 'en_camino',
      'valor_nuevo': 'en_proceso',
      'usuario': {'nombre': 'Andrés Rojas'},
      'created_at': '2026-10-18T07:41:00-05:00',
    },
    {'id': 2, 'accion': 'inicio_registrado', 'campo': 'inicio_en', 'valor_nuevo': '2026-10-18T07:41:00-05:00', 'created_at': '2026-10-18T07:41:00-05:00'},
  ],
};

void main() {
  setUpAll(() => initializeDateFormatting('es'));

  group('Orden de trabajo', () {
    test('lee la orden, su dirección, técnicos y trazabilidad legible', () {
      final orden = OrdenTrabajo.desdeJson(ordenJson());

      expect(orden.numero, 'OT-2026-00009');
      expect(orden.nombreServicio, 'Correctivo');
      expect(orden.nombrePrioridad, 'Crítica');
      expect(orden.nombreEstado, 'En proceso');
      expect(orden.direccion, 'Cra 15 # 100-20, Bogotá');
      expect(orden.tecnicos, ['Andrés Rojas']);
      expect(orden.puedeEjecutar, isTrue);

      expect(orden.trazabilidad.first.evento, 'Cambio de estado');
      expect(orden.trazabilidad.first.descripcion, 'En camino → En proceso');
      // La hora de inicio no se repite como texto en la línea de tiempo.
      expect(orden.trazabilidad.last.evento, 'Inicio registrado');
      expect(orden.trazabilidad.last.descripcion, isEmpty);
    });

    test('ofrece los siguientes estados del flujo (RF-026)', () {
      expect(OrdenTrabajo.desdeJson(ordenJson(estado: 'asignada')).siguientesEstados, ['en_camino']);
      expect(OrdenTrabajo.desdeJson(ordenJson(estado: 'en_camino')).siguientesEstados, ['en_proceso']);
      expect(OrdenTrabajo.desdeJson(ordenJson()).siguientesEstados, ['pendiente', 'finalizada']);
      expect(OrdenTrabajo.desdeJson(ordenJson(estado: 'pendiente')).siguientesEstados, ['en_proceso']);
      expect(OrdenTrabajo.desdeJson(ordenJson(estado: 'finalizada')).siguientesEstados, isEmpty);
      expect(OrdenTrabajo.desdeJson(ordenJson(estado: 'finalizada')).esFinal, isTrue);
    });

    test('expresa la duración en horas y minutos', () {
      expect(duracionTexto(45), '45 min');
      expect(duracionTexto(120), '2 h');
      expect(duracionTexto(106), '1 h 46 min');
      expect(duracionTexto(null), '—');
    });
  });

  testWidgets('la fila de la orden muestra ascensor, horario, estado y prioridad crítica', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('es'),
        supportedLocales: const [Locale('es')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: Scaffold(body: FilaOrden(orden: OrdenTrabajo.desdeJson(ordenJson()))),
      ),
    );

    expect(find.text('ASC-002 · Torre A'), findsOneWidget);
    expect(find.textContaining('07:30–09:30'), findsOneWidget);
    expect(find.text('OT-2026-00009 · Correctivo'), findsOneWidget);
    expect(find.text('En proceso'), findsOneWidget);
    expect(find.text('Crítica'), findsOneWidget);
  });
}

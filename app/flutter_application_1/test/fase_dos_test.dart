import 'package:flutter/material.dart';
import 'package:flutter_application_1/datos/modelos.dart';
import 'package:flutter_application_1/pantallas/equipos.dart';
import 'package:flutter_application_1/widgets/comunes.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

void main() {
  setUpAll(() => initializeDateFormatting('es'));

  group('Modelos de la fase 2', () {
    test('el equipo lee el plan activo con su próxima visita', () {
      final equipo = Equipo.desdeJson({
        'id': 27,
        'codigo': 'ASC-001',
        'estado': 'activo',
        'asignacion_activa': {
          'id': 1,
          'estado': 'activa',
          'plan': {'id': 1, 'nombre': 'Plan Integral'},
          'periodicidad': {'id': 3, 'meses': 3, 'nombre': 'Trimestral', 'activo': true},
          'contrato': {'id': 1, 'numero': 'CT-2026-0001'},
          'precio': '620000.00',
          'precio_con_iva': '737800.00',
          'aplica_iva': true,
          'porcentaje_iva': '19.00',
          'fecha_inicio': '2026-08-01',
          'fecha_fin': '2027-07-31',
          'proximo_mantenimiento': '2026-11-03',
          'hora_preferida': '10:30',
        },
      });

      final plan = equipo.asignacionActiva!;
      expect(plan.planNombre, 'Plan Integral');
      expect(plan.periodicidad, 'Trimestral (3 meses)');
      expect(nombrePeriodicidad(5), 'Cada 5 meses');
      expect(nombrePeriodicidad(6), 'Semestral (6 meses)');
      expect(plan.precio, 620000);
      expect(plan.precioConIva, 737800);
      expect(plan.contratoNumero, 'CT-2026-0001');
      expect(plan.proximoMantenimiento, DateTime(2026, 11, 3));
      expect(plan.horaPreferida, '10:30');
    });

    test('el equipo sin plan no falla', () {
      expect(Equipo.desdeJson({'id': 1, 'codigo': 'ASC-009'}).asignacionActiva, isNull);
    });

    test('un mantenimiento pendiente con fecha pasada se muestra atrasado', () {
      final hoy = DateTime(2026, 10, 17);
      final visita = MantenimientoProgramado.desdeJson({
        'id': 5,
        'estado': 'programado',
        'fecha_programada': '2026-10-14',
        'equipo_id': 27,
        'equipo': {
          'codigo': 'ASC-001',
          'edificio': {'nombre': 'Torre A'},
        },
        'plan': {'nombre': 'Plan Básico'},
      });

      expect(visita.equipoCodigo, 'ASC-001');
      expect(visita.edificioNombre, 'Torre A');
      expect(visita.diasRestantes(hoy), -3);
      expect(estadoMantenimiento(visita, hoy).texto, 'Atrasado');
      expect(textoDias(visita.diasRestantes(hoy)), 'Atrasado 3 días');

      final ejecutado = MantenimientoProgramado(id: 6, estado: 'ejecutado', fechaProgramada: DateTime(2026, 10, 14));
      expect(estadoMantenimiento(ejecutado, hoy).texto, 'Ejecutado');
    });

    test('formatea pesos y días restantes', () {
      expect(dinero(1250000), contains('1.250.000'));
      expect(dinero(null), '—');
      expect(textoDias(0), 'Hoy');
      expect(textoDias(1), 'Mañana');
      expect(textoDias(12), 'En 12 días');
    });
  });

  testWidgets('la sección del plan muestra el plan, la periodicidad y las próximas visitas', (tester) async {
    final plan = AsignacionPlan(
      id: 1,
      estado: 'activa',
      planNombre: 'Plan Premium',
      meses: 1,
      precio: 950000,
      fechaInicio: DateTime(2026, 8, 1),
      fechaFin: DateTime(2027, 7, 31),
      proximoMantenimiento: DateTime.now().add(const Duration(days: 10)),
    );
    final visita = MantenimientoProgramado(id: 3, estado: 'programado', fechaProgramada: DateTime.now().add(const Duration(days: 10)));

    await tester.pumpWidget(
      MaterialApp(
        locale: const Locale('es'),
        supportedLocales: const [Locale('es')],
        localizationsDelegates: GlobalMaterialLocalizations.delegates,
        home: Scaffold(
          body: SingleChildScrollView(child: SeccionPlan(asignacion: plan, visitas: [visita])),
        ),
      ),
    );

    expect(find.text('Plan Premium'), findsOneWidget);
    expect(find.text('Mensual (1 mes)'), findsOneWidget);
    expect(find.text('En 10 días'), findsOneWidget);
    expect(find.text('PRÓXIMAS VISITAS'), findsOneWidget);
  });

  testWidgets('sin plan asignado se indica en la sección', (tester) async {
    await tester.pumpWidget(const MaterialApp(home: Scaffold(body: SeccionPlan(asignacion: null))));
    expect(find.textContaining('no tiene un plan'), findsOneWidget);
  });
}

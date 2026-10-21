import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../datos/modelos.dart';
import '../estado/sesion.dart';
import '../tema.dart';
import '../widgets/comunes.dart';
import 'equipos.dart';
import 'ordenes.dart';

/// Inicio según el rol: resumen general para el personal administrativo,
/// o los ascensores asignados/propios para técnicos y clientes.
class PantallaInicio extends StatefulWidget {
  const PantallaInicio({super.key});

  @override
  State<PantallaInicio> createState() => _PantallaInicioState();
}

class _PantallaInicioState extends State<PantallaInicio> {
  Future<Resumen?>? _resumen;
  Future<Pagina<Equipo>>? _equipos;
  Future<List<MantenimientoProgramado>>? _proximos;
  Future<List<OrdenTrabajo>>? _misOrdenes;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  void _cargar() {
    final sesion = context.read<Sesion>();
    setState(() {
      _resumen = sesion.tienePermiso('resumen.ver') ? sesion.repositorio.resumen() : Future.value(null);
      _equipos = sesion.tienePermiso('equipos.ver') ? sesion.repositorio.equipos() : null;
      // Fase 2: visitas pendientes de los próximos 30 días (incluye las atrasadas del último mes).
      final hoy = DateTime.now();
      final esTecnico = sesion.usuario?.esTecnico ?? false;
      _proximos = sesion.tienePermiso('mantenimientos.ver') && !esTecnico
          ? sesion.repositorio
                .mantenimientos(desde: hoy.subtract(const Duration(days: 30)), hasta: hoy.add(const Duration(days: 30)))
                .then((lista) => lista.where((m) => m.pendiente).toList())
          : null;
      // Fase 3 (RF-023): el técnico ve sus órdenes activas, de la más próxima a la más lejana.
      _misOrdenes = esTecnico && sesion.tienePermiso('ordenes.ver')
          ? sesion.repositorio.ordenes().then(
              (pagina) =>
                  pagina.elementos.toList()
                    ..sort((a, b) => '${a.fecha.toIso8601String()} ${a.hora}'.compareTo('${b.fecha.toIso8601String()} ${b.hora}')),
            )
          : null;
    });
  }

  /// "Lunes 19 de octubre de 2026"
  String _hoy() {
    final texto = DateFormat("EEEE d 'de' MMMM 'de' y", 'es').format(DateTime.now());
    return texto[0].toUpperCase() + texto.substring(1);
  }

  String _saludo() {
    final hora = DateTime.now().hour;
    if (hora < 12) return 'Buenos días';
    if (hora < 19) return 'Buenas tardes';
    return 'Buenas noches';
  }

  @override
  Widget build(BuildContext context) {
    final usuario = context.watch<Sesion>().usuario!;
    final esquema = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(titleSpacing: 16, title: const MarcaSigma(tamanoLogo: 30)),
      body: RefreshIndicator(
        onRefresh: () async => _cargar(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            EncabezadoSigma(
              etiqueta: usuario.esCliente ? 'Portal del cliente · ${usuario.clienteNombre ?? ''}' : usuario.nombreRol,
              titulo: Text.rich(
                TextSpan(
                  children: [
                    TextSpan(text: '${_saludo()}, '),
                    TextSpan(
                      text: usuario.primerNombre,
                      style: TextStyle(color: esquema.primary),
                    ),
                    const TextSpan(text: '.'),
                  ],
                ),
              ),
              descripcion: _hoy(),
            ),
            const SizedBox(height: 16),
            FutureBuilder<Resumen?>(
              future: _resumen,
              builder: (context, instantanea) {
                if (instantanea.connectionState != ConnectionState.done) return const SizedBox.shrink();
                final resumen = instantanea.data;
                if (resumen == null) return const SizedBox.shrink();
                return _PanelResumen(resumen: resumen);
              },
            ),
            if (_misOrdenes != null)
              FutureBuilder<List<OrdenTrabajo>>(
                future: _misOrdenes,
                builder: (context, instantanea) {
                  if (!instantanea.hasData) return const SizedBox.shrink();
                  final lista = instantanea.data!;
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text('Mis próximas órdenes', style: Theme.of(context).textTheme.titleLarge),
                        const SizedBox(height: 8),
                        if (lista.isEmpty)
                          const Card(
                            child: Padding(padding: EdgeInsets.all(16), child: Text('No tiene órdenes activas.')),
                          ),
                        for (final orden in lista.take(4)) FilaOrden(orden: orden, alVolver: _cargar),
                      ],
                    ),
                  );
                },
              ),
            if (_proximos != null)
              FutureBuilder<List<MantenimientoProgramado>>(
                future: _proximos,
                builder: (context, instantanea) {
                  if (!instantanea.hasData) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: ProximosMantenimientos(visitas: instantanea.data!),
                  );
                },
              ),
            if (_equipos != null)
              FutureBuilder<Pagina<Equipo>>(
                future: _equipos,
                builder: (context, instantanea) {
                  if (instantanea.hasError) return VistaError(mensaje: mensajeError(instantanea.error!), alReintentar: _cargar);
                  if (!instantanea.hasData) return const Cargando();
                  final lista = instantanea.data!.elementos;
                  final enServicio = lista.where((e) => e.estado == 'activo').length;
                  return Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      if (!context.read<Sesion>().tienePermiso('resumen.ver')) ...[
                        _TarjetaDestacada(
                          titulo: '$enServicio de ${instantanea.data!.total}',
                          subtitulo: usuario.esCliente ? 'de sus ascensores están en servicio' : 'ascensores en servicio',
                          progreso: lista.isEmpty ? 0 : enServicio / lista.length,
                        ),
                        const SizedBox(height: 16),
                      ],
                      Text(usuario.esCliente ? 'Mis ascensores' : 'Ascensores', style: Theme.of(context).textTheme.titleLarge),
                      const SizedBox(height: 8),
                      if (lista.isEmpty) const EstadoVacio(titulo: 'No hay ascensores asociados'),
                      for (final equipo in lista.take(6)) FilaEquipo(equipo: equipo),
                    ],
                  );
                },
              ),
            if (usuario.esCliente) ...[
              const SizedBox(height: 16),
              Card(
                child: ListTile(
                  leading: Icon(Icons.report_problem_outlined, color: esquema.primary),
                  title: const Text('Solicitar reparación o revisión'),
                  subtitle: const Text('Disponible en la fase 6 (portal del cliente). Mientras tanto, comuníquese con su coordinador.'),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _PanelResumen extends StatelessWidget {
  const _PanelResumen({required this.resumen});

  final Resumen resumen;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _TarjetaDestacada(
          titulo: '${resumen.equiposActivos} de ${resumen.equiposTotal}',
          subtitulo: 'ascensores en servicio · ${resumen.equiposMantenimiento} en mantenimiento',
          progreso: resumen.equiposTotal == 0 ? 0 : resumen.equiposActivos / resumen.equiposTotal,
        ),
        const SizedBox(height: 12),
        Row(
          children: [
            Expanded(
              child: _Indicador(valor: resumen.clientes, etiqueta: 'Clientes', icono: Icons.business_center_outlined),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _Indicador(valor: resumen.edificios, etiqueta: 'Edificios', icono: Icons.apartment_outlined),
            ),
          ],
        ),
        const SizedBox(height: 16),
        if (resumen.ultimosEventos.isNotEmpty)
          Seccion(
            titulo: 'Últimos eventos',
            hijos: [
              LineaTiempo(
                eventos: [for (final (evento, _, _) in resumen.ultimosEventos.take(5)) evento],
                etiquetaEquipo: (evento) => resumen.ultimosEventos.firstWhere((e) => e.$1.id == evento.id).$2,
              ),
            ],
          ),
        const SizedBox(height: 16),
      ],
    );
  }
}

/// Tarjeta morada destacada, como la de "próximo mantenimiento" del prototipo.
class _TarjetaDestacada extends StatelessWidget {
  const _TarjetaDestacada({required this.titulo, required this.subtitulo, required this.progreso});

  final String titulo;
  final String subtitulo;
  final double progreso;

  @override
  Widget build(BuildContext context) {
    return TarjetaDestacada(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const PuntoVivo(),
              const SizedBox(width: 8),
              Text('ESTADO DE LOS ASCENSORES', style: estiloEtiqueta(Colors.white.withValues(alpha: .82), tamano: 10.5)),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            titulo,
            style: const TextStyle(
              fontFamily: SigmaFuentes.titulos,
              color: Colors.white,
              fontSize: 40,
              fontWeight: FontWeight.w700,
              letterSpacing: -1.2,
              height: 1,
            ),
          ),
          const SizedBox(height: 4),
          Text(subtitulo, style: TextStyle(color: Colors.white.withValues(alpha: .92), fontSize: 13.5)),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(5),
            child: LinearProgressIndicator(value: progreso, minHeight: 5, backgroundColor: Colors.white24, color: SigmaColores.lima),
          ),
        ],
      ),
    );
  }
}

class _Indicador extends StatelessWidget {
  const _Indicador({required this.valor, required this.etiqueta, required this.icono});

  final int valor;
  final String etiqueta;
  final IconData icono;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 26,
                  height: 26,
                  decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, borderRadius: BorderRadius.circular(8)),
                  child: Icon(icono, size: 15, color: Theme.of(context).colorScheme.primary),
                ),
                const SizedBox(width: 8),
                Text(etiqueta, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant, fontSize: 13)),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              '$valor',
              style: const TextStyle(fontFamily: SigmaFuentes.titulos, fontSize: 30, fontWeight: FontWeight.w700, letterSpacing: -0.8),
            ),
          ],
        ),
      ),
    );
  }
}

/// RF-017: próximas visitas preventivas de los ascensores que el usuario puede consultar.
class ProximosMantenimientos extends StatelessWidget {
  const ProximosMantenimientos({super.key, required this.visitas});

  final List<MantenimientoProgramado> visitas;

  @override
  Widget build(BuildContext context) {
    return Seccion(
      titulo: 'Próximos mantenimientos',
      hijos: [
        if (visitas.isEmpty)
          const Text('No hay visitas pendientes en los próximos 30 días.')
        else
          for (final visita in visitas.take(5))
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: const Icon(Icons.event_note_outlined, color: SigmaColores.primario),
              title: Text('${visita.equipoCodigo ?? ''} · ${visita.edificioNombre ?? ''}'),
              subtitle: Text([fecha(visita.fechaProgramada), ?visita.hora, ?visita.planNombre].join(' · ')),
              trailing: Insignia(texto: textoDias(visita.diasRestantes()), color: estadoMantenimiento(visita).color),
              onTap: visita.equipoId == null
                  ? null
                  : () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaDetalleEquipo(equipoId: visita.equipoId!))),
            ),
      ],
    );
  }
}

import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/cliente_api.dart';
import '../datos/modelos.dart';
import '../estado/sesion.dart';
import '../tema.dart';
import '../widgets/comunes.dart';
import 'visita.dart';

/// RF-007: listado de ascensores con búsqueda, filtro por estado y paginación.
class PantallaEquipos extends StatefulWidget {
  const PantallaEquipos({super.key, this.edificioId, this.titulo});

  final int? edificioId;
  final String? titulo;

  @override
  State<PantallaEquipos> createState() => _PantallaEquiposState();
}

class _PantallaEquiposState extends State<PantallaEquipos> {
  final _busqueda = TextEditingController();
  Timer? _espera;
  String? _estado;
  final List<Equipo> _equipos = [];
  int _pagina = 1;
  bool _hayMas = false;
  bool _cargando = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _cargar(reiniciar: true);
  }

  @override
  void dispose() {
    _espera?.cancel();
    _busqueda.dispose();
    super.dispose();
  }

  Future<void> _cargar({bool reiniciar = false}) async {
    setState(() {
      _cargando = true;
      _error = null;
      if (reiniciar) {
        _pagina = 1;
        _equipos.clear();
      }
    });
    try {
      final pagina = await context.read<Sesion>().repositorio.equipos(
        busqueda: _busqueda.text.trim(),
        estado: _estado,
        edificioId: widget.edificioId,
        pagina: _pagina,
      );
      if (!mounted) return;
      setState(() {
        _equipos.addAll(pagina.elementos);
        _hayMas = pagina.hayMas;
        _pagina = pagina.paginaActual + 1;
      });
    } catch (e) {
      if (mounted) setState(() => _error = mensajeError(e));
    } finally {
      if (mounted) setState(() => _cargando = false);
    }
  }

  void _alEscribir(String _) {
    _espera?.cancel();
    _espera = Timer(const Duration(milliseconds: 400), () => _cargar(reiniciar: true));
  }

  @override
  Widget build(BuildContext context) {
    final cliente = context.select<Sesion, bool>((s) => s.usuario?.esCliente ?? false);
    return Scaffold(
      appBar: AppBar(title: Text(widget.titulo ?? (cliente ? 'Mis ascensores' : 'Ascensores'))),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: TextField(
              controller: _busqueda,
              onChanged: _alEscribir,
              decoration: const InputDecoration(
                hintText: 'Código, marca, serie o ubicación',
                prefixIcon: Icon(Icons.search),
                isDense: true,
              ),
            ),
          ),
          SizedBox(
            height: 40,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.symmetric(horizontal: 16),
              children: [
                for (final (valor, texto) in [
                  (null, 'Todos'),
                  ('activo', 'Activos'),
                  ('mantenimiento', 'En mantenimiento'),
                  ('inactivo', 'Inactivos'),
                ])
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(texto),
                      selected: _estado == valor,
                      onSelected: (_) {
                        setState(() => _estado = valor);
                        _cargar(reiniciar: true);
                      },
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(onRefresh: () => _cargar(reiniciar: true), child: _contenido()),
          ),
        ],
      ),
    );
  }

  Widget _contenido() {
    if (_error != null && _equipos.isEmpty) {
      return ListView(
        children: [VistaError(mensaje: _error!, alReintentar: () => _cargar(reiniciar: true))],
      );
    }
    if (_cargando && _equipos.isEmpty) return const Cargando();
    if (_equipos.isEmpty) {
      return ListView(
        children: const [EstadoVacio(titulo: 'No se encontraron ascensores', descripcion: 'Ajuste la búsqueda o el filtro.')],
      );
    }
    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      itemCount: _equipos.length + (_hayMas ? 1 : 0),
      itemBuilder: (context, i) {
        if (i == _equipos.length) {
          return Padding(
            padding: const EdgeInsets.all(8),
            child: OutlinedButton(onPressed: _cargando ? null : _cargar, child: Text(_cargando ? 'Cargando…' : 'Cargar más')),
          );
        }
        return FilaEquipo(equipo: _equipos[i], alVolver: () => _cargar(reiniciar: true));
      },
    );
  }
}

class FilaEquipo extends StatelessWidget {
  const FilaEquipo({super.key, required this.equipo, this.alVolver});

  final Equipo equipo;
  final VoidCallback? alVolver;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    final ascensor = equipo.ascensor;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Card(
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () async {
            await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaDetalleEquipo(equipoId: equipo.id)));
            alVolver?.call();
          },
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(color: esquema.primaryContainer, borderRadius: BorderRadius.circular(11)),
                  child: Icon(Icons.elevator_outlined, color: esquema.onPrimaryContainer),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(equipo.codigo, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
                      Text(
                        [equipo.edificioNombre, equipo.ubicacion].whereType<String>().join(' · '),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(color: esquema.onSurfaceVariant, fontSize: 13),
                      ),
                      if (ascensor != null)
                        Text(
                          '${numero(ascensor.capacidadKg, ' kg')} · ${ascensor.paradas ?? '—'} paradas',
                          style: TextStyle(color: esquema.onSurfaceVariant, fontSize: 12),
                        ),
                    ],
                  ),
                ),
                Insignia.estado(equipo.estado),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// RF-008 y RF-009: ficha técnica e historial del ascensor.
class PantallaDetalleEquipo extends StatefulWidget {
  const PantallaDetalleEquipo({super.key, required this.equipoId});

  final int equipoId;

  @override
  State<PantallaDetalleEquipo> createState() => _PantallaDetalleEquipoState();
}

class _PantallaDetalleEquipoState extends State<PantallaDetalleEquipo> {
  Equipo? _equipo;
  List<EventoHistorial>? _historial;
  List<MantenimientoProgramado> _visitas = const [];
  String? _error;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    final sesion = context.read<Sesion>();
    setState(() => _error = null);
    try {
      final equipo = await sesion.repositorio.equipo(widget.equipoId);
      final historial = sesion.tienePermiso('equipos.historial.ver')
          ? await sesion.repositorio.historial(widget.equipoId)
          : <EventoHistorial>[];
      // Fase 2: próximas visitas del plan (tres meses hacia adelante).
      final hoy = DateTime.now();
      final visitas = equipo.asignacionActiva != null && sesion.tienePermiso('mantenimientos.ver')
          ? await sesion.repositorio
                .mantenimientos(desde: hoy.subtract(const Duration(days: 7)), hasta: hoy.add(const Duration(days: 92)), equipoId: equipo.id)
                .catchError((_) => <MantenimientoProgramado>[])
          : <MantenimientoProgramado>[];
      if (mounted) {
        setState(() {
          _equipo = equipo;
          _historial = historial;
          _visitas = visitas.where((m) => m.pendiente).toList();
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = mensajeError(e));
    }
  }

  Future<void> _cambiarEstado(String estado) async {
    final mensajero = ScaffoldMessenger.of(context);
    try {
      await context.read<Sesion>().repositorio.cambiarEstadoEquipo(widget.equipoId, estado);
      mensajero.showSnackBar(const SnackBar(content: Text('Estado actualizado.')));
      _cargar();
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<Sesion>();
    final equipo = _equipo;

    if (_error != null && equipo == null) {
      return Scaffold(
        appBar: AppBar(),
        body: VistaError(mensaje: _error!, alReintentar: _cargar),
      );
    }
    if (equipo == null) return Scaffold(appBar: AppBar(), body: const Cargando());

    final ficha = equipo.ascensor;
    final puedeRegistrar = sesion.tienePermiso('equipos.historial.crear');

    return Scaffold(
      appBar: AppBar(
        title: Text('Ascensor ${equipo.codigo}'),
        actions: [
          if (sesion.tienePermiso('equipos.editar'))
            PopupMenuButton<String>(
              tooltip: 'Cambiar estado',
              icon: const Icon(Icons.swap_vert),
              onSelected: _cambiarEstado,
              itemBuilder: (_) => [
                for (final estado in ['activo', 'mantenimiento', 'inactivo'])
                  PopupMenuItem(value: estado, enabled: estado != equipo.estado, child: Text(estadoEquipo(estado).texto)),
              ],
            ),
        ],
      ),
      floatingActionButton: puedeRegistrar
          ? FloatingActionButton.extended(
              onPressed: () async {
                await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaVisita(equipo: equipo)));
                _cargar();
              },
              icon: const Icon(Icons.assignment_add),
              label: const Text('Registrar visita'),
            )
          : null,
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    [equipo.edificioNombre, equipo.clienteNombre].whereType<String>().join(' · '),
                    style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant),
                  ),
                ),
                Insignia.estado(equipo.estado),
              ],
            ),
            const SizedBox(height: 14),
            Seccion(
              titulo: 'Datos del equipo',
              hijos: [
                Datos([
                  ('Marca', equipo.marca),
                  ('Modelo', equipo.modelo),
                  ('Número de serie', equipo.numeroSerie),
                  ('Ubicación', equipo.ubicacion),
                  ('Instalación', fecha(equipo.fechaInstalacion)),
                ]),
              ],
            ),
            const SizedBox(height: 12),
            SeccionPlan(asignacion: equipo.asignacionActiva, visitas: _visitas),
            const SizedBox(height: 12),
            if (ficha != null)
              Seccion(
                titulo: 'Ficha técnica',
                hijos: [
                  Datos([
                    ('Capacidad', numero(ficha.capacidadKg, ' kg')),
                    ('Velocidad', numero(ficha.velocidadMpm, ' m/min')),
                    ('Paradas', ficha.paradas?.toString()),
                    ('Accionamiento', ficha.accionamiento),
                    ('Motor', ficha.motor),
                    ('Controlador', ficha.controlador),
                    ('Tipo de puerta', ficha.tipoPuerta),
                    ('Operador de puertas', ficha.operadorPuertas),
                    ('Puertas de cabina', ficha.puertasCabina?.toString()),
                    ('Puertas de piso', ficha.puertasPiso?.toString()),
                  ]),
                  if (ficha.especificaciones != null && ficha.especificaciones!.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    const Etiqueta('Especificaciones adicionales'),
                    const SizedBox(height: 4),
                    Text(ficha.especificaciones!),
                  ],
                ],
              ),
            const SizedBox(height: 12),
            if (sesion.tienePermiso('equipos.historial.ver'))
              Seccion(
                titulo: 'Historial del equipo',
                accion: puedeRegistrar
                    ? TextButton.icon(
                        onPressed: () => _registrarEvento(context, equipo),
                        icon: const Icon(Icons.add, size: 18),
                        label: const Text('Evento'),
                      )
                    : null,
                hijos: [
                  if (_historial == null || _historial!.isEmpty)
                    const EstadoVacio(titulo: 'Sin eventos registrados', icono: Icons.history)
                  else
                    LineaTiempo(eventos: _historial!),
                ],
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _registrarEvento(BuildContext context, Equipo equipo) async {
    final registrado = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => _FormularioEvento(equipoId: equipo.id),
    );
    if (registrado == true) {
      if (context.mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Evento registrado en el historial.')));
      _cargar();
    }
  }
}

const tiposEvento = {
  'incidente': 'Incidente / falla reportada',
  'reparacion': 'Reparación',
  'mantenimiento': 'Mantenimiento',
  'inspeccion': 'Inspección',
  'cambio_repuesto': 'Cambio de repuesto',
  'observacion': 'Observación',
};

class _FormularioEvento extends StatefulWidget {
  const _FormularioEvento({required this.equipoId});

  final int equipoId;

  @override
  State<_FormularioEvento> createState() => _FormularioEventoState();
}

class _FormularioEventoState extends State<_FormularioEvento> {
  String _evento = 'observacion';
  final _descripcion = TextEditingController();
  bool _enviando = false;
  String? _error;

  @override
  void dispose() {
    _descripcion.dispose();
    super.dispose();
  }

  Future<void> _guardar() async {
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      await context.read<Sesion>().repositorio.registrarEvento(widget.equipoId, evento: _evento, descripcion: _descripcion.text.trim());
      if (mounted) Navigator.of(context).pop(true);
    } on ErrorApi catch (e) {
      setState(() => _error = e.errorDe('descripcion') ?? e.mensaje);
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text('Registrar evento', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600)),
          const SizedBox(height: 4),
          const Text('El historial no se modifica ni se elimina; para corregir, registre un nuevo evento.'),
          const SizedBox(height: 14),
          DropdownButtonFormField<String>(
            initialValue: _evento,
            decoration: const InputDecoration(labelText: 'Tipo de evento'),
            items: [for (final e in tiposEvento.entries) DropdownMenuItem(value: e.key, child: Text(e.value))],
            onChanged: (valor) => setState(() => _evento = valor!),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _descripcion,
            maxLines: 4,
            decoration: InputDecoration(labelText: 'Descripción', alignLabelWithHint: true, errorText: _error),
          ),
          const SizedBox(height: 14),
          FilledButton(onPressed: _enviando ? null : _guardar, child: Text(_enviando ? 'Guardando…' : 'Registrar')),
        ],
      ),
    );
  }
}

/// Fase 2 (RF-013 a RF-018): plan de mantenimiento vigente y próximas visitas del ascensor.
class SeccionPlan extends StatelessWidget {
  const SeccionPlan({super.key, required this.asignacion, this.visitas = const []});

  final AsignacionPlan? asignacion;
  final List<MantenimientoProgramado> visitas;

  @override
  Widget build(BuildContext context) {
    final plan = asignacion;
    if (plan == null) {
      return const Seccion(
        titulo: 'Plan de mantenimiento',
        hijos: [Text('El ascensor no tiene un plan de mantenimiento preventivo asignado.')],
      );
    }

    final proximo = plan.proximoMantenimiento;
    final dias = proximo == null ? null : MantenimientoProgramado(id: 0, estado: 'programado', fechaProgramada: proximo).diasRestantes();

    return Seccion(
      titulo: 'Plan de mantenimiento',
      hijos: [
        Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: SigmaColores.primario.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(14)),
          child: Row(
            children: [
              const Icon(Icons.event_available, color: SigmaColores.primario),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Etiqueta('Próximo mantenimiento'),
                    const SizedBox(height: 2),
                    Text(fecha(proximo), style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
              if (dias != null)
                Insignia(
                  texto: textoDias(dias),
                  color: dias < 0 ? SigmaColores.malo : (dias <= 7 ? SigmaColores.regular : SigmaColores.primario),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        Datos([
          ('Plan', plan.planNombre),
          ('Periodicidad', plan.periodicidad),
          ('Hora de las visitas', plan.horaPreferida),
          ('Precio por visita', dinero(plan.precio)),
          ('Con IVA', plan.aplicaIva ? dinero(plan.precioConIva) : 'No aplica'),
          ('Vigencia', '${fecha(plan.fechaInicio)} – ${fecha(plan.fechaFin)}'),
          ('Contrato', plan.contratoNumero ?? 'Sin contrato'),
          ('Último mantenimiento', fecha(plan.ultimoMantenimiento)),
        ]),
        if (visitas.isNotEmpty) ...[
          const SizedBox(height: 12),
          const Etiqueta('Próximas visitas'),
          const SizedBox(height: 4),
          for (final visita in visitas.take(4))
            ListTile(
              contentPadding: EdgeInsets.zero,
              dense: true,
              leading: const Icon(Icons.calendar_month_outlined),
              title: Text([fecha(visita.fechaProgramada), ?visita.hora].join(' · ')),
              subtitle: visita.motivo != null && visita.estado == 'reprogramado' ? Text('Reprogramado: ${visita.motivo}') : null,
              trailing: Insignia(texto: estadoMantenimiento(visita).texto, color: estadoMantenimiento(visita).color),
            ),
        ],
      ],
    );
  }
}

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/modelos.dart';
import '../estado/sesion.dart';
import '../tema.dart';
import '../widgets/comunes.dart';
import 'equipos.dart';
import 'planilla.dart';

/// Colores de servicio, prioridad y estado de las órdenes (mismos del panel web).
Color colorServicio(String servicio) => switch (servicio) {
  'correctivo' => SigmaColores.malo,
  'inspeccion' => SigmaColores.cian,
  _ => SigmaColores.primario,
};

Color colorPrioridad(String prioridad) => switch (prioridad) {
  'critica' => SigmaColores.malo,
  'alta' => SigmaColores.regular,
  'media' => SigmaColores.primario,
  _ => SigmaColores.textoSuave,
};

Color colorEstadoOrden(String estado) => switch (estado) {
  'programada' || 'pendiente' => SigmaColores.regular,
  'asignada' => SigmaColores.cian,
  'en_camino' || 'en_proceso' => SigmaColores.primario,
  'finalizada' => SigmaColores.bueno,
  _ => SigmaColores.textoSuave,
};

String duracionTexto(int? minutos) {
  if (minutos == null) return '—';
  final horas = minutos ~/ 60;
  final resto = minutos % 60;
  if (horas == 0) return '$resto min';
  return resto == 0 ? '$horas h' : '$horas h $resto min';
}

/// RF-023: órdenes del técnico (solo las que tiene asignadas).
class PantallaOrdenes extends StatefulWidget {
  const PantallaOrdenes({super.key});

  @override
  State<PantallaOrdenes> createState() => _PantallaOrdenesState();
}

class _PantallaOrdenesState extends State<PantallaOrdenes> {
  bool _activas = true;
  Future<Pagina<OrdenTrabajo>>? _ordenes;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  void _cargar() {
    setState(() => _ordenes = context.read<Sesion>().repositorio.ordenes(activas: _activas));
  }

  @override
  Widget build(BuildContext context) {
    final usuario = context.watch<Sesion>().usuario!;
    return Scaffold(
      appBar: AppBar(title: Text(usuario.esTecnico ? 'Mis órdenes' : 'Órdenes de trabajo')),
      body: RefreshIndicator(
        onRefresh: () async => _cargar(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
          children: [
            SegmentedButton<bool>(
              segments: const [
                ButtonSegment(value: true, label: Text('Activas'), icon: Icon(Icons.pending_actions_outlined)),
                ButtonSegment(value: false, label: Text('Finalizadas'), icon: Icon(Icons.task_alt)),
              ],
              selected: {_activas},
              onSelectionChanged: (seleccion) {
                _activas = seleccion.first;
                _cargar();
              },
            ),
            const SizedBox(height: 12),
            FutureBuilder<Pagina<OrdenTrabajo>>(
              future: _ordenes,
              builder: (context, instantanea) {
                if (instantanea.hasError) return VistaError(mensaje: mensajeError(instantanea.error!), alReintentar: _cargar);
                if (!instantanea.hasData) return const Cargando();
                final lista = instantanea.data!.elementos;
                if (lista.isEmpty) {
                  return EstadoVacio(
                    titulo: _activas ? 'No tiene órdenes activas' : 'Sin órdenes finalizadas',
                    icono: Icons.assignment_turned_in_outlined,
                  );
                }
                return Column(
                  children: [for (final orden in lista) FilaOrden(orden: orden, alVolver: _cargar)],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class FilaOrden extends StatelessWidget {
  const FilaOrden({super.key, required this.orden, this.alVolver});

  final OrdenTrabajo orden;
  final VoidCallback? alVolver;

  @override
  Widget build(BuildContext context) {
    final suave = Theme.of(context).colorScheme.onSurfaceVariant;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () async {
          await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaDetalleOrden(ordenId: orden.id)));
          alVolver?.call();
        },
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: colorSegunModo(context, colorServicio(orden.servicio)).withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  switch (orden.servicio) {
                    'correctivo' => Icons.build_outlined,
                    'inspeccion' => Icons.manage_search_outlined,
                    _ => Icons.event_repeat_outlined,
                  },
                  size: 19,
                  color: colorSegunModo(context, colorServicio(orden.servicio)),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${orden.equipoCodigo ?? ''} · ${orden.edificioNombre ?? ''}',
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${fecha(orden.fecha)} · ${orden.hora}${orden.finEstimado != null ? '–${orden.finEstimado}' : ''}',
                      style: TextStyle(color: suave),
                    ),
                    Text('${orden.numero} · ${orden.nombreServicio}', style: TextStyle(color: suave, fontSize: 12.5)),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        Insignia(texto: orden.nombreEstado, color: colorEstadoOrden(orden.estado)),
                        if (orden.prioridad == 'critica' || orden.prioridad == 'alta')
                          Insignia(texto: orden.nombrePrioridad, color: colorPrioridad(orden.prioridad)),
                      ],
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: suave),
            ],
          ),
        ),
      ),
    );
  }
}

/// RF-025 a RF-029: detalle de la orden; el técnico asignado avanza su estado.
class PantallaDetalleOrden extends StatefulWidget {
  const PantallaDetalleOrden({super.key, required this.ordenId});

  final int ordenId;

  @override
  State<PantallaDetalleOrden> createState() => _PantallaDetalleOrdenState();
}

class _PantallaDetalleOrdenState extends State<PantallaDetalleOrden> {
  OrdenTrabajo? _orden;
  String? _error;
  bool _guardando = false;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    try {
      final orden = await context.read<Sesion>().repositorio.orden(widget.ordenId);
      if (mounted) {
        setState(() {
          _orden = orden;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = mensajeError(e));
    }
  }

  Future<void> _avanzar(String estado) async {
    String? motivo;
    String? informe;
    String? observaciones;

    if (estado == 'pendiente' || estado == 'finalizada') {
      final datos = await showModalBottomSheet<Map<String, String>>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (_) => _FormularioEstado(estado: estado),
      );
      if (datos == null) return;
      motivo = datos['motivo'];
      informe = datos['informe'];
      observaciones = datos['observaciones'];
    }
    if (!mounted) return;

    final mensajero = ScaffoldMessenger.of(context);
    setState(() => _guardando = true);
    try {
      final orden = await context.read<Sesion>().repositorio.cambiarEstadoOrden(
        widget.ordenId,
        estado,
        motivo: motivo,
        informe: informe,
        observaciones: observaciones,
      );
      if (mounted) setState(() => _orden = orden);
      mensajero.showSnackBar(SnackBar(content: Text('Orden ${nombresEstadoOrden[estado]!.toLowerCase()}.')));
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    } finally {
      if (mounted) setState(() => _guardando = false);
    }
  }

  Future<void> _abrir(Widget pantalla) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => pantalla));
    _cargar();
  }

  static const _acciones = {
    'en_camino': ('Voy en camino', Icons.directions_car_outlined),
    'en_proceso': ('Iniciar trabajo', Icons.play_arrow_rounded),
    'pendiente': ('Dejar pendiente', Icons.pause_rounded),
    'finalizada': ('Finalizar orden', Icons.task_alt),
  };

  @override
  Widget build(BuildContext context) {
    final orden = _orden;
    if (_error != null && orden == null) {
      return Scaffold(
        appBar: AppBar(),
        body: VistaError(mensaje: _error!, alReintentar: _cargar),
      );
    }
    if (orden == null) return Scaffold(appBar: AppBar(), body: const Cargando());

    final acciones = orden.puedeEjecutar ? orden.siguientesEstados : const <String>[];
    // "Retomar" es volver a en proceso desde pendiente.
    String textoAccion(String estado) => orden.estado == 'pendiente' && estado == 'en_proceso' ? 'Retomar trabajo' : _acciones[estado]!.$1;

    return Scaffold(
      appBar: AppBar(title: Text(orden.numero)),
      bottomNavigationBar: acciones.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                child: Row(
                  children: [
                    for (final (i, estado) in acciones.indexed) ...[
                      if (i > 0) const SizedBox(width: 10),
                      Expanded(
                        child: estado == 'pendiente'
                            ? OutlinedButton.icon(
                                onPressed: _guardando ? null : () => _avanzar(estado),
                                icon: Icon(_acciones[estado]!.$2),
                                label: Text(textoAccion(estado)),
                              )
                            : FilledButton.icon(
                                onPressed: _guardando ? null : () => _avanzar(estado),
                                icon: Icon(_acciones[estado]!.$2),
                                label: Text(textoAccion(estado)),
                              ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
      body: RefreshIndicator(
        onRefresh: _cargar,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
          children: [
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                Insignia(texto: orden.nombreServicio, color: colorServicio(orden.servicio)),
                Insignia(texto: 'Prioridad ${orden.nombrePrioridad.toLowerCase()}', color: colorPrioridad(orden.prioridad)),
                Insignia(texto: orden.nombreEstado, color: colorEstadoOrden(orden.estado)),
              ],
            ),
            if (orden.estado != 'cancelada') ...[
              const SizedBox(height: 16),
              AvanceEtapas(
                etapas: ['Programada', 'Asignada', 'En camino', orden.estado == 'pendiente' ? 'Pendiente' : 'En proceso', 'Finalizada'],
                actual:
                    const {
                      'programada': 0,
                      'asignada': 1,
                      'en_camino': 2,
                      'en_proceso': 3,
                      'pendiente': 3,
                      'finalizada': 4,
                    }[orden.estado] ??
                    0,
              ),
            ],
            if (orden.estado == 'cancelada' && orden.motivoCancelacion != null) ...[
              const SizedBox(height: 10),
              Text('Cancelada: ${orden.motivoCancelacion}'),
            ],
            const SizedBox(height: 14),
            Seccion(
              titulo: 'Datos de la orden',
              accion: orden.equipoId == null
                  ? null
                  : TextButton(
                      onPressed: () =>
                          Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaDetalleEquipo(equipoId: orden.equipoId!))),
                      child: const Text('Ver ascensor'),
                    ),
              hijos: [
                Datos([
                  ('Ascensor', orden.equipoCodigo),
                  ('Edificio', orden.edificioNombre),
                  ('Dirección', orden.direccion),
                  ('Cliente', orden.clienteNombre),
                  ('Fecha', fecha(orden.fecha)),
                  ('Horario', '${orden.hora}–${orden.finEstimado ?? ''}'),
                ]),
                if (orden.descripcion != null && orden.descripcion!.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  const Etiqueta('Trabajo a realizar'),
                  const SizedBox(height: 4),
                  Text(orden.descripcion!),
                ],
              ],
            ),
            const SizedBox(height: 12),
            Seccion(
              titulo: 'Tiempos',
              hijos: [
                Datos([
                  ('Inicio', orden.inicioEn == null ? 'Al iniciar el trabajo' : fechaHora(orden.inicioEn)),
                  ('Finalización', fechaHora(orden.finEn)),
                  ('Duración real', duracionTexto(orden.duracionMinutos)),
                  ('Estimada', duracionTexto(orden.duracionEstimada)),
                ]),
              ],
            ),
            const SizedBox(height: 12),
            Seccion(titulo: 'Técnicos', hijos: [Text(orden.tecnicos.isEmpty ? 'Sin técnicos asignados' : orden.tecnicos.join(', '))]),
            const SizedBox(height: 12),
            SeccionRepuestos(orden: orden),
            // Fase 4: planilla del preventivo y firmas (RF-025, RF-030 a RF-039).
            if (orden.llevaPlanilla || orden.firmas.isNotEmpty || orden.puedeEjecutar) ...[
              const SizedBox(height: 12),
              Seccion(
                titulo: 'Planilla y firmas',
                hijos: [
                  if (orden.llevaPlanilla)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(
                        orden.planillaCompleta ? Icons.fact_check : Icons.fact_check_outlined,
                        color: orden.planillaCompleta ? SigmaColores.bueno : SigmaColores.primario,
                      ),
                      title: const Text('Planilla de mantenimiento'),
                      subtitle: Text('${orden.planillaRegistrados}/${orden.planillaTotal} ítems registrados'),
                      trailing: const Icon(Icons.chevron_right),
                      onTap: () => _abrir(PantallaPlanilla(orden: orden)),
                    ),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: const Icon(Icons.draw_outlined, color: SigmaColores.primario),
                    title: const Text('Firmas'),
                    subtitle: Text(
                      [
                        'Técnico: ${orden.firmas['tecnico'] ?? 'pendiente'}',
                        'Recibe: ${orden.firmas['cliente'] ?? 'pendiente'}',
                      ].join('\n'),
                    ),
                    trailing: orden.puedeEjecutar && (orden.estado == 'en_proceso' || orden.estado == 'pendiente')
                        ? const Icon(Icons.chevron_right)
                        : null,
                    onTap: orden.puedeEjecutar && (orden.estado == 'en_proceso' || orden.estado == 'pendiente')
                        ? () => _abrir(PantallaFirmas(orden: orden))
                        : null,
                  ),
                  if (orden.informes > 0) Text('Informe PDF generado (${orden.informes} versión(es)). Disponible en el panel web.'),
                ],
              ),
            ],
            if (orden.informe != null) ...[
              const SizedBox(height: 12),
              Seccion(titulo: 'Informe del trabajo', hijos: [Text(orden.informe!)]),
            ],
            const SizedBox(height: 12),
            Seccion(
              titulo: 'Trazabilidad',
              hijos: [
                if (orden.trazabilidad.isEmpty)
                  const EstadoVacio(titulo: 'Sin cambios registrados', icono: Icons.history)
                else
                  LineaTiempo(eventos: orden.trazabilidad),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

/// Motivo para dejar pendiente, o informe para finalizar (RF-026).
class _FormularioEstado extends StatefulWidget {
  const _FormularioEstado({required this.estado});

  final String estado;

  @override
  State<_FormularioEstado> createState() => _FormularioEstadoState();
}

class _FormularioEstadoState extends State<_FormularioEstado> {
  final _formulario = GlobalKey<FormState>();
  final _texto = TextEditingController();
  final _observaciones = TextEditingController();

  @override
  void dispose() {
    _texto.dispose();
    _observaciones.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final finalizar = widget.estado == 'finalizada';
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
      child: Form(
        key: _formulario,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(finalizar ? 'Finalizar orden' : 'Dejar pendiente', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 6),
            Text(
              finalizar
                  ? 'Se registra la hora de finalización y se calcula la duración.'
                  : 'La orden queda pendiente y podrá retomarla después.',
            ),
            const SizedBox(height: 14),
            TextFormField(
              controller: _texto,
              minLines: 3,
              maxLines: 6,
              autofocus: true,
              decoration: InputDecoration(labelText: finalizar ? 'Informe del trabajo realizado' : '¿Por qué queda pendiente?'),
              validator: (valor) {
                final minimo = finalizar ? 10 : 5;
                return (valor ?? '').trim().length < minimo ? 'Escriba al menos $minimo caracteres.' : null;
              },
            ),
            if (finalizar) ...[
              const SizedBox(height: 10),
              TextFormField(
                controller: _observaciones,
                minLines: 1,
                maxLines: 3,
                decoration: const InputDecoration(labelText: 'Observaciones (opcional)'),
              ),
            ],
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () {
                if (!_formulario.currentState!.validate()) return;
                Navigator.of(context).pop({
                  if (finalizar) 'informe': _texto.text.trim() else 'motivo': _texto.text.trim(),
                  if (finalizar && _observaciones.text.trim().isNotEmpty) 'observaciones': _observaciones.text.trim(),
                });
              },
              child: Text(finalizar ? 'Finalizar orden' : 'Dejar pendiente'),
            ),
          ],
        ),
      ),
    );
  }
}

/// RF-042 y RF-052: repuestos usados en la orden. El técnico asignado los registra mientras la orden
/// no esté finalizada ni cancelada; se descuentan del inventario al finalizarla.
class SeccionRepuestos extends StatefulWidget {
  const SeccionRepuestos({super.key, required this.orden});

  final OrdenTrabajo orden;

  @override
  State<SeccionRepuestos> createState() => _SeccionRepuestosState();
}

class _SeccionRepuestosState extends State<SeccionRepuestos> {
  List<RepuestoOrden>? _usados;

  bool get _final => widget.orden.estado == 'finalizada' || widget.orden.estado == 'cancelada';
  bool get _edita => !_final && widget.orden.puedeEjecutar;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    try {
      final usados = await context.read<Sesion>().repositorio.repuestosDeOrden(widget.orden.id);
      if (mounted) setState(() => _usados = usados);
    } catch (_) {
      if (mounted) setState(() => _usados = const []);
    }
  }

  Future<void> _agregar() async {
    final mensajero = ScaffoldMessenger.of(context);
    final elegido = await showModalBottomSheet<(RepuestoInventario, double)>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => const _FormularioRepuesto(),
    );
    if (elegido == null || !mounted) return;
    try {
      final mensaje = await context.read<Sesion>().repositorio.agregarRepuesto(widget.orden.id, elegido.$1.id, elegido.$2);
      mensajero.showSnackBar(SnackBar(content: Text(mensaje)));
      _cargar();
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    }
  }

  Future<void> _quitar(RepuestoOrden usado) async {
    final mensajero = ScaffoldMessenger.of(context);
    try {
      await context.read<Sesion>().repositorio.quitarRepuesto(widget.orden.id, usado.id);
      _cargar();
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final usados = _usados;
    if (usados == null || (usados.isEmpty && !_edita)) return const SizedBox.shrink();
    return Seccion(
      titulo: 'Repuestos usados',
      accion: _edita ? TextButton.icon(onPressed: _agregar, icon: const Icon(Icons.add), label: const Text('Agregar')) : null,
      hijos: [
        if (usados.isEmpty) const Text('Registre los repuestos usados; se descuentan del inventario al finalizar la orden.'),
        for (final u in usados)
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.inventory_2_outlined, color: SigmaColores.primario),
            title: Text(u.repuesto.nombre),
            subtitle: Text('${u.repuesto.codigo} · ${cantidadTexto(u.cantidad)} ${u.repuesto.unidad}'),
            trailing: _final
                ? Insignia(texto: u.faltante > 0 ? 'Faltaron ${cantidadTexto(u.faltante)}' : 'Descontado', color: u.faltante > 0 ? SigmaColores.regular : SigmaColores.bueno)
                : (_edita ? IconButton(onPressed: () => _quitar(u), icon: const Icon(Icons.delete_outline), tooltip: 'Quitar') : null),
          ),
      ],
    );
  }
}

class _FormularioRepuesto extends StatefulWidget {
  const _FormularioRepuesto();

  @override
  State<_FormularioRepuesto> createState() => _FormularioRepuestoState();
}

class _FormularioRepuestoState extends State<_FormularioRepuesto> {
  final _cantidad = TextEditingController(text: '1');
  List<RepuestoInventario>? _repuestos;
  RepuestoInventario? _elegido;
  String? _error;

  @override
  void initState() {
    super.initState();
    context.read<Sesion>().repositorio.repuestosActivos().then((r) {
      if (mounted) setState(() => _repuestos = r);
    }).catchError((Object e) {
      if (mounted) setState(() => _error = mensajeError(e));
    });
  }

  @override
  void dispose() {
    _cantidad.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final repuestos = _repuestos;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text('Agregar repuesto', style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 14),
          if (repuestos == null && _error == null) const Cargando(),
          if (repuestos != null)
            DropdownButtonFormField<RepuestoInventario>(
              initialValue: _elegido,
              isExpanded: true,
              decoration: const InputDecoration(labelText: 'RepuestoInventario'),
              items: [
                for (final r in repuestos)
                  DropdownMenuItem(value: r, child: Text('${r.nombre} · stock ${cantidadTexto(r.stock)} ${r.unidad}', overflow: TextOverflow.ellipsis)),
              ],
              onChanged: (r) => setState(() => _elegido = r),
            ),
          const SizedBox(height: 12),
          TextField(
            controller: _cantidad,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: 'Cantidad${_elegido != null ? ' (${_elegido!.unidad})' : ''}'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 10),
            Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          ],
          const SizedBox(height: 16),
          FilledButton(
            onPressed: () {
              final cantidad = double.tryParse(_cantidad.text.replaceAll(',', '.'));
              if (_elegido == null || cantidad == null || cantidad <= 0) {
                setState(() => _error = 'Elija el repuesto y una cantidad mayor que cero.');
                return;
              }
              Navigator.of(context).pop((_elegido!, cantidad));
            },
            child: const Text('Agregar a la orden'),
          ),
        ],
      ),
    );
  }
}

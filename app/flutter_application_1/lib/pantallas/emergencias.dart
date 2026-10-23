import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/modelos.dart';
import '../estado/sesion.dart';
import '../tema.dart';
import '../widgets/comunes.dart';
import 'equipos.dart';

/// Colores de estado de las emergencias (mismos del panel web).
Color colorEstadoEmergencia(String estado) => switch (estado) {
  'reportada' => SigmaColores.malo,
  'asignada' => SigmaColores.regular,
  'en_sitio' => SigmaColores.primario,
  'resuelta' => SigmaColores.bueno,
  _ => SigmaColores.textoSuave,
};

/// Tiempo desde el reporte frente al objetivo de llegada; si ya hubo llegada, el tiempo de respuesta.
String tiempoEmergencia(Emergencia e) {
  if (e.llegadaEn != null && e.reportadaEn != null) {
    final minutos = e.llegadaEn!.difference(e.reportadaEn!).inMinutes;
    return 'Llegada en $minutos min (objetivo ${e.minutosObjetivo})';
  }
  if (e.abierta && e.reportadaEn != null) {
    final minutos = DateTime.now().difference(e.reportadaEn!).inMinutes;
    return minutos > e.minutosObjetivo
        ? '$minutos min · ${minutos - e.minutosObjetivo} min sobre el objetivo'
        : '$minutos de ${e.minutosObjetivo} min';
  }
  return fechaHora(e.reportadaEn);
}

/// Emergencias del técnico: las que tiene asignadas y las que aún no tienen técnico.
class PantallaEmergencias extends StatefulWidget {
  const PantallaEmergencias({super.key});

  @override
  State<PantallaEmergencias> createState() => _PantallaEmergenciasState();
}

class _PantallaEmergenciasState extends State<PantallaEmergencias> {
  bool _abiertas = true;
  Future<Pagina<Emergencia>>? _emergencias;
  Timer? _reloj;

  @override
  void initState() {
    super.initState();
    _cargar();
    // Las abiertas se actualizan cada minuto: el tiempo frente al objetivo corre.
    _reloj = Timer.periodic(const Duration(minutes: 1), (_) {
      if (mounted && _abiertas) _cargar();
    });
  }

  @override
  void dispose() {
    _reloj?.cancel();
    super.dispose();
  }

  void _cargar() {
    setState(() => _emergencias = context.read<Sesion>().repositorio.emergencias(abiertas: _abiertas));
  }

  Future<void> _reportar() async {
    final mensaje = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => const FormularioReporteEmergencia(),
    );
    if (mensaje == null || !mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(mensaje)));
    _abiertas = true;
    _cargar();
  }

  @override
  Widget build(BuildContext context) {
    final usuario = context.watch<Sesion>().usuario!;
    return Scaffold(
      appBar: AppBar(title: const Text('Emergencias')),
      floatingActionButton: usuario.tienePermiso('emergencias.reportar')
          ? FloatingActionButton.extended(onPressed: _reportar, icon: const Icon(Icons.campaign_outlined), label: const Text('Reportar'))
          : null,
      body: RefreshIndicator(
        onRefresh: () async => _cargar(),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 96),
          children: [
            SegmentedButton<bool>(
              segments: const [
                ButtonSegment(value: true, label: Text('Abiertas'), icon: Icon(Icons.notification_important_outlined)),
                ButtonSegment(value: false, label: Text('Resueltas'), icon: Icon(Icons.task_alt)),
              ],
              selected: {_abiertas},
              onSelectionChanged: (seleccion) {
                _abiertas = seleccion.first;
                _cargar();
              },
            ),
            const SizedBox(height: 12),
            FutureBuilder<Pagina<Emergencia>>(
              future: _emergencias,
              builder: (context, instantanea) {
                if (instantanea.hasError) return VistaError(mensaje: mensajeError(instantanea.error!), alReintentar: _cargar);
                if (!instantanea.hasData) return const Cargando();
                final lista = instantanea.data!.elementos;
                if (lista.isEmpty) {
                  return EstadoVacio(
                    titulo: _abiertas ? 'No hay emergencias abiertas' : 'Sin emergencias resueltas',
                    descripcion: _abiertas ? 'Cuando le asignen una emergencia aparecerá aquí.' : null,
                    icono: Icons.notification_important_outlined,
                  );
                }
                return Column(children: [for (final e in lista) _FilaEmergencia(emergencia: e, alVolver: _cargar)]);
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _FilaEmergencia extends StatelessWidget {
  const _FilaEmergencia({required this.emergencia, this.alVolver});

  final Emergencia emergencia;
  final VoidCallback? alVolver;

  @override
  Widget build(BuildContext context) {
    final suave = Theme.of(context).colorScheme.onSurfaceVariant;
    final color = colorSegunModo(context, emergencia.personasAtrapadas && emergencia.abierta ? SigmaColores.malo : colorEstadoEmergencia(emergencia.estado));
    final tarde = emergencia.abierta && emergencia.llegadaEn == null && emergencia.reportadaEn != null &&
        DateTime.now().difference(emergencia.reportadaEn!).inMinutes > emergencia.minutosObjetivo;
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        borderRadius: BorderRadius.circular(16),
        onTap: () async {
          await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaDetalleEmergencia(emergenciaId: emergencia.id)));
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
                decoration: BoxDecoration(color: color.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(10)),
                child: Icon(emergencia.personasAtrapadas ? Icons.people_alt_outlined : Icons.notification_important_outlined, size: 19, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${emergencia.equipoCodigo ?? ''} · ${emergencia.edificioNombre ?? emergencia.ubicacion ?? ''}',
                      style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
                    ),
                    const SizedBox(height: 2),
                    Text(emergencia.nombreTipo, style: TextStyle(color: suave)),
                    Text(
                      '${emergencia.numero} · ${tiempoEmergencia(emergencia)}',
                      style: TextStyle(color: tarde ? colorSegunModo(context, SigmaColores.malo) : suave, fontSize: 12.5),
                    ),
                    const SizedBox(height: 6),
                    Wrap(
                      spacing: 6,
                      runSpacing: 6,
                      children: [
                        Insignia(texto: emergencia.nombreEstado, color: colorEstadoEmergencia(emergencia.estado)),
                        if (emergencia.personasAtrapadas && emergencia.abierta) const Insignia(texto: 'Personas atrapadas', color: SigmaColores.malo),
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

/// Detalle de la emergencia: el técnico marca la llegada y registra la solución.
class PantallaDetalleEmergencia extends StatefulWidget {
  const PantallaDetalleEmergencia({super.key, required this.emergenciaId});

  final int emergenciaId;

  @override
  State<PantallaDetalleEmergencia> createState() => _PantallaDetalleEmergenciaState();
}

class _PantallaDetalleEmergenciaState extends State<PantallaDetalleEmergencia> {
  Emergencia? _emergencia;
  String? _error;
  bool _guardando = false;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    try {
      final emergencia = await context.read<Sesion>().repositorio.emergencia(widget.emergenciaId);
      if (mounted) {
        setState(() {
          _emergencia = emergencia;
          _error = null;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = mensajeError(e));
    }
  }

  Future<void> _avanzar(String estado) async {
    String? solucion;
    if (estado == 'resuelta') {
      solucion = await showModalBottomSheet<String>(
        context: context,
        isScrollControlled: true,
        showDragHandle: true,
        builder: (_) => const _FormularioSolucion(),
      );
      if (solucion == null) return;
    }
    if (!mounted) return;

    final mensajero = ScaffoldMessenger.of(context);
    setState(() => _guardando = true);
    try {
      final emergencia = await context.read<Sesion>().repositorio.cambiarEstadoEmergencia(widget.emergenciaId, estado, solucion: solucion);
      if (mounted) setState(() => _emergencia = emergencia);
      mensajero.showSnackBar(SnackBar(content: Text(estado == 'en_sitio' ? 'Llegada registrada.' : 'Emergencia resuelta.')));
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    } finally {
      if (mounted) setState(() => _guardando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final e = _emergencia;
    if (_error != null && e == null) {
      return Scaffold(
        appBar: AppBar(),
        body: VistaError(mensaje: _error!, alReintentar: _cargar),
      );
    }
    if (e == null) return Scaffold(appBar: AppBar(), body: const Cargando());

    // Igual que el permiso de la API: el técnico atiende las suyas o las que no tienen técnico.
    final usuario = context.watch<Sesion>().usuario!;
    final gestiona = usuario.tienePermiso('emergencias.gestionar');
    final atiende = usuario.tienePermiso('emergencias.atender') && (e.tecnicoNombre == null || e.tecnicoNombre == usuario.nombre);
    final acciones = e.siguientes.where((s) => (s == 'en_sitio' || s == 'resuelta') && (gestiona || atiende)).toList();

    return Scaffold(
      appBar: AppBar(title: Text(e.numero)),
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
                        child: i == 0
                            ? FilledButton.icon(
                                onPressed: _guardando ? null : () => _avanzar(estado),
                                icon: Icon(estado == 'en_sitio' ? Icons.location_on_outlined : Icons.task_alt),
                                label: Text(estado == 'en_sitio' ? 'Llegué al sitio' : 'Registrar solución'),
                              )
                            : OutlinedButton.icon(
                                onPressed: _guardando ? null : () => _avanzar(estado),
                                icon: const Icon(Icons.task_alt),
                                label: const Text('Registrar solución'),
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
                Insignia(texto: e.nombreEstado, color: colorEstadoEmergencia(e.estado)),
                Insignia(texto: e.nombreTipo, color: e.personasAtrapadas ? SigmaColores.malo : SigmaColores.regular),
              ],
            ),
            if (e.personasAtrapadas && e.abierta) ...[
              const SizedBox(height: 12),
              Card(
                color: colorSegunModo(context, SigmaColores.malo).withValues(alpha: 0.12),
                child: const Padding(
                  padding: EdgeInsets.all(14),
                  child: Text('Hay personas atrapadas. Mantenga la comunicación por el citófono de la cabina hasta liberarlas.'),
                ),
              ),
            ],
            if (e.estado != 'cancelada') ...[
              const SizedBox(height: 16),
              AvanceEtapas(
                etapas: const ['Reportada', 'En camino', 'En sitio', 'Resuelta'],
                actual: const {'reportada': 0, 'asignada': 1, 'en_sitio': 2, 'resuelta': 3}[e.estado] ?? 0,
              ),
            ],
            const SizedBox(height: 14),
            Seccion(
              titulo: 'Tiempo de respuesta',
              hijos: [
                Text(
                  tiempoEmergencia(e),
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    color: e.dentroObjetivo == false ? colorSegunModo(context, SigmaColores.malo) : null,
                  ),
                ),
                const SizedBox(height: 8),
                Datos([
                  ('Reportada', fechaHora(e.reportadaEn)),
                  ('Objetivo de llegada', '${e.minutosObjetivo} min'),
                  ('Llegada', e.llegadaEn == null ? 'Pendiente' : fechaHora(e.llegadaEn)),
                  ('Resuelta', fechaHora(e.resueltaEn)),
                ]),
              ],
            ),
            const SizedBox(height: 12),
            Seccion(
              titulo: 'Reporte',
              accion: e.equipoId == null
                  ? null
                  : TextButton(
                      onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaDetalleEquipo(equipoId: e.equipoId!))),
                      child: const Text('Ver ascensor'),
                    ),
              hijos: [
                Datos([
                  ('Ascensor', e.equipoCodigo),
                  ('Edificio', e.edificioNombre),
                  ('Ubicación', e.ubicacion),
                  ('Cliente', e.clienteNombre),
                  ('Quién reportó', e.reportanteNombre),
                  ('Teléfono', e.reportanteTelefono),
                  ('Técnico', e.tecnicoNombre ?? 'Sin asignar'),
                  ('Orden de trabajo', e.ordenNumero),
                ]),
                if (e.descripcion != null && e.descripcion!.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  const Etiqueta('Descripción'),
                  const SizedBox(height: 4),
                  Text(e.descripcion!),
                ],
                if (e.solucion != null && e.solucion!.isNotEmpty) ...[
                  const SizedBox(height: 10),
                  const Etiqueta('Solución'),
                  const SizedBox(height: 4),
                  Text(e.solucion!),
                ],
              ],
            ),
            const SizedBox(height: 12),
            Seccion(
              titulo: 'Seguimiento',
              hijos: [
                for (final s in e.seguimiento)
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    dense: true,
                    leading: Icon(Icons.circle, size: 10, color: colorSegunModo(context, colorEstadoEmergencia(s.estado))),
                    minLeadingWidth: 12,
                    title: Text(s.nota ?? nombresEstadoEmergencia[s.estado] ?? s.estado),
                    subtitle: Text([fechaHora(s.fecha), ?s.usuario].join(' · ')),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _FormularioSolucion extends StatefulWidget {
  const _FormularioSolucion();

  @override
  State<_FormularioSolucion> createState() => _FormularioSolucionState();
}

class _FormularioSolucionState extends State<_FormularioSolucion> {
  final _formulario = GlobalKey<FormState>();
  final _texto = TextEditingController();

  @override
  void dispose() {
    _texto.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
      child: Form(
        key: _formulario,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Registrar solución', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 6),
            const Text('Se cierra la emergencia y queda en el historial del ascensor.'),
            const SizedBox(height: 14),
            TextFormField(
              controller: _texto,
              minLines: 3,
              maxLines: 6,
              autofocus: true,
              decoration: const InputDecoration(labelText: 'Qué se hizo', hintText: 'Rescate de pasajeros, ajuste del operador de puertas…'),
              validator: (valor) => (valor ?? '').trim().length < 5 ? 'Describa qué se hizo.' : null,
            ),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: () {
                if (_formulario.currentState!.validate()) Navigator.of(context).pop(_texto.text.trim());
              },
              child: const Text('Registrar solución'),
            ),
          ],
        ),
      ),
    );
  }
}

/// Reporte de una emergencia desde la app: se busca el ascensor por código, edificio o ubicación.
class FormularioReporteEmergencia extends StatefulWidget {
  const FormularioReporteEmergencia({super.key, this.equipo});

  /// Si se abre desde la ficha del ascensor, ya viene elegido.
  final Equipo? equipo;

  @override
  State<FormularioReporteEmergencia> createState() => _FormularioReporteEmergenciaState();
}

class _FormularioReporteEmergenciaState extends State<FormularioReporteEmergencia> {
  final _busqueda = TextEditingController();
  final _descripcion = TextEditingController();
  Equipo? _equipo;
  String _tipo = 'falla';
  List<Equipo> _resultados = const [];
  Timer? _espera;
  bool _enviando = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _equipo = widget.equipo;
  }

  @override
  void dispose() {
    _espera?.cancel();
    _busqueda.dispose();
    _descripcion.dispose();
    super.dispose();
  }

  void _buscar(String texto) {
    _espera?.cancel();
    _espera = Timer(const Duration(milliseconds: 350), () async {
      if (texto.trim().length < 2) {
        if (mounted) setState(() => _resultados = const []);
        return;
      }
      try {
        final pagina = await context.read<Sesion>().repositorio.equipos(busqueda: texto.trim());
        if (mounted) setState(() => _resultados = pagina.elementos.take(6).toList());
      } catch (e) {
        if (mounted) setState(() => _error = mensajeError(e));
      }
    });
  }

  Future<void> _enviar() async {
    if (_equipo == null) {
      setState(() => _error = 'Elija el ascensor.');
      return;
    }
    setState(() {
      _enviando = true;
      _error = null;
    });
    try {
      final r = await context.read<Sesion>().repositorio.reportarEmergencia(
        equipoId: _equipo!.id,
        tipo: _tipo,
        descripcion: _descripcion.text.trim().isEmpty ? null : _descripcion.text.trim(),
      );
      if (mounted) Navigator.of(context).pop(r.mensaje.isEmpty ? 'Emergencia ${r.emergencia.numero} reportada.' : r.mensaje);
    } catch (e) {
      if (mounted) setState(() => _error = mensajeError(e));
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 0, 16, MediaQuery.viewInsetsOf(context).bottom + 16),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Reportar emergencia', style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 14),
            if (_equipo != null)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.elevator_outlined),
                title: Text(_equipo!.codigo),
                subtitle: Text(_equipo!.edificioNombre ?? ''),
                trailing: widget.equipo == null ? TextButton(onPressed: () => setState(() => _equipo = null), child: const Text('Cambiar')) : null,
              )
            else ...[
              TextField(
                controller: _busqueda,
                autofocus: true,
                decoration: const InputDecoration(labelText: 'Ascensor', hintText: 'Código, edificio o ubicación', prefixIcon: Icon(Icons.search)),
                onChanged: _buscar,
              ),
              for (final equipo in _resultados)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.elevator_outlined),
                  title: Text(equipo.codigo),
                  subtitle: Text(equipo.edificioNombre ?? ''),
                  onTap: () => setState(() {
                    _equipo = equipo;
                    _resultados = const [];
                  }),
                ),
            ],
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _tipo,
              decoration: const InputDecoration(labelText: 'Qué pasa'),
              items: [for (final t in nombresTipoEmergencia.entries) DropdownMenuItem(value: t.key, child: Text(t.value))],
              onChanged: (valor) => setState(() => _tipo = valor ?? 'falla'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _descripcion,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(labelText: 'Descripción (opcional)', hintText: 'Piso, síntomas, cuántas personas…'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 10),
              Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
            ],
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: _enviando ? null : _enviar,
              icon: const Icon(Icons.campaign_outlined),
              label: Text(_enviando ? 'Enviando…' : 'Reportar emergencia'),
            ),
          ],
        ),
      ),
    );
  }
}

import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:signature/signature.dart';

import '../datos/modelos.dart';
import '../estado/sesion.dart';
import '../tema.dart';
import '../widgets/comunes.dart';

Color colorBRM(String estado) => switch (estado) {
  'B' => SigmaColores.bueno,
  'R' => SigmaColores.regular,
  'M' => SigmaColores.malo,
  _ => SigmaColores.textoSuave,
};

/// RF-030 a RF-038: planilla del preventivo por secciones. Editable solo con la orden en proceso
/// por un técnico asignado; los cambios se envían con "Guardar".
class PantallaPlanilla extends StatefulWidget {
  const PantallaPlanilla({super.key, required this.orden});

  final OrdenTrabajo orden;

  @override
  State<PantallaPlanilla> createState() => _PantallaPlanillaState();
}

class _PantallaPlanillaState extends State<PantallaPlanilla> {
  List<ItemChecklist>? _items;
  Map<int, ResultadoItem> _guardados = {};
  final Map<int, ResultadoItem> _cambios = {};
  final _recomendaciones = TextEditingController();
  String _recomendacionesGuardadas = '';
  int _fotos = 0;
  String? _error;
  bool _guardando = false;

  bool get _editable => widget.orden.puedeEjecutar && widget.orden.estado == 'en_proceso';

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  @override
  void dispose() {
    _recomendaciones.dispose();
    super.dispose();
  }

  Future<void> _cargar() async {
    final repositorio = context.read<Sesion>().repositorio;
    try {
      final items = await repositorio.catalogoChecklist();
      final planilla = await repositorio.planilla(widget.orden.id);
      if (!mounted) return;
      setState(() {
        _items = items;
        _aplicar(planilla);
      });
    } catch (e) {
      if (mounted) setState(() => _error = mensajeError(e));
    }
  }

  void _aplicar(Planilla planilla) {
    _guardados = planilla.resultados;
    _recomendacionesGuardadas = planilla.recomendaciones;
    _recomendaciones.text = planilla.recomendaciones;
    _fotos = planilla.fotos;
  }

  ResultadoItem _valor(int itemId) => _cambios[itemId] ?? _guardados[itemId] ?? ResultadoItem();

  void _cambiar(int itemId, void Function(ResultadoItem) cambio) {
    setState(() {
      final copia = (_cambios[itemId] ?? _guardados[itemId] ?? ResultadoItem()).copia();
      cambio(copia);
      _cambios[itemId] = copia;
    });
  }

  int get _pendientes => _cambios.length + (_recomendaciones.text != _recomendacionesGuardadas ? 1 : 0);

  Future<void> _guardar() async {
    final mensajero = ScaffoldMessenger.of(context);
    final incompletos = _cambios.entries.where((e) => !e.value.completo || e.value.faltaObservacion).toList();
    if (incompletos.isNotEmpty) {
      mensajero.showSnackBar(SnackBar(content: Text('${incompletos.length} ítem(s) sin resultado y estado, o en R/M sin observación.')));
      return;
    }
    setState(() => _guardando = true);
    try {
      final planilla = await context.read<Sesion>().repositorio.guardarPlanilla(
        widget.orden.id,
        Map.of(_cambios),
        recomendaciones: _recomendaciones.text.trim().isEmpty ? null : _recomendaciones.text.trim(),
      );
      if (!mounted) return;
      setState(() {
        _cambios.clear();
        _aplicar(planilla);
      });
      mensajero.showSnackBar(
        SnackBar(
          content: Text(
            planilla.faltantes == 0 ? 'Planilla guardada y completa.' : 'Planilla guardada. Faltan ${planilla.faltantes} ítems.',
          ),
        ),
      );
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    } finally {
      if (mounted) setState(() => _guardando = false);
    }
  }

  Future<void> _crearCorrectivo(ResultadoItem resultado) async {
    final mensajero = ScaffoldMessenger.of(context);
    try {
      final numero = await context.read<Sesion>().repositorio.crearCorrectivo(widget.orden.id, resultado.id!);
      mensajero.showSnackBar(SnackBar(content: Text('Correctivo $numero creado.')));
      _cargar();
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    }
  }

  Future<void> _agregarFoto(ImageSource fuente) async {
    final mensajero = ScaffoldMessenger.of(context);
    final repositorio = context.read<Sesion>().repositorio;
    try {
      final foto = await ImagePicker().pickImage(source: fuente, maxWidth: 1600, imageQuality: 80);
      if (foto == null) return;
      await repositorio.subirFotoPlanilla(widget.orden.id, await foto.readAsBytes(), foto.name);
      mensajero.showSnackBar(const SnackBar(content: Text('Foto agregada.')));
      _cargar();
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    }
  }

  @override
  Widget build(BuildContext context) {
    final items = _items;
    if (_error != null && items == null) {
      return Scaffold(
        appBar: AppBar(),
        body: VistaError(mensaje: _error!, alReintentar: _cargar),
      );
    }
    if (items == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Planilla')),
        body: const Cargando(),
      );
    }

    final hechos = items.where((i) => _valor(i.id).completo).length;

    return DefaultTabController(
      length: nombresSeccion.length + 1,
      child: Scaffold(
        appBar: AppBar(
          title: Text('Planilla · $hechos/${items.length}'),
          bottom: TabBar(
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            tabs: [
              for (final entrada in nombresSeccion.entries)
                Tab(
                  text:
                      '${entrada.value} ${items.where((i) => i.seccion == entrada.key && _valor(i.id).completo).length}/${items.where((i) => i.seccion == entrada.key).length}',
                ),
              const Tab(text: 'Cierre'),
            ],
          ),
        ),
        bottomNavigationBar: _editable
            ? SafeArea(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                  child: FilledButton.icon(
                    onPressed: _pendientes == 0 || _guardando ? null : _guardar,
                    icon: const Icon(Icons.save_outlined),
                    label: Text(_guardando ? 'Guardando…' : (_pendientes == 0 ? 'Todo guardado' : 'Guardar ($_pendientes)')),
                  ),
                ),
              )
            : null,
        body: TabBarView(
          children: [
            for (final seccion in nombresSeccion.keys)
              ListView(
                padding: const EdgeInsets.fromLTRB(12, 12, 12, 24),
                children: [
                  _resumen(items),
                  const SizedBox(height: 10),
                  for (final item in items.where((i) => i.seccion == seccion)) _filaItem(item),
                ],
              ),
            _cierre(),
          ],
        ),
      ),
    );
  }

  static String _textoResultado(String resultado) => switch (resultado) {
    'NA' => 'N.A.',
    '' => 'Resultado',
    _ => resultado,
  };

  static String _siguienteResultado(String actual) => switch (actual) {
    'INS' => 'EST',
    'EST' => 'NA',
    _ => 'INS',
  };

  /// Avance de toda la planilla con el conteo B / R / M (anillo del prototipo).
  Widget _resumen(List<ItemChecklist> items) {
    final valores = [for (final i in items) _valor(i.id)];
    final hechos = valores.where((v) => v.completo).length;
    int cuenta(String e) => valores.where((v) => v.estado == e).length;
    final suave = Theme.of(context).colorScheme.onSurfaceVariant;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Row(
          children: [
            AnilloAvance(valor: items.isEmpty ? 0 : hechos / items.length, tamano: 58),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '${items.isEmpty ? 0 : (hechos * 100 / items.length).round()} %',
                    style: const TextStyle(fontFamily: SigmaFuentes.titulos, fontSize: 24, fontWeight: FontWeight.w700),
                  ),
                  Text('$hechos de ${items.length} ítems', style: TextStyle(color: suave, fontSize: 12.5)),
                  Text('B ${cuenta('B')} · R ${cuenta('R')} · M ${cuenta('M')}', style: estiloEtiqueta(suave, tamano: 11.5)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _filaItem(ItemChecklist item) {
    final valor = _valor(item.id);
    final guardado = _guardados[item.id];
    final suave = Theme.of(context).colorScheme.onSurfaceVariant;

    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  item.codigo,
                  style: TextStyle(fontSize: 12, color: suave, fontFeatures: const [FontFeature.tabularFigures()]),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(item.nombre, style: const TextStyle(fontWeight: FontWeight.w600)),
                ),
                if (item.critico) const Icon(Icons.shield_outlined, size: 16, color: SigmaColores.malo),
              ],
            ),
            const SizedBox(height: 8),
            if (_editable)
              // Prototipo: B / R / M cuadrados y el resultado en un botón punteado que alterna INS → EST → N.A.
              Wrap(
                spacing: 8,
                runSpacing: 8,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  BotonesBRM(valor: valor.estado, alCambiar: (estado) => _cambiar(item.id, (r) => r.estado = estado)),
                  BotonCiclo(
                    texto: _textoResultado(valor.resultado),
                    alPresionar: () => _cambiar(item.id, (r) => r.resultado = _siguienteResultado(r.resultado)),
                  ),
                ],
              )
            else
              Row(
                children: [
                  if (valor.estado.isNotEmpty) ...[BotonesBRM(valor: valor.estado), const SizedBox(width: 8)],
                  Text(
                    valor.resultado.isEmpty ? 'Sin registrar' : _textoResultado(valor.resultado),
                    style: estiloEtiqueta(Theme.of(context).colorScheme.onSurfaceVariant, tamano: 12),
                  ),
                ],
              ),
            if (_editable && (valor.estado == 'R' || valor.estado == 'M' || valor.observacion.isNotEmpty))
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: TextFormField(
                  key: ValueKey('obs-${item.id}-${guardado?.observacion}'),
                  initialValue: valor.observacion,
                  decoration: InputDecoration(
                    labelText: valor.estado == 'R' || valor.estado == 'M' ? 'Observación (obligatoria)' : 'Observación',
                    errorText: valor.faltaObservacion ? 'R o M exigen observación' : null,
                    isDense: true,
                  ),
                  onChanged: (texto) => _cambiar(item.id, (r) => r.observacion = texto),
                ),
              )
            else if (!_editable && valor.observacion.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(valor.observacion, style: TextStyle(color: suave)),
              ),
            // RF-038: correctivo derivado desde un ítem guardado en R o M.
            if (guardado != null && (guardado.estado == 'R' || guardado.estado == 'M') && !_cambios.containsKey(item.id))
              Padding(
                padding: const EdgeInsets.only(top: 6),
                child: guardado.correctivoNumero != null
                    ? Text(
                        'Correctivo ${guardado.correctivoNumero}',
                        style: const TextStyle(color: SigmaColores.primario, fontWeight: FontWeight.w600),
                      )
                    : widget.orden.puedeEjecutar
                    ? OutlinedButton.icon(
                        onPressed: () => _crearCorrectivo(guardado),
                        icon: const Icon(Icons.build_outlined, size: 18),
                        label: const Text('Crear orden correctiva'),
                      )
                    : const SizedBox.shrink(),
              ),
          ],
        ),
      ),
    );
  }

  Widget _cierre() {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Seccion(
          titulo: 'Recomendaciones',
          hijos: [
            TextField(
              controller: _recomendaciones,
              enabled: _editable,
              minLines: 3,
              maxLines: 6,
              onChanged: (_) => setState(() {}),
              decoration: const InputDecoration(hintText: 'Se incluyen en el informe PDF.'),
            ),
          ],
        ),
        const SizedBox(height: 12),
        Seccion(
          titulo: 'Fotos',
          accion: Text('$_fotos/20'),
          hijos: [
            const Text('Las fotos se suben de inmediato y se incluyen en el informe PDF.'),
            if (_editable) ...[
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _agregarFoto(ImageSource.camera),
                      icon: const Icon(Icons.photo_camera_outlined),
                      label: const Text('Cámara'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _agregarFoto(ImageSource.gallery),
                      icon: const Icon(Icons.photo_library_outlined),
                      label: const Text('Galería'),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ],
    );
  }
}

/// RF-025: firmas del técnico y de quien recibe el servicio.
class PantallaFirmas extends StatefulWidget {
  const PantallaFirmas({super.key, required this.orden});

  final OrdenTrabajo orden;

  @override
  State<PantallaFirmas> createState() => _PantallaFirmasState();
}

class _PantallaFirmasState extends State<PantallaFirmas> {
  final _controladores = {
    'tecnico': SignatureController(penStrokeWidth: 3, penColor: Colors.black),
    'cliente': SignatureController(penStrokeWidth: 3, penColor: Colors.black),
  };
  late final _nombres = {
    'tecnico': TextEditingController(
      text: widget.orden.firmas['tecnico'] ?? (widget.orden.tecnicos.isNotEmpty ? widget.orden.tecnicos.first : ''),
    ),
    'cliente': TextEditingController(text: widget.orden.firmas['cliente'] ?? ''),
  };
  final _cargo = TextEditingController();
  late final Map<String, String> _registradas = Map.of(widget.orden.firmas);
  String? _guardando;

  @override
  void dispose() {
    for (final c in _controladores.values) {
      c.dispose();
    }
    for (final c in _nombres.values) {
      c.dispose();
    }
    _cargo.dispose();
    super.dispose();
  }

  Future<void> _guardar(String tipo) async {
    final mensajero = ScaffoldMessenger.of(context);
    final nombre = _nombres[tipo]!.text.trim();
    if (nombre.length < 3) {
      mensajero.showSnackBar(const SnackBar(content: Text('Escriba el nombre de quien firma.')));
      return;
    }
    final png = await _controladores[tipo]!.toPngBytes();
    if (png == null) {
      mensajero.showSnackBar(const SnackBar(content: Text('Dibuje la firma antes de guardar.')));
      return;
    }
    if (!mounted) return;
    setState(() => _guardando = tipo);
    try {
      await context.read<Sesion>().repositorio.firmarOrden(
        widget.orden.id,
        tipo: tipo,
        nombre: nombre,
        pngBase64: base64Encode(png),
        cargo: tipo == 'cliente' && _cargo.text.trim().isNotEmpty ? _cargo.text.trim() : null,
      );
      setState(() => _registradas[tipo] = nombre);
      mensajero.showSnackBar(const SnackBar(content: Text('Firma registrada.')));
    } catch (e) {
      mensajero.showSnackBar(SnackBar(content: Text(mensajeError(e))));
    } finally {
      if (mounted) setState(() => _guardando = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    const titulos = {'tecnico': 'Firma del técnico', 'cliente': 'Firma de quien recibe'};
    return Scaffold(
      appBar: AppBar(title: const Text('Firmas')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          for (final tipo in const ['tecnico', 'cliente']) ...[
            Seccion(
              titulo: titulos[tipo]!,
              accion: _registradas.containsKey(tipo) ? const Insignia(texto: 'Registrada', color: SigmaColores.bueno) : null,
              hijos: [
                TextField(
                  controller: _nombres[tipo],
                  decoration: const InputDecoration(labelText: 'Nombre'),
                ),
                if (tipo == 'cliente') ...[
                  const SizedBox(height: 8),
                  TextField(
                    controller: _cargo,
                    decoration: const InputDecoration(labelText: 'Cargo (opcional)'),
                  ),
                ],
                const SizedBox(height: 10),
                Container(
                  height: 150,
                  decoration: BoxDecoration(
                    border: Border.all(color: SigmaColores.borde),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Signature(controller: _controladores[tipo]!, backgroundColor: Colors.white),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    TextButton.icon(
                      onPressed: _controladores[tipo]!.clear,
                      icon: const Icon(Icons.cleaning_services_outlined),
                      label: const Text('Borrar'),
                    ),
                    const Spacer(),
                    FilledButton(
                      onPressed: _guardando != null ? null : () => _guardar(tipo),
                      child: Text(
                        _guardando == tipo ? 'Guardando…' : (_registradas.containsKey(tipo) ? 'Volver a firmar' : 'Guardar firma'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
            const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }
}

import 'dart:async';
import 'dart:convert';
import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:signature/signature.dart';

import '../datos/modelos.dart';
import '../estado/sesion.dart';
import '../estado/visitas.dart';
import '../tema.dart';
import '../widgets/comunes.dart';

/// Planilla de asistencia técnica (control de mantenimiento y reparaciones) que el técnico diligencia en la visita.
///
/// Las secciones y actividades vienen de la plantilla que edita el administrador; se guarda en el dispositivo
/// mientras se llena (funciona sin conexión) y al enviarla el servidor genera el PDF con el formato de la planilla.
class PantallaVisita extends StatefulWidget {
  const PantallaVisita({super.key, required this.equipo, this.almacen});

  final Equipo equipo;
  final AlmacenVisitas? almacen;

  @override
  State<PantallaVisita> createState() => _PantallaVisitaState();
}

class _PantallaVisitaState extends State<PantallaVisita> {
  static const _maximoFotos = 6;

  late final AlmacenVisitas _almacen = widget.almacen ?? AlmacenVisitas();
  VisitaBorrador? _visita;
  String? _error;
  Timer? _guardado;
  bool _enviando = false;

  final _observaciones = TextEditingController();
  final _repuesto = TextEditingController();
  final _cantidad = TextEditingController();
  final _nombreRecibe = TextEditingController();
  final _documentoRecibe = TextEditingController();
  final _documentoTecnico = TextEditingController();
  final Map<int, TextEditingController> _observacionItem = {};
  final _selectorImagen = ImagePicker();
  late final SignatureController _firma = SignatureController(penStrokeWidth: 3, penColor: Colors.black, onDrawEnd: _guardarFirma);
  late final SignatureController _firmaRecibe = SignatureController(penStrokeWidth: 3, penColor: Colors.black, onDrawEnd: _guardarFirmaRecibe);

  @override
  void initState() {
    super.initState();
    _abrir();
  }

  /// Recupera el borrador o empieza uno con la plantilla vigente (la del servidor o la guardada sin conexión).
  Future<void> _abrir() async {
    final repositorio = context.read<Sesion>().repositorio;
    final guardada = await _almacen.leer(widget.equipo.id);
    List<SeccionPlanilla>? plantilla;
    try {
      plantilla = await repositorio.plantillaPlanilla();
      if (plantilla.isNotEmpty) await _almacen.guardarPlantilla(plantilla);
    } catch (_) {
      plantilla = await _almacen.leerPlantilla();
    }

    if (guardada == null && (plantilla == null || plantilla.isEmpty)) {
      if (mounted) setState(() => _error = 'Conéctese a internet para descargar la planilla la primera vez.');
      return;
    }
    final visita = guardada ?? VisitaBorrador(equipoId: widget.equipo.id, equipoCodigo: widget.equipo.codigo, plantilla: plantilla!);
    if (guardada != null && plantilla != null && plantilla.isNotEmpty) visita.actualizarPlantilla(plantilla);

    _observaciones.text = visita.observaciones;
    _nombreRecibe.text = visita.nombreRecibe;
    _documentoRecibe.text = visita.documentoRecibe;
    _documentoTecnico.text = visita.documentoTecnico;
    for (final entrada in visita.respuestas.entries) {
      _observacionItem[entrada.key] = TextEditingController(text: entrada.value.observacion);
    }
    if (!mounted) return;
    setState(() => _visita = visita);
    if (guardada != null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Se recuperó la planilla guardada en el dispositivo.')));
    }
  }

  @override
  void dispose() {
    _guardado?.cancel();
    if (_visita != null) _almacen.guardar(_visita!);
    for (final c in [_observaciones, _repuesto, _cantidad, _nombreRecibe, _documentoRecibe, _documentoTecnico, ..._observacionItem.values]) {
      c.dispose();
    }
    _firma.dispose();
    _firmaRecibe.dispose();
    super.dispose();
  }

  /// Guarda el borrador poco después de cada cambio.
  void _cambio(VoidCallback accion) {
    setState(accion);
    _guardado?.cancel();
    _guardado = Timer(const Duration(milliseconds: 500), () => _almacen.guardar(_visita!));
  }

  Future<void> _guardarFirma() async {
    final png = await _firma.toPngBytes();
    if (png != null) _cambio(() => _visita!.firmaBase64 = base64Encode(png));
  }

  Future<void> _guardarFirmaRecibe() async {
    final png = await _firmaRecibe.toPngBytes();
    if (png != null) _cambio(() => _visita!.firmaRecibeBase64 = base64Encode(png));
  }

  void _aviso(String texto) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(texto)));

  void _agregarRepuesto() {
    final nombre = _repuesto.text.trim();
    final cantidad = int.tryParse(_cantidad.text.trim());
    if (nombre.isEmpty || cantidad == null || cantidad <= 0) {
      _aviso('Escriba el repuesto y una cantidad mayor que cero');
      return;
    }
    _cambio(() => _visita!.repuestos.add(Repuesto(nombre, cantidad)));
    _repuesto.clear();
    _cantidad.clear();
  }

  Future<void> _tomarFoto(ImageSource origen) async {
    if (_visita!.fotos.length >= _maximoFotos) {
      _aviso('Puede adjuntar hasta $_maximoFotos fotografías por visita.');
      return;
    }
    try {
      final imagen = await _selectorImagen.pickImage(source: origen, maxWidth: 1280, imageQuality: 60);
      if (imagen == null) return;
      final datos = await imagen.readAsBytes();
      _cambio(() => _visita!.fotos.add(base64Encode(datos)));
    } catch (_) {
      _aviso('No fue posible obtener la fotografía.');
    }
  }

  Future<void> _enviar() async {
    final visita = _visita!;
    if (!visita.listaParaEnviar) {
      _aviso(visita.pendientes.join(' · '));
      return;
    }
    setState(() => _enviando = true);
    try {
      final guardado = await context.read<Sesion>().repositorio.registrarVisita(widget.equipo.id, visita.paraEnviar(), codigo: widget.equipo.codigo);
      // Enviada o en la cola de envíos (que ya lleva todos los datos): el borrador ya no hace falta.
      await _almacen.borrar(widget.equipo.id);
      _visita = null;
      if (!mounted) return;
      _aviso(
        guardado.enCola
            ? 'Sin conexión: la planilla quedó lista en el celular y se enviará sola al tener internet. El PDF se genera al llegar.'
            : 'Planilla N.º ${guardado.valor?['numero']} registrada. El PDF quedó en la ficha del ascensor.',
      );
      Navigator.of(context).pop(true);
    } catch (e) {
      _aviso('${mensajeError(e)} La planilla sigue guardada en el dispositivo.');
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  Future<void> _descartar() async {
    final confirmar = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Descartar planilla'),
        content: const Text('Se borrará lo diligenciado en el dispositivo para esta visita.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(contexto, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(contexto, true), child: const Text('Descartar')),
        ],
      ),
    );
    if (confirmar != true) return;
    await _almacen.borrar(widget.equipo.id);
    _visita = null;
    if (mounted) Navigator.of(context).pop(false);
  }

  String _hora(DateTime fecha) => '${fecha.hour.toString().padLeft(2, '0')}:${fecha.minute.toString().padLeft(2, '0')}';

  @override
  Widget build(BuildContext context) {
    if (_error != null) {
      return Scaffold(appBar: AppBar(title: const Text('Planilla de asistencia técnica')), body: VistaError(mensaje: _error!, alReintentar: () {
        setState(() => _error = null);
        _abrir();
      }));
    }
    final visita = _visita;
    if (visita == null) return Scaffold(appBar: AppBar(), body: const Cargando());

    final suave = Theme.of(context).colorScheme.onSurfaceVariant;
    final tecnico = context.read<Sesion>().usuario?.nombre ?? 'Técnico';
    final conteo = visita.conteo;

    return Scaffold(
      appBar: AppBar(
        title: Text('Planilla · ${widget.equipo.codigo}'),
        actions: [IconButton(tooltip: 'Descartar planilla', onPressed: _descartar, icon: const Icon(Icons.delete_outline))],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
        children: [
          // ENCABEZADO DE LA PLANILLA
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(color: const Color(0xFF14294A), borderRadius: BorderRadius.circular(14)),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text('PLANILLA DE ASISTENCIA TÉCNICA', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 16)),
                const Text('Control de mantenimiento y reparaciones', style: TextStyle(color: Color(0xFFC9D3E6), fontSize: 12)),
                const SizedBox(height: 10),
                _datoEncabezado('Edificio', [widget.equipo.edificioNombre, 'Ascensor ${widget.equipo.codigo}'].whereType<String>().join(' · ')),
                if ((widget.equipo.ubicacion ?? '').isNotEmpty) _datoEncabezado('Ubicación', widget.equipo.ubicacion!),
                _datoEncabezado('Técnico', tecnico),
                _datoEncabezado('Fecha', '${visita.inicio.day.toString().padLeft(2, '0')}/${visita.inicio.month.toString().padLeft(2, '0')}/${visita.inicio.year}'),
                _datoEncabezado('Entrada / salida', '${_hora(visita.inicio)} · la salida se registra al enviar'),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              const Icon(Icons.save_outlined, size: 16, color: SigmaColores.cian),
              const SizedBox(width: 6),
              Expanded(child: Text('Se guarda en el dispositivo mientras la llena.', style: TextStyle(color: suave, fontSize: 12.5))),
            ],
          ),
          const SizedBox(height: 12),

          // TIPO DE SERVICIO
          SegmentedButton<String>(
            segments: [for (final entrada in tiposServicio.entries) ButtonSegment(value: entrada.key, label: Text(entrada.value))],
            selected: {visita.tipoServicio},
            showSelectedIcon: false,
            onSelectionChanged: (valor) => _cambio(() => visita.tipoServicio = valor.first),
          ),
          const SizedBox(height: 14),

          // AVANCE
          Row(
            children: [
              Expanded(child: Text('${visita.respondidos} de ${visita.totalItems} actividades', style: const TextStyle(fontWeight: FontWeight.w600))),
              Text('B ${conteo['B']} · R ${conteo['R']} · M ${conteo['M']} · N.A. ${conteo['NA']}', style: TextStyle(color: suave, fontSize: 12.5)),
            ],
          ),
          const SizedBox(height: 6),
          ClipRRect(
            borderRadius: BorderRadius.circular(5),
            child: LinearProgressIndicator(value: visita.totalItems == 0 ? 0 : visita.respondidos / visita.totalItems, minHeight: 6),
          ),
          const SizedBox(height: 6),
          Text('INS = Inspección · B Bueno · R Regular · M Malo · N.A. No aplica. R o M exigen observación.', style: TextStyle(fontSize: 12, color: suave)),
          const SizedBox(height: 10),

          // SECCIONES DE LA PLANILLA
          for (final (indice, seccion) in visita.plantilla.indexed)
            Card(
              margin: const EdgeInsets.only(bottom: 10),
              clipBehavior: Clip.antiAlias,
              child: ExpansionTile(
                key: PageStorageKey('seccion-${seccion.clave}'),
                initiallyExpanded: indice == 0,
                backgroundColor: Theme.of(context).colorScheme.surface,
                title: Text(seccion.nombre.toUpperCase(), style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                subtitle: Text('${visita.respondidosEn(seccion)}/${seccion.items.length} marcadas', style: TextStyle(color: suave, fontSize: 12)),
                childrenPadding: const EdgeInsets.fromLTRB(14, 0, 14, 10),
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton.icon(
                      onPressed: () => _cambio(() => visita.todoBueno(seccion)),
                      icon: const Icon(Icons.done_all, size: 18),
                      label: const Text('Marcar pendientes en B'),
                    ),
                  ),
                  for (final item in seccion.items) _filaItem(item, visita.respuesta(item.id)),
                ],
              ),
            ),
          const SizedBox(height: 4),

          // OBSERVACIONES
          Seccion(
            titulo: 'Observaciones',
            hijos: [
              TextField(
                key: const Key('campo-observaciones'),
                controller: _observaciones,
                maxLines: 4,
                onChanged: (texto) => _cambio(() => visita.observaciones = texto),
                decoration: const InputDecoration(hintText: 'Trabajo realizado, hallazgos y recomendaciones…', alignLabelWithHint: true),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // REPUESTOS
          Seccion(
            titulo: 'Repuestos utilizados',
            hijos: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(flex: 3, child: TextField(controller: _repuesto, decoration: const InputDecoration(labelText: 'Repuesto', isDense: true))),
                  const SizedBox(width: 8),
                  Expanded(
                    flex: 2,
                    child: TextField(controller: _cantidad, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Cantidad', isDense: true)),
                  ),
                  IconButton.filledTonal(onPressed: _agregarRepuesto, tooltip: 'Añadir repuesto', icon: const Icon(Icons.add)),
                ],
              ),
              for (final (indice, repuesto) in visita.repuestos.indexed)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  dense: true,
                  leading: const Icon(Icons.build_outlined),
                  title: Text(repuesto.nombre),
                  subtitle: Text('Cantidad: ${repuesto.cantidad}'),
                  trailing: IconButton(
                    icon: const Icon(Icons.delete_outline, color: SigmaColores.malo),
                    tooltip: 'Quitar',
                    onPressed: () => _cambio(() => visita.repuestos.removeAt(indice)),
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),

          // FOTOS (apoyo del técnico, quedan en el dispositivo)
          Seccion(
            titulo: 'Fotos de apoyo',
            accion: Text('${visita.fotos.length}/$_maximoFotos', style: TextStyle(color: suave)),
            hijos: [
              Row(
                children: [
                  Expanded(child: OutlinedButton.icon(onPressed: () => _tomarFoto(ImageSource.camera), icon: const Icon(Icons.photo_camera_outlined), label: const Text('Cámara'))),
                  const SizedBox(width: 8),
                  Expanded(child: OutlinedButton.icon(onPressed: () => _tomarFoto(ImageSource.gallery), icon: const Icon(Icons.photo_library_outlined), label: const Text('Galería'))),
                ],
              ),
              const SizedBox(height: 10),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final (indice, foto) in visita.fotos.indexed)
                    Stack(
                      children: [
                        ClipRRect(borderRadius: BorderRadius.circular(10), child: Image.memory(base64Decode(foto), width: 88, height: 88, fit: BoxFit.cover)),
                        Positioned(
                          top: 2,
                          right: 2,
                          child: IconButton.filledTonal(
                            iconSize: 16,
                            visualDensity: VisualDensity.compact,
                            tooltip: 'Quitar foto',
                            onPressed: () => _cambio(() => visita.fotos.removeAt(indice)),
                            icon: const Icon(Icons.close),
                          ),
                        ),
                      ],
                    ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 12),

          // FIRMAS
          Seccion(
            titulo: 'Recibido por el edificio',
            accion: visita.tieneFirmaRecibe ? const Insignia(texto: 'Firmado', color: SigmaColores.bueno) : null,
            hijos: [
              TextField(
                key: const Key('nombre-recibe'),
                controller: _nombreRecibe,
                textCapitalization: TextCapitalization.words,
                decoration: const InputDecoration(labelText: 'Nombre de quien recibe'),
                onChanged: (texto) => _cambio(() => visita.nombreRecibe = texto),
              ),
              const SizedBox(height: 10),
              TextField(
                key: const Key('documento-recibe'),
                controller: _documentoRecibe,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'C.C. de quien recibe'),
                onChanged: (texto) => _cambio(() => visita.documentoRecibe = texto),
              ),
              const SizedBox(height: 10),
              _recuadroFirma(controlador: _firmaRecibe, guardada: visita.firmaRecibeBase64, alBorrar: () => visita.firmaRecibeBase64 = null),
            ],
          ),
          const SizedBox(height: 12),
          Seccion(
            titulo: 'Realizado por (técnico)',
            accion: visita.tieneFirma ? const Insignia(texto: 'Firmado', color: SigmaColores.bueno) : null,
            hijos: [
              TextField(
                controller: _documentoTecnico,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Su C.C. (opcional, si no se toma la registrada)'),
                onChanged: (texto) => _cambio(() => visita.documentoTecnico = texto),
              ),
              const SizedBox(height: 10),
              _recuadroFirma(controlador: _firma, guardada: visita.firmaBase64, alBorrar: () => visita.firmaBase64 = null),
            ],
          ),
          const SizedBox(height: 18),

          if (!visita.listaParaEnviar)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text('${visita.pendientes.join(' · ')}.', style: TextStyle(color: suave), textAlign: TextAlign.center),
            ),
          FilledButton.icon(
            key: const Key('boton-enviar-visita'),
            onPressed: _enviando || !visita.listaParaEnviar ? null : _enviar,
            icon: const Icon(Icons.cloud_upload_outlined),
            label: Text(_enviando ? 'Enviando…' : 'Enviar planilla'),
          ),
        ],
      ),
    );
  }

  Widget _datoEncabezado(String etiqueta, String valor) => Padding(
    padding: const EdgeInsets.only(top: 3),
    child: Text.rich(
      TextSpan(
        children: [
          TextSpan(text: '$etiqueta: ', style: const TextStyle(color: Color(0xFFC9D3E6), fontWeight: FontWeight.w600)),
          TextSpan(text: valor, style: const TextStyle(color: Colors.white)),
        ],
      ),
      style: const TextStyle(fontSize: 13),
    ),
  );

  Widget _filaItem(ItemPlanilla item, RespuestaItem respuesta) {
    final necesitaObservacion = !respuesta.na && (respuesta.estado == 'R' || respuesta.estado == 'M');
    final esquema = Theme.of(context).colorScheme;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (item.critico) const Padding(padding: EdgeInsets.only(right: 4, top: 1), child: Icon(Icons.shield_outlined, size: 16, color: SigmaColores.malo)),
              Expanded(child: Text(item.nombre, style: const TextStyle(fontWeight: FontWeight.w500))),
            ],
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              BotonesBRM(
                valor: respuesta.na ? '' : respuesta.estado,
                ancho: 44,
                alCambiar: (elegido) {
                  _cambio(() {
                    respuesta.na = false;
                    respuesta.estado = elegido == respuesta.estado ? '' : elegido;
                  });
                  if (respuesta.estado == 'M' && item.critico) _aviso('Actividad crítica en Malo: avise al coordinador.');
                },
              ),
              const SizedBox(width: 8),
              Semantics(
                button: true,
                selected: respuesta.na,
                label: 'No aplica',
                child: InkWell(
                  onTap: () => _cambio(() {
                    respuesta.na = !respuesta.na;
                    if (respuesta.na) respuesta.estado = '';
                  }),
                  borderRadius: BorderRadius.circular(10),
                  child: AnimatedContainer(
                    duration: const Duration(milliseconds: 150),
                    height: 38,
                    padding: const EdgeInsets.symmetric(horizontal: 12),
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: respuesta.na ? esquema.secondaryContainer : esquema.surface,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: respuesta.na ? esquema.secondary : SigmaColores.borde),
                    ),
                    child: Text('N.A.', style: TextStyle(fontWeight: FontWeight.w700, color: respuesta.na ? esquema.onSecondaryContainer : esquema.onSurfaceVariant)),
                  ),
                ),
              ),
            ],
          ),
          if (necesitaObservacion)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: TextField(
                controller: _observacionItem.putIfAbsent(item.id, () => TextEditingController(text: respuesta.observacion)),
                onChanged: (texto) => _cambio(() => respuesta.observacion = texto),
                decoration: InputDecoration(
                  isDense: true,
                  labelText: 'Observación (obligatoria)',
                  errorText: respuesta.faltaObservacion ? 'Obligatoria para Regular o Malo' : null,
                  prefixIcon: Icon(Icons.circle, size: 12, color: colorEstadoRevision(respuesta.estado)),
                ),
              ),
            ),
        ],
      ),
    );
  }

  /// Recuadro de firma: muestra la guardada o el lienzo para firmar.
  Widget _recuadroFirma({required SignatureController controlador, required String? guardada, required VoidCallback alBorrar}) {
    final hayGuardada = guardada != null && guardada.isNotEmpty;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          height: 150,
          decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(10), border: Border.all(color: SigmaColores.borde)),
          clipBehavior: Clip.antiAlias,
          child: hayGuardada && controlador.isEmpty
              ? Image.memory(Uint8List.fromList(base64Decode(guardada)))
              : Signature(controller: controlador, backgroundColor: Colors.white),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: () => _cambio(() {
            controlador.clear();
            alBorrar();
          }),
          icon: const Icon(Icons.refresh),
          label: const Text('Borrar firma'),
        ),
      ],
    );
  }
}

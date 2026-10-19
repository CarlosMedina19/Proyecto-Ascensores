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

/// Registro de una visita de mantenimiento desde la app del técnico.
///
/// Se guarda automáticamente en el dispositivo (funciona sin conexión y no se
/// pierde al cerrar la sesión). Al enviarla, el resumen queda en el historial
/// del equipo (RF-009). El checklist completo por ítem, el informe PDF y la
/// carga de fotos y firmas al servidor llegan en la fase 4.
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
  Timer? _guardado;
  bool _enviando = false;

  final _diagnostico = TextEditingController();
  final _observaciones = TextEditingController();
  final _repuesto = TextEditingController();
  final _cantidad = TextEditingController();
  final Map<String, TextEditingController> _observacionZona = {};
  final _selectorImagen = ImagePicker();
  late final SignatureController _firma = SignatureController(penStrokeWidth: 3, penColor: Colors.black, onDrawEnd: _guardarFirma);

  @override
  void initState() {
    super.initState();
    _abrir();
  }

  Future<void> _abrir() async {
    final guardada = await _almacen.leer(widget.equipo.id);
    final visita = guardada ?? VisitaBorrador(equipoId: widget.equipo.id, equipoCodigo: widget.equipo.codigo);
    _diagnostico.text = visita.diagnostico;
    _observaciones.text = visita.observaciones;
    for (final zona in visita.zonas.entries) {
      _observacionZona[zona.key] = TextEditingController(text: zona.value.observacion);
    }
    if (mounted) setState(() => _visita = visita);
    if (guardada != null && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Se recuperó la visita guardada en el dispositivo.')));
    }
  }

  @override
  void dispose() {
    _guardado?.cancel();
    if (_visita != null) _almacen.guardar(_visita!);
    _diagnostico.dispose();
    _observaciones.dispose();
    _repuesto.dispose();
    _cantidad.dispose();
    for (final c in _observacionZona.values) {
      c.dispose();
    }
    _firma.dispose();
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

  void _agregarRepuesto() {
    final nombre = _repuesto.text.trim();
    final cantidad = int.tryParse(_cantidad.text.trim());
    if (nombre.isEmpty || _cantidad.text.trim().isEmpty) {
      _aviso('Escribe el repuesto y la cantidad');
      return;
    }
    if (cantidad == null || cantidad <= 0) {
      _aviso('La cantidad debe ser un número mayor que cero');
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

  void _aviso(String texto) => ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(texto)));

  Future<void> _enviar() async {
    final visita = _visita!;
    if (!visita.listaParaEnviar) {
      _aviso(visita.pendientes.join(' · '));
      return;
    }
    setState(() => _enviando = true);
    try {
      await context.read<Sesion>().repositorio.registrarEvento(widget.equipo.id, evento: 'mantenimiento', descripcion: visita.resumen());
      await _almacen.borrar(widget.equipo.id);
      _visita = null;
      if (!mounted) return;
      _aviso('Visita registrada en el historial del ascensor.');
      Navigator.of(context).pop(true);
    } catch (e) {
      _aviso('${mensajeError(e)} La visita sigue guardada en el dispositivo.');
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  Future<void> _descartar() async {
    final confirmar = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Descartar visita'),
        content: const Text('Se borrará la información guardada en el dispositivo para esta visita.'),
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

  @override
  Widget build(BuildContext context) {
    final visita = _visita;
    if (visita == null) return Scaffold(appBar: AppBar(), body: const Cargando());

    final total = visita.zonas.length;
    final revisadas = visita.zonasRevisadas;
    final suave = Theme.of(context).colorScheme.onSurfaceVariant;

    return Scaffold(
      appBar: AppBar(
        title: Text('Visita · ${widget.equipo.codigo}'),
        actions: [IconButton(tooltip: 'Descartar visita', onPressed: _descartar, icon: const Icon(Icons.delete_outline))],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 32),
        children: [
          Text([widget.equipo.edificioNombre, widget.equipo.ubicacion].whereType<String>().join(' · '), style: TextStyle(color: suave)),
          const SizedBox(height: 10),
          Row(
            children: [
              Icon(Icons.save_outlined, size: 16, color: SigmaColores.cian),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Guardado en el dispositivo · iniciada ${fechaHora(visita.inicio)}',
                  style: const TextStyle(color: SigmaColores.cian, fontSize: 13),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // LISTA DE REVISIÓN POR ZONA
          Seccion(
            titulo: 'Revisión por zona',
            accion: Text('$revisadas de $total', style: TextStyle(color: suave)),
            hijos: [
              ClipRRect(
                borderRadius: BorderRadius.circular(5),
                child: LinearProgressIndicator(value: revisadas / total, minHeight: 6),
              ),
              const SizedBox(height: 6),
              Text('B = Bueno · R = Regular · M = Malo. Regular o Malo exigen observación.', style: TextStyle(fontSize: 12, color: suave)),
              const SizedBox(height: 8),
              for (final zona in visita.zonas.entries) _filaZona(zona.key, zona.value),
            ],
          ),
          const SizedBox(height: 12),

          // ACTIVIDADES REALIZADAS
          Seccion(
            titulo: 'Actividades realizadas',
            accion: Text('${visita.actividades.length} de ${actividadesVisita.length}', style: TextStyle(color: suave)),
            hijos: [
              for (final actividad in actividadesVisita)
                CheckboxListTile(
                  contentPadding: EdgeInsets.zero,
                  title: Text(actividad),
                  value: visita.actividades.contains(actividad),
                  onChanged: (marcado) => _cambio(() {
                    if (marcado == true) {
                      visita.actividades.add(actividad);
                    } else {
                      visita.actividades.remove(actividad);
                    }
                  }),
                ),
            ],
          ),
          const SizedBox(height: 12),

          // DIAGNÓSTICO Y OBSERVACIONES
          Seccion(
            titulo: 'Diagnóstico del técnico',
            hijos: [
              TextField(
                key: const Key('campo-diagnostico'),
                controller: _diagnostico,
                maxLines: 3,
                onChanged: (texto) => _cambio(() => visita.diagnostico = texto),
                decoration: const InputDecoration(
                  labelText: '¿Qué encontraste?',
                  hintText: 'Describe el estado o la falla del ascensor…',
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _observaciones,
                maxLines: 3,
                onChanged: (texto) => _cambio(() => visita.observaciones = texto),
                decoration: const InputDecoration(
                  labelText: 'Observaciones',
                  hintText: 'Describe el trabajo realizado…',
                  alignLabelWithHint: true,
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // REPUESTOS UTILIZADOS
          Seccion(
            titulo: 'Repuestos utilizados',
            hijos: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    flex: 3,
                    child: TextField(
                      controller: _repuesto,
                      decoration: const InputDecoration(labelText: 'Repuesto', hintText: 'Ej. Rodamiento', isDense: true),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    flex: 2,
                    child: TextField(
                      controller: _cantidad,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Cantidad', isDense: true),
                    ),
                  ),
                  IconButton.filledTonal(onPressed: _agregarRepuesto, tooltip: 'Añadir repuesto', icon: const Icon(Icons.add)),
                ],
              ),
              const SizedBox(height: 6),
              if (visita.repuestos.isEmpty)
                Text('Todavía no has agregado repuestos.', style: TextStyle(color: suave))
              else
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
              Text('El descuento de inventario se habilita en la fase 5.', style: TextStyle(fontSize: 12, color: suave)),
            ],
          ),
          const SizedBox(height: 12),

          // FOTOS DEL MANTENIMIENTO
          Seccion(
            titulo: 'Fotos del mantenimiento',
            accion: Text('${visita.fotos.length}/$_maximoFotos', style: TextStyle(color: suave)),
            hijos: [
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _tomarFoto(ImageSource.camera),
                      icon: const Icon(Icons.photo_camera_outlined),
                      label: const Text('Cámara'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () => _tomarFoto(ImageSource.gallery),
                      icon: const Icon(Icons.photo_library_outlined),
                      label: const Text('Galería'),
                    ),
                  ),
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
                        ClipRRect(
                          borderRadius: BorderRadius.circular(10),
                          child: Image.memory(base64Decode(foto), width: 96, height: 96, fit: BoxFit.cover),
                        ),
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
              Text('Las fotos quedan en el dispositivo; se subirán al informe en la fase 4.', style: TextStyle(fontSize: 12, color: suave)),
            ],
          ),
          const SizedBox(height: 12),

          // FIRMA DEL TÉCNICO
          Seccion(
            titulo: 'Firma del técnico',
            accion: visita.tieneFirma ? const Insignia(texto: 'Registrada', color: SigmaColores.bueno) : null,
            hijos: [
              if (visita.tieneFirma && _firma.isEmpty)
                Container(
                  height: 160,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: SigmaColores.borde),
                  ),
                  child: Image.memory(Uint8List.fromList(base64Decode(visita.firmaBase64!))),
                )
              else
                Container(
                  height: 160,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: SigmaColores.borde),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Signature(controller: _firma, backgroundColor: Colors.white),
                ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => _cambio(() {
                  _firma.clear();
                  visita.firmaBase64 = null;
                }),
                icon: const Icon(Icons.refresh),
                label: const Text('Borrar firma'),
              ),
            ],
          ),
          const SizedBox(height: 18),

          if (!visita.listaParaEnviar)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(
                '${visita.pendientes.join(' · ')}.',
                style: TextStyle(color: suave),
                textAlign: TextAlign.center,
              ),
            ),
          FilledButton.icon(
            key: const Key('boton-enviar-visita'),
            onPressed: _enviando || !visita.listaParaEnviar ? null : _enviar,
            icon: const Icon(Icons.cloud_upload_outlined),
            label: Text(_enviando ? 'Enviando…' : 'Enviar al historial del ascensor'),
          ),
        ],
      ),
    );
  }

  Widget _filaZona(String nombre, RevisionZona revision) {
    final necesitaObservacion = revision.estado == 'R' || revision.estado == 'M';
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(nombre, style: const TextStyle(fontWeight: FontWeight.w500)),
              ),
              SegmentedButton<String>(
                showSelectedIcon: false,
                emptySelectionAllowed: true,
                style: const ButtonStyle(visualDensity: VisualDensity.compact),
                segments: [
                  for (final estado in ['B', 'R', 'M']) ButtonSegment(value: estado, label: Text(estado), tooltip: nombresEstado[estado]),
                ],
                selected: revision.estado.isEmpty ? <String>{} : {revision.estado},
                onSelectionChanged: (seleccion) {
                  final estado = seleccion.isEmpty ? '' : seleccion.first;
                  _cambio(() => revision.estado = estado);
                  if (estado == 'M') _aviso('$nombre en Malo: informe al coordinador.');
                },
              ),
            ],
          ),
          if (necesitaObservacion)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: TextField(
                controller: _observacionZona[nombre],
                onChanged: (texto) => _cambio(() => revision.observacion = texto),
                decoration: InputDecoration(
                  isDense: true,
                  labelText: 'Observación (obligatoria)',
                  hintText: 'Describe el hallazgo',
                  errorText: revision.faltaObservacion ? 'Obligatoria para Regular o Malo' : null,
                  prefixIcon: Icon(Icons.circle, size: 12, color: colorEstadoRevision(revision.estado)),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

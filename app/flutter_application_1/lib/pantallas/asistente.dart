import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../datos/cliente_api.dart';
import '../datos/modelos.dart';
import '../estado/sesion.dart';
import 'equipos.dart';
import 'ordenes.dart';

class _Mensaje {
  _Mensaje.usuario(this.texto, {this.foto}) : delUsuario = true, acciones = const [], sugerencias = const [];
  _Mensaje.asistente(RespuestaAsistente r)
    : delUsuario = false,
      texto = r.texto,
      foto = null,
      acciones = r.acciones.where((a) => a.equipoId != null || a.ordenId != null).toList(),
      sugerencias = r.sugerencias;
  _Mensaje.error(this.texto) : delUsuario = false, foto = null, acciones = const [], sugerencias = const [];

  final bool delUsuario;
  final String texto;
  final Uint8List? foto;
  final List<AccionAsistente> acciones;
  final List<String> sugerencias;
}

/// Asistente de SIGMA en la app (el mismo del panel web). Responde según el rol: el cliente solo consulta lo de
/// su empresa y el técnico, sus órdenes y ascensores. Con una foto, sugiere qué revisar según la planilla y qué
/// repuestos hay en el inventario.
class PantallaAsistente extends StatefulWidget {
  const PantallaAsistente({super.key});

  @override
  State<PantallaAsistente> createState() => _PantallaAsistenteState();
}

class _PantallaAsistenteState extends State<PantallaAsistente> {
  final _texto = TextEditingController();
  final _desplazamiento = ScrollController();
  final _selector = ImagePicker();
  final _sesion = 'app-${DateTime.now().millisecondsSinceEpoch}';
  final List<_Mensaje> _mensajes = [];
  Uint8List? _foto;
  String? _nombreFoto;
  bool _enviando = false;

  @override
  void dispose() {
    _texto.dispose();
    _desplazamiento.dispose();
    super.dispose();
  }

  String _saludo(Usuario usuario) {
    final nombre = usuario.nombre.split(' ').first;
    if (usuario.esCliente) {
      return 'Hola, $nombre. Puedo contarle el estado de sus ascensores, los próximos mantenimientos, sus órdenes, '
          'emergencias y su estado de cuenta.';
    }
    if (usuario.esTecnico) {
      return 'Hola, $nombre. Pregúnteme por sus órdenes o el estado de un ascensor. Con una foto (cámara o galería) le digo '
          'qué revisar según la planilla y qué repuestos hay en el inventario. Cuénteme qué zona es: puertas, cabina, '
          'cuarto de máquinas, cables o foso.';
    }
    return 'Hola, $nombre. Puedo buscar ascensores, órdenes, emergencias y mantenimientos según su rol. También puede '
        'enviarme una foto de un ascensor para revisar su estado.';
  }

  List<String> _sugerenciasIniciales(Usuario usuario) {
    if (usuario.esCliente) return ['Estado de mis ascensores', 'Próximos mantenimientos', 'Mi estado de cuenta'];
    if (usuario.esTecnico) return ['Mis órdenes activas', 'Próximos mantenimientos', 'Repuestos para puertas'];
    return ['Órdenes activas', 'Emergencias abiertas', 'Próximos mantenimientos'];
  }

  Future<void> _elegirFoto() async {
    final fuente = await showModalBottomSheet<ImageSource>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Tomar foto'),
              onTap: () => Navigator.of(context).pop(ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Elegir de la galería'),
              onTap: () => Navigator.of(context).pop(ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (fuente == null) return;
    final archivo = await _selector.pickImage(source: fuente, maxWidth: 1600, imageQuality: 82);
    if (archivo == null) return;
    final bytes = await archivo.readAsBytes();
    if (!mounted) return;
    setState(() {
      _foto = bytes;
      _nombreFoto = archivo.name;
    });
  }

  Future<void> _enviar([String? sugerencia]) async {
    final mensaje = (sugerencia ?? _texto.text).trim();
    final foto = sugerencia == null ? _foto : null;
    if ((mensaje.isEmpty && foto == null) || _enviando) return;
    final repositorio = context.read<Sesion>().repositorio;

    setState(() {
      _mensajes.add(_Mensaje.usuario(mensaje, foto: foto));
      _enviando = true;
      if (sugerencia == null) {
        _texto.clear();
        _foto = null;
      }
    });
    _bajar();

    try {
      final respuesta = await repositorio.preguntarAsistente(mensaje, sesion: _sesion, foto: foto, nombreFoto: _nombreFoto);
      if (!mounted) return;
      setState(() => _mensajes.add(_Mensaje.asistente(respuesta)));
    } on ErrorApi catch (e) {
      if (!mounted) return;
      setState(() => _mensajes.add(_Mensaje.error(e.mensaje)));
    } finally {
      if (mounted) setState(() => _enviando = false);
      _bajar();
    }
  }

  void _bajar() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_desplazamiento.hasClients) {
        _desplazamiento.animateTo(
          _desplazamiento.position.maxScrollExtent,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      }
    });
  }

  void _abrir(AccionAsistente accion) {
    final destino = accion.equipoId != null
        ? PantallaDetalleEquipo(equipoId: accion.equipoId!)
        : PantallaDetalleOrden(ordenId: accion.ordenId!);
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => destino));
  }

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    final usuario = context.watch<Sesion>().usuario;
    // Si la sesión se cerró (inactividad) con el chat abierto, la app vuelve al ingreso: no hay nada que mostrar.
    if (usuario == null) return const Scaffold(body: SizedBox.shrink());
    final ultimas = _mensajes.isNotEmpty && !_mensajes.last.delUsuario ? _mensajes.last.sugerencias : _sugerenciasIniciales(usuario);

    return Scaffold(
      appBar: AppBar(
        title: const Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Asistente SIGMA'),
            Text('Responde según su rol', style: TextStyle(fontSize: 12.5, fontWeight: FontWeight.w400)),
          ],
        ),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: ListView(
                controller: _desplazamiento,
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                children: [
                  _Burbuja(texto: _saludo(usuario), delUsuario: false),
                  for (final m in _mensajes) ...[
                    _Burbuja(texto: m.texto, delUsuario: m.delUsuario, foto: m.foto),
                    if (m.acciones.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 6,
                          children: [
                            for (final a in m.acciones)
                              ActionChip(avatar: const Icon(Icons.open_in_new, size: 16), label: Text(a.texto), onPressed: () => _abrir(a)),
                          ],
                        ),
                      ),
                  ],
                  if (_enviando)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      child: Row(
                        children: [
                          const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2)),
                          const SizedBox(width: 10),
                          Text('Consultando…', style: TextStyle(color: esquema.onSurfaceVariant)),
                        ],
                      ),
                    ),
                  if (!_enviando && ultimas.isNotEmpty)
                    Wrap(
                      spacing: 8,
                      runSpacing: 6,
                      children: [for (final s in ultimas) ActionChip(label: Text(s), onPressed: () => _enviar(s))],
                    ),
                ],
              ),
            ),
            if (_foto != null)
              Container(
                margin: const EdgeInsets.fromLTRB(14, 0, 14, 8),
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: esquema.surfaceContainerHighest,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  children: [
                    ClipRRect(borderRadius: BorderRadius.circular(8), child: Image.memory(_foto!, width: 54, height: 54, fit: BoxFit.cover)),
                    const SizedBox(width: 10),
                    const Expanded(child: Text('Foto lista. Escriba qué zona es o qué necesita saber (opcional).')),
                    IconButton(
                      tooltip: 'Quitar la foto',
                      icon: const Icon(Icons.close),
                      onPressed: () => setState(() => _foto = null),
                    ),
                  ],
                ),
              ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 0, 10, 10),
              child: Row(
                children: [
                  IconButton.filledTonal(
                    tooltip: 'Adjuntar una foto del ascensor',
                    icon: const Icon(Icons.add_a_photo_outlined),
                    onPressed: _enviando ? null : _elegirFoto,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: TextField(
                      controller: _texto,
                      minLines: 1,
                      maxLines: 4,
                      maxLength: 1000,
                      textInputAction: TextInputAction.send,
                      onSubmitted: (_) => _enviar(),
                      decoration: const InputDecoration(hintText: 'Escriba su pregunta…', counterText: '', isDense: true),
                    ),
                  ),
                  const SizedBox(width: 8),
                  IconButton.filled(
                    tooltip: 'Enviar',
                    icon: const Icon(Icons.send),
                    onPressed: _enviando ? null : () => _enviar(),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Burbuja extends StatelessWidget {
  const _Burbuja({required this.texto, required this.delUsuario, this.foto});

  final String texto;
  final bool delUsuario;
  final Uint8List? foto;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    final fondo = delUsuario ? esquema.primary : esquema.surfaceContainerHigh;
    final color = delUsuario ? esquema.onPrimary : esquema.onSurface;
    return Align(
      alignment: delUsuario ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        constraints: BoxConstraints(maxWidth: MediaQuery.sizeOf(context).width * 0.82),
        margin: const EdgeInsets.only(bottom: 8),
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: fondo,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(14),
            topRight: const Radius.circular(14),
            bottomLeft: Radius.circular(delUsuario ? 14 : 4),
            bottomRight: Radius.circular(delUsuario ? 4 : 14),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (foto != null)
              Padding(
                padding: EdgeInsets.only(bottom: texto.isEmpty ? 0 : 8),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.memory(foto!, height: 160, fit: BoxFit.cover, semanticLabel: 'Foto enviada al asistente'),
                ),
              ),
            if (texto.isNotEmpty) SelectableText(texto, style: TextStyle(color: color, height: 1.35)),
          ],
        ),
      ),
    );
  }
}

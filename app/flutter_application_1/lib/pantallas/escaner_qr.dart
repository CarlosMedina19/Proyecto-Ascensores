import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../estado/sesion.dart';
import '../tema.dart';
import '../widgets/comunes.dart';
import 'emergencias.dart';
import 'ordenes.dart';

/// Código del ascensor dentro del QR: la URL `.../reportar/código` o el código solo.
String? codigoDeQr(String? texto) {
  final valor = (texto ?? '').trim();
  if (valor.isEmpty) return null;
  final enUrl = RegExp(r'/reportar/([A-Za-z0-9]+)').firstMatch(valor);
  if (enUrl != null) return enUrl.group(1);
  return RegExp(r'^[A-Za-z0-9]{8,32}$').hasMatch(valor) ? valor : null;
}

/// Cámara para escanear el QR pegado en la cabina (solo técnicos).
class PantallaEscanerQr extends StatefulWidget {
  const PantallaEscanerQr({super.key});

  @override
  State<PantallaEscanerQr> createState() => _PantallaEscanerQrState();
}

class _PantallaEscanerQrState extends State<PantallaEscanerQr> {
  final _camara = MobileScannerController(formats: const [BarcodeFormat.qrCode]);
  bool _leido = false;
  String? _aviso;

  @override
  void dispose() {
    _camara.dispose();
    super.dispose();
  }

  void _alDetectar(BarcodeCapture captura) {
    if (_leido) return;
    final codigo = captura.barcodes.map((b) => codigoDeQr(b.rawValue)).firstWhere((c) => c != null, orElse: () => null);
    if (codigo == null) {
      setState(() => _aviso = 'Ese código no es el QR de un ascensor de SIGMA.');
      return;
    }
    _leido = true;
    Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => PantallaAscensorQr(codigo: codigo)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Escanear QR'),
        actions: [IconButton(tooltip: 'Linterna', icon: const Icon(Icons.flashlight_on_outlined), onPressed: () => _camara.toggleTorch())],
      ),
      body: Stack(
        fit: StackFit.expand,
        children: [
          MobileScanner(
            controller: _camara,
            onDetect: _alDetectar,
            errorBuilder: (context, error) => Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text('No se pudo abrir la cámara. Revise el permiso de cámara de la app.', textAlign: TextAlign.center),
              ),
            ),
          ),
          IgnorePointer(
            child: Center(
              child: Container(
                width: 240,
                height: 240,
                decoration: BoxDecoration(border: Border.all(color: Colors.white, width: 3), borderRadius: BorderRadius.circular(20)),
              ),
            ),
          ),
          Positioned(
            left: 16,
            right: 16,
            bottom: 32,
            child: Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(color: Colors.black.withValues(alpha: 0.6), borderRadius: BorderRadius.circular(12)),
              child: Text(
                _aviso ?? 'Apunte al código QR pegado en la cabina del ascensor.',
                textAlign: TextAlign.center,
                style: const TextStyle(color: Colors.white),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// Ascensor escaneado: su estado y las dos acciones del técnico.
class PantallaAscensorQr extends StatefulWidget {
  const PantallaAscensorQr({super.key, required this.codigo});

  final String codigo;

  @override
  State<PantallaAscensorQr> createState() => _PantallaAscensorQrState();
}

class _PantallaAscensorQrState extends State<PantallaAscensorQr> {
  Map<String, dynamic>? _estado;
  Map<String, dynamic>? _ascensor;
  String? _error;
  bool _iniciando = false;

  static const _estados = {
    'activo': ('En servicio', SigmaColores.bueno),
    'mantenimiento': ('En mantenimiento', SigmaColores.regular),
    'falla': ('Falla reportada', SigmaColores.malo),
    'inactivo': ('Fuera de servicio', SigmaColores.tintaSuave),
  };

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  Future<void> _cargar() async {
    final repositorio = context.read<Sesion>().repositorio;
    try {
      final resultados = await Future.wait([repositorio.estadoQr(widget.codigo), repositorio.ascensorQr(widget.codigo)]);
      if (mounted) {
        setState(() {
          _estado = resultados[0];
          _ascensor = resultados[1];
        });
      }
    } catch (e) {
      if (mounted) setState(() => _error = mensajeError(e));
    }
  }

  Future<void> _realizarMantenimiento() async {
    setState(() {
      _iniciando = true;
      _error = null;
    });
    try {
      final r = await context.read<Sesion>().repositorio.iniciarMantenimientoQr(widget.codigo);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(r.mensaje)));
      Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => PantallaDetalleOrden(ordenId: r.ordenId)));
    } catch (e) {
      if (mounted) setState(() => _error = mensajeError(e));
    } finally {
      if (mounted) setState(() => _iniciando = false);
    }
  }

  Future<void> _reportarAnomalia() async {
    final sesion = context.read<Sesion>();
    final equipo = await sesion.repositorio.equipo(_ascensor!['equipo_id'] as int);
    if (!mounted) return;
    final mensaje = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (_) => FormularioReporteEmergencia(equipo: equipo),
    );
    if (mensaje == null || !mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(mensaje)));
    _cargar();
  }

  @override
  Widget build(BuildContext context) {
    final estado = _estado;
    final orden = _ascensor?['orden'] as Map<String, dynamic>?;
    final (texto, color) = _estados[estado?['estado']] ?? _estados['activo']!;

    return Scaffold(
      appBar: AppBar(title: Text(estado == null ? 'Ascensor' : 'Ascensor ${estado['codigo']}')),
      body: estado == null || _ascensor == null
          ? Center(child: _error == null ? const CircularProgressIndicator() : Padding(padding: const EdgeInsets.all(24), child: Text(_error!, textAlign: TextAlign.center)))
          : ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text([estado['nombre'], estado['edificio'], estado['direccion']].whereType<String>().join(' · '), style: Theme.of(context).textTheme.bodyMedium),
                const SizedBox(height: 10),
                Align(alignment: Alignment.centerLeft, child: Insignia(texto: texto, color: color)),
                const SizedBox(height: 14),
                Datos([
                  ('Tipo', estado['tipo'] as String?),
                  ('Paradas', estado['paradas']?.toString()),
                  ('Último mantenimiento', estado['ultimo_mantenimiento'] == null ? null : fecha(DateTime.parse(estado['ultimo_mantenimiento'] as String))),
                  ('Próximo mantenimiento', estado['proximo_mantenimiento'] == null ? null : fecha(DateTime.parse(estado['proximo_mantenimiento'] as String))),
                ]),
                if (_error != null) ...[
                  const SizedBox(height: 12),
                  Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                ],
                const SizedBox(height: 20),
                FilledButton.icon(
                  onPressed: _iniciando ? null : _realizarMantenimiento,
                  icon: const Icon(Icons.build_outlined),
                  label: Text(_iniciando ? 'Iniciando…' : 'Realizar mantenimiento'),
                  style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
                ),
                if (orden != null)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text('Continúa su orden ${orden['numero']}.', textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodySmall),
                  ),
                const SizedBox(height: 12),
                OutlinedButton.icon(
                  onPressed: _reportarAnomalia,
                  icon: const Icon(Icons.warning_amber_rounded),
                  label: const Text('Reportar anomalía'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(52),
                    foregroundColor: colorSegunModo(context, SigmaColores.malo),
                    side: BorderSide(color: colorSegunModo(context, SigmaColores.malo)),
                  ),
                ),
              ],
            ),
    );
  }
}

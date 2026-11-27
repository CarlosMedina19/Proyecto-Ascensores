import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../estado/cola_envios.dart';
import '../widgets/comunes.dart';

String _cambios(int n) => n == 1 ? '1 cambio' : '$n cambios';
String _guardados(int n) => n == 1 ? '1 cambio guardado' : '$n cambios guardados';

/// Franja sobre la barra inferior: sin conexión, enviando o con cambios por enviar. No ocupa espacio si todo
/// está al día. Al terminar de enviar avisa cuántos cambios llegaron al servidor.
class AvisoConexion extends StatefulWidget {
  const AvisoConexion({super.key});

  @override
  State<AvisoConexion> createState() => _AvisoConexionState();
}

class _AvisoConexionState extends State<AvisoConexion> {
  ColaEnvios? _cola;
  bool _enviabaAntes = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final cola = context.read<ColaEnvios>();
    if (cola != _cola) {
      _cola?.removeListener(_alCambiar);
      _cola = cola..addListener(_alCambiar);
    }
  }

  @override
  void dispose() {
    _cola?.removeListener(_alCambiar);
    super.dispose();
  }

  void _alCambiar() {
    final cola = _cola!;
    if (_enviabaAntes && !cola.enviando && cola.enviadosUltimaVez > 0 && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(cola.enviadosUltimaVez == 1 ? 'Se envió 1 cambio guardado sin conexión.' : 'Se enviaron ${cola.enviadosUltimaVez} cambios guardados sin conexión.')));
    }
    _enviabaAntes = cola.enviando;
  }

  @override
  Widget build(BuildContext context) {
    final cola = context.watch<ColaEnvios>();
    final esquema = Theme.of(context).colorScheme;
    final pendientes = cola.pendientes;
    final rechazados = cola.rechazados;
    if (!cola.sinConexion && pendientes == 0 && rechazados == 0) return const SizedBox.shrink();

    final (icono, texto, fondo, tinta) = switch (true) {
      _ when cola.enviando => (null, 'Enviando ${_guardados(pendientes)} en el celular…', esquema.primaryContainer, esquema.onPrimaryContainer),
      _ when cola.sinConexion => (
        Icons.cloud_off_outlined,
        pendientes > 0 ? 'Sin conexión · ${_guardados(pendientes)} en el celular' : 'Sin conexión · trabajando con los datos guardados',
        esquema.tertiaryContainer,
        esquema.onTertiaryContainer,
      ),
      _ when rechazados > 0 => (Icons.error_outline, '${rechazados == 1 ? '1 cambio no se pudo aplicar' : '$rechazados cambios no se pudieron aplicar'} · Revisar', esquema.errorContainer, esquema.onErrorContainer),
      _ => (Icons.cloud_upload_outlined, '${_cambios(pendientes)} por enviar', esquema.primaryContainer, esquema.onPrimaryContainer),
    };

    return Material(
      color: fondo,
      child: InkWell(
        onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PantallaEnviosPendientes())),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
          child: Row(
            children: [
              if (icono == null)
                SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: tinta))
              else
                Icon(icono, size: 18, color: tinta),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  texto,
                  style: TextStyle(color: tinta, fontSize: 13.5, fontWeight: FontWeight.w600),
                ),
              ),
              Icon(Icons.chevron_right, size: 18, color: tinta),
            ],
          ),
        ),
      ),
    );
  }
}

/// Cambios hechos sin conexión que esperan en el celular, en el orden en que se enviarán.
class PantallaEnviosPendientes extends StatelessWidget {
  const PantallaEnviosPendientes({super.key});

  Future<void> _descartar(BuildContext context, EnvioPendiente envio) async {
    final cola = context.read<ColaEnvios>();
    final confirmar = await showDialog<bool>(
      context: context,
      builder: (contexto) => AlertDialog(
        title: const Text('Descartar cambio'),
        content: Text('«${envio.descripcion}» no se aplicará. Si aún lo necesita, hágalo de nuevo desde la orden.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(contexto, false), child: const Text('Cancelar')),
          FilledButton(onPressed: () => Navigator.pop(contexto, true), child: const Text('Descartar')),
        ],
      ),
    );
    if (confirmar == true) await cola.descartar(envio);
  }

  @override
  Widget build(BuildContext context) {
    final cola = context.watch<ColaEnvios>();
    final esquema = Theme.of(context).colorScheme;
    final envios = cola.envios;

    return Scaffold(
      appBar: AppBar(title: const Text('Cambios sin enviar')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          Text(
            cola.sinConexion
                ? 'No hay conexión. Lo que haga queda guardado en este celular y se envía solo, en el mismo orden, cuando vuelva el internet.'
                : 'Se envían solos, en el orden en que los hizo. Si el servidor no acepta uno, queda marcado aquí para que lo revise.',
            style: TextStyle(color: esquema.onSurfaceVariant),
          ),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: cola.enviando || cola.pendientes == 0 ? null : cola.sincronizar,
            icon: cola.enviando
                ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                : const Icon(Icons.cloud_upload_outlined),
            label: Text(cola.enviando ? 'Enviando…' : 'Enviar ahora'),
          ),
          const SizedBox(height: 16),
          if (envios.isEmpty)
            const EstadoVacio(titulo: 'Todo está enviado', descripcion: 'No hay cambios guardados en el celular.', icono: Icons.cloud_done_outlined)
          else
            for (final envio in envios)
              Card(
                child: ListTile(
                  leading: Icon(envio.rechazado ? Icons.error_outline : Icons.schedule, color: envio.rechazado ? esquema.error : esquema.primary),
                  title: Text(envio.descripcion, style: const TextStyle(fontWeight: FontWeight.w600)),
                  subtitle: Text(
                    [
                      'Guardado ${fechaHora(envio.creadoEn)}',
                      if (envio.rechazado) 'No se aplicó: ${envio.error ?? 'el servidor lo rechazó'}' else 'Por enviar',
                    ].join('\n'),
                  ),
                  isThreeLine: envio.rechazado,
                  trailing: envio.rechazado
                      ? IconButton(tooltip: 'Descartar', icon: const Icon(Icons.delete_outline), onPressed: () => _descartar(context, envio))
                      : null,
                ),
              ),
        ],
      ),
    );
  }
}

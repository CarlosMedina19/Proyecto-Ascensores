import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../datos/modelos.dart';
import '../tema.dart';

final _fechaHora = DateFormat("d MMM yyyy, HH:mm", 'es');
final _fecha = DateFormat('d MMM yyyy', 'es');
final _dia = DateFormat('d MMM', 'es');
final _hora = DateFormat('HH:mm', 'es');

String fechaHora(DateTime? valor) => valor == null ? '—' : _fechaHora.format(valor);
String fecha(DateTime? valor) => valor == null ? '—' : _fecha.format(valor);
String diaCorto(DateTime? valor) => valor == null ? '—' : _dia.format(valor).replaceAll('.', '');
String hora(DateTime? valor) => valor == null ? '' : _hora.format(valor);

/// "cambio_estado" → "Cambio estado"
String humanizar(String texto) {
  final limpio = texto.replaceAll('_', ' ').trim();
  return limpio.isEmpty ? limpio : limpio[0].toUpperCase() + limpio.substring(1);
}

String numero(num? valor, [String sufijo = '']) => valor == null ? '—' : '${NumberFormat.decimalPattern('es_CO').format(valor)}$sufijo';

/// Logo de SIGMA: la Σ trazada como el recorrido de un ascensor, con la cabina en cian.
class LogoSigma extends StatelessWidget {
  const LogoSigma({super.key, this.tamano = 40});

  final double tamano;

  @override
  Widget build(BuildContext context) => SizedBox.square(
    dimension: tamano,
    child: CustomPaint(painter: _PintorLogo()),
  );
}

class _PintorLogo extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final escala = size.width / 40;
    canvas.scale(escala);
    final fondo = RRect.fromRectAndRadius(const Rect.fromLTWH(0, 0, 40, 40), const Radius.circular(11));
    canvas.drawRRect(
      fondo,
      Paint()
        ..shader = const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF9A6BFF), Color(0xFF6B3FD4), Color(0xFF3A1A8F)],
          stops: [0, .55, 1],
        ).createShader(const Rect.fromLTWH(0, 0, 40, 40)),
    );
    final trazo = Path()
      ..moveTo(28, 11.5)
      ..lineTo(12.5, 11.5)
      ..lineTo(20.5, 20)
      ..lineTo(12.5, 28.5)
      ..lineTo(28, 28.5);
    canvas.drawPath(
      trazo,
      Paint()
        ..color = Colors.white
        ..style = PaintingStyle.stroke
        ..strokeWidth = 3.2
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(const Rect.fromLTWH(24.5, 16.5, 7, 7), const Radius.circular(2)),
      Paint()..color = const Color(0xFF38D3EE),
    );
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// Estado del equipo con su color.
({String texto, Color color}) estadoEquipo(String estado) => switch (estado) {
  'activo' => (texto: 'Activo', color: SigmaColores.bueno),
  'mantenimiento' => (texto: 'En mantenimiento', color: SigmaColores.regular),
  _ => (texto: 'Inactivo', color: SigmaColores.textoSuave),
};

/// "$ 1.250.000" en pesos colombianos, sin decimales.
String dinero(num? valor) =>
    valor == null ? '—' : NumberFormat.currency(locale: 'es_CO', symbol: r'$', decimalDigits: 0).format(valor);

/// Estado de un mantenimiento programado; el pendiente con fecha pasada se muestra atrasado.
({String texto, Color color}) estadoMantenimiento(MantenimientoProgramado mantenimiento, [DateTime? hoy]) {
  if (mantenimiento.pendiente && mantenimiento.diasRestantes(hoy) < 0) {
    return (texto: 'Atrasado', color: SigmaColores.malo);
  }
  return switch (mantenimiento.estado) {
    'programado' => (texto: 'Programado', color: SigmaColores.primario),
    'reprogramado' => (texto: 'Reprogramado', color: SigmaColores.cian),
    'ejecutado' => (texto: 'Ejecutado', color: SigmaColores.bueno),
    _ => (texto: 'Cancelado', color: SigmaColores.textoSuave),
  };
}

/// "Hoy", "Mañana", "En 5 días" o "Atrasado 3 días".
String textoDias(int dias) => switch (dias) {
  0 => 'Hoy',
  1 => 'Mañana',
  < 0 => 'Atrasado ${-dias} ${dias == -1 ? 'día' : 'días'}',
  _ => 'En $dias días',
};

/// Color de los estados B/R/M del checklist.
Color colorEstadoRevision(String estado) => switch (estado) {
  'B' => SigmaColores.bueno,
  'R' => SigmaColores.regular,
  'M' => SigmaColores.malo,
  _ => SigmaColores.textoSuave,
};

class Insignia extends StatelessWidget {
  const Insignia({super.key, required this.texto, required this.color});

  factory Insignia.estado(String estado) {
    final datos = estadoEquipo(estado);
    return Insignia(texto: datos.texto, color: datos.color);
  }

  final String texto;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(7, 3, 9, 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            texto,
            style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class Cargando extends StatelessWidget {
  const Cargando({super.key});

  @override
  Widget build(BuildContext context) => const Center(
    child: Padding(padding: EdgeInsets.all(32), child: CircularProgressIndicator()),
  );
}

class EstadoVacio extends StatelessWidget {
  const EstadoVacio({super.key, required this.titulo, this.descripcion, this.icono = Icons.inbox_outlined, this.accion});

  final String titulo;
  final String? descripcion;
  final IconData icono;
  final Widget? accion;

  @override
  Widget build(BuildContext context) {
    final suave = Theme.of(context).colorScheme.onSurfaceVariant;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icono, size: 48, color: suave),
            const SizedBox(height: 12),
            Text(titulo, textAlign: TextAlign.center, style: Theme.of(context).textTheme.titleMedium),
            if (descripcion != null) ...[
              const SizedBox(height: 6),
              Text(
                descripcion!,
                textAlign: TextAlign.center,
                style: TextStyle(color: suave),
              ),
            ],
            if (accion != null) ...[const SizedBox(height: 16), accion!],
          ],
        ),
      ),
    );
  }
}

class VistaError extends StatelessWidget {
  const VistaError({super.key, required this.mensaje, required this.alReintentar});

  final String mensaje;
  final VoidCallback alReintentar;

  @override
  Widget build(BuildContext context) => EstadoVacio(
    titulo: 'No se pudo cargar la información',
    descripcion: mensaje,
    icono: Icons.cloud_off_outlined,
    accion: OutlinedButton.icon(onPressed: alReintentar, icon: const Icon(Icons.refresh), label: const Text('Reintentar')),
  );
}

/// Título pequeño en mayúsculas, como las etiquetas del panel web.
class Etiqueta extends StatelessWidget {
  const Etiqueta(this.texto, {super.key, this.color});

  final String texto;
  final Color? color;

  @override
  Widget build(BuildContext context) => Text(
    texto.toUpperCase(),
    style: TextStyle(
      fontSize: 11,
      letterSpacing: 1,
      fontWeight: FontWeight.w600,
      color: color ?? Theme.of(context).colorScheme.onSurfaceVariant,
    ),
  );
}

class Seccion extends StatelessWidget {
  const Seccion({super.key, required this.titulo, required this.hijos, this.accion});

  final String titulo;
  final List<Widget> hijos;
  final Widget? accion;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(titulo, style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w700)),
                ),
                ?accion,
              ],
            ),
            const SizedBox(height: 12),
            ...hijos,
          ],
        ),
      ),
    );
  }
}

/// Rejilla de dos columnas con pares etiqueta / valor.
class Datos extends StatelessWidget {
  const Datos(this.elementos, {super.key});

  final List<(String, String?)> elementos;

  @override
  Widget build(BuildContext context) {
    final borde = Theme.of(context).dividerTheme.color ?? SigmaColores.borde;
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: borde),
        borderRadius: BorderRadius.circular(12),
      ),
      clipBehavior: Clip.antiAlias,
      child: LayoutBuilder(
        builder: (context, limites) {
          final ancho = (limites.maxWidth - 1) / 2;
          return Wrap(
            spacing: 1,
            runSpacing: 1,
            children: [
              for (final (etiqueta, valor) in elementos)
                Container(
                  width: ancho,
                  padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
                  decoration: BoxDecoration(
                    border: Border(
                      bottom: BorderSide(color: borde),
                      right: BorderSide(color: borde),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Etiqueta(etiqueta),
                      const SizedBox(height: 2),
                      Text(valor == null || valor.isEmpty ? '—' : valor, style: const TextStyle(fontSize: 14)),
                    ],
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

/// Color del punto de la línea de tiempo según el tipo de evento.
Color colorEvento(String evento) {
  if (evento.startsWith('alta')) return SigmaColores.cian;
  if (evento.contains('baja') || evento == 'incidente') return SigmaColores.malo;
  if (evento.contains('estado') || evento == 'reparacion' || evento == 'cambio_repuesto') return SigmaColores.regular;
  if (evento.contains('mantenimiento') || evento.contains('inspeccion')) return SigmaColores.bueno;
  return SigmaColores.primario;
}

class LineaTiempo extends StatelessWidget {
  const LineaTiempo({super.key, required this.eventos, this.etiquetaEquipo});

  final List<EventoHistorial> eventos;

  /// Texto adicional por evento (por ejemplo, el código del equipo).
  final String? Function(EventoHistorial)? etiquetaEquipo;

  @override
  Widget build(BuildContext context) {
    final suave = Theme.of(context).colorScheme.onSurfaceVariant;
    return Column(
      children: [
        for (final evento in eventos)
          Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  width: 52,
                  child: Text(
                    '${diaCorto(evento.fecha)}\n${hora(evento.fecha)}',
                    style: TextStyle(fontSize: 12, color: suave, fontFeatures: const [FontFeature.tabularFigures()]),
                  ),
                ),
                Container(
                  margin: const EdgeInsets.only(top: 4, right: 10),
                  width: 11,
                  height: 11,
                  decoration: BoxDecoration(color: colorEvento(evento.evento), shape: BoxShape.circle),
                ),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        [humanizar(evento.evento), etiquetaEquipo?.call(evento)].whereType<String>().join(' · '),
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                      Text(evento.usuarioNombre ?? 'Sistema', style: TextStyle(fontSize: 12.5, color: suave)),
                      if (evento.descripcion != null && evento.descripcion!.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 2),
                          child: Text(evento.descripcion!, style: TextStyle(color: suave)),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

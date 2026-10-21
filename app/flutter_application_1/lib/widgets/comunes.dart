import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:intl/intl.dart' hide TextDirection;

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

/// Logo del prototipo: la Σ como recorrido de un ascensor, con la cabina en el centro (viewBox 120).
class _PintorLogo extends CustomPainter {
  static const _metal = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF5B4A82), Color(0xFF241640), Color(0xFF3A2A5E), Color(0xFF1B1030)],
    stops: [0, .42, .7, 1],
  );
  static const _neon = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFF2EFFA), Color(0xFFAA94E0), Color(0xFF5A30C5)],
    stops: [0, .35, 1],
  );

  @override
  void paint(Canvas canvas, Size size) {
    canvas.scale(size.width / 120);
    const todo = Rect.fromLTWH(0, 0, 120, 120);
    canvas.drawRRect(RRect.fromRectAndRadius(todo, const Radius.circular(28)), Paint()..color = const Color(0xFF150B2D));
    canvas.drawRRect(
      RRect.fromRectAndRadius(const Rect.fromLTWH(1, 1, 118, 118), const Radius.circular(27)),
      Paint()
        ..shader = _neon.createShader(todo)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2
        ..color = Colors.white.withValues(alpha: .55),
    );

    // Rieles de guía a la derecha.
    for (final riel in const [Rect.fromLTWH(103, 22, 4.5, 50), Rect.fromLTWH(110, 32, 4.5, 40)]) {
      canvas.drawRect(riel, Paint()..shader = _metal.createShader(riel));
      canvas.drawRect(
        riel,
        Paint()
          ..color = const Color(0xFFAA94E0)
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1.2,
      );
    }

    final recorrido = Path()
      ..moveTo(96, 12)
      ..lineTo(24, 36)
      ..lineTo(24, 52)
      ..lineTo(96, 76)
      ..lineTo(96, 92)
      ..lineTo(24, 112);
    canvas.drawPath(
      recorrido,
      Paint()
        ..color = const Color(0xFF8161D1).withValues(alpha: .6)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 16
        ..strokeJoin = StrokeJoin.bevel
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 2.4),
    );
    for (final (ancho, degradado) in [(13.0, _neon), (9.6, _metal)]) {
      canvas.drawPath(
        recorrido,
        Paint()
          ..shader = degradado.createShader(todo)
          ..style = PaintingStyle.stroke
          ..strokeWidth = ancho
          ..strokeJoin = StrokeJoin.bevel,
      );
    }

    // Cabina con su resplandor, el visor, la luz interior con el nombre y las dos hojas de puerta.
    canvas.drawRRect(
      RRect.fromRectAndRadius(const Rect.fromLTWH(40, 30, 40, 78), const Radius.circular(4)),
      Paint()
        ..color = const Color(0xFF9A7FE0).withValues(alpha: .6)
        ..maskFilter = const MaskFilter.blur(BlurStyle.normal, 2.4),
    );
    const cabina = Rect.fromLTWH(42, 32, 36, 74);
    canvas.drawRRect(RRect.fromRectAndRadius(cabina, const Radius.circular(3)), Paint()..shader = _metal.createShader(cabina));
    canvas.drawRRect(
      RRect.fromRectAndRadius(cabina, const Radius.circular(3)),
      Paint()
        ..shader = _neon.createShader(cabina)
        ..style = PaintingStyle.stroke
        ..strokeWidth = 1.8,
    );
    canvas.drawRRect(
      RRect.fromRectAndRadius(const Rect.fromLTWH(45.5, 35, 29, 10), const Radius.circular(2)),
      Paint()..color = const Color(0xFF0E0820),
    );
    canvas.drawPath(
      Path()..addPolygon(const [Offset(55, 37.3), Offset(58, 42.4), Offset(52, 42.4)], true),
      Paint()..color = const Color(0xFFE6DFF6),
    );
    canvas.drawPath(
      Path()..addPolygon(const [Offset(62, 37.6), Offset(68, 37.6), Offset(65, 42.7)], true),
      Paint()..color = const Color(0xFF9A7FE0),
    );
    const luz = Rect.fromLTWH(45.5, 48, 29, 55);
    canvas.drawRect(
      luz,
      Paint()
        ..shader = const RadialGradient(
          radius: .6,
          colors: [Color(0xFFFFFFFF), Color(0xFFE6DFF6), Color(0xFF9A7FE0)],
          stops: [0, .55, 1],
        ).createShader(luz),
    );
    // "SIGMA" en vertical dentro de la cabina, con la fuente de la marca.
    var y = 49.0 + 9.2;
    for (final letra in 'SIGMA'.split('')) {
      final texto = TextPainter(
        text: TextSpan(
          text: letra,
          style: const TextStyle(fontFamily: SigmaFuentes.marca, fontSize: 8.4, color: Color(0xFF2F1967), height: 1),
        ),
        textDirection: TextDirection.ltr,
      )..layout();
      texto.paint(canvas, Offset(60 - texto.width / 2, y - texto.height * .8));
      y += 9.6;
    }
    for (final x in [45.5, 66.5]) {
      final hoja = Rect.fromLTWH(x, 48, 8, 55);
      canvas.drawRect(hoja, Paint()..shader = _metal.createShader(hoja));
    }
    final rendija = Paint()
      ..color = const Color(0xFFF2EFFA)
      ..strokeWidth = 1.1;
    canvas.drawLine(const Offset(53.5, 48), const Offset(53.5, 103), rendija);
    canvas.drawLine(const Offset(66.5, 48), const Offset(66.5, 103), rendija);
    canvas.drawRRect(
      RRect.fromRectAndRadius(const Rect.fromLTWH(38, 105.5, 44, 2.4), const Radius.circular(1.2)),
      Paint()..color = const Color(0xFFAA94E0),
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
String dinero(num? valor) => valor == null ? '—' : NumberFormat.currency(locale: 'es_CO', symbol: r'$', decimalDigits: 0).format(valor);

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
    final color = colorSegunModo(context, this.color);
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
            style: TextStyle(fontFamily: SigmaFuentes.texto, color: color, fontSize: 12, fontWeight: FontWeight.w500),
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
  Widget build(BuildContext context) =>
      Text(texto.toUpperCase(), style: estiloEtiqueta(color ?? Theme.of(context).colorScheme.onSurfaceVariant));
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
                Expanded(child: Text(titulo, style: Theme.of(context).textTheme.titleMedium?.copyWith(fontSize: 17))),
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

/// Logo y nombre "SIGMA" en Michroma; sobre fondo oscuro lleva un brillo suave.
class MarcaSigma extends StatelessWidget {
  const MarcaSigma({super.key, this.tamanoLogo = 36, this.subtitulo, this.sobreOscuro = false});

  final double tamanoLogo;
  final String? subtitulo;

  /// Sobre fondos oscuros el nombre lleva el degradado claro; si no, toma el color del texto.
  final bool sobreOscuro;

  @override
  Widget build(BuildContext context) {
    final oscuro = sobreOscuro || Theme.of(context).brightness == Brightness.dark;
    final nombre = Text(
      'SIGMA',
      style: TextStyle(
        fontFamily: SigmaFuentes.marca,
        fontSize: tamanoLogo * 0.45,
        letterSpacing: tamanoLogo * 0.07,
        color: oscuro ? const Color(0xFFECE6FB) : Theme.of(context).colorScheme.onSurface,
        height: 1,
        shadows: oscuro ? const [Shadow(color: Color(0x739A7FE0), blurRadius: 14)] : null,
      ),
    );
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        LogoSigma(tamano: tamanoLogo),
        SizedBox(width: tamanoLogo * 0.28),
        Flexible(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              nombre,
              if (subtitulo != null) ...[
                const SizedBox(height: 4),
                Text(
                  subtitulo!,
                  style: estiloEtiqueta(oscuro ? SigmaColores.tintaSuave : Theme.of(context).colorScheme.onSurfaceVariant, tamano: 10),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

/// Encabezado de pantalla del prototipo: etiqueta superior, título grande y texto de apoyo,
/// dentro de una tarjeta con borde luminoso y brillo en la esquina.
class EncabezadoSigma extends StatelessWidget {
  const EncabezadoSigma({super.key, this.etiqueta, required this.titulo, this.descripcion, this.pie});

  final String? etiqueta;
  final Widget titulo;
  final String? descripcion;
  final Widget? pie;

  @override
  Widget build(BuildContext context) {
    final tema = Theme.of(context);
    final oscuro = tema.brightness == Brightness.dark;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: ShapeDecoration(
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(18),
          side: BorderSide(color: tema.colorScheme.outlineVariant),
        ),
        gradient: RadialGradient(
          center: const Alignment(1, -1),
          radius: 1.3,
          colors: [oscuro ? const Color(0x1FA58BFF) : const Color(0x125B34C4), tema.colorScheme.surface],
          stops: const [0, .62],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          DefaultTextStyle.merge(style: tema.textTheme.headlineSmall, child: titulo),
          if (etiqueta != null) ...[
            const SizedBox(height: 6),
            Text(
              etiqueta!,
              style: TextStyle(color: tema.colorScheme.primary, fontWeight: FontWeight.w600, fontSize: 13),
            ),
          ],
          if (descripcion != null) ...[
            const SizedBox(height: 6),
            Text(descripcion!, style: TextStyle(color: tema.colorScheme.onSurfaceVariant)),
          ],
          if (pie != null) ...[const SizedBox(height: 14), pie!],
        ],
      ),
    );
  }
}

/// Botones B / R / M del checklist: cuadrados, monoespaciados y rellenos del color del estado al elegirse.
class BotonesBRM extends StatelessWidget {
  const BotonesBRM({super.key, required this.valor, this.alCambiar, this.ancho = 40});

  final String valor;
  final ValueChanged<String>? alCambiar;
  final double ancho;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        for (final (i, estado) in const ['B', 'R', 'M'].indexed) ...[
          if (i > 0) const SizedBox(width: 4),
          Semantics(
            button: true,
            selected: valor == estado,
            label: const {'B': 'Bueno', 'R': 'Regular', 'M': 'Malo'}[estado],
            child: InkWell(
              onTap: alCambiar == null ? null : () => alCambiar!(estado),
              borderRadius: BorderRadius.circular(10),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 150),
                width: ancho,
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: valor == estado ? colorSegunModo(context, colorEstadoRevision(estado)) : esquema.surface,
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: valor == estado ? colorSegunModo(context, colorEstadoRevision(estado)) : esquema.outlineVariant,
                  ),
                ),
                child: Text(
                  estado,
                  style: TextStyle(
                    fontFamily: SigmaFuentes.mono,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                    color: valor == estado
                        ? (Theme.of(context).brightness == Brightness.dark ? SigmaColores.tinta : Colors.white)
                        : esquema.onSurfaceVariant,
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

/// Botón de borde punteado que alterna un valor (INS, EST, N.A.), como en el prototipo.
class BotonCiclo extends StatelessWidget {
  const BotonCiclo({super.key, required this.texto, this.alPresionar, this.icono});

  final String texto;
  final VoidCallback? alPresionar;
  final IconData? icono;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return InkWell(
      onTap: alPresionar,
      borderRadius: BorderRadius.circular(10),
      child: CustomPaint(
        painter: _BordePunteado(esquema.outline),
        child: Container(
          height: 38,
          padding: const EdgeInsets.symmetric(horizontal: 11),
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (icono != null) ...[Icon(icono, size: 15, color: esquema.onSurfaceVariant), const SizedBox(width: 6)],
              Text(
                texto,
                style: TextStyle(
                  fontFamily: SigmaFuentes.mono,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w500,
                  color: esquema.onSurfaceVariant,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _BordePunteado extends CustomPainter {
  _BordePunteado(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final ruta = Path()..addRRect(RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(10)));
    final pincel = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;
    for (final metrica in ruta.computeMetrics()) {
      for (var d = 0.0; d < metrica.length; d += 7) {
        canvas.drawPath(metrica.extractPath(d, math.min(d + 4, metrica.length)), pincel);
      }
    }
  }

  @override
  bool shouldRepaint(covariant _BordePunteado anterior) => anterior.color != color;
}

/// Avance por etapas de la orden (stepper del prototipo): barras con la etapa actual resaltada.
class AvanceEtapas extends StatelessWidget {
  const AvanceEtapas({super.key, required this.etapas, required this.actual});

  final List<String> etapas;

  /// Índice de la etapa actual; las anteriores se muestran como hechas.
  final int actual;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (final (i, etapa) in etapas.indexed) ...[
          if (i > 0) const SizedBox(width: 5),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  height: 5,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(5),
                    color: i < actual ? esquema.outline : (i == actual ? null : esquema.outlineVariant),
                    gradient: i == actual ? LinearGradient(colors: [esquema.primary, SigmaColores.primarioClaro]) : null,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  etapa,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: i == actual ? FontWeight.w600 : FontWeight.w400,
                    color: i == actual ? esquema.primary : (i < actual ? esquema.onSurfaceVariant : esquema.outline),
                  ),
                ),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

/// Anillo de avance (checklist del prototipo).
class AnilloAvance extends StatelessWidget {
  const AnilloAvance({super.key, required this.valor, this.tamano = 70});

  /// Entre 0 y 1.
  final double valor;
  final double tamano;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return SizedBox.square(
      dimension: tamano,
      child: TweenAnimationBuilder<double>(
        tween: Tween(end: valor.clamp(0, 1).toDouble()),
        duration: const Duration(milliseconds: 500),
        curve: Curves.easeOutCubic,
        builder: (context, v, _) =>
            CustomPaint(painter: _PintorAnillo(v, esquema.surfaceContainerHighest, v >= 1 ? SigmaColores.bueno : esquema.primary)),
      ),
    );
  }
}

class _PintorAnillo extends CustomPainter {
  _PintorAnillo(this.valor, this.fondo, this.color);

  final double valor;
  final Color fondo;
  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final centro = size.center(Offset.zero);
    final radio = size.width / 2 - 4;
    final pincel = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 7
      ..strokeCap = StrokeCap.round;
    canvas.drawCircle(centro, radio, pincel..color = fondo);
    if (valor > 0) {
      canvas.drawArc(Rect.fromCircle(center: centro, radius: radio), -math.pi / 2, 2 * math.pi * valor, false, pincel..color = color);
    }
  }

  @override
  bool shouldRepaint(covariant _PintorAnillo anterior) => anterior.valor != valor || anterior.color != color;
}

/// Tarjeta destacada en degradado morado (bloque "próximo mantenimiento" del prototipo).
class TarjetaDestacada extends StatelessWidget {
  const TarjetaDestacada({super.key, required this.child, this.alTocar});

  final Widget child;
  final VoidCallback? alTocar;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: Ink(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(20),
          gradient: const RadialGradient(
            center: Alignment(1, -1),
            radius: 1.6,
            colors: [Color(0xFF2E2648), SigmaColores.escenaPanel, Color(0xFF141218)],
            stops: [0, .55, 1],
          ),
        ),
        child: InkWell(
          onTap: alTocar,
          borderRadius: BorderRadius.circular(20),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: DefaultTextStyle.merge(
              style: const TextStyle(color: Colors.white),
              child: child,
            ),
          ),
        ),
      ),
    );
  }
}

/// Punto verde lima que late: dato en vivo (prototipo).
class PuntoVivo extends StatefulWidget {
  const PuntoVivo({super.key, this.color = SigmaColores.lima});

  final Color color;

  @override
  State<PuntoVivo> createState() => _PuntoVivoState();
}

class _PuntoVivoState extends State<PuntoVivo> with SingleTickerProviderStateMixin {
  late final _latido = AnimationController(vsync: this, duration: const Duration(seconds: 2));

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final sinMovimiento = MediaQuery.maybeDisableAnimationsOf(context) ?? false;
    if (sinMovimiento) {
      _latido.stop();
    } else if (!_latido.isAnimating) {
      _latido.repeat();
    }
  }

  @override
  void dispose() {
    _latido.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SizedBox.square(
      dimension: 8,
      child: AnimatedBuilder(
        animation: _latido,
        builder: (context, _) => CustomPaint(painter: _PintorPunto(widget.color, _latido.value)),
      ),
    );
  }
}

class _PintorPunto extends CustomPainter {
  _PintorPunto(this.color, this.t);

  final Color color;
  final double t;

  @override
  void paint(Canvas canvas, Size size) {
    final centro = size.center(Offset.zero);
    canvas.drawCircle(
      centro,
      size.width / 2 * (0.6 + t),
      Paint()
        ..color = color.withValues(alpha: 0.6 * (1 - t))
        ..style = PaintingStyle.stroke
        ..strokeWidth = 2,
    );
    canvas.drawCircle(centro, size.width / 2, Paint()..color = color);
  }

  @override
  bool shouldRepaint(covariant _PintorPunto anterior) => anterior.t != t;
}

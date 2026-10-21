import 'package:flutter/material.dart';

/// Colores de SIGMA (mismo sistema visual del prototipo y del panel web):
/// morado = marca y acción; verde lima = dato vivo; verde/ámbar/rojo = Bueno/Regular/Malo.
class SigmaColores {
  SigmaColores._();

  static const primario = Color(0xFF4E29AA);
  static const primarioClaro = Color(0xFF5A30C5);
  static const primarioOscuroModo = Color(0xFF9A7FE0);
  static const cian = Color(0xFF5C7A12);
  static const cianOscuroModo = Color(0xFF85AA29);
  static const lima = Color(0xFFB5D65A);
  static const bueno = Color(0xFF1F8A43);
  static const regular = Color(0xFFB86E06);
  static const malo = Color(0xFFC2362B);
  static const tinta = Color(0xFF150B2D);
  static const tintaTexto = Color(0xFFF2EFFA);
  static const tintaSuave = Color(0xFFAA94E0);
  static const superficieSuave = Color(0xFFF7F4FC);
  static const borde = Color(0xFFE6DFF6);
  static const textoSuave = Color(0xFF6B5B95);

  /// Fondo de la página y de las tarjetas en cada modo.
  static const paginaClaro = Color(0xFFF2EFFA);
  static const paginaOscuro = Color(0xFF160B33);
  static const tarjetaOscuro = Color(0xFF251452);
}

/// En modo oscuro los colores de estado y de marca pasan a sus variantes claras (prototipo),
/// para que las insignias y los botones B / R / M se lean sobre el fondo morado.
Color colorSegunModo(BuildContext context, Color color) {
  if (Theme.of(context).brightness != Brightness.dark) return color;
  return switch (color) {
    SigmaColores.primario || SigmaColores.primarioClaro => SigmaColores.primarioOscuroModo,
    SigmaColores.cian => SigmaColores.cianOscuroModo,
    SigmaColores.bueno => const Color(0xFF7FD69A),
    SigmaColores.regular => const Color(0xFFF2AE3D),
    SigmaColores.malo => const Color(0xFFFF8A80),
    SigmaColores.textoSuave => const Color(0xFFAA94E0),
    _ => color,
  };
}

/// Tipografías del prototipo (incluidas en assets/fuentes).
class SigmaFuentes {
  SigmaFuentes._();

  static const texto = 'Geist';
  static const titulos = 'BricolageGrotesque';
  static const mono = 'JetBrainsMono';
  static const marca = 'Michroma';
}

/// Degradado del borde luminoso de tarjetas y encabezados.
LinearGradient degradadoBorde(Brightness brillo) => brillo == Brightness.dark
    ? const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xD9CDC0ED), Color(0xBF5A30C5), Color(0xD93E2188), Color(0xBF5A30C5), Color(0xBF85AA29)],
        stops: [0, .28, .5, .72, 1],
      )
    : const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [Color(0xBF5A30C5), Color(0x99CDC0ED), Color(0xE6E6DFF6), Color(0x99CDC0ED), Color(0x9985AA29)],
        stops: [0, .28, .5, .72, 1],
      );

/// Borde redondeado pintado con un degradado (el "borde luminoso" del prototipo).
class BordeLuminoso extends ShapeBorder {
  const BordeLuminoso({required this.degradado, this.radio = 16, this.ancho = 1});

  final Gradient degradado;
  final double radio;
  final double ancho;

  RRect _rrect(Rect rect) => RRect.fromRectAndRadius(rect, Radius.circular(radio));

  @override
  EdgeInsetsGeometry get dimensions => EdgeInsets.all(ancho);

  @override
  Path getInnerPath(Rect rect, {TextDirection? textDirection}) => Path()..addRRect(_rrect(rect).deflate(ancho));

  @override
  Path getOuterPath(Rect rect, {TextDirection? textDirection}) => Path()..addRRect(_rrect(rect));

  @override
  void paint(Canvas canvas, Rect rect, {TextDirection? textDirection}) {
    canvas.drawRRect(
      _rrect(rect).deflate(ancho / 2),
      Paint()
        ..shader = degradado.createShader(rect)
        ..style = PaintingStyle.stroke
        ..strokeWidth = ancho,
    );
  }

  @override
  ShapeBorder scale(double t) => BordeLuminoso(degradado: degradado, radio: radio * t, ancho: ancho * t);
}

/// Estilo de etiqueta en mayúsculas y monoespaciada (eyebrow del prototipo).
TextStyle estiloEtiqueta(Color color, {double tamano = 11}) =>
    TextStyle(fontFamily: SigmaFuentes.mono, fontSize: tamano, letterSpacing: 1, color: color, fontWeight: FontWeight.w400);

ThemeData temaSigma(Brightness brillo) {
  final oscuro = brillo == Brightness.dark;
  final tarjeta = oscuro ? SigmaColores.tarjetaOscuro : Colors.white;
  final pagina = oscuro ? SigmaColores.paginaOscuro : SigmaColores.paginaClaro;
  final texto = oscuro ? const Color(0xFFF2EFFA) : const Color(0xFF22124A);
  final suave = oscuro ? const Color(0xFFAA94E0) : SigmaColores.textoSuave;
  final borde = oscuro ? const Color(0xFF3E2188) : SigmaColores.borde;
  final bordeFuerte = oscuro ? const Color(0xFF4E29AA) : const Color(0xFFCDC0ED);
  final primario = oscuro ? SigmaColores.primarioOscuroModo : SigmaColores.primario;
  final primarioSuave = oscuro ? const Color(0xFF3E2188) : const Color(0xFFE6DFF6);

  final esquema = ColorScheme.fromSeed(
    seedColor: SigmaColores.primario,
    brightness: brillo,
    primary: primario,
    onPrimary: oscuro ? SigmaColores.tinta : Colors.white,
    primaryContainer: primarioSuave,
    onPrimaryContainer: primario,
    secondary: oscuro ? SigmaColores.cianOscuroModo : SigmaColores.cian,
    error: oscuro ? const Color(0xFFFF8A80) : SigmaColores.malo,
    surface: tarjeta,
    onSurface: texto,
    onSurfaceVariant: suave,
    surfaceContainerHighest: oscuro ? const Color(0xFF2F1967) : const Color(0xFFEEEAF8),
    outline: bordeFuerte,
    outlineVariant: borde,
  );

  TextStyle titulo(double tamano, FontWeight peso, {double espaciado = -0.3}) =>
      TextStyle(fontFamily: SigmaFuentes.titulos, fontSize: tamano, fontWeight: peso, letterSpacing: espaciado, color: texto, height: 1.15);

  final base = ThemeData(useMaterial3: true, brightness: brillo, fontFamily: SigmaFuentes.texto);
  final textos = base.textTheme
      .apply(fontFamily: SigmaFuentes.texto, bodyColor: texto, displayColor: texto)
      .copyWith(
        displaySmall: titulo(34, FontWeight.w700, espaciado: -1),
        headlineMedium: titulo(28, FontWeight.w700, espaciado: -0.8),
        headlineSmall: titulo(24, FontWeight.w700, espaciado: -0.6),
        titleLarge: titulo(20, FontWeight.w600),
        titleMedium: titulo(16.5, FontWeight.w600, espaciado: -0.1),
      );

  return base.copyWith(
    colorScheme: esquema,
    textTheme: textos,
    scaffoldBackgroundColor: pagina,
    appBarTheme: AppBarTheme(
      backgroundColor: pagina,
      foregroundColor: texto,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      shadowColor: borde,
      titleTextStyle: titulo(20, FontWeight.w600),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      margin: EdgeInsets.zero,
      color: tarjeta,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: borde),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: bordeFuerte),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide(color: primario, width: 1.6),
      ),
      filled: true,
      fillColor: oscuro ? const Color(0xFF21114A) : SigmaColores.superficieSuave,
      labelStyle: TextStyle(color: suave),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(0, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: const TextStyle(fontFamily: SigmaFuentes.texto, fontWeight: FontWeight.w600, fontSize: 15),
        elevation: 0,
        shadowColor: primario,
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 44),
        foregroundColor: texto,
        side: BorderSide(color: bordeFuerte),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
        textStyle: const TextStyle(fontFamily: SigmaFuentes.texto, fontWeight: FontWeight.w500),
      ),
    ),
    textButtonTheme: TextButtonThemeData(
      style: TextButton.styleFrom(
        foregroundColor: primario,
        textStyle: const TextStyle(fontFamily: SigmaFuentes.texto, fontWeight: FontWeight.w600),
      ),
    ),
    // Control segmentado del prototipo: la opción elegida resalta en morado suave.
    segmentedButtonTheme: SegmentedButtonThemeData(
      style: ButtonStyle(
        backgroundColor: WidgetStateProperty.resolveWith((e) => e.contains(WidgetState.selected) ? primarioSuave : tarjeta),
        foregroundColor: WidgetStateProperty.resolveWith((e) => e.contains(WidgetState.selected) ? primario : suave),
        side: WidgetStatePropertyAll(BorderSide(color: borde)),
        textStyle: const WidgetStatePropertyAll(TextStyle(fontFamily: SigmaFuentes.texto, fontWeight: FontWeight.w500)),
      ),
    ),
    chipTheme: base.chipTheme.copyWith(
      backgroundColor: tarjeta,
      selectedColor: primarioSuave,
      side: BorderSide(color: borde),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      labelStyle: TextStyle(fontFamily: SigmaFuentes.texto, color: texto, fontSize: 13),
    ),
    // Barra inferior como la del prototipo: activo en morado sobre fondo suave.
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: tarjeta,
      surfaceTintColor: Colors.transparent,
      indicatorColor: primarioSuave,
      height: 66,
      labelTextStyle: WidgetStateProperty.resolveWith(
        (estados) => TextStyle(
          fontFamily: SigmaFuentes.texto,
          fontSize: 11,
          fontWeight: estados.contains(WidgetState.selected) ? FontWeight.w600 : FontWeight.w400,
          color: estados.contains(WidgetState.selected) ? primario : suave,
        ),
      ),
      iconTheme: WidgetStateProperty.resolveWith(
        (estados) => IconThemeData(color: estados.contains(WidgetState.selected) ? primario : suave),
      ),
    ),
    tabBarTheme: TabBarThemeData(
      labelColor: texto,
      unselectedLabelColor: suave,
      indicatorColor: primario,
      dividerColor: borde,
      labelStyle: const TextStyle(fontFamily: SigmaFuentes.texto, fontWeight: FontWeight.w600, fontSize: 13.5),
      unselectedLabelStyle: const TextStyle(fontFamily: SigmaFuentes.texto, fontSize: 13.5),
    ),
    // Avisos en tinta oscura con texto claro (toast del prototipo).
    snackBarTheme: SnackBarThemeData(
      backgroundColor: SigmaColores.tinta,
      contentTextStyle: const TextStyle(fontFamily: SigmaFuentes.texto, color: SigmaColores.tintaTexto, fontWeight: FontWeight.w500),
      actionTextColor: const Color(0xFFCDC0ED),
      behavior: SnackBarBehavior.floating,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
    dialogTheme: DialogThemeData(
      backgroundColor: tarjeta,
      surfaceTintColor: Colors.transparent,
      shape: BordeLuminoso(degradado: degradadoBorde(brillo), radio: 20),
      titleTextStyle: titulo(20, FontWeight.w600),
    ),
    bottomSheetTheme: BottomSheetThemeData(backgroundColor: tarjeta, surfaceTintColor: Colors.transparent),
    progressIndicatorTheme: ProgressIndicatorThemeData(
      color: primario,
      linearTrackColor: oscuro ? const Color(0xFF2F1967) : const Color(0xFFEEEAF8),
    ),
    dividerTheme: DividerThemeData(color: borde, space: 1),
  );
}

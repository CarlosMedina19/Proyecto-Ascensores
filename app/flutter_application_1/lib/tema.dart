import 'package:flutter/material.dart';

/// Colores de SIGMA (mismo sistema visual del panel web):
/// morado = marca y acción; cian = dato vivo; verde/ámbar/rojo = Bueno/Regular/Malo.
class SigmaColores {
  SigmaColores._();

  static const primario = Color(0xFF6B3FD4);
  static const primarioClaro = Color(0xFF8257F0);
  static const primarioOscuroModo = Color(0xFFA983FF);
  static const cian = Color(0xFF0B8CAB);
  static const cianOscuroModo = Color(0xFF38D3EE);
  static const bueno = Color(0xFF1F9D5C);
  static const regular = Color(0xFFC27508);
  static const malo = Color(0xFFD9344A);
  static const tinta = Color(0xFF120D1C);
  static const superficieSuave = Color(0xFFF8F6FC);
  static const borde = Color(0xFFE6E0F0);
  static const textoSuave = Color(0xFF655E74);
}

ThemeData temaSigma(Brightness brillo) {
  final oscuro = brillo == Brightness.dark;
  final esquema = ColorScheme.fromSeed(
    seedColor: SigmaColores.primario,
    brightness: brillo,
    primary: oscuro ? SigmaColores.primarioOscuroModo : SigmaColores.primario,
    secondary: oscuro ? SigmaColores.cianOscuroModo : SigmaColores.cian,
    error: SigmaColores.malo,
    surface: oscuro ? const Color(0xFF0A0810) : Colors.white,
  );

  final borde = oscuro ? const Color(0xFF252030) : SigmaColores.borde;

  return ThemeData(
    useMaterial3: true,
    colorScheme: esquema,
    scaffoldBackgroundColor: esquema.surface,
    appBarTheme: AppBarTheme(
      backgroundColor: esquema.surface,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      scrolledUnderElevation: 0.5,
      titleTextStyle: TextStyle(color: esquema.onSurface, fontSize: 20, fontWeight: FontWeight.w700, letterSpacing: -0.3),
    ),
    cardTheme: CardThemeData(
      elevation: 0,
      margin: EdgeInsets.zero,
      color: oscuro ? const Color(0xFF120F19) : Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: borde),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(11)),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(11),
        borderSide: BorderSide(color: oscuro ? const Color(0xFF382F48) : const Color(0xFFD2C9E2)),
      ),
      filled: true,
      fillColor: oscuro ? const Color(0xFF120F19) : Colors.white,
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        minimumSize: const Size(0, 48),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
        textStyle: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        minimumSize: const Size(0, 44),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(11)),
      ),
    ),
    navigationBarTheme: NavigationBarThemeData(
      backgroundColor: oscuro ? const Color(0xFF120F19) : SigmaColores.superficieSuave,
      indicatorColor: esquema.primaryContainer,
    ),
    dividerTheme: DividerThemeData(color: borde, space: 1),
  );
}

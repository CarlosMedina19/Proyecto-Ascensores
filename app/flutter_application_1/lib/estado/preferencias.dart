import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Preferencias locales del dispositivo (tema claro/oscuro).
class Preferencias extends ChangeNotifier {
  static const _claveTema = 'sigma.tema';

  ThemeMode modoTema = ThemeMode.system;

  Future<void> cargar() async {
    final preferencias = await SharedPreferences.getInstance();
    modoTema = switch (preferencias.getString(_claveTema)) {
      'oscuro' => ThemeMode.dark,
      'claro' => ThemeMode.light,
      _ => ThemeMode.system,
    };
    notifyListeners();
  }

  Future<void> cambiarTema(ThemeMode modo) async {
    modoTema = modo;
    notifyListeners();
    final preferencias = await SharedPreferences.getInstance();
    await preferencias.setString(_claveTema, modo == ThemeMode.dark ? 'oscuro' : 'claro');
  }
}

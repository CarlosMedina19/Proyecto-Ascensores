import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Preferencias locales del dispositivo (tema claro/oscuro y modo demostración).
class Preferencias extends ChangeNotifier {
  static const _claveTema = 'sigma.tema';
  static const _claveDemostracion = 'sigma.demostracion';

  ThemeMode modoTema = ThemeMode.system;

  /// Muestra las referencias del proyecto formativo. Apagado, la app solo muestra textos comerciales.
  bool demostracion = false;

  Future<void> cargar() async {
    final preferencias = await SharedPreferences.getInstance();
    modoTema = switch (preferencias.getString(_claveTema)) {
      'oscuro' => ThemeMode.dark,
      'claro' => ThemeMode.light,
      _ => ThemeMode.system,
    };
    demostracion = preferencias.getBool(_claveDemostracion) ?? false;
    notifyListeners();
  }

  Future<void> cambiarTema(ThemeMode modo) async {
    modoTema = modo;
    notifyListeners();
    final preferencias = await SharedPreferences.getInstance();
    await preferencias.setString(_claveTema, modo == ThemeMode.dark ? 'oscuro' : 'claro');
  }

  Future<void> cambiarDemostracion(bool activo) async {
    demostracion = activo;
    notifyListeners();
    final preferencias = await SharedPreferences.getInstance();
    await preferencias.setBool(_claveDemostracion, activo);
  }
}

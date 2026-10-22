import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:provider/provider.dart';

import 'datos/cliente_api.dart';
import 'datos/repositorio.dart';
import 'estado/preferencias.dart';
import 'estado/sesion.dart';
import 'pantallas/inicio_sesion.dart';
import 'pantallas/principal.dart';
import 'tema.dart';
import 'widgets/comunes.dart';
import 'widgets/puertas.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  // Si algo falla al preparar fechas o preferencias, la app arranca igual con los valores por defecto.
  try {
    await initializeDateFormatting('es');
  } catch (_) {}

  final preferencias = Preferencias();
  try {
    await preferencias.cargar().timeout(const Duration(seconds: 5));
  } catch (_) {}
  final sesion = Sesion(repositorio: Repositorio(ClienteApi()));
  sesion.iniciar();

  runApp(SigmaApp(sesion: sesion, preferencias: preferencias));
}

/// SIGMA — Sistema Integral de Gestión y Mantenimiento de Ascensores.
/// Una sola app con interfaces según el rol del usuario.
class SigmaApp extends StatelessWidget {
  const SigmaApp({super.key, required this.sesion, required this.preferencias});

  final Sesion sesion;
  final Preferencias preferencias;

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: sesion),
        ChangeNotifierProvider.value(value: preferencias),
      ],
      child: Consumer<Preferencias>(
        builder: (context, preferencias, _) => MaterialApp(
          debugShowCheckedModeBanner: false,
          title: 'SIGMA',
          theme: temaSigma(Brightness.light),
          darkTheme: temaSigma(Brightness.dark),
          themeMode: preferencias.modoTema,
          locale: const Locale('es', 'CO'),
          supportedLocales: const [Locale('es', 'CO'), Locale('es')],
          localizationsDelegates: GlobalMaterialLocalizations.delegates,
          // Las puertas de ascensor quedan por encima de todas las pantallas.
          builder: (context, child) => PuertasSigma(child: child!),
          home: const _Raiz(),
        ),
      ),
    );
  }
}

/// Muestra el inicio de sesión o la app según el estado de la sesión.
class _Raiz extends StatelessWidget {
  const _Raiz();

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<Sesion>();
    if (sesion.cargando) {
      return const Scaffold(body: Center(child: LogoSigma(tamano: 64)));
    }
    // Al cerrar la sesión se descartan las pantallas abiertas.
    return sesion.autenticado
        ? const PantallaPrincipal(key: ValueKey('principal'))
        : const PantallaInicioSesion(key: ValueKey('inicio-sesion'));
  }
}

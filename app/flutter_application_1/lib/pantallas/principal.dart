import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/modelos.dart';
import '../estado/sesion.dart';
import 'edificios.dart';
import 'equipos.dart';
import 'inicio.dart';
import 'ordenes.dart';
import 'perfil.dart';
import 'visitas.dart';

class _Pestana {
  const _Pestana(this.titulo, this.icono, this.iconoActivo, this.pantalla);

  final String titulo;
  final IconData icono;
  final IconData iconoActivo;
  final Widget pantalla;
}

/// Una sola app con interfaces según el rol (técnico, cliente o personal administrativo).
List<_Pestana> _pestanasPara(Usuario usuario) {
  final cliente = usuario.esCliente;
  return [
    const _Pestana('Inicio', Icons.home_outlined, Icons.home, PantallaInicio()),
    // Fase 3: órdenes de trabajo (el técnico ve solo las suyas).
    if (usuario.tienePermiso('ordenes.ver'))
      const _Pestana('Órdenes', Icons.assignment_turned_in_outlined, Icons.assignment_turned_in, PantallaOrdenes()),
    if (usuario.tienePermiso('equipos.ver'))
      _Pestana(cliente ? 'Mis ascensores' : 'Ascensores', Icons.elevator_outlined, Icons.elevator, const PantallaEquipos()),
    if (usuario.tienePermiso('edificios.ver'))
      _Pestana(cliente ? 'Edificios' : 'Edificios', Icons.apartment_outlined, Icons.apartment, const PantallaEdificios()),
    if (usuario.tienePermiso('equipos.historial.crear'))
      const _Pestana('Visitas', Icons.assignment_outlined, Icons.assignment, PantallaVisitas()),
    const _Pestana('Perfil', Icons.person_outline, Icons.person, PantallaPerfil()),
  ];
}

class PantallaPrincipal extends StatefulWidget {
  const PantallaPrincipal({super.key});

  @override
  State<PantallaPrincipal> createState() => _PantallaPrincipalState();
}

class _PantallaPrincipalState extends State<PantallaPrincipal> {
  int _indice = 0;

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<Sesion>();
    final pestanas = _pestanasPara(sesion.usuario!);
    final indice = _indice.clamp(0, pestanas.length - 1);

    // Cualquier toque cuenta como actividad (RF-001).
    return Listener(
      behavior: HitTestBehavior.translucent,
      onPointerDown: (_) => sesion.registrarActividad(),
      child: Stack(
        children: [
          Scaffold(
            body: IndexedStack(index: indice, children: [for (final p in pestanas) p.pantalla]),
            bottomNavigationBar: NavigationBar(
              selectedIndex: indice,
              onDestinationSelected: (i) => setState(() => _indice = i),
              destinations: [
                for (final p in pestanas) NavigationDestination(icon: Icon(p.icono), selectedIcon: Icon(p.iconoActivo), label: p.titulo),
              ],
            ),
          ),
          if (sesion.segundosParaCierre != null) _AvisoInactividad(segundos: sesion.segundosParaCierre!),
        ],
      ),
    );
  }
}

class _AvisoInactividad extends StatelessWidget {
  const _AvisoInactividad({required this.segundos});

  final int segundos;

  @override
  Widget build(BuildContext context) {
    final sesion = context.read<Sesion>();
    return Positioned.fill(
      child: Material(
        color: Colors.black54,
        child: Center(
          child: Card(
            margin: const EdgeInsets.all(24),
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text('¿Sigue ahí?', style: TextStyle(fontSize: 20, fontWeight: FontWeight.w600)),
                  const SizedBox(height: 8),
                  Text(
                    'Por seguridad, su sesión se cerrará en $segundos segundos por inactividad. Las visitas guardadas en el dispositivo no se pierden.',
                  ),
                  const SizedBox(height: 18),
                  FilledButton(onPressed: sesion.mantenerSesion, child: const Text('Mantener sesión activa')),
                  const SizedBox(height: 8),
                  OutlinedButton(onPressed: () => sesion.cerrarSesion(), child: const Text('Cerrar sesión')),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

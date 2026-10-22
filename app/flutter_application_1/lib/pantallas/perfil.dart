import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/cliente_api.dart';
import '../estado/preferencias.dart';
import '../estado/sesion.dart';
import '../widgets/comunes.dart';

/// Datos de la cuenta, cambio de contraseña (RF-001), tema y cierre de sesión.
class PantallaPerfil extends StatelessWidget {
  const PantallaPerfil({super.key});

  @override
  Widget build(BuildContext context) {
    final sesion = context.watch<Sesion>();
    final preferencias = context.watch<Preferencias>();
    final usuario = sesion.usuario!;

    return Scaffold(
      appBar: AppBar(title: const Text('Mi perfil')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 28,
                backgroundColor: Theme.of(context).colorScheme.primary,
                child: Text(
                  usuario.nombre.split(' ').where((p) => p.isNotEmpty).take(2).map((p) => p[0].toUpperCase()).join(),
                  style: TextStyle(color: Theme.of(context).colorScheme.onPrimary, fontWeight: FontWeight.w600, fontSize: 18),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(usuario.nombre, style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w600)),
                    Text(usuario.correo),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Datos([
            ('Rol', usuario.nombreRol),
            ('Cliente', usuario.clienteNombre),
            ('Último acceso', fechaHora(usuario.ultimoAcceso)),
            ('Cierre por inactividad', '${sesion.minutosInactividad} min'),
          ]),
          const SizedBox(height: 18),
          Card(
            child: Column(
              children: [
                ListTile(
                  leading: const Icon(Icons.key_outlined),
                  title: const Text('Cambiar contraseña'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const PantallaCambiarContrasena())),
                ),
                const Divider(),
                SwitchListTile(
                  secondary: const Icon(Icons.dark_mode_outlined),
                  title: const Text('Modo oscuro'),
                  value: preferencias.modoTema == ThemeMode.dark,
                  onChanged: (oscuro) => preferencias.cambiarTema(oscuro ? ThemeMode.dark : ThemeMode.light),
                ),
                const Divider(),
                SwitchListTile(
                  secondary: const Icon(Icons.co_present_outlined),
                  title: const Text('Modo demostración'),
                  subtitle: const Text('Muestra las referencias del proyecto formativo. Desactívelo ante un cliente.'),
                  value: preferencias.demostracion,
                  onChanged: preferencias.cambiarDemostracion,
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          OutlinedButton.icon(onPressed: () => sesion.cerrarSesion(), icon: const Icon(Icons.logout), label: const Text('Cerrar sesión')),
          const SizedBox(height: 8),
          Text(
            'Las visitas guardadas en este dispositivo se conservan al cerrar la sesión.',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 12, color: Theme.of(context).colorScheme.onSurfaceVariant),
          ),
        ],
      ),
    );
  }
}

class PantallaCambiarContrasena extends StatefulWidget {
  const PantallaCambiarContrasena({super.key});

  @override
  State<PantallaCambiarContrasena> createState() => _PantallaCambiarContrasenaState();
}

class _PantallaCambiarContrasenaState extends State<PantallaCambiarContrasena> {
  final _actual = TextEditingController();
  final _nueva = TextEditingController();
  final _confirmacion = TextEditingController();
  Map<String, String> _errores = {};
  String? _error;
  bool _enviando = false;

  @override
  void dispose() {
    _actual.dispose();
    _nueva.dispose();
    _confirmacion.dispose();
    super.dispose();
  }

  Future<void> _guardar() async {
    setState(() {
      _enviando = true;
      _errores = {};
      _error = null;
    });
    try {
      final mensaje = await context.read<Sesion>().repositorio.cambiarContrasena(
        actual: _actual.text,
        nueva: _nueva.text,
        confirmacion: _confirmacion.text,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(mensaje)));
      Navigator.of(context).pop();
    } on ErrorApi catch (e) {
      setState(() {
        _errores = {
          for (final c in ['contrasena_actual', 'password'])
            if (e.errorDe(c) != null) c: e.errorDe(c)!,
        };
        if (_errores.isEmpty) _error = e.mensaje;
      });
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Cambiar contraseña')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text('Use al menos 8 caracteres, con letras y números. Se cerrarán sus sesiones en otros dispositivos.'),
          const SizedBox(height: 16),
          if (_error != null) Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
          TextField(
            controller: _actual,
            obscureText: true,
            decoration: InputDecoration(labelText: 'Contraseña actual', errorText: _errores['contrasena_actual']),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _nueva,
            obscureText: true,
            decoration: InputDecoration(labelText: 'Nueva contraseña', errorText: _errores['password']),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _confirmacion,
            obscureText: true,
            decoration: const InputDecoration(labelText: 'Confirmar nueva contraseña'),
          ),
          const SizedBox(height: 18),
          FilledButton(onPressed: _enviando ? null : _guardar, child: Text(_enviando ? 'Guardando…' : 'Cambiar contraseña')),
        ],
      ),
    );
  }
}

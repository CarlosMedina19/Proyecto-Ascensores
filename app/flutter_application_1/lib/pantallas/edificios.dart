import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/modelos.dart';
import '../estado/sesion.dart';
import '../widgets/comunes.dart';
import 'equipos.dart';

/// RF-006: edificios de los clientes (el cliente solo ve los suyos).
class PantallaEdificios extends StatefulWidget {
  const PantallaEdificios({super.key});

  @override
  State<PantallaEdificios> createState() => _PantallaEdificiosState();
}

class _PantallaEdificiosState extends State<PantallaEdificios> {
  final _busqueda = TextEditingController();
  Timer? _espera;
  Future<Pagina<Edificio>>? _pagina;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  @override
  void dispose() {
    _espera?.cancel();
    _busqueda.dispose();
    super.dispose();
  }

  void _cargar() {
    setState(() {
      _pagina = context.read<Sesion>().repositorio.edificios(busqueda: _busqueda.text.trim());
    });
  }

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Edificios')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
            child: TextField(
              controller: _busqueda,
              onChanged: (_) {
                _espera?.cancel();
                _espera = Timer(const Duration(milliseconds: 400), _cargar);
              },
              decoration: const InputDecoration(hintText: 'Nombre, dirección o ciudad', prefixIcon: Icon(Icons.search), isDense: true),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () async => _cargar(),
              child: FutureBuilder<Pagina<Edificio>>(
                future: _pagina,
                builder: (context, instantanea) {
                  if (instantanea.hasError) {
                    return ListView(
                      children: [VistaError(mensaje: mensajeError(instantanea.error!), alReintentar: _cargar)],
                    );
                  }
                  if (!instantanea.hasData) return const Cargando();
                  final lista = instantanea.data!.elementos;
                  if (lista.isEmpty) return ListView(children: const [EstadoVacio(titulo: 'No se encontraron edificios')]);
                  return ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    itemCount: lista.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 10),
                    itemBuilder: (context, i) {
                      final edificio = lista[i];
                      return Card(
                        child: ListTile(
                          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                          leading: CircleAvatar(
                            backgroundColor: esquema.secondaryContainer,
                            child: Icon(Icons.apartment, color: esquema.onSecondaryContainer),
                          ),
                          title: Text(edificio.nombre, style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: Text(
                            [edificio.direccion, edificio.ciudad].whereType<String>().join(' · ') +
                                (edificio.clienteNombre != null ? '\n${edificio.clienteNombre}' : ''),
                          ),
                          isThreeLine: edificio.clienteNombre != null,
                          trailing: Text(
                            '${edificio.cantidadEquipos ?? 0}\nascensores',
                            textAlign: TextAlign.center,
                            style: const TextStyle(fontSize: 12),
                          ),
                          onTap: () =>
                              Navigator.of(context)
                                  .push(MaterialPageRoute(builder: (_) => PantallaDetalleEdificio(edificioId: edificio.id))),
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class PantallaDetalleEdificio extends StatefulWidget {
  const PantallaDetalleEdificio({super.key, required this.edificioId});

  final int edificioId;

  @override
  State<PantallaDetalleEdificio> createState() => _PantallaDetalleEdificioState();
}

class _PantallaDetalleEdificioState extends State<PantallaDetalleEdificio> {
  late Future<Edificio> _edificio;

  @override
  void initState() {
    super.initState();
    _edificio = context.read<Sesion>().repositorio.edificio(widget.edificioId);
  }

  void _recargar() => setState(() {
    _edificio = context.read<Sesion>().repositorio.edificio(widget.edificioId);
  });

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<Edificio>(
      future: _edificio,
      builder: (context, instantanea) {
        if (instantanea.hasError) {
          return Scaffold(
            appBar: AppBar(),
            body: VistaError(mensaje: mensajeError(instantanea.error!), alReintentar: _recargar),
          );
        }
        if (!instantanea.hasData) return Scaffold(appBar: AppBar(), body: const Cargando());
        final edificio = instantanea.data!;
        return Scaffold(
          appBar: AppBar(title: Text(edificio.nombre)),
          body: ListView(
            padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(edificio.clienteNombre ?? '', style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
                  ),
                  Insignia(
                    texto: edificio.activo ? 'Activo' : 'Inactivo',
                    color: edificio.activo ? estadoEquipo('activo').color : estadoEquipo('inactivo').color,
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Seccion(
                titulo: 'Ubicación y contacto',
                hijos: [
                  Datos([
                    ('Dirección', edificio.direccion),
                    ('Ciudad', [edificio.ciudad, edificio.departamento].whereType<String>().join(', ')),
                    ('Pisos', edificio.pisos?.toString()),
                    ('Contacto', edificio.nombreContacto),
                    ('Teléfono', edificio.telefonoContacto),
                    ('Correo', edificio.correoContacto),
                  ]),
                  if (edificio.observaciones != null && edificio.observaciones!.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Text(edificio.observaciones!),
                  ],
                ],
              ),
              const SizedBox(height: 16),
              Text('Ascensores (${edificio.equipos.length})', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
              const SizedBox(height: 8),
              if (edificio.equipos.isEmpty) const EstadoVacio(titulo: 'El edificio no tiene ascensores registrados'),
              for (final equipo in edificio.equipos) FilaEquipo(equipo: equipo, alVolver: _recargar),
            ],
          ),
        );
      },
    );
  }
}

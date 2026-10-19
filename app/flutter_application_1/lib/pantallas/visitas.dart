import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../estado/sesion.dart';
import '../estado/visitas.dart';
import '../widgets/comunes.dart';
import 'equipos.dart';

/// Pestaña del técnico: visitas guardadas en el dispositivo y pendientes por enviar.
/// La agenda y las órdenes de trabajo llegan en la fase 3.
class PantallaVisitas extends StatefulWidget {
  const PantallaVisitas({super.key});

  @override
  State<PantallaVisitas> createState() => _PantallaVisitasState();
}

class _PantallaVisitasState extends State<PantallaVisitas> {
  late Future<List<VisitaBorrador>> _pendientes;

  @override
  void initState() {
    super.initState();
    _pendientes = AlmacenVisitas().pendientes();
  }

  void _recargar() => setState(() {
    _pendientes = AlmacenVisitas().pendientes();
  });

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    context.watch<Sesion>();
    return Scaffold(
      appBar: AppBar(title: const Text('Visitas')),
      body: RefreshIndicator(
        onRefresh: () async => _recargar(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: ListTile(
                leading: Icon(Icons.calendar_month_outlined, color: esquema.primary),
                title: const Text('Agenda y órdenes de trabajo'),
                subtitle: const Text('Se habilitan en la fase 3. Por ahora, registre la visita desde la ficha del ascensor.'),
              ),
            ),
            const SizedBox(height: 18),
            const Text('Guardadas en el dispositivo', style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700)),
            const SizedBox(height: 4),
            Text(
              'Funcionan sin conexión. Ábralas para completarlas y enviarlas al historial.',
              style: TextStyle(color: esquema.onSurfaceVariant),
            ),
            const SizedBox(height: 10),
            FutureBuilder<List<VisitaBorrador>>(
              future: _pendientes,
              builder: (context, instantanea) {
                if (!instantanea.hasData) return const Cargando();
                final lista = instantanea.data!;
                if (lista.isEmpty) {
                  return const EstadoVacio(
                    titulo: 'No hay visitas pendientes',
                    descripcion: 'Abra un ascensor y toque "Registrar visita".',
                    icono: Icons.assignment_turned_in_outlined,
                  );
                }
                return Column(
                  children: [
                    for (final visita in lista)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: Card(
                          child: ListTile(
                            leading: Icon(Icons.edit_note, color: esquema.primary),
                            title: Text('Ascensor ${visita.equipoCodigo}', style: const TextStyle(fontWeight: FontWeight.w700)),
                            subtitle: Text(
                              'Iniciada ${fechaHora(visita.inicio)} · ${visita.zonasRevisadas}/${visita.zonas.length} zonas'
                              '${visita.listaParaEnviar ? ' · lista para enviar' : ''}',
                            ),
                            trailing: const Icon(Icons.chevron_right),
                            onTap: () async {
                              await Navigator.of(context)
                                  .push(MaterialPageRoute(builder: (_) => PantallaDetalleEquipo(equipoId: visita.equipoId)));
                              _recargar();
                            },
                          ),
                        ),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

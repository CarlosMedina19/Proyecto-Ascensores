import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../datos/cliente_api.dart';
import '../datos/modelos.dart';
import '../estado/sesion.dart';
import '../estado/visitas.dart';
import '../widgets/comunes.dart';
import 'equipos.dart';

/// Pestaña del técnico: planillas por enviar (guardadas en el celular, funcionan sin conexión) y las ya enviadas.
class PantallaVisitas extends StatefulWidget {
  const PantallaVisitas({super.key});

  @override
  State<PantallaVisitas> createState() => _PantallaVisitasState();
}

class _PantallaVisitasState extends State<PantallaVisitas> {
  late Future<List<VisitaBorrador>> _pendientes;
  late Future<List<VisitaEnviada>> _enviadas;

  @override
  void initState() {
    super.initState();
    _cargar();
  }

  void _cargar() {
    _pendientes = AlmacenVisitas().pendientes();
    _enviadas = context.read<Sesion>().repositorio.visitasRecientes();
  }

  Future<void> _recargar() async {
    setState(_cargar);
    await Future.wait([_pendientes.catchError((_) => <VisitaBorrador>[]), _enviadas.catchError((_) => <VisitaEnviada>[])]);
  }

  Future<void> _abrirEquipo(int equipoId) async {
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => PantallaDetalleEquipo(equipoId: equipoId)));
    if (mounted) await _recargar();
  }

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Visitas')),
      body: RefreshIndicator(
        onRefresh: _recargar,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 96),
          children: [
            Card(
              child: ListTile(
                leading: Icon(Icons.edit_document, color: esquema.primary),
                title: const Text('Registrar una visita'),
                subtitle: const Text('Abra el ascensor (pestaña Ascensores o el QR de la cabina) y toque «Registrar visita».'),
              ),
            ),
            const SizedBox(height: 18),
            const _Titulo('Por enviar', 'Guardadas en este celular: funcionan sin conexión. Ábralas para completarlas y enviarlas.'),
            FutureBuilder<List<VisitaBorrador>>(
              future: _pendientes,
              builder: (context, instantanea) {
                if (instantanea.connectionState != ConnectionState.done) return const _CargandoCorto();
                final lista = instantanea.data ?? const <VisitaBorrador>[];
                if (lista.isEmpty) {
                  return const _Vacio(Icons.assignment_turned_in_outlined, 'No hay planillas pendientes en este celular.');
                }
                return Column(
                  children: [
                    for (final visita in lista)
                      Card(
                        child: ListTile(
                          leading: Icon(Icons.edit_note, color: esquema.primary),
                          title: Text('Ascensor ${visita.equipoCodigo}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: Text(
                            'Iniciada ${fechaHora(visita.inicio)} · ${visita.respondidos}/${visita.totalItems} actividades'
                            '${visita.listaParaEnviar ? ' · lista para enviar' : ''}',
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => _abrirEquipo(visita.equipoId),
                        ),
                      ),
                  ],
                );
              },
            ),
            const SizedBox(height: 18),
            const _Titulo('Enviadas', 'Sus últimas planillas de asistencia técnica. El PDF queda en la ficha del ascensor.'),
            FutureBuilder<List<VisitaEnviada>>(
              future: _enviadas,
              builder: (context, instantanea) {
                if (instantanea.connectionState != ConnectionState.done) return const _CargandoCorto();
                if (instantanea.hasError) {
                  final error = instantanea.error;
                  return _Vacio(Icons.cloud_off_outlined, error is ErrorApi ? error.mensaje : 'No fue posible cargar las planillas enviadas.');
                }
                final lista = instantanea.data ?? const <VisitaEnviada>[];
                if (lista.isEmpty) return const _Vacio(Icons.inbox_outlined, 'Aún no ha enviado planillas.');
                return Column(
                  children: [
                    for (final visita in lista)
                      Card(
                        child: ListTile(
                          leading: CircleAvatar(
                            backgroundColor: esquema.primaryContainer,
                            child: Icon(Icons.description_outlined, color: esquema.onPrimaryContainer),
                          ),
                          title: Text('Planilla ${visita.numero} · ${visita.equipoCodigo}', style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: Text(
                            [
                              visita.tipo,
                              if (visita.edificio != null) visita.edificio!,
                              fechaHora(visita.salida),
                              'B ${visita.conteo['B'] ?? 0} · R ${visita.conteo['R'] ?? 0} · M ${visita.conteo['M'] ?? 0}',
                            ].join(' · '),
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: visita.equipoId > 0 ? () => _abrirEquipo(visita.equipoId) : null,
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

class _Titulo extends StatelessWidget {
  const _Titulo(this.titulo, this.descripcion);

  final String titulo;
  final String descripcion;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(titulo, style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w600)),
          const SizedBox(height: 2),
          Text(descripcion, style: TextStyle(color: Theme.of(context).colorScheme.onSurfaceVariant)),
        ],
      ),
    );
  }
}

/// Indicador pequeño: no ocupa media pantalla mientras carga.
class _CargandoCorto extends StatelessWidget {
  const _CargandoCorto();

  @override
  Widget build(BuildContext context) => const Padding(
    padding: EdgeInsets.symmetric(vertical: 16),
    child: Center(child: SizedBox(width: 24, height: 24, child: CircularProgressIndicator(strokeWidth: 2.5))),
  );
}

class _Vacio extends StatelessWidget {
  const _Vacio(this.icono, this.texto);

  final IconData icono;
  final String texto;

  @override
  Widget build(BuildContext context) {
    final esquema = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(border: Border.all(color: esquema.outlineVariant), borderRadius: BorderRadius.circular(12)),
      child: Row(
        children: [
          Icon(icono, color: esquema.onSurfaceVariant),
          const SizedBox(width: 12),
          Expanded(child: Text(texto, style: TextStyle(color: esquema.onSurfaceVariant))),
        ],
      ),
    );
  }
}

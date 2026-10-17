
import 'package:flutter/material.dart';
import 'dart:typed_data';
import 'package:image_picker/image_picker.dart';
import 'package:signature/signature.dart';

void main() {
  runApp(const MyApp());
}

// APLICACIÓN PRINCIPAL
class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'Proyecto Ascensores',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: Colors.blue,
        ),
        useMaterial3: true,
      ),
      home: const LoginPage(),
    );
  }
}

// INICIO DE SESIÓN
class LoginPage extends StatefulWidget {
  const LoginPage({super.key});

  @override
  State<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends State<LoginPage> {
  final correoController = TextEditingController();
  final passwordController = TextEditingController();

  String mensaje = '';

  @override
  void dispose() {
    correoController.dispose();
    passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Proyecto Ascensores'),
      ),
      body: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            children: [
              const Icon(Icons.elevator, size: 80),
              const SizedBox(height: 15),
              const Text(
                'Iniciar sesión',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 30),
              TextField(
                controller: correoController,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'Correo',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.email),
                ),
              ),
              const SizedBox(height: 20),
              TextField(
                controller: passwordController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Contraseña',
                  border: OutlineInputBorder(),
                  prefixIcon: Icon(Icons.lock),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () {
                    if (correoController.text.trim().isEmpty) {
                      setState(() {
                        mensaje = 'Escribe tu correo';
                      });
                    } else if (passwordController.text.isEmpty) {
                      setState(() {
                        mensaje = 'Escribe tu contraseña';
                      });
                    } else {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) =>
                              const PantallaPrincipal(),
                        ),
                      );
                    }
                  },
                  child: const Text('INGRESAR'),
                ),
              ),
              const SizedBox(height: 10),
              Text(
                mensaje,
                style: const TextStyle(color: Colors.red),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// SELECCIÓN DE ROL
class PantallaPrincipal extends StatelessWidget {
  const PantallaPrincipal({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Proyecto Ascensores'),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.elevator, size: 80),
              const SizedBox(height: 15),
              const Text(
                'Bienvenido',
                style: TextStyle(
                  fontSize: 28,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(height: 30),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const MenuTecnico(),
                      ),
                    );
                  },
                  icon: const Icon(Icons.engineering),
                  label: const Text('Ingresar como técnico'),
                ),
              ),
              const SizedBox(height: 15),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => const MenuCliente(),
                      ),
                    );
                  },
                  icon: const Icon(Icons.person),
                  label: const Text('Ingresar como cliente'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// MENÚ DEL TÉCNICO
class MenuTecnico extends StatelessWidget {
  const MenuTecnico({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Panel del técnico'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Gestión de mantenimiento',
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 15),
          ListTile(
            leading: const Icon(Icons.calendar_month),
            title: const Text('Mi agenda'),
            subtitle: const Text('Consultar visitas programadas'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const AgendaPage(),
                ),
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.assignment),
            title: const Text('Órdenes de trabajo'),
            subtitle: const Text('Reparaciones y mantenimientos'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const OrdenesTrabajoPage(),
                ),
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.elevator),
            title: const Text('Equipos'),
            subtitle: const Text('Consultar ascensores'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const PaginaDetalle(
                    titulo: 'Equipos',
                    descripcion:
                        'Aquí aparecerá el listado de ascensores.',
                    icono: Icons.elevator,
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

// AGENDA DEL TÉCNICO
class AgendaPage extends StatelessWidget {
  const AgendaPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mi agenda')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: const [
          Text(
            'Visitas programadas',
            style: TextStyle(
              fontSize: 23,
              fontWeight: FontWeight.bold,
            ),
          ),
          SizedBox(height: 16),
          Card(
            child: ListTile(
              leading: Icon(
                Icons.calendar_month,
                color: Colors.blue,
              ),
              title: Text('Mantenimiento preventivo'),
              subtitle: Text(
                'Ascensor A-01\nFecha: Por programar',
              ),
            ),
          ),
          Card(
            child: ListTile(
              leading: Icon(
                Icons.warning_amber,
                color: Colors.orange,
              ),
              title: Text('Revisión de avería'),
              subtitle: Text(
                'Ascensor B-02\nFecha: Por programar',
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ÓRDENES DE TRABAJO
class OrdenesTrabajoPage extends StatelessWidget {
  const OrdenesTrabajoPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Órdenes de trabajo'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Mis órdenes asignadas',
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 16),
          Card(
            child: ListTile(
              leading: const Icon(
                Icons.build_circle,
                color: Colors.orange,
                size: 40,
              ),
              title: const Text('Orden #001'),
              subtitle: const Text(
                'Mantenimiento preventivo\n'
                'Ascensor A-01\n'
                'Estado: Pendiente',
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const DetalleOrdenPage(
                      numero: 'Orden #001',
                      equipo: 'Ascensor A-01',
                      tipo: 'Mantenimiento preventivo',
                      estado: 'Pendiente',
                    ),
                  ),
                );
              },
            ),
          ),
          const SizedBox(height: 12),
          Card(
            child: ListTile(
              leading: const Icon(
                Icons.warning_amber,
                color: Colors.red,
                size: 40,
              ),
              title: const Text('Orden #002'),
              subtitle: const Text(
                'Revisión de avería\n'
                'Ascensor B-02\n'
                'Estado: Prioritaria',
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const DetalleOrdenPage(
                      numero: 'Orden #002',
                      equipo: 'Ascensor B-02',
                      tipo: 'Revisión de avería',
                      estado: 'Prioritaria',
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

// DETALLE DE CADA ORDEN Y CHECKLIST
class DetalleOrdenPage extends StatefulWidget {
  final String numero;
  final String equipo;
  final String tipo;
  final String estado;

  const DetalleOrdenPage({
    super.key,
    required this.numero,
    required this.equipo,
    required this.tipo,
    required this.estado,
  });

  @override
  State<DetalleOrdenPage> createState() =>
      _DetalleOrdenPageState();
}

class _DetalleOrdenPageState extends State<DetalleOrdenPage> {
  bool revisarPuertas = false;
  bool revisarPanel = false;
  bool revisarSeguridad = false;

  // ACTIVIDADES REALIZADAS
  bool limpiezaRealizada = false;
  bool lubricacionRealizada = false;
  bool ajusteRealizado = false;

  final TextEditingController diagnosticoController =
      TextEditingController();

  final TextEditingController observacionesController =
      TextEditingController();

  final TextEditingController repuestoController =
      TextEditingController();

  final TextEditingController cantidadController =
      TextEditingController();

  List<String> repuestos = [];

  // FOTOS DEL MANTENIMIENTO
  final ImagePicker selectorImagen = ImagePicker();
  final List<Uint8List> fotosMantenimiento = [];

  // FIRMA DEL TÉCNICO
  final SignatureController controladorFirma = SignatureController(
    penStrokeWidth: 3,
    penColor: Colors.black,
  );

  void agregarRepuesto() {
    String nombre = repuestoController.text.trim();
    String cantidad = cantidadController.text.trim();

    if (nombre.isEmpty || cantidad.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Escribe el repuesto y la cantidad'),
        ),
      );
      return;
    }

    if (int.tryParse(cantidad) == null ||
        int.parse(cantidad) <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'La cantidad debe ser un número mayor que cero',
          ),
        ),
      );
      return;
    }

    setState(() {
      repuestos.add('$nombre - Cantidad: $cantidad');
    });

    repuestoController.clear();
    cantidadController.clear();
  }

  Future<void> seleccionarFoto() async {
    final XFile? imagen = await selectorImagen.pickImage(
      source: ImageSource.gallery,
    );

    if (imagen == null) {
      return;
    }

    final Uint8List datosImagen = await imagen.readAsBytes();

    setState(() {
      fotosMantenimiento.add(datosImagen);
    });
  }

  @override
  void dispose() {
    diagnosticoController.dispose();
    observacionesController.dispose();
    repuestoController.dispose();
    cantidadController.dispose();
    controladorFirma.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final int tareasCompletadas = [
      revisarPuertas,
      revisarPanel,
      revisarSeguridad,
    ].where((tarea) => tarea).length;

    final int actividadesCompletadas = [
      limpiezaRealizada,
      lubricacionRealizada,
      ajusteRealizado,
    ].where((actividad) => actividad).length;

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.numero),
      ),
      body: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          const Icon(
            Icons.elevator,
            size: 70,
            color: Colors.blue,
          ),
          const SizedBox(height: 15),
          Text(
            widget.tipo,
            style: const TextStyle(
              fontSize: 23,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          Text(
            'Equipo: ${widget.equipo}',
            style: const TextStyle(fontSize: 18),
          ),
          const SizedBox(height: 8),
          Text(
            'Estado: ${widget.estado}',
            style: const TextStyle(fontSize: 16),
          ),

          // LISTA DE REVISIÓN
          const Divider(height: 35),
          const Text(
            'Lista de revisión',
            style: TextStyle(
              fontSize: 21,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Tareas completadas: $tareasCompletadas de 3',
            style: const TextStyle(fontSize: 16),
          ),
          const SizedBox(height: 8),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Revisar puertas'),
            value: revisarPuertas,
            onChanged: (valor) {
              setState(() {
                revisarPuertas = valor ?? false;
              });
            },
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Revisar panel de control'),
            value: revisarPanel,
            onChanged: (valor) {
              setState(() {
                revisarPanel = valor ?? false;
              });
            },
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text(
              'Revisar sistemas de seguridad',
            ),
            value: revisarSeguridad,
            onChanged: (valor) {
              setState(() {
                revisarSeguridad = valor ?? false;
              });
            },
          ),

          // ACTIVIDADES REALIZADAS
          const Divider(height: 35),
          const Text(
            'Actividades realizadas',
            style: TextStyle(
              fontSize: 21,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Limpieza del equipo'),
            value: limpiezaRealizada,
            onChanged: (valor) {
              setState(() {
                limpiezaRealizada = valor ?? false;
              });
            },
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Lubricación del equipo'),
            value: lubricacionRealizada,
            onChanged: (valor) {
              setState(() {
                lubricacionRealizada = valor ?? false;
              });
            },
          ),
          CheckboxListTile(
            contentPadding: EdgeInsets.zero,
            title: const Text('Ajustes necesarios'),
            value: ajusteRealizado,
            onChanged: (valor) {
              setState(() {
                ajusteRealizado = valor ?? false;
              });
            },
          ),
          const SizedBox(height: 8),
          Text(
            'Actividades completadas: '
            '$actividadesCompletadas de 3',
            style: const TextStyle(fontSize: 16),
          ),

          const SizedBox(height: 20),

          // VERIFICAR CHECKLIST
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () {
                if (revisarPuertas &&
                    revisarPanel &&
                    revisarSeguridad) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Checklist completado correctamente',
                      ),
                    ),
                  );
                } else {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Debes marcar las tres tareas',
                      ),
                    ),
                  );
                }
              },
              icon: const Icon(Icons.check_circle),
              label: const Text('Verificar checklist'),
            ),
          ),

          // DIAGNÓSTICO
          const Divider(height: 35),
          const Text(
            'Diagnóstico del técnico',
            style: TextStyle(
              fontSize: 21,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: diagnosticoController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: '¿Qué problema encontraste?',
              hintText: 'Describe la falla del ascensor...',
              border: OutlineInputBorder(),
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 20),

          // OBSERVACIONES
          TextField(
            controller: observacionesController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Observaciones',
              hintText: 'Describe el trabajo realizado...',
              border: OutlineInputBorder(),
              alignLabelWithHint: true,
            ),
          ),
          const SizedBox(height: 20),

          // REVISAR DIAGNÓSTICO
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () {
                if (diagnosticoController.text.trim().isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Escribe el diagnóstico'),
                    ),
                  );
                } else if (observacionesController.text
                    .trim()
                    .isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Escribe las observaciones'),
                    ),
                  );
                } else {
                  showDialog(
                    context: context,
                    builder: (context) => AlertDialog(
                      title: const Text(
                        'Resumen del mantenimiento',
                      ),
                      content: SingleChildScrollView(
                        child: Text(
                          'Diagnóstico:\n'
                          '${diagnosticoController.text}\n\n'
                          'Observaciones:\n'
                          '${observacionesController.text}',
                        ),
                      ),
                      actions: [
                        TextButton(
                          onPressed: () => Navigator.pop(context),
                          child: const Text('Cerrar'),
                        ),
                      ],
                    ),
                  );
                }
              },
              icon: const Icon(Icons.save),
              label: const Text('Revisar diagnóstico'),
            ),
          ),

          // REPUESTOS UTILIZADOS
          const Divider(height: 35),
          const Text(
            'Repuestos utilizados',
            style: TextStyle(
              fontSize: 21,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: repuestoController,
            decoration: const InputDecoration(
              labelText: 'Nombre del repuesto',
              hintText: 'Ej. Rodamiento',
              border: OutlineInputBorder(),
              prefixIcon: Icon(Icons.build),
            ),
          ),
          const SizedBox(height: 15),
          TextField(
            controller: cantidadController,
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(
              labelText: 'Cantidad utilizada',
              hintText: 'Ej. 2',
              border: OutlineInputBorder(),
              prefixIcon: Icon(Icons.numbers),
            ),
          ),
          const SizedBox(height: 15),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: agregarRepuesto,
              icon: const Icon(Icons.add),
              label: const Text('Añadir repuesto'),
            ),
          ),
          const SizedBox(height: 15),
          if (repuestos.isEmpty)
            const Text('Todavía no has agregado repuestos.')
          else
            ...repuestos.map(
              (repuesto) => Card(
                child: ListTile(
                  leading: const Icon(Icons.build),
                  title: Text(repuesto),
                  trailing: IconButton(
                    icon: const Icon(
                      Icons.delete,
                      color: Colors.red,
                    ),
                    onPressed: () {
                      setState(() {
                        repuestos.remove(repuesto);
                      });
                    },
                  ),
                ),
              ),
            ),

          // FOTOS DEL MANTENIMIENTO
          const Divider(height: 35),
          const Text(
            'Fotos del mantenimiento',
            style: TextStyle(
              fontSize: 21,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: seleccionarFoto,
              icon: const Icon(Icons.add_a_photo),
              label: const Text('Adjuntar foto'),
            ),
          ),
          const SizedBox(height: 15),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: fotosMantenimiento.map((foto) {
              return Stack(
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Image.memory(
                      foto,
                      width: 140,
                      height: 140,
                      fit: BoxFit.cover,
                    ),
                  ),
                  Positioned(
                    top: 4,
                    right: 4,
                    child: IconButton(
                      onPressed: () {
                        setState(() {
                          fotosMantenimiento.remove(foto);
                        });
                      },
                      icon: const Icon(
                        Icons.delete,
                        color: Colors.red,
                      ),
                      style: IconButton.styleFrom(
                        backgroundColor: Colors.white,
                      ),
                    ),
                  ),
                ],
              );
            }).toList(),
          ),

          // FIRMA DEL TÉCNICO
          const Divider(height: 35),
          const Text(
            'Firma del técnico',
            style: TextStyle(
              fontSize: 21,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            height: 200,
            decoration: BoxDecoration(
              border: Border.all(color: Colors.grey),
              borderRadius: BorderRadius.circular(10),
              color: Colors.white,
            ),
            child: Signature(
              controller: controladorFirma,
              backgroundColor: Colors.white,
            ),
          ),
          const SizedBox(height: 10),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () {
                setState(() {
                  controladorFirma.clear();
                });
              },
              icon: const Icon(Icons.delete),
              label: const Text('Borrar firma'),
            ),
          ),
        ],
      ),
    );
  }
}

// PORTAL DEL CLIENTE
class MenuCliente extends StatelessWidget {
  const MenuCliente({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Portal del cliente'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          ListTile(
            leading: const Icon(Icons.elevator),
            title: const Text('Mis ascensores'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const PaginaDetalle(
                    titulo: 'Mis ascensores',
                    descripcion:
                        'Aquí aparecerán los ascensores asociados al cliente.',
                    icono: Icons.elevator,
                  ),
                ),
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.build),
            title: const Text('Servicios contratados'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const PaginaDetalle(
                    titulo: 'Servicios contratados',
                    descripcion:
                        'Aquí aparecerán los servicios y sus próximos mantenimientos.',
                    icono: Icons.build,
                  ),
                ),
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.history),
            title: const Text('Historial de mantenimiento'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const PaginaDetalle(
                    titulo: 'Historial de mantenimiento',
                    descripcion:
                        'Aquí aparecerá el historial de servicios realizados.',
                    icono: Icons.history,
                  ),
                ),
              );
            },
          ),
          ListTile(
            leading: const Icon(Icons.report_problem),
            title: const Text('Solicitar reparación'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => const PaginaDetalle(
                    titulo: 'Solicitar reparación',
                    descripcion:
                        'Aquí podrás registrar solicitudes de reparación.',
                    icono: Icons.report_problem,
                  ),
                ),
              );
            },
          ),
        ],
      ),
    );
  }
}

// PÁGINA GENÉRICA REUTILIZABLE
class PaginaDetalle extends StatelessWidget {
  final String titulo;
  final String descripcion;
  final IconData icono;

  const PaginaDetalle({
    super.key,
    required this.titulo,
    required this.descripcion,
    required this.icono,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(titulo),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icono, size: 80),
              const SizedBox(height: 20),
              Text(
                titulo,
                style: const TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.bold,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 15),
              Text(
                descripcion,
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 16),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

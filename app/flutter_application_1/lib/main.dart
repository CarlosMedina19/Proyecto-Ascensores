import 'package:flutter/material.dart';

void main() {
  runApp(const AplicacionAscensores());
}

class AplicacionAscensores extends StatelessWidget {
  const AplicacionAscensores({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'Ascensores',
      theme: ThemeData(
        colorScheme: .fromSeed(seedColor: Colors.deepPurple),
      ),
      home: const PaginaInicio(titulo: 'Proyecto Ascensores'),
    );
  }
}

class PaginaInicio extends StatefulWidget {
  const PaginaInicio({super.key, required this.titulo});

  final String titulo;

  @override
  State<PaginaInicio> createState() => EstadoPaginaInicio();
}

class EstadoPaginaInicio extends State<PaginaInicio> {
  int _contador = 0;

  void _incrementarContador() {
    setState(() {
      _contador++;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: Theme.of(context).colorScheme.inversePrimary,
        title: Text(widget.titulo),
      ),
      body: Center(
        child: Column(
          mainAxisAlignment: .center,
          children: [
            const Text('Has presionado el botón esta cantidad de veces:'),
            Text(
              '$_contador',
              style: Theme.of(context).textTheme.headlineMedium,
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _incrementarContador,
        tooltip: 'Incrementar',
        child: const Icon(Icons.add),
      ),
    );
  }
}

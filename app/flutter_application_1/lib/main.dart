import 'package:flutter/material.dart';

void main() {
  runApp(const MyApp());
}

class MyApp extends StatefulWidget {
  const MyApp({super.key});

  @override
  State<MyApp> createState() => _MyAppState();
}

class _MyAppState extends State<MyApp> {
  final TextEditingController correoController =
      TextEditingController();

  final TextEditingController passwordController =
      TextEditingController();
  String mensaje = '';

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      home: Scaffold(
        appBar: AppBar(
          title: const Text('Proyecto Ascensores'),
        ),
        body: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            children: [
              const Text(
                'Iniciar sesión',
                style: TextStyle(fontSize: 28),
              ),

              const SizedBox(height: 30),

              TextField(
                controller: correoController,
                decoration: const InputDecoration(
                  labelText: 'Correo',
                  border: OutlineInputBorder(),
                ),
              ),

              const SizedBox(height: 20),

              TextField(
                controller: passwordController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Contraseña',
                  border: OutlineInputBorder(),
                ),
              ),

              const SizedBox(height: 20),

              ElevatedButton(
                onPressed: () {
  if (correoController.text.isEmpty) {
    mensaje = 'Escribe tu correo';
  } else if (passwordController.text.isEmpty) {
    mensaje = 'Escribe tu contraseña';
  } else {
    mensaje = 'Datos completos';
  }
                },
                child: const Text('INGRESAR'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

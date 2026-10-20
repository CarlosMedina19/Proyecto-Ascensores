import 'package:flutter/material.dart';

import 'comunes.dart';

/// Puertas de ascensor que se cierran al ingresar y se abren ya dentro de la app (prototipo).
class PuertasSigma extends StatefulWidget {
  const PuertasSigma({super.key, required this.child});

  final Widget child;

  static _PuertasSigmaState? _activas;

  /// Cierra las puertas y se resuelve cuando están cerradas; después se abren solas.
  /// Sin las puertas montadas o con las animaciones del sistema desactivadas, se resuelve de inmediato.
  static Future<void> cerrar() =>
      // Tope de seguridad: si la app queda en segundo plano y la animación no avanza, se entra igual.
      (_activas?._cerrar() ?? Future.value()).timeout(const Duration(seconds: 2), onTimeout: () {});

  @override
  State<PuertasSigma> createState() => _PuertasSigmaState();
}

class _PuertasSigmaState extends State<PuertasSigma> with SingleTickerProviderStateMixin {
  late final _animacion = AnimationController(vsync: this);

  @override
  void initState() {
    super.initState();
    PuertasSigma._activas = this;
  }

  @override
  void dispose() {
    if (PuertasSigma._activas == this) PuertasSigma._activas = null;
    _animacion.dispose();
    super.dispose();
  }

  Future<void> _cerrar() async {
    if (MediaQuery.maybeDisableAnimationsOf(context) ?? false) return;
    await _animacion.animateTo(1, duration: const Duration(milliseconds: 500), curve: const Cubic(.77, 0, .175, 1));
    // Se abren después de que la app cambie de pantalla.
    Future.delayed(const Duration(milliseconds: 180), () {
      if (mounted) _animacion.animateBack(0, duration: const Duration(milliseconds: 600), curve: const Cubic(.32, .72, 0, 1));
    });
  }

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        widget.child,
        AnimatedBuilder(
          animation: _animacion,
          builder: (context, _) {
            final v = _animacion.value;
            if (v == 0) return const SizedBox.shrink();
            return Positioned.fill(
              child: IgnorePointer(
                child: Stack(
                  children: [
                    for (final izquierda in [true, false])
                      Positioned(
                        top: 0,
                        bottom: 0,
                        left: izquierda ? 0 : null,
                        right: izquierda ? null : 0,
                        child: FractionallySizedBox(
                          heightFactor: 1,
                          child: LayoutBuilder(
                            builder: (context, _) {
                              final ancho = MediaQuery.sizeOf(context).width * .505;
                              return Transform.translate(
                                offset: Offset((izquierda ? -1 : 1) * ancho * (1 - v), 0),
                                child: Container(
                                  width: ancho,
                                  decoration: BoxDecoration(
                                    gradient: const LinearGradient(
                                      colors: [Color(0xFF150B2D), Color(0xFF2F1967), Color(0xFF22124A)],
                                      stops: [0, .55, 1],
                                    ),
                                    border: Border(
                                      right: izquierda ? const BorderSide(color: Color(0x8CB5D65A), width: 2) : BorderSide.none,
                                      left: izquierda ? BorderSide.none : const BorderSide(color: Color(0x8CB5D65A), width: 2),
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
                        ),
                      ),
                    Center(
                      child: Opacity(
                        opacity: ((v - .6) / .4).clamp(0, 1).toDouble(),
                        child: Transform.scale(scale: .9 + .1 * v, child: const LogoSigma(tamano: 64)),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }
}

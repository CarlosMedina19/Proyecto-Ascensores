import { useCallback, useEffect, useRef, useState } from 'react';

const EVENTOS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart'];
// Cada cuánto se avisa al servidor que el usuario sigue activo.
const INTERVALO_PING_MS = 2 * 60 * 1000;

/**
 * RF-001: la sesión se cierra tras N minutos de inactividad y se avisa
 * un minuto antes para que el usuario pueda mantenerla activa.
 *
 * - `alPing` se llama periódicamente mientras hay actividad, para que el
 *   token del servidor no expire mientras el usuario trabaja.
 * - `alExpirar` se llama al cumplirse el tiempo sin actividad.
 */
export function useInactividad({ minutos, alPing, alExpirar, activo = true }) {
  const [segundosRestantes, setSegundosRestantes] = useState(null);
  const ultimaActividad = useRef(Date.now());
  const ultimoPing = useRef(Date.now());
  const avisoMostrado = useRef(false);

  // Las funciones se guardan en referencias para que un nuevo render no reinicie el contador.
  const alPingRef = useRef(alPing);
  const alExpirarRef = useRef(alExpirar);
  alPingRef.current = alPing;
  alExpirarRef.current = alExpirar;

  const mantenerSesion = useCallback(() => {
    avisoMostrado.current = false;
    setSegundosRestantes(null);
    ultimaActividad.current = Date.now();
    ultimoPing.current = Date.now();
    alPingRef.current?.();
  }, []);

  useEffect(() => {
    if (!activo) return undefined;
    ultimaActividad.current = Date.now();

    const registrarActividad = () => {
      if (avisoMostrado.current) return; // Con el aviso abierto se exige confirmar.
      ultimaActividad.current = Date.now();
      if (Date.now() - ultimoPing.current > INTERVALO_PING_MS) {
        ultimoPing.current = Date.now();
        alPingRef.current?.();
      }
    };

    EVENTOS.forEach((evento) => window.addEventListener(evento, registrarActividad, { passive: true }));

    const limiteMs = minutos * 60 * 1000;
    const avisoMs = Math.max(limiteMs - 60 * 1000, 0);

    const temporizador = setInterval(() => {
      const inactivoMs = Date.now() - ultimaActividad.current;
      if (inactivoMs >= limiteMs) {
        clearInterval(temporizador);
        avisoMostrado.current = false;
        setSegundosRestantes(null);
        alExpirarRef.current?.();
      } else if (inactivoMs >= avisoMs) {
        avisoMostrado.current = true;
        setSegundosRestantes(Math.ceil((limiteMs - inactivoMs) / 1000));
      }
    }, 1000);

    return () => {
      clearInterval(temporizador);
      EVENTOS.forEach((evento) => window.removeEventListener(evento, registrarActividad));
    };
  }, [activo, minutos]);

  return { segundosRestantes, mantenerSesion };
}

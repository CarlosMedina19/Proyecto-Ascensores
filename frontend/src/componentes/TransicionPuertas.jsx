import { useEffect, useState } from 'react';
import { Logo } from './Marca';

const CIERRE_MS = 700;
const APERTURA_MS = 700;
let mostrar = null;

const movimientoReducido = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

/**
 * Cierra las puertas de ascensor sobre la pantalla. La promesa se resuelve cuando ya están cerradas;
 * después se abren solas. Sin la transición montada o con movimiento reducido se resuelve de inmediato.
 */
export function cerrarPuertas() {
  if (!mostrar || movimientoReducido()) return Promise.resolve();
  return new Promise((resolver) => mostrar(resolver));
}

/** Puertas que se cierran al ingresar y se abren ya dentro del panel. */
export default function TransicionPuertas() {
  const [fase, setFase] = useState(null);

  useEffect(() => {
    const temporizadores = [];
    mostrar = (resolver) => {
      setFase('montada');
      // Un cuadro después para que la transición de cierre se vea.
      requestAnimationFrame(() => requestAnimationFrame(() => setFase('cerradas')));
      temporizadores.push(
        setTimeout(() => {
          resolver();
          setFase('abiertas');
          temporizadores.push(setTimeout(() => setFase(null), APERTURA_MS));
        }, CIERRE_MS),
      );
    };
    return () => {
      mostrar = null;
      temporizadores.forEach(clearTimeout);
    };
  }, []);

  if (!fase) return null;
  return (
    <div className={`transicion-puertas ${fase === 'montada' ? '' : fase}`} aria-hidden="true">
      <i className="transicion-puertas__izquierda" />
      <i className="transicion-puertas__derecha" />
      <span className="transicion-puertas__logo">
        <Logo tamano={64} />
      </span>
    </div>
  );
}

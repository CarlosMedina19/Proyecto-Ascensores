import { useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { X } from 'lucide-react';

const CLAVE_VISTO = 'sigma.recorrido-visto';
const EVENTO = 'sigma:recorrido';

/** Pasos del recorrido. Cada uno señala un elemento con data-recorrido; si el rol no lo ve, el paso se omite. */
const PASOS = [
  {
    objetivo: 'menu',
    titulo: 'Todo SIGMA en el menú',
    texto: 'Clientes agrupados por conjunto, institucional y residencial; desde cada cliente llega a sus edificios y ascensores sin cambiar de sección.',
  },
  {
    objetivo: 'buscador',
    titulo: 'Busque cualquier cosa',
    texto: 'Escriba el código de un ascensor, el número de una orden o el nombre de un cliente. Atajo: Ctrl + K.',
  },
  {
    objetivo: 'emergencias',
    titulo: 'Emergencias 24/7',
    texto: 'Los atrapamientos y fallas llegan desde el panel, la app o el código QR de la cabina, con el tiempo de llegada frente al objetivo.',
  },
  {
    objetivo: 'certificaciones',
    titulo: 'Certificación anual',
    texto: 'Cada ascensor con su revisión anual y su certificado. SIGMA avisa 60, 30 y 15 días antes del vencimiento.',
  },
  {
    objetivo: 'indicadores',
    titulo: 'Indicadores del servicio',
    texto: 'Disponibilidad, tiempo medio entre fallas, tiempo de respuesta y cumplimiento del plan, listos para presentar al cliente.',
  },
];

/** Abre el recorrido desde cualquier parte (botón de ayuda o modo demostración). */
export function abrirRecorrido() {
  window.dispatchEvent(new Event(EVENTO));
}

export function recorridoVisto() {
  try {
    return localStorage.getItem(CLAVE_VISTO) === '1';
  } catch {
    return true;
  }
}

/** Recorrido guiado de 3 a 5 pasos sobre la pantalla real, con teclado (Esc, flechas) y foco dentro del cuadro. */
export default function RecorridoGuiado() {
  const [pasos, setPasos] = useState(null);
  const [indice, setIndice] = useState(0);
  const [caja, setCaja] = useState(null);
  const dialogo = useRef(null);

  const cerrar = useCallback(() => {
    setPasos(null);
    try {
      localStorage.setItem(CLAVE_VISTO, '1');
    } catch {
      // Sin almacenamiento local el recorrido puede volver a ofrecerse.
    }
  }, []);

  useEffect(() => {
    const abrir = () => {
      const visibles = PASOS.filter((p) => document.querySelector(`[data-recorrido="${p.objetivo}"]`));
      if (visibles.length) {
        setPasos(visibles);
        setIndice(0);
      }
    };
    window.addEventListener(EVENTO, abrir);
    return () => window.removeEventListener(EVENTO, abrir);
  }, []);

  const paso = pasos?.[indice];

  // Ubica el resaltado y el cuadro junto al elemento del paso.
  useLayoutEffect(() => {
    if (!paso) return undefined;
    const ubicar = () => {
      let elemento = document.querySelector(`[data-recorrido="${paso.objetivo}"]`);
      // Un contenedor con display: contents no tiene caja propia; se usa la de su primer hijo.
      if (elemento && !elemento.getClientRects().length) elemento = elemento.firstElementChild;
      if (!elemento) return setCaja(null);
      elemento.scrollIntoView({ block: 'nearest' });
      setCaja(elemento.getBoundingClientRect());
    };
    ubicar();
    window.addEventListener('resize', ubicar);
    return () => window.removeEventListener('resize', ubicar);
  }, [paso]);

  useEffect(() => {
    if (!paso) return undefined;
    dialogo.current?.focus();
    const alTecla = (e) => {
      if (e.key === 'Escape') cerrar();
      if (e.key === 'ArrowRight' && indice < pasos.length - 1) setIndice((i) => i + 1);
      if (e.key === 'ArrowLeft' && indice > 0) setIndice((i) => i - 1);
    };
    window.addEventListener('keydown', alTecla);
    return () => window.removeEventListener('keydown', alTecla);
  }, [paso, indice, pasos, cerrar]);

  if (!paso) return null;

  const margen = 8;
  const ancho = Math.min(340, window.innerWidth - 32);
  let estilo = { left: '50%', top: '50%', transform: 'translate(-50%, -50%)' };
  if (caja) {
    const aLaDerecha = caja.right + margen + ancho + 16 < window.innerWidth;
    const izquierda = aLaDerecha ? caja.right + margen + 8 : Math.max(16, Math.min(caja.left, window.innerWidth - ancho - 16));
    const arriba = aLaDerecha ? Math.max(16, Math.min(caja.top, window.innerHeight - 220)) : Math.min(caja.bottom + margen + 8, window.innerHeight - 220);
    estilo = { left: izquierda, top: arriba };
  }
  const ultimo = indice === pasos.length - 1;

  // En body, para que ningún contenedor recorte el velo fijo.
  return createPortal(
    <div className="recorrido" role="presentation">
      {caja ? (
        <>
          {/* Velo en cuatro bandas alrededor del elemento señalado. */}
          <div className="recorrido__velo" style={{ left: 0, top: 0, right: 0, height: Math.max(0, caja.top - margen) }} aria-hidden="true" />
          <div className="recorrido__velo" style={{ left: 0, top: caja.bottom + margen, right: 0, bottom: 0 }} aria-hidden="true" />
          <div className="recorrido__velo" style={{ left: 0, top: caja.top - margen, width: Math.max(0, caja.left - margen), height: caja.height + margen * 2 }} aria-hidden="true" />
          <div className="recorrido__velo" style={{ left: caja.right + margen, top: caja.top - margen, right: 0, height: caja.height + margen * 2 }} aria-hidden="true" />
        </>
      ) : (
        <div className="recorrido__velo" style={{ inset: 0 }} aria-hidden="true" />
      )}
      {caja && (
        <div
          className="recorrido__foco"
          style={{ left: caja.left - margen, top: caja.top - margen, width: caja.width + margen * 2, height: caja.height + margen * 2 }}
          aria-hidden="true"
        />
      )}
      <div ref={dialogo} className="recorrido__cuadro" style={{ ...estilo, width: ancho }} role="dialog" aria-modal="true" aria-labelledby="recorrido-titulo" tabIndex={-1}>
        <button type="button" className="boton-icono recorrido__cerrar" onClick={cerrar} aria-label="Cerrar el recorrido">
          <X size={16} />
        </button>
        <span className="texto-pequeno texto-suave">
          Paso {indice + 1} de {pasos.length}
        </span>
        <h2 id="recorrido-titulo">{paso.titulo}</h2>
        <p>{paso.texto}</p>
        <div className="recorrido__acciones">
          {indice > 0 && (
            <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setIndice((i) => i - 1)}>
              Anterior
            </button>
          )}
          <span style={{ flex: 1 }} />
          <button type="button" className="boton boton--pequeno" onClick={() => (ultimo ? cerrar() : setIndice((i) => i + 1))}>
            {ultimo ? 'Terminar recorrido' : 'Siguiente'}
          </button>
        </div>
      </div>
    </div>,
    document.body,
  );
}

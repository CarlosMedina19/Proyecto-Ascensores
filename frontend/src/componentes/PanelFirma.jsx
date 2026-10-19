import { forwardRef, useEffect, useImperativeHandle, useRef, useState } from 'react';
import { Eraser } from 'lucide-react';

/**
 * Área para firmar con el mouse, el dedo o un lápiz (eventos de puntero).
 * `ref.current.png()` devuelve la firma como data URL PNG, o null si está vacía.
 */
const PanelFirma = forwardRef(function PanelFirma({ etiqueta = 'Firma', alto = 160 }, ref) {
  const lienzo = useRef(null);
  const dibujando = useRef(false);
  const [vacio, setVacio] = useState(true);

  // Ajusta la resolución del lienzo al tamaño real en pantalla.
  useEffect(() => {
    const elemento = lienzo.current;
    const escala = window.devicePixelRatio || 1;
    elemento.width = elemento.offsetWidth * escala;
    elemento.height = alto * escala;
    const contexto = elemento.getContext('2d');
    contexto.scale(escala, escala);
    contexto.lineWidth = 2.2;
    contexto.lineCap = 'round';
    contexto.lineJoin = 'round';
    contexto.strokeStyle = '#17121f';
  }, [alto]);

  function punto(evento) {
    const caja = lienzo.current.getBoundingClientRect();
    return [evento.clientX - caja.left, evento.clientY - caja.top];
  }

  function iniciar(evento) {
    evento.preventDefault();
    lienzo.current.setPointerCapture(evento.pointerId);
    dibujando.current = true;
    const contexto = lienzo.current.getContext('2d');
    contexto.beginPath();
    contexto.moveTo(...punto(evento));
  }

  function mover(evento) {
    if (!dibujando.current) return;
    const contexto = lienzo.current.getContext('2d');
    contexto.lineTo(...punto(evento));
    contexto.stroke();
    setVacio(false);
  }

  function terminar() {
    dibujando.current = false;
  }

  function borrar() {
    const elemento = lienzo.current;
    elemento.getContext('2d').clearRect(0, 0, elemento.width, elemento.height);
    setVacio(true);
  }

  useImperativeHandle(ref, () => ({
    png: () => (vacio ? null : lienzo.current.toDataURL('image/png')),
    borrar,
    vacio,
  }));

  return (
    <div className="panel-firma">
      <canvas
        ref={lienzo}
        className="panel-firma__lienzo"
        style={{ height: alto }}
        onPointerDown={iniciar}
        onPointerMove={mover}
        onPointerUp={terminar}
        onPointerLeave={terminar}
        role="img"
        aria-label={`${etiqueta}: dibuje la firma con el mouse o el dedo`}
      />
      {vacio && <span className="panel-firma__ayuda">Firme aquí</span>}
      <button type="button" className="boton boton--fantasma boton--pequeno panel-firma__borrar" onClick={borrar} disabled={vacio}>
        <Eraser size={14} /> Borrar
      </button>
    </div>
  );
});

export default PanelFirma;

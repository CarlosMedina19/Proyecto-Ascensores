import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bot, ExternalLink, FileDown, Link2, MessageCircle, Send, X } from 'lucide-react';
import { asistente } from '../api/servicios';

function sesionDe(modo) {
  const clave = `sigma.asistente.${modo}`;
  try {
    const guardada = sessionStorage.getItem(clave);
    if (guardada) return guardada;
    const nueva = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
    sessionStorage.setItem(clave, nueva);
    return nueva;
  } catch {
    return `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 10)}`;
  }
}

/**
 * Chat flotante del asistente de SIGMA.
 * - modo «publico»: página de inicio, información general y contacto.
 * - modo «panel»: con sesión; responde según el rol (búsquedas, órdenes, PDF…).
 */
export default function ChatAsistente({ modo = 'panel', titulo = 'Asistente SIGMA', saludo, sugerencias = [] }) {
  const navegar = useNavigate();
  const [abierto, setAbierto] = useState(false);
  const [mensajes, setMensajes] = useState(() => [{ de: 'bot', texto: saludo, sugerencias }]);
  const [texto, setTexto] = useState('');
  const [enviando, setEnviando] = useState(false);
  const lista = useRef(null);
  const sesion = useRef(sesionDe(modo));

  useEffect(() => {
    if (lista.current) lista.current.scrollTop = lista.current.scrollHeight;
  }, [mensajes, abierto]);

  useEffect(() => {
    if (!abierto) return undefined;
    const alTecla = (e) => e.key === 'Escape' && setAbierto(false);
    window.addEventListener('keydown', alTecla);
    return () => window.removeEventListener('keydown', alTecla);
  }, [abierto]);

  async function enviar(mensaje) {
    const limpio = (mensaje ?? texto).trim();
    if (!limpio || enviando) return;
    setTexto('');
    setMensajes((m) => [...m, { de: 'usuario', texto: limpio }]);
    setEnviando(true);
    try {
      const r = await (modo === 'publico' ? asistente.publico(limpio, sesion.current) : asistente.panel(limpio, sesion.current));
      setMensajes((m) => [...m, { de: 'bot', ...r.datos }]);
    } catch (e) {
      setMensajes((m) => [...m, { de: 'bot', texto: e.message || 'No pude responder en este momento. Intente de nuevo.', error: true }]);
    } finally {
      setEnviando(false);
    }
  }

  async function usarAccion(accion) {
    if (accion.tipo === 'enlace') {
      setAbierto(false);
      navegar(accion.ruta);
    } else if (accion.tipo === 'pdf') {
      try {
        await asistente.descargar(accion.ruta, accion.nombre);
      } catch (e) {
        setMensajes((m) => [...m, { de: 'bot', texto: e.message, error: true }]);
      }
    }
  }

  return (
    <div className={`asistente asistente--${modo}`}>
      {abierto && (
        <section className="asistente__panel" role="dialog" aria-label={titulo}>
          <header className="asistente__cabecera">
            <span className="asistente__avatar" aria-hidden="true">
              <Bot size={18} />
            </span>
            <div>
              <b>{titulo}</b>
              <small>{modo === 'publico' ? 'Información y contacto' : 'Responde según su rol'}</small>
            </div>
            <button type="button" className="asistente__cerrar" onClick={() => setAbierto(false)} aria-label="Cerrar el asistente">
              <X size={18} />
            </button>
          </header>

          <div className="asistente__mensajes" ref={lista} aria-live="polite">
            {mensajes.map((m, i) => (
              <div key={i} className={`asistente__mensaje asistente__mensaje--${m.de}${m.error ? ' asistente__mensaje--error' : ''}`}>
                {m.texto && <p>{m.texto}</p>}
                {!!m.acciones?.length && (
                  <div className="asistente__acciones">
                    {m.acciones.map((a, k) =>
                      a.tipo === 'externo' ? (
                        <a key={k} className="asistente__accion" href={a.ruta} target="_blank" rel="noreferrer">
                          {a.ruta.startsWith('mailto:') ? <ExternalLink size={14} /> : <MessageCircle size={14} />} {a.texto}
                        </a>
                      ) : (
                        <button key={k} type="button" className="asistente__accion" onClick={() => usarAccion(a)}>
                          {a.tipo === 'pdf' ? <FileDown size={14} /> : <Link2 size={14} />} {a.texto}
                        </button>
                      ),
                    )}
                  </div>
                )}
                {!!m.sugerencias?.length && i === mensajes.length - 1 && (
                  <div className="asistente__sugerencias">
                    {m.sugerencias.map((s) => (
                      <button key={s} type="button" onClick={() => enviar(s)}>
                        {s}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            ))}
            {enviando && (
              <div className="asistente__mensaje asistente__mensaje--bot asistente__escribiendo" aria-label="Escribiendo">
                <i />
                <i />
                <i />
              </div>
            )}
          </div>

          <form
            className="asistente__formulario"
            onSubmit={(e) => {
              e.preventDefault();
              enviar();
            }}
          >
            <input value={texto} onChange={(e) => setTexto(e.target.value)} placeholder="Escriba su pregunta…" maxLength={1000} aria-label="Mensaje para el asistente" />
            <button type="submit" disabled={!texto.trim() || enviando} aria-label="Enviar">
              <Send size={17} />
            </button>
          </form>
        </section>
      )}

      <button type="button" className="asistente__boton" onClick={() => setAbierto((a) => !a)} aria-expanded={abierto} aria-label={abierto ? 'Cerrar el asistente' : `Abrir ${titulo}`}>
        {abierto ? <X size={22} /> : <Bot size={24} />}
      </button>
    </div>
  );
}

import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Bot, Camera, ExternalLink, FileDown, ImagePlus, Images, Link2, MessageCircle, Send, X } from 'lucide-react';
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

const TIPOS_FOTO = ['image/jpeg', 'image/png', 'image/webp'];

/**
 * Reduce la foto a 1600 px como máximo y la convierte a JPG antes de enviarla (las del celular pesan
 * varios MB y algunos iPhone usan HEIC). Si el navegador no puede decodificarla, se envía tal cual.
 */
async function prepararFoto(archivo) {
  try {
    const imagen = await createImageBitmap(archivo);
    const escala = Math.min(1, 1600 / Math.max(imagen.width, imagen.height));
    const lienzo = document.createElement('canvas');
    lienzo.width = Math.round(imagen.width * escala);
    lienzo.height = Math.round(imagen.height * escala);
    lienzo.getContext('2d').drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
    imagen.close?.();
    const blob = await new Promise((resolver) => lienzo.toBlob(resolver, 'image/jpeg', 0.85));
    if (blob) return new File([blob], 'foto.jpg', { type: 'image/jpeg' });
  } catch {
    // Navegador sin createImageBitmap o formato que no decodifica: se valida abajo.
  }
  if (!TIPOS_FOTO.includes(archivo.type)) throw new Error('Formato no compatible. Use una foto JPG, PNG o WEBP.');
  return archivo;
}

/**
 * Chat flotante del asistente de SIGMA.
 * - modo «publico»: página de inicio, información general y contacto.
 * - modo «panel»: con sesión; responde según el rol (búsquedas, órdenes, PDF…) y analiza fotos del
 *   ascensor tomadas con la cámara o elegidas de la galería.
 */
export default function ChatAsistente({ modo = 'panel', titulo = 'Asistente SIGMA', saludo, sugerencias = [] }) {
  const navegar = useNavigate();
  const [abierto, setAbierto] = useState(false);
  const [mensajes, setMensajes] = useState(() => [{ de: 'bot', texto: saludo, sugerencias }]);
  const [texto, setTexto] = useState('');
  const [enviando, setEnviando] = useState(false);
  const [foto, setFoto] = useState(null);
  const [menuFoto, setMenuFoto] = useState(false);
  const lista = useRef(null);
  const sesion = useRef(sesionDe(modo));
  const entradaCamara = useRef(null);
  const entradaGaleria = useRef(null);
  const conFotos = modo === 'panel';

  useEffect(() => {
    if (lista.current) lista.current.scrollTop = lista.current.scrollHeight;
  }, [mensajes, abierto]);

  useEffect(() => {
    if (!abierto) return undefined;
    const alTecla = (e) => e.key === 'Escape' && setAbierto(false);
    window.addEventListener('keydown', alTecla);
    return () => window.removeEventListener('keydown', alTecla);
  }, [abierto]);

  function elegirFoto(evento) {
    const archivo = evento.target.files?.[0];
    evento.target.value = '';
    setMenuFoto(false);
    if (!archivo) return;
    if (archivo.size > 15 * 1024 * 1024) {
      setMensajes((m) => [...m, { de: 'bot', texto: 'La foto pesa más de 15 MB. Tome otra o elija una más liviana.', error: true }]);
      return;
    }
    setFoto((anterior) => {
      if (anterior) URL.revokeObjectURL(anterior.vista);
      return { archivo, vista: URL.createObjectURL(archivo) };
    });
  }

  function quitarFoto() {
    if (foto) URL.revokeObjectURL(foto.vista);
    setFoto(null);
  }

  async function enviar(mensaje) {
    const limpio = (mensaje ?? texto).trim();
    const adjunta = mensaje === undefined ? foto : null;
    if ((!limpio && !adjunta) || enviando) return;
    setTexto('');
    setFoto(null);
    setMensajes((m) => [...m, { de: 'usuario', texto: limpio, imagen: adjunta?.vista }]);
    setEnviando(true);
    try {
      const imagen = adjunta ? await prepararFoto(adjunta.archivo) : null;
      const r = await (modo === 'publico' ? asistente.publico(limpio, sesion.current) : asistente.panel(limpio, sesion.current, imagen));
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
                {m.imagen && <img className="asistente__foto" src={m.imagen} alt="Foto enviada al asistente" />}
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

          {foto && (
            <div className="asistente__adjunto">
              <img src={foto.vista} alt="" />
              <span>Foto lista para analizar. Diga qué parte es (puerta, cabina, cuarto de máquinas…) y el código del ascensor.</span>
              <button type="button" onClick={quitarFoto} aria-label="Quitar la foto">
                <X size={15} />
              </button>
            </div>
          )}
          <form
            className="asistente__formulario"
            onSubmit={(e) => {
              e.preventDefault();
              enviar();
            }}
          >
            {conFotos && (
              <div className="asistente__foto-menu">
                <button type="button" className="asistente__adjuntar" onClick={() => setMenuFoto((v) => !v)} aria-expanded={menuFoto} aria-label="Adjuntar una foto del ascensor" title="Adjuntar una foto del ascensor">
                  <ImagePlus size={18} />
                </button>
                {menuFoto && (
                  <div className="asistente__opciones-foto" role="menu">
                    <button type="button" role="menuitem" onClick={() => entradaCamara.current?.click()}>
                      <Camera size={15} /> Tomar foto
                    </button>
                    <button type="button" role="menuitem" onClick={() => entradaGaleria.current?.click()}>
                      <Images size={15} /> Elegir de la galería
                    </button>
                  </div>
                )}
                <input ref={entradaCamara} type="file" accept="image/*" capture="environment" hidden onChange={elegirFoto} data-testid="foto-camara" />
                <input ref={entradaGaleria} type="file" accept="image/*" hidden onChange={elegirFoto} data-testid="foto-galeria" />
              </div>
            )}
            <input value={texto} onChange={(e) => setTexto(e.target.value)} placeholder={foto ? 'Agregue un comentario (opcional)…' : 'Escriba su pregunta…'} maxLength={1000} aria-label="Mensaje para el asistente" />
            <button type="submit" disabled={(!texto.trim() && !foto) || enviando} aria-label="Enviar">
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

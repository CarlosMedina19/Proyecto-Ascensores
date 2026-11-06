// Fotos del ascensor: selección con vista previa, imágenes protegidas y galería.
import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { ChevronLeft, ChevronRight, Expand, ImagePlus, Trash2, X } from 'lucide-react';
import { urlArchivo } from '../api/cliente';
import { fotosEquipo } from '../api/servicios';
import { fecha } from '../utilidades/formato';
import { Confirmacion } from './Basicos';

export const MAXIMO_FOTOS = 3;
const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];
const PESO_MAXIMO = 20 * 1024 * 1024;

/** Revisa tipo y peso antes de enviar; el servidor vuelve a validar. */
export function validarFotos(archivos, disponibles) {
  if (archivos.length > disponibles) {
    return `Puede agregar máximo ${disponibles} ${disponibles === 1 ? 'foto' : 'fotos'} más (límite de ${MAXIMO_FOTOS}).`;
  }
  const invalido = archivos.find((a) => !TIPOS.includes(a.type));
  if (invalido) return `«${invalido.name}» no es una imagen JPG, PNG o WEBP.`;
  const pesado = archivos.find((a) => a.size > PESO_MAXIMO);
  if (pesado) return `«${pesado.name}» pesa más de 20 MB.`;
  return '';
}

/** Selector de fotos nuevas (aún no enviadas) con miniaturas. */
export function SelectorFotos({ archivos, alCambiar, disponibles = MAXIMO_FOTOS, error }) {
  const id = useId();
  const entrada = useRef(null);
  const [aviso, setAviso] = useState('');
  const [vistas, setVistas] = useState([]);

  useEffect(() => {
    const urls = archivos.map((archivo) => URL.createObjectURL(archivo));
    setVistas(urls);
    return () => urls.forEach((url) => URL.revokeObjectURL(url));
  }, [archivos]);

  function agregar(evento) {
    const nuevos = Array.from(evento.target.files || []);
    evento.target.value = '';
    const problema = validarFotos([...archivos, ...nuevos], disponibles);
    setAviso(problema);
    if (!problema) alCambiar([...archivos, ...nuevos]);
  }

  const quedan = disponibles - archivos.length;

  return (
    <div className="campo">
      <span className="campo__etiqueta" id={`${id}-etiqueta`}>
        Fotos del ascensor <span className="texto-suave">({archivos.length} de {disponibles})</span>
      </span>
      <div className="fotos" aria-labelledby={`${id}-etiqueta`}>
        {vistas.map((url, indice) => (
          <figure key={url} className="fotos__item">
            <img src={url} alt={`Foto nueva ${indice + 1}: ${archivos[indice]?.name}`} />
            <button
              type="button"
              className="fotos__quitar"
              onClick={() => alCambiar(archivos.filter((_, i) => i !== indice))}
              aria-label={`Quitar ${archivos[indice]?.name}`}
            >
              <X size={14} />
            </button>
          </figure>
        ))}
        {quedan > 0 && (
          <button type="button" className="fotos__agregar" onClick={() => entrada.current?.click()}>
            <ImagePlus size={22} aria-hidden="true" />
            <span>Agregar foto</span>
            <small>JPG, PNG o WEBP · máx. 20 MB</small>
          </button>
        )}
      </div>
      <input
        ref={entrada}
        id={id}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        hidden
        onChange={agregar}
        data-testid="entrada-fotos"
      />
      {aviso || error ? <span className="campo__error">{aviso || error}</span> : <span className="campo__ayuda">Máximo {MAXIMO_FOTOS} fotos por ascensor.</span>}
    </div>
  );
}

/** Imagen guardada en la API: se descarga con el token del usuario. */
export function ImagenProtegida({ ruta, alt, ...resto }) {
  const [url, setUrl] = useState(null);
  const [fallo, setFallo] = useState(false);

  useEffect(() => {
    let local = null;
    let vigente = true;
    urlArchivo(ruta)
      .then((creada) => {
        local = creada;
        if (vigente) setUrl(creada);
        else URL.revokeObjectURL(creada);
      })
      .catch(() => vigente && setFallo(true));
    return () => {
      vigente = false;
      if (local) URL.revokeObjectURL(local);
    };
  }, [ruta]);

  if (fallo) return <span className="fotos__error">No se pudo cargar</span>;
  if (!url) return <span className="fotos__cargando" role="img" aria-label="Cargando foto" />;
  return <img src={url} alt={alt} {...resto} />;
}

/** Galería de las fotos guardadas, con agregar y eliminar si el usuario puede gestionarlas. */
export function GaleriaFotos({ equipoId, fotos, editable, alCambiar, notificar }) {
  const [ampliada, setAmpliada] = useState(null); // índice de la foto en el visor
  const [nuevas, setNuevas] = useState([]);
  const [porEliminar, setPorEliminar] = useState(null);
  const [procesando, setProcesando] = useState(false);
  const [error, setError] = useState('');
  const disponibles = MAXIMO_FOTOS - fotos.length;

  async function subir() {
    setProcesando(true);
    setError('');
    try {
      const respuesta = await fotosEquipo.subir(equipoId, nuevas);
      setNuevas([]);
      notificar?.(respuesta.mensaje || 'Fotos agregadas.');
      alCambiar();
    } catch (e) {
      setError(Object.values(e.errores || {})[0]?.[0] || e.message);
    } finally {
      setProcesando(false);
    }
  }

  async function eliminar() {
    setProcesando(true);
    try {
      await fotosEquipo.eliminar(equipoId, porEliminar.id);
      notificar?.('Foto eliminada.');
      alCambiar();
    } catch (e) {
      notificar?.(e.message, 'error');
    } finally {
      setProcesando(false);
      setPorEliminar(null);
    }
  }

  return (
    <>
      {fotos.length > 0 ? (
        // La primera foto va destacada; las demás, como miniaturas a su lado.
        <div className={`galeria galeria--${Math.min(fotos.length, 3)}`}>
          {fotos.map((foto, indice) => (
            <figure key={foto.id} className={`galeria__item${indice === 0 ? ' galeria__item--principal' : ''}`}>
              <button type="button" className="galeria__ver" onClick={() => setAmpliada(indice)} aria-label={`Ver foto ${indice + 1} de ${fotos.length} en grande`}>
                <ImagenProtegida ruta={foto.ruta_archivo} alt={`Foto ${indice + 1} del ascensor`} />
                <span className="galeria__velo" aria-hidden="true">
                  <Expand size={16} />
                </span>
              </button>
              <figcaption className="galeria__pie">
                <span>
                  {indice + 1}/{fotos.length}
                </span>
                {foto.created_at && <span>{fecha(foto.created_at)}</span>}
              </figcaption>
              {editable && (
                <button type="button" className="galeria__quitar" onClick={() => setPorEliminar(foto)} aria-label={`Eliminar foto ${indice + 1}`} title="Eliminar foto">
                  <Trash2 size={14} />
                </button>
              )}
            </figure>
          ))}
        </div>
      ) : (
        !editable && <p className="texto-suave texto-pequeno" style={{ margin: 0 }}>El ascensor no tiene fotos.</p>
      )}

      {editable && disponibles > 0 && (
        <div style={{ marginTop: fotos.length ? 14 : 0 }}>
          <SelectorFotos archivos={nuevas} alCambiar={setNuevas} disponibles={disponibles} error={error} />
          {nuevas.length > 0 && (
            <button type="button" className="boton boton--pequeno" onClick={subir} disabled={procesando} style={{ marginTop: 8 }}>
              {procesando ? 'Subiendo…' : `Subir ${nuevas.length === 1 ? 'foto' : `${nuevas.length} fotos`}`}
            </button>
          )}
        </div>
      )}

      {ampliada !== null && fotos[ampliada] && <VisorFotos fotos={fotos} indice={ampliada} alCambiar={setAmpliada} alCerrar={() => setAmpliada(null)} />}
      <Confirmacion
        abierto={Boolean(porEliminar)}
        titulo="Eliminar foto"
        mensaje="La foto se elimina del ascensor."
        textoConfirmar="Eliminar"
        peligro
        procesando={procesando}
        alConfirmar={eliminar}
        alCerrar={() => setPorEliminar(null)}
      />
    </>
  );
}

/** Visor a pantalla completa: flechas (o ← →) para pasar de foto y Esc para cerrar. */
function VisorFotos({ fotos, indice, alCambiar, alCerrar }) {
  const foto = fotos[indice];
  const total = fotos.length;
  const cerrar = useRef(null);
  const pasar = (paso) => alCambiar((indice + paso + total) % total);

  useEffect(() => {
    const anterior = document.activeElement;
    cerrar.current?.focus();
    const original = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    return () => {
      document.body.style.overflow = original;
      anterior?.focus?.();
    };
  }, []);

  function alTeclear(evento) {
    if (evento.key === 'Escape') alCerrar();
    if (evento.key === 'ArrowRight' && total > 1) pasar(1);
    if (evento.key === 'ArrowLeft' && total > 1) pasar(-1);
  }

  return createPortal(
    <div className="visor" role="dialog" aria-modal="true" aria-label={`Foto ${indice + 1} de ${total}`} onKeyDown={alTeclear} onClick={alCerrar}>
      <div className="visor__barra" onClick={(e) => e.stopPropagation()}>
        <span className="visor__titulo">
          <strong>{foto.nombre || `Foto ${indice + 1}`}</strong>
          <small>
            {indice + 1} de {total}
            {foto.created_at ? ` · ${fecha(foto.created_at)}` : ''}
          </small>
        </span>
        <button ref={cerrar} type="button" className="visor__boton" onClick={alCerrar} aria-label="Cerrar">
          <X size={20} />
        </button>
      </div>
      <div className="visor__escenario">
        <ImagenProtegida key={foto.id} ruta={foto.ruta_archivo} alt={foto.nombre || `Foto ${indice + 1}`} className="visor__imagen" onClick={(e) => e.stopPropagation()} />
      </div>
      {total > 1 && (
        <>
          <button type="button" className="visor__boton visor__flecha visor__flecha--anterior" onClick={(e) => (e.stopPropagation(), pasar(-1))} aria-label="Foto anterior">
            <ChevronLeft size={26} />
          </button>
          <button type="button" className="visor__boton visor__flecha visor__flecha--siguiente" onClick={(e) => (e.stopPropagation(), pasar(1))} aria-label="Foto siguiente">
            <ChevronRight size={26} />
          </button>
          <div className="visor__puntos" onClick={(e) => e.stopPropagation()}>
            {fotos.map((f, k) => (
              <button key={f.id} type="button" className="visor__punto" aria-label={`Ir a la foto ${k + 1}`} aria-current={k === indice} onClick={() => alCambiar(k)} />
            ))}
          </div>
        </>
      )}
    </div>,
    document.body,
  );
}

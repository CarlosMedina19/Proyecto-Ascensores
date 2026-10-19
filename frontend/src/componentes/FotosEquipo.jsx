// Fotos del ascensor: selección con vista previa, imágenes protegidas y galería.
import { useEffect, useId, useRef, useState } from 'react';
import { ImagePlus, Trash2, X } from 'lucide-react';
import { urlArchivo } from '../api/cliente';
import { fotosEquipo } from '../api/servicios';
import { Confirmacion, Modal } from './Basicos';

export const MAXIMO_FOTOS = 3;
const TIPOS = ['image/jpeg', 'image/png', 'image/webp'];
const PESO_MAXIMO = 5 * 1024 * 1024;

/** Revisa tipo y peso antes de enviar; el servidor vuelve a validar. */
export function validarFotos(archivos, disponibles) {
  if (archivos.length > disponibles) {
    return `Puede agregar máximo ${disponibles} ${disponibles === 1 ? 'foto' : 'fotos'} más (límite de ${MAXIMO_FOTOS}).`;
  }
  const invalido = archivos.find((a) => !TIPOS.includes(a.type));
  if (invalido) return `«${invalido.name}» no es una imagen JPG, PNG o WEBP.`;
  const pesado = archivos.find((a) => a.size > PESO_MAXIMO);
  if (pesado) return `«${pesado.name}» pesa más de 5 MB.`;
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
            <small>JPG, PNG o WEBP · máx. 5 MB</small>
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
  if (!url) return <span className="fotos__cargando" aria-label="Cargando foto" />;
  return <img src={url} alt={alt} {...resto} />;
}

/** Galería de las fotos guardadas, con agregar y eliminar si el usuario puede gestionarlas. */
export function GaleriaFotos({ equipoId, fotos, editable, alCambiar, notificar }) {
  const [ampliada, setAmpliada] = useState(null);
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
        <div className="fotos">
          {fotos.map((foto, indice) => (
            <figure key={foto.id} className="fotos__item">
              <button type="button" className="fotos__ver" onClick={() => setAmpliada(foto)} aria-label={`Ampliar foto ${indice + 1}`}>
                <ImagenProtegida ruta={foto.ruta_archivo} alt={`Foto ${indice + 1} del ascensor`} />
              </button>
              {editable && (
                <button type="button" className="fotos__quitar" onClick={() => setPorEliminar(foto)} aria-label={`Eliminar foto ${indice + 1}`}>
                  <Trash2 size={13} />
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

      <Modal abierto={Boolean(ampliada)} titulo={ampliada?.nombre || 'Foto'} alCerrar={() => setAmpliada(null)} ancho>
        {ampliada && <ImagenProtegida ruta={ampliada.ruta_archivo} alt={ampliada.nombre} className="fotos__ampliada" />}
      </Modal>
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

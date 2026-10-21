// Componentes básicos reutilizables de la interfaz.
import { Fragment, useEffect, useRef, useState } from 'react';
import { ChevronLeft, ChevronRight, CircleAlert, Inbox } from 'lucide-react';

export function Cargando({ texto = 'Cargando…' }) {
  return (
    <div className="cargando" role="status">
      <div className="esqueleto" aria-hidden="true">
        <span />
        <span />
        <span />
      </div>
      <span className="cargando__texto">{texto}</span>
    </div>
  );
}

export function EstadoVacio({ titulo = 'No hay registros', descripcion, children }) {
  return (
    <div className="vacio">
      <Inbox size={36} aria-hidden="true" />
      <div style={{ fontWeight: 600, color: 'var(--color-texto)' }}>{titulo}</div>
      {descripcion && <div className="texto-pequeno">{descripcion}</div>}
      {children && <div style={{ marginTop: 14 }}>{children}</div>}
    </div>
  );
}

export function Alerta({ tipo = 'peligro', children }) {
  if (!children) return null;
  return (
    <div className={`alerta alerta--${tipo}`} role={tipo === 'peligro' ? 'alert' : 'status'}>
      <CircleAlert size={18} style={{ flexShrink: 0, marginTop: 1 }} aria-hidden="true" />
      <div>{children}</div>
    </div>
  );
}

export function Insignia({ tipo = 'neutro', children }) {
  return <span className={`insignia insignia--${tipo}`}>{children}</span>;
}

/** Descripción de una sección en forma de lista corta (una idea por elemento). */
export function ListaDescripcion({ elementos }) {
  return (
    <ul className="pagina__lista">
      {elementos.filter(Boolean).map((texto) => (
        <li key={texto}>{texto}</li>
      ))}
    </ul>
  );
}

export function EncabezadoPagina({ titulo, descripcion, migas, children }) {
  return (
    <div className="pagina__encabezado">
      <div>
        {migas && <div className="migas">{migas}</div>}
        <h1 className="pagina__titulo">{titulo}</h1>
        {Array.isArray(descripcion) ? <ListaDescripcion elementos={descripcion} /> : descripcion && <p className="pagina__descripcion">{descripcion}</p>}
      </div>
      {children && <div className="pagina__acciones">{children}</div>}
    </div>
  );
}

export function Tarjeta({ titulo, acciones, children, sinRelleno = false }) {
  return (
    <section className="tarjeta">
      {sinRelleno ? (
        children
      ) : (
        <div className="tarjeta__cuerpo">
          {(titulo || acciones) && (
            <h2 className="tarjeta__titulo">
              <span>{titulo}</span>
              {acciones && <span style={{ display: 'flex', gap: 8 }}>{acciones}</span>}
            </h2>
          )}
          {children}
        </div>
      )}
    </section>
  );
}

/**
 * Tabla genérica. Cada columna: { clave, titulo, render?, clase? }.
 */
/**
 * Tabla de datos. Con `expandir(fila)` cada fila se despliega en el mismo lugar para mostrar su
 * contenido (por ejemplo, los edificios y ascensores de un cliente) sin cambiar de página.
 */
export function Tabla({ columnas, filas, alSeleccionar, claveFila = 'id', vacio, expandir, etiquetaExpandir = 'Desplegar' }) {
  const [abiertas, setAbiertas] = useState(() => new Set());
  if (!filas.length) return vacio || <EstadoVacio />;

  const alternar = (clave) =>
    setAbiertas((actual) => {
      const siguiente = new Set(actual);
      if (siguiente.has(clave)) siguiente.delete(clave);
      else siguiente.add(clave);
      return siguiente;
    });

  return (
    <div className="tabla-contenedor">
      <table className="tabla">
        <thead>
          <tr>
            {expandir && <th className="tabla__expandir" aria-label={etiquetaExpandir} />}
            {columnas.map((columna) => (
              <th key={columna.clave} className={columna.clase} scope="col">
                {columna.titulo}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {filas.map((fila) => {
            const clave = typeof claveFila === 'function' ? claveFila(fila) : fila[claveFila];
            const abierta = abiertas.has(clave);
            return (
              <Fragment key={clave}>
                <tr
                  className={[alSeleccionar && 'tabla__fila--enlace', abierta && 'tabla__fila--abierta'].filter(Boolean).join(' ') || undefined}
                  onClick={alSeleccionar ? () => alSeleccionar(fila) : undefined}
                >
                  {expandir && (
                    <td className="tabla__expandir">
                      <button
                        type="button"
                        className="boton-desplegar"
                        aria-expanded={abierta}
                        aria-label={`${etiquetaExpandir} ${fila.nombre ?? fila.codigo ?? ''}`.trim()}
                        onClick={(e) => {
                          e.stopPropagation();
                          alternar(clave);
                        }}
                      >
                        <ChevronRight size={16} aria-hidden="true" />
                      </button>
                    </td>
                  )}
                  {columnas.map((columna) => (
                    <td key={columna.clave} className={columna.clase}>
                      {columna.render ? columna.render(fila) : (fila[columna.clave] ?? '—')}
                    </td>
                  ))}
                </tr>
                {abierta && (
                  <tr className="tabla__detalle">
                    <td colSpan={columnas.length + 1}>{expandir(fila)}</td>
                  </tr>
                )}
              </Fragment>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

export function Paginacion({ metadatos, alCambiar }) {
  if (!metadatos || metadatos.total === 0) return null;
  const { pagina_actual: pagina, ultima_pagina: ultima, desde, hasta, total } = metadatos;

  return (
    <div className="paginacion">
      <span>
        Mostrando {desde}–{hasta} de {total}
      </span>
      <div className="paginacion__botones">
        <button
          type="button"
          className="boton boton--secundario boton--pequeno"
          disabled={pagina <= 1}
          onClick={() => alCambiar(pagina - 1)}
        >
          <ChevronLeft size={16} /> Anterior
        </button>
        <span style={{ alignSelf: 'center' }}>
          Página {pagina} de {ultima}
        </span>
        <button
          type="button"
          className="boton boton--secundario boton--pequeno"
          disabled={pagina >= ultima}
          onClick={() => alCambiar(pagina + 1)}
        >
          Siguiente <ChevronRight size={16} />
        </button>
      </div>
    </div>
  );
}

export function Modal({ titulo, abierto, alCerrar, children, pie, ancho = false }) {
  const referencia = useRef(null);
  // alCerrar suele llegar como función nueva en cada render; se guarda aparte para que el efecto
  // no vuelva a correr (y no le quite el foco al campo) cada vez que el usuario escribe.
  const cerrar = useRef(alCerrar);
  cerrar.current = alCerrar;

  useEffect(() => {
    if (!abierto) return undefined;
    referencia.current?.focus();
    const alPresionar = (evento) => evento.key === 'Escape' && cerrar.current?.();
    window.addEventListener('keydown', alPresionar);
    return () => window.removeEventListener('keydown', alPresionar);
  }, [abierto]);

  if (!abierto) return null;

  return (
    <div className="modal__fondo" onMouseDown={(e) => e.target === e.currentTarget && alCerrar?.()}>
      <div
        className={`modal${ancho ? ' modal--ancho' : ''}`}
        role="dialog"
        aria-modal="true"
        aria-label={titulo}
        tabIndex={-1}
        ref={referencia}
      >
        <div className="modal__encabezado">{titulo}</div>
        <div className="modal__cuerpo">{children}</div>
        {pie && <div className="modal__pie">{pie}</div>}
      </div>
    </div>
  );
}

export function Confirmacion({ abierto, titulo, mensaje, textoConfirmar = 'Confirmar', peligro, procesando, alConfirmar, alCerrar }) {
  return (
    <Modal
      abierto={abierto}
      titulo={titulo}
      alCerrar={alCerrar}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar} disabled={procesando}>
            Cancelar
          </button>
          <button
            type="button"
            className={`boton${peligro ? ' boton--peligro' : ''}`}
            onClick={alConfirmar}
            disabled={procesando}
          >
            {procesando ? 'Procesando…' : textoConfirmar}
          </button>
        </>
      }
    >
      {mensaje}
    </Modal>
  );
}

/** Lista de pares etiqueta/valor para las vistas de detalle. */
export function Datos({ elementos, columnas = 2 }) {
  return (
    <dl className={`datos${columnas === 3 ? ' datos--3' : ''}`}>
      {elementos.map(([etiqueta, valor]) => (
        <div key={etiqueta}>
          <dt>{etiqueta}</dt>
          <dd>{valor === null || valor === undefined || valor === '' ? '—' : valor}</dd>
        </div>
      ))}
    </dl>
  );
}

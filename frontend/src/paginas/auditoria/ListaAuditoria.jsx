import { lazy, Suspense, useCallback, useEffect, useRef, useState } from 'react';
import { Eye, History, MapPin, Radio, Search, X } from 'lucide-react';
import { auditoria } from '../../api/servicios';
import { escuchar, tiempoRealDisponible } from '../../api/tiempoReal';
import { filasSegunPantalla, useListado } from '../../hooks/useListado';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Modal, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { ACCIONES_AUDITORIA, fechaHora, humanizar } from '../../utilidades/formato';

const MapaTecnicos = lazy(() => import('./MapaTecnicos'));

/** Movimientos que se muestran sin buscar: los de las últimas horas. */
const HORAS_RECIENTES = 24;
const FILTROS_HISTORIAL = { modulo: '', accion: '', fecha_desde: '', fecha_hasta: '', busqueda: '' };

function tipoAccion(accion) {
  if (accion === 'eliminado' || accion === 'desactivado' || accion === 'cuenta_bloqueada') return 'peligro';
  if (accion === 'creado' || accion === 'activado') return 'exito';
  if (accion?.includes('sesion') || accion?.includes('contrasena') || accion?.includes('credencial')) return 'info';
  return 'alerta';
}

function haceCuanto(valor) {
  if (!valor) return 'Sin ubicación aún';
  const minutos = Math.round((Date.now() - new Date(valor).getTime()) / 60000);
  if (minutos < 1) return 'Hace un momento';
  if (minutos < 60) return `Hace ${minutos} min`;
  return fechaHora(valor);
}

/** Técnicos haciendo un mantenimiento ahora: mapa y lista. */
function TecnicosEnVivo({ tecnicos, error, enVivo }) {
  const [seleccionado, setSeleccionado] = useState(null);
  const conPosicion = tecnicos.filter((t) => t.latitud != null);

  return (
    <Tarjeta
      titulo={
        <>
          <MapPin size={16} aria-hidden="true" /> Técnicos en mantenimiento
        </>
      }
      acciones={
        <Insignia tipo={enVivo ? 'exito' : 'neutro'}>
          <Radio size={12} aria-hidden="true" /> {enVivo ? 'En vivo' : 'Actualiza cada minuto'}
        </Insignia>
      }
    >
      <Alerta>{error}</Alerta>
      <div className="auditoria-vivo">
        <Suspense fallback={<div className="mapa-auditoria mapa-auditoria--cargando">Cargando mapa…</div>}>
          <MapaTecnicos tecnicos={tecnicos} seleccionado={seleccionado} />
        </Suspense>
        <div className="auditoria-vivo__lista">
          <p className="texto-pequeno texto-suave">
            {tecnicos.length
              ? `${tecnicos.length} mantenimiento${tecnicos.length === 1 ? '' : 's'} en proceso · ${conPosicion.length} con ubicación`
              : 'Ningún técnico está haciendo un mantenimiento en este momento.'}
          </p>
          <ul>
            {tecnicos.map((t) => {
              const clave = `${t.orden?.id}-${t.tecnico_id}`;
              return (
                <li key={clave}>
                  <button
                    type="button"
                    className={`auditoria-vivo__tecnico${seleccionado === clave ? ' auditoria-vivo__tecnico--activo' : ''}`}
                    onClick={() => setSeleccionado(clave)}
                    disabled={t.latitud == null}
                  >
                    <b>{t.tecnico}</b>
                    <span>
                      {t.equipo}
                      {t.edificio ? ` · ${t.edificio}` : ''}
                    </span>
                    <span className="texto-pequeno texto-suave">
                      Orden {t.orden?.numero} · {haceCuanto(t.registrada_en)}
                    </span>
                  </button>
                </li>
              );
            })}
          </ul>
        </div>
      </div>
    </Tarjeta>
  );
}

export default function ListaAuditoria() {
  const [modulos, setModulos] = useState([]);
  const [detalle, setDetalle] = useState(null);
  const [historial, setHistorial] = useState(false);
  const [tecnicos, setTecnicos] = useState([]);
  const [errorVivo, setErrorVivo] = useState('');
  const [enVivo, setEnVivo] = useState(false);
  const [nuevos, setNuevos] = useState(() => new Set());

  const consultar = useCallback(
    (f) => auditoria.listar(historial ? f : { ...f, recientes_horas: HORAS_RECIENTES, modulo: '', accion: '', fecha_desde: '', fecha_hasta: '', busqueda: '' }),
    [historial],
  );
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(consultar, FILTROS_HISTORIAL, filasSegunPantalla(42));

  const cargarEnVivo = useCallback(() => {
    auditoria
      .enVivo()
      .then((r) => {
        setTecnicos(r.datos || []);
        setErrorVivo('');
      })
      .catch((e) => setErrorVivo(e.message));
  }, []);

  useEffect(() => {
    auditoria
      .modulos()
      .then((r) => setModulos(r.datos))
      .catch(() => {});
  }, []);

  // Sin WebSocket la vista se actualiza cada minuto; con él, solo como respaldo cada 5 minutos.
  useEffect(() => {
    cargarEnVivo();
    const temporizador = setInterval(cargarEnVivo, (tiempoRealDisponible() ? 5 : 1) * 60 * 1000);
    return () => clearInterval(temporizador);
  }, [cargarEnVivo]);

  // Tiempo real: registros nuevos y posiciones de los técnicos.
  const recargarRef = useRef(recargar);
  recargarRef.current = recargar;
  const historialRef = useRef(historial);
  historialRef.current = historial;
  useEffect(() => {
    if (!tiempoRealDisponible()) return undefined;
    let espera = null;
    const dejar = escuchar('auditoria', {
      'registro.creado': (registro) => {
        setEnVivo(true);
        if (registro.modulo === 'ordenes' || registro.modelo === 'OrdenTrabajo') cargarEnVivo();
        if (historialRef.current) return;
        setNuevos((actual) => new Set(actual).add(registro.id));
        clearTimeout(espera);
        espera = setTimeout(() => recargarRef.current(), 600);
      },
      'ubicacion.actualizada': (ubicacion) => {
        setEnVivo(true);
        setTecnicos((lista) => {
          const clave = `${ubicacion.orden?.id}-${ubicacion.tecnico_id}`;
          const existe = lista.some((t) => `${t.orden?.id}-${t.tecnico_id}` === clave);
          if (!existe) return [...lista, ubicacion];
          return lista.map((t) => (`${t.orden?.id}-${t.tecnico_id}` === clave ? { ...t, ...ubicacion } : t));
        });
      },
    });
    setEnVivo(true);
    return () => {
      clearTimeout(espera);
      dejar();
    };
  }, [cargarEnVivo]);

  function alternarHistorial() {
    setHistorial((valor) => !valor);
    setPagina(1);
    setNuevos(new Set());
  }

  return (
    <>
      <EncabezadoPagina
        titulo="Auditoría"
        descripcion={['Mantenimientos en curso y ubicación de los técnicos', `Movimientos de las últimas ${HORAS_RECIENTES} horas`, 'Los registros no se editan ni se eliminan']}
      />

      <TecnicosEnVivo tecnicos={tecnicos} error={errorVivo} enVivo={enVivo} />

      <Tarjeta sinRelleno>
        <div className="auditoria-cabecera">
          <h2 className="tarjeta__titulo" style={{ margin: 0 }}>
            <span>{historial ? 'Historial de auditoría' : 'Movimientos recientes'}</span>
          </h2>
          <button type="button" className={`boton ${historial ? 'boton--secundario' : 'boton--fantasma'} boton--pequeno`} onClick={alternarHistorial}>
            {historial ? (
              <>
                <X size={15} /> Ver solo recientes
              </>
            ) : (
              <>
                <History size={15} /> Buscar en el historial
              </>
            )}
          </button>
        </div>

        {historial && (
          <div className="filtros">
            <div className="filtros__busqueda">
              <Search size={15} aria-hidden="true" />
              <input
                className="campo__control"
                placeholder="Usuario, correo o IP"
                value={filtros.busqueda}
                onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
                aria-label="Buscar por usuario, correo o IP"
              />
            </div>
            <select className="campo__control" value={filtros.modulo} onChange={(e) => cambiarFiltro('modulo', e.target.value)} aria-label="Módulo">
              <option value="">Todos los módulos</option>
              {modulos.map((modulo) => (
                <option key={modulo} value={modulo}>
                  {humanizar(modulo)}
                </option>
              ))}
            </select>
            <select className="campo__control" value={filtros.accion} onChange={(e) => cambiarFiltro('accion', e.target.value)} aria-label="Acción">
              <option value="">Todas las acciones</option>
              {Object.entries(ACCIONES_AUDITORIA).map(([valor, texto]) => (
                <option key={valor} value={valor}>
                  {texto}
                </option>
              ))}
            </select>
            <label className="texto-pequeno texto-suave" htmlFor="fecha-desde">
              Desde
            </label>
            <input id="fecha-desde" type="date" className="campo__control" value={filtros.fecha_desde} onChange={(e) => cambiarFiltro('fecha_desde', e.target.value)} />
            <label className="texto-pequeno texto-suave" htmlFor="fecha-hasta">
              Hasta
            </label>
            <input id="fecha-hasta" type="date" className="campo__control" value={filtros.fecha_hasta} onChange={(e) => cambiarFiltro('fecha_hasta', e.target.value)} />
          </div>
        )}

        {error && (
          <div style={{ padding: 16 }}>
            <Alerta>{error}</Alerta>
          </div>
        )}

        {cargando && !filas.length ? (
          <Cargando />
        ) : (
          <Tabla
            filas={filas}
            vacio={
              historial ? (
                <EstadoVacio titulo="No hay registros para los filtros seleccionados" descripcion="Amplíe el rango de fechas o quite filtros." />
              ) : (
                <EstadoVacio titulo={`Sin movimientos en las últimas ${HORAS_RECIENTES} horas`} descripcion="Los registros anteriores se consultan en el historial." />
              )
            }
            columnas={[
              {
                clave: 'created_at',
                titulo: 'Fecha',
                render: (r) => (
                  <span className={nuevos.has(r.id) ? 'auditoria-nuevo' : undefined}>
                    {fechaHora(r.created_at)}
                    {nuevos.has(r.id) && <span className="oculto-visual"> (nuevo)</span>}
                  </span>
                ),
              },
              { clave: 'usuario', titulo: 'Usuario', render: (r) => r.usuario?.nombre || 'Sistema' },
              {
                clave: 'accion',
                titulo: 'Acción',
                render: (r) => <Insignia tipo={tipoAccion(r.accion)}>{ACCIONES_AUDITORIA[r.accion] || humanizar(r.accion)}</Insignia>,
              },
              { clave: 'modulo', titulo: 'Módulo', render: (r) => humanizar(r.modulo || '—') },
              { clave: 'registro', titulo: 'Registro', render: (r) => `${r.modelo} #${r.modelo_id}` },
              { clave: 'ip', titulo: 'IP', render: (r) => <span className="codigo">{r.ip || '—'}</span> },
              {
                clave: 'ver',
                titulo: <span className="oculto-visual">Detalle</span>,
                clase: 'tabla__acciones',
                render: (r) =>
                  r.cambios && (
                    <button type="button" className="boton boton--fantasma" onClick={() => setDetalle(r)} aria-label="Ver cambios">
                      <Eye size={16} />
                    </button>
                  ),
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>

      <Modal
        abierto={Boolean(detalle)}
        titulo="Cambios registrados"
        ancho
        alCerrar={() => setDetalle(null)}
        pie={
          <button type="button" className="boton boton--secundario" onClick={() => setDetalle(null)}>
            Cerrar
          </button>
        }
      >
        {detalle && (
          <table className="tabla">
            <thead>
              <tr>
                <th>Campo</th>
                <th>Valor</th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(detalle.cambios || {}).map(([campo, valor]) => (
                <tr key={campo}>
                  <td className="codigo" style={{ border: 'none' }}>
                    {campo}
                  </td>
                  <td style={{ wordBreak: 'break-word' }}>{typeof valor === 'object' ? JSON.stringify(valor) : String(valor ?? '—')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Modal>
    </>
  );
}

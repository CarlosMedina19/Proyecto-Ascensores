import { useEffect, useState } from 'react';
import { ArrowDown, ArrowUp, Check, Pencil, Plus, RotateCcw, ShieldAlert, Trash2, X } from 'lucide-react';
import { plantillaPlanilla } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, Confirmacion, EncabezadoPagina } from '../../componentes/Basicos';

const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

/** Texto editable en el mismo lugar: clic en el lápiz, Enter guarda, Escape cancela. */
function TextoEditable({ valor, alGuardar, etiqueta, clase = '' }) {
  const [editando, setEditando] = useState(false);
  const [texto, setTexto] = useState(valor);

  useEffect(() => setTexto(valor), [valor]);

  async function guardar() {
    const limpio = texto.trim();
    if (!limpio || limpio === valor) {
      setEditando(false);
      setTexto(valor);
      return;
    }
    const ok = await alGuardar(limpio);
    if (ok !== false) setEditando(false);
  }

  if (!editando) {
    return (
      <span className={`planilla-editor__texto ${clase}`}>
        {valor}
        <button type="button" className="planilla-editor__icono" onClick={() => setEditando(true)} aria-label={`Editar ${etiqueta}`} title="Editar">
          <Pencil size={13} />
        </button>
      </span>
    );
  }
  return (
    <span className="planilla-editor__edicion">
      <input
        className="campo__control"
        value={texto}
        autoFocus
        maxLength={200}
        aria-label={etiqueta}
        onChange={(e) => setTexto(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter') guardar();
          if (e.key === 'Escape') {
            setTexto(valor);
            setEditando(false);
          }
        }}
      />
      <button type="button" className="planilla-editor__icono" onClick={guardar} aria-label="Guardar" title="Guardar">
        <Check size={15} />
      </button>
      <button
        type="button"
        className="planilla-editor__icono"
        onClick={() => {
          setTexto(valor);
          setEditando(false);
        }}
        aria-label="Cancelar"
        title="Cancelar"
      >
        <X size={15} />
      </button>
    </span>
  );
}

function NuevaActividad({ seccion, alAgregar }) {
  const [texto, setTexto] = useState('');
  const [critica, setCritica] = useState(false);

  async function agregar(evento) {
    evento.preventDefault();
    if (!texto.trim()) return;
    const ok = await alAgregar({ seccion_id: seccion.id, nombre: texto.trim(), critico: critica });
    if (ok !== false) {
      setTexto('');
      setCritica(false);
    }
  }

  return (
    <form className="planilla-editor__nueva" onSubmit={agregar}>
      <input className="campo__control" placeholder="Nueva actividad…" value={texto} maxLength={200} onChange={(e) => setTexto(e.target.value)} aria-label={`Nueva actividad en ${seccion.nombre}`} />
      <label className="planilla-editor__critica" title="Crítica: en estado Malo avisa de inmediato al coordinador">
        <input type="checkbox" checked={critica} onChange={(e) => setCritica(e.target.checked)} /> Crítica
      </label>
      <button type="submit" className="boton boton--pequeno" disabled={!texto.trim()}>
        <Plus size={15} /> Agregar
      </button>
    </form>
  );
}

/**
 * Configuración · Planilla de asistencia técnica. Se ve como la planilla en papel y se edita en el mismo lugar:
 * secciones y actividades se agregan, renombran, ordenan, marcan como críticas o se quitan.
 * Lo que se quita y ya se usó queda en las planillas diligenciadas (no se pierde el historial).
 * Cada sección tiene un interruptor para desactivarla (sale de las planillas nuevas, conserva sus actividades)
 * y volver a activarla.
 */
export default function PlantillaPlanilla() {
  const notificar = useNotificar();
  const [secciones, setSecciones] = useState(null);
  const [error, setError] = useState('');
  const [nuevaSeccion, setNuevaSeccion] = useState('');
  const [confirmar, setConfirmar] = useState(null);
  const [procesando, setProcesando] = useState(false);

  useEffect(() => {
    plantillaPlanilla
      .obtener()
      .then((r) => setSecciones(r.datos))
      .catch((e) => setError(e.message));
  }, []);

  async function ejecutar(accion) {
    try {
      const r = await accion();
      setSecciones(r.datos);
      if (r.mensaje) notificar(r.mensaje);
      return true;
    } catch (e) {
      notificar(e.message, 'error');
      return false;
    }
  }

  async function confirmarAccion() {
    setProcesando(true);
    await ejecutar(confirmar.accion);
    setProcesando(false);
    setConfirmar(null);
  }

  if (error) return <Alerta>{error}</Alerta>;
  if (!secciones) return <Cargando />;

  const activas = secciones.filter((s) => s.habilitada !== false);
  const total = activas.reduce((a, s) => a + s.items.length, 0);
  const desactivadas = secciones.length - activas.length;
  // Dos columnas equilibradas, como en la planilla en papel.
  const mitad = secciones.reduce((a, s) => a + s.items.length + 2, 0) / 2;
  const columnas = [[], []];
  let acumulado = 0;
  secciones.forEach((s, indice) => {
    columnas[acumulado < mitad ? 0 : 1].push({ ...s, indice });
    acumulado += s.items.length + 2;
  });

  return (
    <>
      <EncabezadoPagina
        titulo="Planilla de asistencia técnica"
        descripcion={[
          'La diligencian los técnicos en cada visita (app y panel)',
          [plural(activas.length, 'sección activa', 'secciones activas'), plural(total, 'actividad', 'actividades'), desactivadas ? plural(desactivadas, 'desactivada', 'desactivadas') : null].filter(Boolean).join(' · '),
          'Los cambios aplican a las próximas planillas',
        ]}
      >
        <button type="button" className="boton boton--secundario" onClick={() => setConfirmar({ titulo: 'Restablecer la planilla original', mensaje: 'La planilla vuelve a las 45 actividades del formato original. Las planillas ya diligenciadas no cambian.', textoConfirmar: 'Restablecer', accion: () => plantillaPlanilla.restablecer() })}>
          <RotateCcw size={16} /> Restablecer original
        </button>
      </EncabezadoPagina>

      <div className="planilla-editor">
        <div className="planilla-editor__cabecera">
          <div>
            <b>PLANILLA DE ASISTENCIA TÉCNICA</b>
            <span>Control de mantenimiento y reparaciones</span>
          </div>
          <span className="planilla-editor__datos">Edificio · Dirección · Técnico · Fecha, entrada y salida · Preventivo / Correctivo / Reparación</span>
        </div>

        <div className="planilla-editor__columnas">
          {columnas.map((columna, k) => (
            <div key={k} className="planilla-editor__columna">
              {columna.map((seccion) => {
                const habilitada = seccion.habilitada !== false;
                return (
                  <section key={seccion.id} className={`planilla-editor__seccion${habilitada ? '' : ' planilla-editor__seccion--inactiva'}`} aria-label={habilitada ? seccion.nombre : `${seccion.nombre} (desactivada)`}>
                    <header className="planilla-editor__titulo">
                      <TextoEditable valor={seccion.nombre} etiqueta={`nombre de la sección ${seccion.nombre}`} alGuardar={(nombre) => ejecutar(() => plantillaPlanilla.actualizarSeccion(seccion.id, { nombre }))} />
                      <span className="planilla-editor__acciones">
                        <button
                          type="button"
                          role="switch"
                          aria-checked={habilitada}
                          className={`planilla-editor__interruptor${habilitada ? ' planilla-editor__interruptor--activa' : ''}`}
                          onClick={() => ejecutar(() => plantillaPlanilla.actualizarSeccion(seccion.id, { habilitada: !habilitada }))}
                          aria-label={`${habilitada ? 'Desactivar' : 'Activar'} la sección ${seccion.nombre}`}
                          title={habilitada ? 'Desactivar: deja de aparecer en las planillas nuevas' : 'Activar: vuelve a las planillas nuevas'}
                        >
                          <span className="planilla-editor__interruptor-pista" aria-hidden="true">
                            <span />
                          </span>
                          {habilitada ? 'Activa' : 'Desactivada'}
                        </button>
                        <button type="button" className="planilla-editor__icono" disabled={seccion.indice === 0} onClick={() => ejecutar(() => plantillaPlanilla.actualizarSeccion(seccion.id, { mover: 'arriba' }))} aria-label={`Subir la sección ${seccion.nombre}`} title="Subir">
                          <ArrowUp size={14} />
                        </button>
                        <button type="button" className="planilla-editor__icono" disabled={seccion.indice === secciones.length - 1} onClick={() => ejecutar(() => plantillaPlanilla.actualizarSeccion(seccion.id, { mover: 'abajo' }))} aria-label={`Bajar la sección ${seccion.nombre}`} title="Bajar">
                          <ArrowDown size={14} />
                        </button>
                        <button
                          type="button"
                          className="planilla-editor__icono"
                          onClick={() => setConfirmar({ titulo: `Quitar la sección «${seccion.nombre}»`, mensaje: `Se quitan sus ${seccion.items.length} actividades de las próximas planillas.`, textoConfirmar: 'Quitar', peligro: true, accion: () => plantillaPlanilla.eliminarSeccion(seccion.id) })}
                          aria-label={`Quitar la sección ${seccion.nombre}`}
                          title="Quitar sección"
                        >
                          <Trash2 size={14} />
                        </button>
                      </span>
                    </header>
                    {!habilitada && <p className="planilla-editor__aviso">Desactivada: no aparece en las planillas nuevas ni se exige para finalizar. Sus actividades se conservan.</p>}
                    <table className="planilla-editor__tabla">
                      <thead>
                        <tr>
                          <th>Actividad</th>
                          <th>INS</th>
                          <th>EST</th>
                          <th>N.A.</th>
                          <th>
                            <span className="oculto-visual">Acciones</span>
                          </th>
                        </tr>
                      </thead>
                      <tbody>
                        {seccion.items.map((item, i) => (
                          <tr key={item.id}>
                            <td>
                              <TextoEditable valor={item.nombre} etiqueta={`actividad ${item.nombre}`} alGuardar={(nombre) => ejecutar(() => plantillaPlanilla.actualizarItem(item.id, { nombre }))} />
                            </td>
                            <td className="planilla-editor__casilla" aria-hidden="true" />
                            <td className="planilla-editor__casilla" aria-hidden="true" />
                            <td className="planilla-editor__casilla" aria-hidden="true" />
                            <td className="planilla-editor__acciones">
                              <button
                                type="button"
                                className={`planilla-editor__icono${item.critico ? ' planilla-editor__icono--critica' : ''}`}
                                onClick={() => ejecutar(() => plantillaPlanilla.actualizarItem(item.id, { critico: !item.critico }))}
                                aria-label={item.critico ? `Quitar crítica a ${item.nombre}` : `Marcar como crítica ${item.nombre}`}
                                aria-pressed={item.critico}
                                title={item.critico ? 'Crítica: en Malo avisa de inmediato' : 'Marcar como crítica'}
                              >
                                <ShieldAlert size={14} />
                              </button>
                              <button type="button" className="planilla-editor__icono" disabled={i === 0} onClick={() => ejecutar(() => plantillaPlanilla.actualizarItem(item.id, { mover: 'arriba' }))} aria-label={`Subir ${item.nombre}`} title="Subir">
                                <ArrowUp size={14} />
                              </button>
                              <button type="button" className="planilla-editor__icono" disabled={i === seccion.items.length - 1} onClick={() => ejecutar(() => plantillaPlanilla.actualizarItem(item.id, { mover: 'abajo' }))} aria-label={`Bajar ${item.nombre}`} title="Bajar">
                                <ArrowDown size={14} />
                              </button>
                              <button
                                type="button"
                                className="planilla-editor__icono"
                                onClick={() => setConfirmar({ titulo: 'Quitar actividad', mensaje: `«${item.nombre}» deja de aparecer en las próximas planillas.`, textoConfirmar: 'Quitar', peligro: true, accion: () => plantillaPlanilla.eliminarItem(item.id) })}
                                aria-label={`Quitar ${item.nombre}`}
                                title="Quitar"
                              >
                                <Trash2 size={14} />
                              </button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                    <NuevaActividad seccion={seccion} alAgregar={(datos) => ejecutar(() => plantillaPlanilla.crearItem(datos))} />
                  </section>
                );
              })}
            </div>
          ))}
        </div>

        <form
          className="planilla-editor__nueva planilla-editor__nueva--seccion"
          onSubmit={async (e) => {
            e.preventDefault();
            if (nuevaSeccion.trim() && (await ejecutar(() => plantillaPlanilla.crearSeccion(nuevaSeccion.trim())))) setNuevaSeccion('');
          }}
        >
          <input className="campo__control" placeholder="Nueva sección, ej. Sala de bombas" value={nuevaSeccion} maxLength={100} onChange={(e) => setNuevaSeccion(e.target.value)} aria-label="Nombre de la nueva sección" />
          <button type="submit" className="boton" disabled={!nuevaSeccion.trim()}>
            <Plus size={16} /> Agregar sección
          </button>
        </form>

        <p className="planilla-editor__convenciones">
          <b>Convenciones:</b> INS = Inspección · EST = Estado (B = Bueno, R = Regular, M = Malo) · N.A. = No aplica · <ShieldAlert size={13} aria-hidden="true" /> crítica: en Malo avisa de inmediato al coordinador
        </p>
      </div>

      <Confirmacion
        abierto={Boolean(confirmar)}
        titulo={confirmar?.titulo}
        mensaje={confirmar?.mensaje}
        textoConfirmar={confirmar?.textoConfirmar}
        peligro={confirmar?.peligro}
        procesando={procesando}
        alConfirmar={confirmarAccion}
        alCerrar={() => setConfirmar(null)}
      />
    </>
  );
}

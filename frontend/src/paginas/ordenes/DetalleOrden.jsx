import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Ban, CalendarClock, CheckCircle2, Flag, Navigation, PauseCircle, PlayCircle, Users } from 'lucide-react';
import { ordenes } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Datos, EncabezadoPagina, EstadoVacio, Insignia, Modal, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto } from '../../componentes/Campos';
import LineaTiempo from '../../componentes/LineaTiempo';
import {
  ACCIONES_ORDEN,
  ESTADOS_ORDEN,
  PRIORIDADES_ORDEN,
  SERVICIOS_ORDEN,
  duracion,
  fecha,
  fechaHora,
  fechaIso,
  iniciales,
} from '../../utilidades/formato';
import SelectorTecnicos from './SelectorTecnicos';
import PlanillaOrden from './PlanillaOrden';
import FirmasInformes from './FirmasInformes';

const EN_CURSO = ['en_camino', 'en_proceso'];
const FINALES = ['finalizada', 'cancelada'];
const CANCELABLES = ['programada', 'asignada', 'pendiente'];

/** RF-026: acciones del técnico asignado según el estado actual. */
const ACCIONES_TECNICO = {
  asignada: [{ estado: 'en_camino', texto: 'Voy en camino', icono: Navigation }],
  en_camino: [{ estado: 'en_proceso', texto: 'Iniciar trabajo', icono: PlayCircle }],
  en_proceso: [
    { estado: 'pendiente', texto: 'Dejar pendiente', icono: PauseCircle, secundaria: true },
    { estado: 'finalizada', texto: 'Finalizar orden', icono: CheckCircle2 },
  ],
  pendiente: [{ estado: 'en_proceso', texto: 'Retomar trabajo', icono: PlayCircle }],
};

function valorLegible(campo, valor) {
  if (valor === null || valor === undefined) return '—';
  if (campo === 'estado') return ESTADOS_ORDEN[valor]?.texto ?? valor;
  if (campo === 'prioridad') return PRIORIDADES_ORDEN[valor]?.texto ?? valor;
  if (campo === 'inicio_en' || campo === 'fin_en') return fechaHora(valor);
  return valor;
}

function PieModal({ alCerrar, alGuardar, enviando, texto, peligro }) {
  return (
    <>
      <button type="button" className="boton boton--secundario" onClick={alCerrar}>
        Volver
      </button>
      <button type="button" className={`boton${peligro ? ' boton--peligro' : ''}`} onClick={alGuardar} disabled={enviando}>
        {enviando ? 'Guardando…' : texto}
      </button>
    </>
  );
}

/** Cambio de estado del técnico (pendiente exige motivo; finalizar exige informe). */
function ModalEstado({ orden, accion, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ motivo: '', informe: '', observaciones: orden.observaciones ?? '' });
  const pideFormulario = accion.estado === 'pendiente' || accion.estado === 'finalizada';

  async function guardar() {
    const cuerpo = { estado: accion.estado };
    if (accion.estado === 'pendiente') cuerpo.motivo = datos.motivo;
    if (accion.estado === 'finalizada') Object.assign(cuerpo, { informe: datos.informe, observaciones: datos.observaciones || null });
    const respuesta = await enviar(() => ordenes.cambiarEstado(orden.id, cuerpo));
    if (respuesta) alGuardar(`Orden ${ESTADOS_ORDEN[accion.estado].texto.toLowerCase()}.`);
  }

  return (
    <Modal abierto ancho={accion.estado === 'finalizada'} titulo={accion.texto} alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto={accion.texto} />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        {!pideFormulario && (
          <p style={{ margin: 0 }}>
            {accion.estado === 'en_proceso' && !orden.inicio_en ? 'Se registrará la hora de inicio del trabajo.' : `La orden pasará a «${ESTADOS_ORDEN[accion.estado].texto}».`}
          </p>
        )}
        {accion.estado === 'pendiente' && (
          <CampoArea etiqueta="¿Por qué queda pendiente?" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={3} placeholder="Falta un repuesto, acceso restringido…" />
        )}
        {accion.estado === 'finalizada' && (
          <>
            <Alerta tipo="info">
              Se registra la hora de finalización, se calcula la duración y se emite el informe PDF. Requiere las dos firmas
              {orden.lleva_planilla ? ' y la planilla completa' : ''}.
            </Alerta>
            <CampoArea etiqueta="Informe del trabajo realizado" obligatorio valor={datos.informe} alCambiar={cambiar('informe')} error={error('informe')} rows={5} />
            <CampoArea etiqueta="Observaciones" valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} rows={2} />
          </>
        )}
      </div>
    </Modal>
  );
}

function ModalAsignar({ orden, alCerrar, alGuardar }) {
  const [seleccion, setSeleccion] = useState(orden.tecnicos.map((t) => t.id));
  const { error, mensajeError, enviando, enviar } = useFormulario({});

  async function guardar() {
    const respuesta = await enviar(() => ordenes.asignar(orden.id, seleccion));
    if (respuesta) alGuardar('Técnicos asignados.');
  }

  return (
    <Modal abierto ancho titulo={`Técnicos de ${orden.numero}`} alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Guardar técnicos" />}>
      <div className="formulario">
        <Alerta>{mensajeError || error('tecnicos')}</Alerta>
        <p className="texto-pequeno texto-suave" style={{ margin: 0 }}>
          {fecha(orden.fecha)} · {orden.hora} – {orden.fin_estimado}. Los técnicos nuevos reciben un aviso.
        </p>
        <SelectorTecnicos fecha={orden.fecha} hora={orden.hora} duracion={orden.duracion_estimada} ordenId={orden.id} seleccion={seleccion} alCambiar={setSeleccion} />
      </div>
    </Modal>
  );
}

function ModalReprogramar({ orden, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    fecha: orden.fecha,
    hora: orden.hora,
    duracion_estimada: String(orden.duracion_estimada),
    motivo: '',
  });

  async function guardar() {
    const respuesta = await enviar(() => ordenes.reprogramar(orden.id, { ...datos, duracion_estimada: Number(datos.duracion_estimada) }));
    if (respuesta) alGuardar('Orden reprogramada.');
  }

  return (
    <Modal abierto titulo="Reprogramar orden" alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Reprogramar" />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <div className="formulario__fila formulario__fila--3">
          <CampoTexto etiqueta="Fecha" tipo="date" obligatorio min={fechaIso()} valor={datos.fecha} alCambiar={cambiar('fecha')} error={error('fecha')} />
          <CampoTexto etiqueta="Hora" tipo="time" obligatorio step="900" valor={datos.hora} alCambiar={cambiar('hora')} error={error('hora')} />
          <CampoTexto etiqueta="Duración (min)" tipo="number" min="15" max="720" step="15" valor={datos.duracion_estimada} alCambiar={cambiar('duracion_estimada')} error={error('duracion_estimada')} />
        </div>
        <CampoArea etiqueta="Motivo" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={2} />
        <span className="campo__ayuda">Se revisa que los técnicos asignados no tengan otra orden en el nuevo horario.</span>
      </div>
    </Modal>
  );
}

function ModalPrioridad({ orden, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ prioridad: orden.prioridad, motivo: '' });

  async function guardar() {
    const respuesta = await enviar(() => ordenes.cambiarPrioridad(orden.id, { prioridad: datos.prioridad, motivo: datos.motivo || null }));
    if (respuesta) alGuardar('Prioridad actualizada.');
  }

  return (
    <Modal abierto titulo="Cambiar prioridad" alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Guardar prioridad" />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <CampoSelector
          etiqueta="Prioridad"
          vacio={null}
          valor={datos.prioridad}
          alCambiar={cambiar('prioridad')}
          error={error('prioridad')}
          opciones={Object.entries(PRIORIDADES_ORDEN).map(([valor, { texto }]) => ({ valor, texto }))}
          ayuda={datos.prioridad === 'critica' ? 'Se avisa de inmediato a los coordinadores.' : undefined}
        />
        <CampoArea etiqueta="Motivo" valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={2} />
      </div>
    </Modal>
  );
}

function ModalCancelar({ orden, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ motivo: '' });

  async function guardar() {
    const respuesta = await enviar(() => ordenes.cancelar(orden.id, datos.motivo));
    if (respuesta) alGuardar('Orden cancelada.');
  }

  return (
    <Modal abierto titulo={`Cancelar ${orden.numero}`} alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Cancelar orden" peligro />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <p style={{ margin: 0 }}>La cancelación es definitiva. Se avisa a los técnicos asignados.</p>
        <CampoArea etiqueta="Motivo" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={3} />
      </div>
    </Modal>
  );
}

export default function DetalleOrden() {
  const { id } = useParams();
  const notificar = useNotificar();
  const [orden, setOrden] = useState(null);
  const [error, setError] = useState('');
  const [modal, setModal] = useState(null);

  const cargar = useCallback(() => {
    ordenes
      .obtener(id)
      .then((r) => setOrden(r.datos))
      .catch((e) => setError(e.message));
  }, [id]);

  useEffect(cargar, [cargar]);

  function alGuardar(mensaje) {
    setModal(null);
    notificar(mensaje);
    cargar();
  }

  if (error) return <Alerta>{error}</Alerta>;
  if (!orden) return <Cargando />;

  const final = FINALES.includes(orden.estado);
  const enCurso = EN_CURSO.includes(orden.estado);
  const permisos = orden.permisos || {};
  const accionesTecnico = permisos.ejecutar ? ACCIONES_TECNICO[orden.estado] || [] : [];
  const servicio = SERVICIOS_ORDEN[orden.servicio];
  const prioridad = PRIORIDADES_ORDEN[orden.prioridad];
  const estado = ESTADOS_ORDEN[orden.estado];

  const eventos = (orden.trazabilidad || []).map((t) => ({
    id: t.id,
    created_at: t.created_at,
    evento: t.accion === 'estado_cambiado' && t.valor_nuevo === 'cancelada' ? 'baja' : t.accion,
    titulo: ACCIONES_ORDEN[t.accion] ?? t.accion,
    usuario: t.usuario,
    descripcion: [
      t.campo && t.campo !== 'tecnicos' && (t.valor_anterior || t.valor_nuevo)
        ? t.valor_anterior
          ? `${valorLegible(t.campo, t.valor_anterior)} → ${valorLegible(t.campo, t.valor_nuevo)}`
          : valorLegible(t.campo, t.valor_nuevo)
        : t.campo === 'tecnicos'
          ? t.valor_nuevo || t.valor_anterior
          : null,
      t.detalle,
    ]
      .filter(Boolean)
      .join('. '),
  }));

  return (
    <>
      <EncabezadoPagina
        titulo={`Orden ${orden.numero}`}
        migas={<Link to="/ordenes">Órdenes</Link>}
        descripcion={
          <>
            <Insignia tipo={servicio?.tipo}>{servicio?.texto}</Insignia> <Insignia tipo={prioridad?.tipo}>Prioridad {prioridad?.texto.toLowerCase()}</Insignia>{' '}
            <Insignia tipo={estado?.tipo}>{estado?.texto}</Insignia>
          </>
        }
      >
        {!final && permisos.cancelar && CANCELABLES.includes(orden.estado) && (
          <button type="button" className="boton boton--secundario" onClick={() => setModal({ tipo: 'cancelar' })}>
            <Ban size={16} /> Cancelar
          </button>
        )}
        {!final && permisos.editar && (
          <button type="button" className="boton boton--secundario" onClick={() => setModal({ tipo: 'prioridad' })}>
            <Flag size={16} /> Prioridad
          </button>
        )}
        {!final && !enCurso && permisos.editar && (
          <button type="button" className="boton boton--secundario" onClick={() => setModal({ tipo: 'reprogramar' })}>
            <CalendarClock size={16} /> Reprogramar
          </button>
        )}
        {!final && !enCurso && permisos.asignar && (
          <button type="button" className={`boton${accionesTecnico.length ? ' boton--secundario' : ''}`} onClick={() => setModal({ tipo: 'asignar' })}>
            <Users size={16} /> {orden.tecnicos.length ? 'Cambiar técnicos' : 'Asignar técnicos'}
          </button>
        )}
        {accionesTecnico.map((accion) => (
          <button key={accion.estado} type="button" className={`boton${accion.secundaria ? ' boton--secundario' : ''}`} onClick={() => setModal({ tipo: 'estado', accion })}>
            <accion.icono size={16} /> {accion.texto}
          </button>
        ))}
      </EncabezadoPagina>

      {orden.estado === 'programada' && <Alerta tipo="alerta">La orden aún no tiene técnicos. Debe tener al menos uno para quedar asignada.</Alerta>}
      {orden.estado === 'cancelada' && <Alerta tipo="info">Orden cancelada. Motivo: {orden.motivo_cancelacion}</Alerta>}

      <div className="rejilla rejilla--2" style={{ alignItems: 'start' }}>
        <div className="rejilla">
          <Tarjeta titulo="Datos de la orden">
            <Datos
              elementos={[
                ['Cliente', orden.cliente?.nombre],
                ['Edificio', orden.edificio ? <Link to={`/edificios/${orden.edificio.id}`}>{orden.edificio.nombre}</Link> : null],
                ['Ascensor', orden.equipo ? <Link to={`/equipos/${orden.equipo.id}`}>{orden.equipo.codigo}</Link> : null],
                ['Dirección', [orden.edificio?.direccion, orden.edificio?.ciudad].filter(Boolean).join(', ')],
                ['Fecha', fecha(orden.fecha)],
                ['Horario', `${orden.hora} – ${orden.fin_estimado} (${duracion(orden.duracion_estimada)})`],
                ['Creada por', orden.creado_por?.nombre],
                ['Creada', fechaHora(orden.created_at)],
              ]}
            />
            {orden.descripcion && (
              <>
                <div className="separador" />
                <div className="texto-pequeno texto-suave">Trabajo a realizar</div>
                <p style={{ margin: '4px 0 0', whiteSpace: 'pre-line' }}>{orden.descripcion}</p>
              </>
            )}
            {orden.observaciones && (
              <>
                <div className="separador" />
                <div className="texto-pequeno texto-suave">Observaciones</div>
                <p style={{ margin: '4px 0 0', whiteSpace: 'pre-line' }}>{orden.observaciones}</p>
              </>
            )}
          </Tarjeta>

          <Tarjeta titulo="Tiempos">
            <Datos
              elementos={[
                ['Inicio', orden.inicio_en ? fechaHora(orden.inicio_en) : 'Se registra al iniciar el trabajo'],
                ['Finalización', orden.fin_en ? fechaHora(orden.fin_en) : '—'],
                ['Duración real', duracion(orden.duracion_minutos)],
                ['Duración estimada', duracion(orden.duracion_estimada)],
              ]}
            />
          </Tarjeta>

          {orden.informe && (
            <Tarjeta titulo="Informe del trabajo">
              <p style={{ margin: 0, whiteSpace: 'pre-line' }}>{orden.informe}</p>
            </Tarjeta>
          )}
        </div>

        <div className="rejilla">
          <Tarjeta titulo={`Técnicos (${orden.tecnicos.length})`}>
            {orden.tecnicos.length ? (
              <ul className="tecnicos tecnicos--sin-borde">
                {orden.tecnicos.map((t) => (
                  <li key={t.id}>
                    <span className="avatar" aria-hidden="true">
                      {iniciales(t.nombre)}
                    </span>
                    <span className="tecnicos__texto">
                      <b>{t.nombre}</b>
                      <small>{[t.telefono, ...(t.especialidades || [])].filter(Boolean).join(' · ')}</small>
                    </span>
                  </li>
                ))}
              </ul>
            ) : (
              <EstadoVacio titulo="Sin técnicos asignados" />
            )}
          </Tarjeta>

          {/* Fase 4: correctivo derivado (RF-038). */}
          {(orden.orden_origen || orden.correctivos_derivados?.length > 0) && (
            <Tarjeta titulo="Correctivos relacionados">
              {orden.orden_origen && (
                <p style={{ marginTop: 0 }}>
                  Creado desde la orden <Link to={`/ordenes/${orden.orden_origen.id}`}>{orden.orden_origen.numero}</Link>
                  {orden.orden_origen.item && <> · ítem «{orden.orden_origen.item}»</>}.
                </p>
              )}
              {orden.correctivos_derivados?.length > 0 && (
                <ul className="visitas">
                  {orden.correctivos_derivados.map((c) => (
                    <li key={c.id}>
                      <Link to={`/ordenes/${c.id}`} className="visitas__boton">
                        <b className="tabla__codigo">{c.numero}</b>
                        <Insignia tipo={ESTADOS_ORDEN[c.estado]?.tipo}>{ESTADOS_ORDEN[c.estado]?.texto}</Insignia>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </Tarjeta>
          )}

          <FirmasInformes orden={orden} alCambiar={cargar} notificar={notificar} />
        </div>
      </div>

      {/* Fase 4: planilla del mantenimiento preventivo (RF-030 a RF-038). */}
      {orden.lleva_planilla && (
        <div style={{ marginTop: 16 }}>
          <PlanillaOrden orden={orden} alCambiar={cargar} notificar={notificar} />
        </div>
      )}

      <div style={{ marginTop: 16 }}>
        <Tarjeta titulo="Trazabilidad">
          {eventos.length ? <LineaTiempo eventos={eventos} /> : <EstadoVacio titulo="Sin cambios registrados" />}
        </Tarjeta>
      </div>

      {modal?.tipo === 'estado' && <ModalEstado orden={orden} accion={modal.accion} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {modal?.tipo === 'asignar' && <ModalAsignar orden={orden} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {modal?.tipo === 'reprogramar' && <ModalReprogramar orden={orden} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {modal?.tipo === 'prioridad' && <ModalPrioridad orden={orden} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {modal?.tipo === 'cancelar' && <ModalCancelar orden={orden} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
    </>
  );
}

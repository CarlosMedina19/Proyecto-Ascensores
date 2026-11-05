import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { CalendarClock, Plus } from 'lucide-react';
import { asignaciones, configuracion, contratos } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Datos, EstadoVacio, Insignia, Modal, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';
import LineaTiempo from '../../componentes/LineaTiempo';
import { dinero, diasHasta, estadoMantenimiento, fecha, fechaIso, periodicidad } from '../../utilidades/formato';
import ModalMantenimiento from '../mantenimientos/ModalMantenimiento';

function PieModal({ alCerrar, alGuardar, enviando, texto, peligro }) {
  return (
    <>
      <button type="button" className="boton boton--secundario" onClick={alCerrar}>
        Cancelar
      </button>
      <button type="button" className={`boton${peligro ? ' boton--peligro' : ''}`} onClick={alGuardar} disabled={enviando}>
        {enviando ? 'Guardando…' : texto}
      </button>
    </>
  );
}

/** Periodicidades activas (cada cuántos meses se visita el ascensor). */
function usePeriodicidades() {
  const [lista, setLista] = useState(null);
  useEffect(() => {
    configuracion
      .periodicidades()
      .then((r) => setLista(r.datos.filter((p) => p.activo).sort((a, b) => a.meses - b.meses)))
      .catch(() => setLista([]));
  }, []);
  return lista;
}

/** Frecuencia de las visitas preventivas. Los planes son de alquiler del software: aquí no se elige plan. */
function SelectorFrecuencia({ periodicidades, datos, cambiar, error }) {
  return (
    <CampoSelector
      etiqueta="Frecuencia de mantenimiento"
      obligatorio
      valor={datos.periodicidad_id}
      alCambiar={cambiar('periodicidad_id')}
      error={error('periodicidad_id')}
      opciones={periodicidades.map((p) => ({ valor: String(p.id), texto: periodicidad(p.meses) }))}
    />
  );
}

/** RF-013 y RF-014: programa de mantenimiento del ascensor (frecuencia, vigencia y hora de las visitas). */
function ModalAsignar({ equipo, alCerrar, alGuardar }) {
  const { tienePermiso } = useAutenticacion();
  const periodicidades = usePeriodicidades();
  const [listaContratos, setListaContratos] = useState([]);
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    periodicidad_id: '',
    contrato_id: '',
    fecha_inicio: fechaIso(),
    fecha_fin: '',
    aplica_iva: true,
    precio: '',
    hora_preferida: '08:00',
  });

  const clienteId = equipo.edificio?.cliente_id ?? equipo.edificio?.cliente?.id;
  useEffect(() => {
    if (!tienePermiso('contratos.ver') || !clienteId) return;
    contratos
      .listar({ cliente_id: clienteId, estado: 'vigente', por_pagina: 50 })
      .then((r) => setListaContratos(r.datos))
      .catch(() => {});
  }, [clienteId, tienePermiso]);

  async function guardar() {
    const respuesta = await enviar(() =>
      asignaciones.crear({
        equipo_id: equipo.id,
        periodicidad_id: datos.periodicidad_id ? Number(datos.periodicidad_id) : null,
        contrato_id: datos.contrato_id ? Number(datos.contrato_id) : null,
        fecha_inicio: datos.fecha_inicio,
        fecha_fin: datos.fecha_fin || null,
        aplica_iva: datos.aplica_iva,
        precio: datos.precio === '' ? null : Number(datos.precio),
        hora_preferida: datos.hora_preferida || null,
      }),
    );
    if (respuesta) alGuardar('Mantenimientos programados.');
  }

  return (
    <Modal
      abierto
      ancho
      titulo={`Programar mantenimiento de ${equipo.codigo}`}
      alCerrar={alCerrar}
      pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Programar visitas" />}
    >
      {periodicidades === null ? (
        <Cargando />
      ) : (
        <div className="formulario">
          <Alerta>{mensajeError}</Alerta>
          <SelectorFrecuencia periodicidades={periodicidades} datos={datos} cambiar={cambiar} error={error} />
          {listaContratos.length > 0 && (
            <CampoSelector
              etiqueta="Contrato"
              vacio="Sin contrato"
              valor={datos.contrato_id}
              alCambiar={cambiar('contrato_id')}
              error={error('contrato_id')}
              ayuda="La programación no pasa de la fecha final del contrato."
              opciones={listaContratos.map((c) => ({ valor: String(c.id), texto: `${c.numero} · hasta ${fecha(c.fecha_fin)}` }))}
            />
          )}
          <div className="formulario__fila">
            <CampoTexto etiqueta="Fecha inicial" tipo="date" obligatorio valor={datos.fecha_inicio} alCambiar={cambiar('fecha_inicio')} error={error('fecha_inicio')} ayuda="La primera visita es esta fecha más la periodicidad." />
            <CampoTexto etiqueta="Fecha final" tipo="date" valor={datos.fecha_fin} alCambiar={cambiar('fecha_fin')} error={error('fecha_fin')} ayuda="Vacía: la del contrato o un año." />
          </div>
          <CampoTexto
            etiqueta="Hora de las visitas"
            tipo="time"
            min="06:00"
            max="18:00"
            step="900"
            valor={datos.hora_preferida}
            alCambiar={cambiar('hora_preferida')}
            error={error('hora_preferida')}
            ayuda="Hora acordada con el cliente (6:00 a 18:00). Cada visita se puede reprogramar."
          />
          {tienePermiso('precios.editar') && (
            <CampoTexto etiqueta="Valor por visita (opcional)" tipo="number" min="0" step="1000" valor={datos.precio} alCambiar={cambiar('precio')} error={error('precio')} ayuda="Vacío: las visitas van incluidas en el contrato." />
          )}
          <Casilla etiqueta="Aplica IVA" valor={datos.aplica_iva} alCambiar={cambiar('aplica_iva')} />
        </div>
      )}
    </Modal>
  );
}

/** RF-016: cambio de la frecuencia de mantenimiento con motivo. */
function ModalCambiarPlan({ asignacion, alCerrar, alGuardar }) {
  const periodicidades = usePeriodicidades();
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    periodicidad_id: String(asignacion.periodicidad_id),
    motivo: '',
  });

  async function guardar() {
    const respuesta = await enviar(() =>
      asignaciones.cambiarPlan(asignacion.id, {
        periodicidad_id: datos.periodicidad_id ? Number(datos.periodicidad_id) : null,
        motivo: datos.motivo,
      }),
    );
    if (respuesta) alGuardar('Frecuencia actualizada. Las visitas pendientes se reprogramaron.');
  }

  return (
    <Modal abierto ancho titulo="Cambiar la frecuencia de mantenimiento" alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Guardar cambio" />}>
      {periodicidades === null ? (
        <Cargando />
      ) : (
        <div className="formulario">
          <Alerta>{mensajeError}</Alerta>
          <Alerta tipo="info">Aplica desde la siguiente visita pendiente. Las visitas ya ejecutadas no cambian.</Alerta>
          <SelectorFrecuencia periodicidades={periodicidades} datos={datos} cambiar={cambiar} error={error} />
          <CampoArea etiqueta="Motivo" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={2} />
        </div>
      )}
    </Modal>
  );
}

/** RF-015, RF-014: precio pactado, vigencia y finalización (todas con motivo). */
function ModalSimple({ tipo, asignacion, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    precio: String(Number(asignacion.precio)),
    fecha_fin: asignacion.fecha_fin,
    motivo: '',
  });

  const configuracion = {
    precio: {
      titulo: 'Cambiar valor por visita',
      texto: 'Guardar precio',
      llamada: () => asignaciones.cambiarPrecio(asignacion.id, { precio: Number(datos.precio), motivo: datos.motivo }),
      mensaje: 'Precio actualizado en las visitas pendientes.',
      aviso: 'El nuevo precio aplica a las visitas pendientes. Las ejecutadas conservan su precio.',
    },
    vigencia: {
      titulo: 'Cambiar vigencia del programa',
      texto: 'Guardar vigencia',
      llamada: () => asignaciones.cambiarVigencia(asignacion.id, { fecha_fin: datos.fecha_fin, motivo: datos.motivo }),
      mensaje: 'Vigencia actualizada.',
      aviso: 'Las visitas se reprograman hasta la nueva fecha final (sin pasar la del contrato).',
    },
    finalizar: {
      titulo: 'Finalizar programa de mantenimiento',
      texto: 'Finalizar programa',
      llamada: () => asignaciones.finalizar(asignacion.id, datos.motivo),
      mensaje: 'Programa de mantenimiento finalizado.',
      aviso: 'Se cancelan las visitas pendientes. El historial se conserva.',
      peligro: true,
    },
  }[tipo];

  async function guardar() {
    const respuesta = await enviar(configuracion.llamada);
    if (respuesta) alGuardar(configuracion.mensaje);
  }

  return (
    <Modal abierto titulo={configuracion.titulo} alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto={configuracion.texto} peligro={configuracion.peligro} />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <p className="texto-pequeno texto-suave" style={{ margin: 0 }}>
          {configuracion.aviso}
        </p>
        {tipo === 'precio' && (
          <CampoTexto etiqueta="Precio por visita" tipo="number" min="1" step="1000" obligatorio valor={datos.precio} alCambiar={cambiar('precio')} error={error('precio')} />
        )}
        {tipo === 'vigencia' && (
          <CampoTexto etiqueta="Fecha final" tipo="date" obligatorio valor={datos.fecha_fin} alCambiar={cambiar('fecha_fin')} error={error('fecha_fin')} />
        )}
        <CampoArea etiqueta="Motivo" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={2} />
      </div>
    </Modal>
  );
}

/**
 * Tarjeta del programa de mantenimiento del ascensor: frecuencia vigente, próximas visitas,
 * acciones y cambios registrados.
 */
export default function PlanDelEquipo({ equipo, alCambiar }) {
  const { tienePermiso } = useAutenticacion();
  const notificar = useNotificar();
  const resumen = equipo.asignacion_activa;
  const [detalle, setDetalle] = useState(null);
  const [modal, setModal] = useState(null);
  const [visita, setVisita] = useState(null);

  const cargar = useCallback(() => {
    if (!resumen || !tienePermiso('asignaciones.ver')) return;
    asignaciones
      .obtener(resumen.id)
      .then((r) => setDetalle(r.datos))
      .catch(() => setDetalle(null));
  }, [resumen, tienePermiso]);

  useEffect(cargar, [cargar]);

  function alGuardar(mensaje) {
    setModal(null);
    setVisita(null);
    notificar(mensaje);
    alCambiar();
  }

  const puedeEditar = tienePermiso('asignaciones.editar');

  if (!resumen) {
    return (
      <Tarjeta titulo="Programa de mantenimiento">
        <EstadoVacio titulo="Sin mantenimientos programados" descripcion="Indique cada cuánto se visita el ascensor y SIGMA programa las visitas en días hábiles.">
          {tienePermiso('asignaciones.crear') && equipo.estado !== 'inactivo' && (
            <button type="button" className="boton" onClick={() => setModal('asignar')}>
              <Plus size={16} /> Programar mantenimiento
            </button>
          )}
        </EstadoVacio>
        {modal === 'asignar' && <ModalAsignar equipo={equipo} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      </Tarjeta>
    );
  }

  const asignacion = detalle || resumen;
  const proximas = (detalle?.mantenimientos || []).filter((m) => m.estado === 'programado' || m.estado === 'reprogramado').slice(0, 4);
  const diasProximo = diasHasta(asignacion.proximo_mantenimiento);
  const cambios = [
    ...(detalle?.cambios || []).map((c) => ({
      id: `c${c.id}`,
      created_at: c.created_at,
      evento: `cambio_${c.campo}`,
      usuario: c.usuario,
      descripcion: `${c.valor_anterior ?? '—'} → ${c.valor_nuevo ?? '—'}. ${c.motivo}`,
    })),
    ...(detalle?.historial_precios || [])
      .filter((h) => h.precio_anterior)
      .map((h) => ({
        id: `p${h.id}`,
        created_at: h.created_at,
        evento: 'cambio_precio',
        usuario: h.usuario,
        descripcion: `${dinero(h.precio_anterior)} → ${dinero(h.precio_nuevo)}. ${h.motivo}`,
      })),
  ].sort((a, b) => b.created_at.localeCompare(a.created_at));

  return (
    <Tarjeta
      titulo="Programa de mantenimiento"
      acciones={
        puedeEditar && (
          <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setModal('plan')}>
            Cambiar frecuencia
          </button>
        )
      }
    >
      <div className="proximo">
        <CalendarClock size={20} aria-hidden="true" />
        <div>
          <div className="texto-pequeno texto-suave">Próximo mantenimiento</div>
          <strong>{fecha(asignacion.proximo_mantenimiento)}</strong>
          {diasProximo !== null && (
            <Insignia tipo={diasProximo < 0 ? 'peligro' : diasProximo <= 7 ? 'alerta' : 'info'}>
              {diasProximo < 0 ? `Atrasado ${Math.abs(diasProximo)} días` : diasProximo === 0 ? 'Hoy' : `En ${diasProximo} días`}
            </Insignia>
          )}
        </div>
      </div>

      <Datos
        elementos={[
          ...(asignacion.plan ? [['Plan', tienePermiso('planes.ver') ? <Link to={`/planes/${asignacion.plan_id}`}>{asignacion.plan.nombre}</Link> : asignacion.plan.nombre]] : []),
          ['Periodicidad', periodicidad(asignacion.periodicidad?.meses)],
          ['Hora de las visitas', asignacion.hora_preferida],
          ['Precio por visita', `${dinero(asignacion.precio)}${asignacion.aplica_iva ? ` + IVA ${Number(asignacion.porcentaje_iva)} %` : ''}`],
          ['Total con IVA', dinero(asignacion.precio_con_iva)],
          ['Vigencia', `${fecha(asignacion.fecha_inicio)} – ${fecha(asignacion.fecha_fin)}`],
          [
            'Contrato',
            asignacion.contrato ? (
              tienePermiso('contratos.ver') ? <Link to={`/contratos/${asignacion.contrato.id}`}>{asignacion.contrato.numero}</Link> : asignacion.contrato.numero
            ) : (
              'Sin contrato'
            ),
          ],
          ['Último mantenimiento', fecha(asignacion.ultimo_mantenimiento)],
        ]}
      />

      {(puedeEditar || tienePermiso('precios.editar')) && (
        <div className="acciones-linea">
          {tienePermiso('precios.editar') && (
            <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setModal('precio')}>
              Cambiar precio
            </button>
          )}
          {puedeEditar && (
            <>
              <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setModal('vigencia')}>
                Cambiar vigencia
              </button>
              <button type="button" className="boton boton--fantasma boton--pequeno" onClick={() => setModal('finalizar')}>
                Finalizar programa
              </button>
            </>
          )}
        </div>
      )}

      {detalle && (
        <>
          <div className="separador" />
          <p className="etiqueta-superior">Próximas visitas</p>
          {proximas.length ? (
            <ul className="visitas">
              {proximas.map((m) => {
                const estado = estadoMantenimiento(m);
                return (
                  <li key={m.id}>
                    <button type="button" className="visitas__boton" onClick={() => setVisita({ ...m, equipo: { id: equipo.id, codigo: equipo.codigo, edificio: equipo.edificio }, plan: asignacion.plan, periodicidad: asignacion.periodicidad })}>
                      <span className="tabular">{fecha(m.fecha_programada)}</span>
                      <Insignia tipo={estado.tipo}>{estado.texto}</Insignia>
                    </button>
                  </li>
                );
              })}
            </ul>
          ) : (
            <p className="texto-suave texto-pequeno">No hay visitas pendientes dentro de la vigencia.</p>
          )}
          {cambios.length > 0 && (
            <>
              <div className="separador" />
              <p className="etiqueta-superior">Cambios del programa</p>
              <LineaTiempo eventos={cambios} />
            </>
          )}
        </>
      )}

      {modal === 'plan' && <ModalCambiarPlan asignacion={asignacion} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {['precio', 'vigencia', 'finalizar'].includes(modal) && <ModalSimple tipo={modal} asignacion={asignacion} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {visita && <ModalMantenimiento mantenimiento={visita} alCerrar={() => setVisita(null)} alGuardar={alGuardar} />}
    </Tarjeta>
  );
}

import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Ban, MessageSquarePlus, Pencil, RefreshCw } from 'lucide-react';
import { contratos } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Datos, EncabezadoPagina, EstadoVacio, Insignia, Modal, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto } from '../../componentes/Campos';
import LineaTiempo from '../../componentes/LineaTiempo';
import { ESTADOS_CONTRATO, ESTADOS_RENOVACION, dinero, fecha, fechaHora, fechaIso, periodicidad } from '../../utilidades/formato';
import { VencimientoContrato } from './ListaContratos';

function sumarDias(fechaTexto, dias) {
  const dia = new Date(`${fechaTexto}T00:00:00`);
  dia.setDate(dia.getDate() + dias);
  return fechaIso(dia);
}

function sumarAnio(fechaTexto) {
  const dia = new Date(`${fechaTexto}T00:00:00`);
  dia.setFullYear(dia.getFullYear() + 1);
  dia.setDate(dia.getDate() - 1);
  return fechaIso(dia);
}

/** Pie estándar de los modales con formulario. */
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

/** RF-020: seguimiento de la renovación. */
function ModalSeguimiento({ contrato, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ resultado: 'contactado', observacion: '' });

  async function guardar() {
    const respuesta = await enviar(() => contratos.registrarSeguimiento(contrato.id, datos));
    if (respuesta) alGuardar('Seguimiento registrado.');
  }

  return (
    <Modal abierto titulo="Registrar seguimiento de renovación" alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Registrar" />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <CampoSelector
          etiqueta="Resultado"
          obligatorio
          vacio={null}
          valor={datos.resultado}
          alCambiar={cambiar('resultado')}
          error={error('resultado')}
          opciones={[
            { valor: 'contactado', texto: 'Cliente contactado' },
            { valor: 'no_renovado', texto: 'El cliente no renovará' },
          ]}
        />
        <CampoArea etiqueta="Observación" valor={datos.observacion} alCambiar={cambiar('observacion')} error={error('observacion')} rows={3} />
        <span className="campo__ayuda">Para registrar que el cliente sí renovó, use «Renovar contrato».</span>
      </div>
    </Modal>
  );
}

/** RF-020: renovación. Crea un contrato nuevo con los mismos ascensores y extiende sus planes. */
function ModalRenovar({ contrato, alCerrar, alGuardar }) {
  const inicio = sumarDias(contrato.fecha_fin, 1);
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    fecha_inicio: inicio,
    fecha_fin: sumarAnio(inicio),
    valor: String(Number(contrato.valor)),
    condiciones: contrato.condiciones ?? '',
    observacion: '',
  });

  async function guardar() {
    const respuesta = await enviar(() =>
      contratos.renovar(contrato.id, {
        ...datos,
        valor: datos.valor === '' ? null : Number(datos.valor),
        condiciones: datos.condiciones || null,
        observacion: datos.observacion || null,
      }),
    );
    if (respuesta) alGuardar(respuesta.mensaje || 'Contrato renovado.', respuesta.datos.id);
  }

  return (
    <Modal abierto ancho titulo={`Renovar ${contrato.numero}`} alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Renovar contrato" />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <Alerta tipo="info">
          Se crea un contrato nuevo con los {contrato.equipos.length} ascensores de este contrato. Los planes activos se extienden hasta la
          nueva fecha final.
        </Alerta>
        <div className="formulario__fila formulario__fila--3">
          <CampoTexto etiqueta="Fecha inicial" tipo="date" obligatorio valor={datos.fecha_inicio} alCambiar={cambiar('fecha_inicio')} error={error('fecha_inicio')} />
          <CampoTexto etiqueta="Fecha final" tipo="date" obligatorio valor={datos.fecha_fin} alCambiar={cambiar('fecha_fin')} error={error('fecha_fin')} />
          <CampoTexto etiqueta="Valor" tipo="number" min="0" step="1000" valor={datos.valor} alCambiar={cambiar('valor')} error={error('valor')} />
        </div>
        <CampoArea etiqueta="Condiciones" valor={datos.condiciones} alCambiar={cambiar('condiciones')} error={error('condiciones')} rows={3} />
        <CampoArea etiqueta="Observación del seguimiento" valor={datos.observacion} alCambiar={cambiar('observacion')} error={error('observacion')} rows={2} />
      </div>
    </Modal>
  );
}

function ModalCancelar({ contrato, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ motivo: '' });

  async function guardar() {
    const respuesta = await enviar(() => contratos.cancelar(contrato.id, datos.motivo));
    if (respuesta) alGuardar('Contrato cancelado.');
  }

  return (
    <Modal abierto titulo={`Cancelar ${contrato.numero}`} alCerrar={alCerrar} pie={<PieModal alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Cancelar contrato" peligro />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <p style={{ margin: 0 }}>Los mantenimientos pendientes de los planes vinculados se cancelan. Los ejecutados se conservan.</p>
        <CampoArea etiqueta="Motivo" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={3} />
      </div>
    </Modal>
  );
}

export default function DetalleContrato() {
  const { id } = useParams();
  const navegar = useNavigate();
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const [contrato, setContrato] = useState(null);
  const [error, setError] = useState('');
  const [modal, setModal] = useState(null);

  const cargar = useCallback(() => {
    setError('');
    contratos
      .obtener(id)
      .then((r) => setContrato(r.datos))
      .catch((e) => setError(e.message));
  }, [id]);

  useEffect(cargar, [cargar]);

  function alGuardar(mensaje, nuevoId) {
    setModal(null);
    notificar(mensaje);
    if (nuevoId) navegar(`/contratos/${nuevoId}`);
    else cargar();
  }

  if (error) return <Alerta>{error}</Alerta>;
  if (!contrato) return <Cargando />;

  const estado = ESTADOS_CONTRATO[contrato.estado];
  const vigente = contrato.estado === 'vigente';
  const renovable = vigente || contrato.estado === 'vencido';
  const asignacionPorEquipo = Object.fromEntries(
    (contrato.asignaciones || []).filter((a) => a.estado === 'activa').map((a) => [a.equipo_id, a]),
  );
  const seguimientos = (contrato.seguimientos || []).map((s) => ({
    id: s.id,
    created_at: s.created_at,
    evento: s.resultado,
    usuario: s.usuario,
    descripcion: s.observacion,
  }));

  return (
    <>
      <EncabezadoPagina
        titulo={`Contrato ${contrato.numero}`}
        migas={<Link to="/contratos">Contratos</Link>}
        descripcion={
          <>
            {contrato.cliente?.nombre} · <Insignia tipo={estado?.tipo}>{estado?.texto}</Insignia> <VencimientoContrato contrato={contrato} />
          </>
        }
      >
        {vigente && tienePermiso('contratos.editar') && (
          <button type="button" className="boton boton--secundario" onClick={() => setModal('cancelar')}>
            <Ban size={16} /> Cancelar
          </button>
        )}
        {renovable && tienePermiso('contratos.renovacion') && (
          <>
            <button type="button" className="boton boton--secundario" onClick={() => setModal('seguimiento')}>
              <MessageSquarePlus size={16} /> Seguimiento
            </button>
            <button type="button" className="boton boton--secundario" onClick={() => setModal('renovar')}>
              <RefreshCw size={16} /> Renovar
            </button>
          </>
        )}
        {vigente && tienePermiso('contratos.editar') && (
          <Link to={`/contratos/${id}/editar`} className="boton">
            <Pencil size={16} /> Editar
          </Link>
        )}
      </EncabezadoPagina>

      <div className="rejilla rejilla--2" style={{ alignItems: 'start' }}>
        <Tarjeta titulo="Datos del contrato">
          <Datos
            elementos={[
              ['Vigencia', `${fecha(contrato.fecha_inicio)} – ${fecha(contrato.fecha_fin)}`],
              ['Valor', dinero(contrato.valor)],
              ['Renovación', ESTADOS_RENOVACION[contrato.estado_renovacion]],
              [
                'Contrato anterior',
                contrato.contrato_anterior ? <Link to={`/contratos/${contrato.contrato_anterior.id}`}>{contrato.contrato_anterior.numero}</Link> : null,
              ],
              ['Registrado', fechaHora(contrato.created_at)],
            ]}
          />
          {contrato.servicios && (
            <>
              <div className="separador" />
              <div className="texto-pequeno texto-suave">Servicios</div>
              <p style={{ margin: '4px 0 0', whiteSpace: 'pre-line' }}>{contrato.servicios}</p>
            </>
          )}
          {contrato.condiciones && (
            <>
              <div className="separador" />
              <div className="texto-pequeno texto-suave">Condiciones</div>
              <p style={{ margin: '4px 0 0', whiteSpace: 'pre-line' }}>{contrato.condiciones}</p>
            </>
          )}
        </Tarjeta>

        <Tarjeta titulo="Seguimiento de la renovación">
          {seguimientos.length ? <LineaTiempo eventos={seguimientos} /> : <EstadoVacio titulo="Sin seguimientos registrados" />}
        </Tarjeta>
      </div>

      <div style={{ marginTop: 16 }}>
        <Tarjeta sinRelleno>
          <div className="tarjeta__cuerpo" style={{ paddingBottom: 0 }}>
            <h2 className="tarjeta__titulo">Ascensores incluidos ({contrato.equipos.length})</h2>
          </div>
          <Tabla
            filas={contrato.equipos}
            alSeleccionar={(equipo) => navegar(`/equipos/${equipo.id}`)}
            columnas={[
              { clave: 'codigo', titulo: 'Código', render: (e) => <strong>{e.codigo}</strong> },
              { clave: 'edificio', titulo: 'Edificio', render: (e) => e.edificio?.nombre },
              {
                clave: 'plan',
                titulo: 'Plan',
                render: (e) => {
                  const asignacion = asignacionPorEquipo[e.id];
                  return asignacion ? (
                    <>
                      {asignacion.plan?.nombre}
                      <div className="tabla__secundario">{periodicidad(asignacion.periodicidad?.meses)}</div>
                    </>
                  ) : (
                    <span className="texto-suave">Sin plan asignado</span>
                  );
                },
              },
              {
                clave: 'proximo',
                titulo: 'Próximo mantenimiento',
                render: (e) => fecha(asignacionPorEquipo[e.id]?.proximo_mantenimiento),
              },
            ]}
            vacio={<EstadoVacio titulo="El contrato no tiene ascensores" />}
          />
        </Tarjeta>
      </div>

      {modal === 'seguimiento' && <ModalSeguimiento contrato={contrato} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {modal === 'renovar' && <ModalRenovar contrato={contrato} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {modal === 'cancelar' && <ModalCancelar contrato={contrato} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
    </>
  );
}

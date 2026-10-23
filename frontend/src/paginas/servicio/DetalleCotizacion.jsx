import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { Check, ClipboardList, X } from 'lucide-react';
import { servicio } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Datos, EncabezadoPagina, Insignia, Modal, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoArea } from '../../componentes/Campos';
import ModalOrdenDerivada from '../../componentes/ModalOrdenDerivada';
import { dinero, fecha, fechaHora, numero } from '../../utilidades/formato';
import { ESTADOS_COTIZACION } from '../../utilidades/servicio';

/** Detalle de la cotización. El cliente la aprueba o la rechaza con motivo, una sola vez. */
export default function DetalleCotizacion() {
  const { id } = useParams();
  const { tienePermiso } = useAutenticacion();
  const [cotizacion, setCotizacion] = useState(null);
  const [error, setError] = useState('');
  const [decision, setDecision] = useState(null);
  const [creandoOrden, setCreandoOrden] = useState(false);

  useEffect(() => {
    servicio
      .cotizacion(id)
      .then((r) => setCotizacion(r.datos))
      .catch((e) => setError(e.message));
  }, [id]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!cotizacion) return <Cargando />;

  const estado = ESTADOS_COTIZACION[cotizacion.estado];
  const puedeResponder = tienePermiso('cotizaciones.responder') && cotizacion.estado === 'enviada';
  const puedeCrearOrden = tienePermiso('ordenes.crear') && cotizacion.estado === 'aprobada' && !cotizacion.orden;

  return (
    <>
      <EncabezadoPagina titulo={`${cotizacion.numero} · ${cotizacion.titulo}`} descripcion={cotizacion.descripcion} migas={<Link to="/cotizaciones">Cotizaciones</Link>}>
        {puedeResponder && (
          <>
            <button type="button" className="boton boton--secundario" onClick={() => setDecision('rechazada')}>
              <X size={16} aria-hidden="true" /> Rechazar
            </button>
            <button type="button" className="boton" onClick={() => setDecision('aprobada')}>
              <Check size={16} aria-hidden="true" /> Aprobar cotización
            </button>
          </>
        )}
        {puedeCrearOrden && (
          <button type="button" className="boton" onClick={() => setCreandoOrden(true)}>
            <ClipboardList size={16} aria-hidden="true" /> Crear orden de trabajo
          </button>
        )}
      </EncabezadoPagina>

      {creandoOrden && (
        <ModalOrdenDerivada
          titulo={`Orden de trabajo para ${cotizacion.numero}`}
          ayuda="Mantenimiento correctivo con los renglones aprobados. Si no elige fecha, queda para el siguiente día hábil a las 08:00."
          crear={(datos) => servicio.ordenDesdeCotizacion(cotizacion.id, datos)}
          alCerrar={() => setCreandoOrden(false)}
        />
      )}

      {cotizacion.estado === 'vencida' && <Alerta tipo="alerta">La cotización venció el {fecha(cotizacion.vigente_hasta)}. Pida una nueva a su coordinador si aún necesita el trabajo.</Alerta>}

      <div className="rejilla rejilla--2" style={{ marginBottom: 16 }}>
        <Tarjeta titulo="Resumen">
          <Datos
            elementos={[
              ['Estado', <Insignia key="e" tipo={estado?.tipo}>{estado?.texto}</Insignia>],
              ['Total', <strong key="t">{dinero(cotizacion.total)}</strong>],
              ['Emitida', fecha(cotizacion.fecha_emision)],
              ['Vigente hasta', fecha(cotizacion.vigente_hasta)],
              ['Respondida', cotizacion.respondida_en ? `${fechaHora(cotizacion.respondida_en)}${cotizacion.respondida_por ? ` · ${cotizacion.respondida_por}` : ''}` : '—'],
              ['Motivo del rechazo', cotizacion.motivo_rechazo || '—'],
              ['Orden de trabajo', cotizacion.orden ? <Link key="o" to={`/ordenes/${cotizacion.orden.id}`}>{cotizacion.orden.numero}</Link> : '—'],
            ]}
          />
        </Tarjeta>
        <Tarjeta titulo="Ascensor">
          <Datos
            columnas={1}
            elementos={[
              ['Ascensor', <Link key="a" to={`/equipos/${cotizacion.equipo?.id}`}>{cotizacion.equipo?.codigo}</Link>],
              ['Ubicación', cotizacion.equipo?.ubicacion || cotizacion.equipo?.edificio?.nombre],
              ['Cliente', cotizacion.cliente?.nombre],
            ]}
          />
        </Tarjeta>
      </div>

      <Tarjeta titulo="Detalle" sinRelleno>
        <Tabla
          filas={cotizacion.items}
          claveFila="descripcion"
          columnas={[
            { clave: 'descripcion', titulo: 'Descripción' },
            { clave: 'cantidad', titulo: 'Cantidad', clase: 'numero', render: (i) => numero(i.cantidad) },
            { clave: 'valor_unitario', titulo: 'Valor unitario', clase: 'numero', render: (i) => dinero(i.valor_unitario) },
            { clave: 'valor_total', titulo: 'Valor', clase: 'numero', render: (i) => dinero(i.valor_total) },
          ]}
        />
        <dl className="totales" style={{ padding: '12px 20px 18px' }}>
          <div>
            <dt>Subtotal</dt>
            <dd>{dinero(cotizacion.subtotal)}</dd>
          </div>
          <div>
            <dt>IVA</dt>
            <dd>{dinero(cotizacion.iva)}</dd>
          </div>
          <div className="totales__total">
            <dt>Total</dt>
            <dd>{dinero(cotizacion.total)}</dd>
          </div>
        </dl>
      </Tarjeta>

      {decision && (
        <ModalRespuesta
          cotizacion={cotizacion}
          decision={decision}
          alCerrar={(nueva) => {
            setDecision(null);
            if (nueva) setCotizacion(nueva);
          }}
        />
      )}
    </>
  );
}

function ModalRespuesta({ cotizacion, decision, alCerrar }) {
  const notificar = useNotificar();
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ motivo: '' });
  const aprueba = decision === 'aprobada';

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() => servicio.responderCotizacion(cotizacion.id, { decision, motivo: datos.motivo || null }));
    if (respuesta) {
      notificar(respuesta.mensaje);
      alCerrar(respuesta.datos);
    }
  }

  return (
    <Modal
      titulo={aprueba ? 'Aprobar cotización' : 'Rechazar cotización'}
      abierto
      alCerrar={() => alCerrar(null)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(null)}>
            Volver
          </button>
          <button type="submit" form="formulario-respuesta" className={aprueba ? 'boton' : 'boton boton--peligro'} disabled={enviando}>
            {enviando ? 'Enviando…' : aprueba ? `Aprobar por ${dinero(cotizacion.total)}` : 'Rechazar cotización'}
          </button>
        </>
      }
    >
      <form id="formulario-respuesta" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        {aprueba ? (
          <p style={{ margin: 0 }}>
            Al aprobar, autoriza el trabajo «{cotizacion.titulo}» por <b>{dinero(cotizacion.total)}</b> (IVA incluido). Le avisaremos cuando se programe la visita.
          </p>
        ) : (
          <CampoArea etiqueta="Motivo del rechazo" obligatorio rows={3} valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} placeholder="Precio, alcance, otro proveedor…" />
        )}
      </form>
    </Modal>
  );
}

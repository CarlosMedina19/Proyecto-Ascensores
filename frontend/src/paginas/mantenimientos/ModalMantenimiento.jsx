import { useState } from 'react';
import { Link } from 'react-router-dom';
import { mantenimientos } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Datos, Insignia, Modal } from '../../componentes/Basicos';
import { CampoArea, CampoTexto } from '../../componentes/Campos';
import { dinero, estadoMantenimiento, fecha, fechaIso, periodicidad } from '../../utilidades/formato';

const TITULOS = {
  reprogramar: 'Reprogramar mantenimiento',
  ejecutar: 'Registrar ejecución',
  cancelar: 'Cancelar mantenimiento',
};

/**
 * Detalle de un mantenimiento programado con sus acciones (RF-018):
 * reprogramar (fecha hábil y motivo), registrar la ejecución o cancelar con motivo.
 */
export default function ModalMantenimiento({ mantenimiento, alCerrar, alGuardar }) {
  const { tienePermiso } = useAutenticacion();
  const [accion, setAccion] = useState(null);
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    fecha: '',
    hora: '',
    motivo: '',
    fecha_ejecucion: fechaIso(),
    observacion: '',
  });

  const estado = estadoMantenimiento(mantenimiento);
  const pendiente = mantenimiento.estado === 'programado' || mantenimiento.estado === 'reprogramado';
  // Fase 3: una visita con orden de trabajo en curso se gestiona desde la orden.
  const ordenActiva = mantenimiento.orden_activa;
  const puedeGestionar = pendiente && !ordenActiva && tienePermiso('mantenimientos.gestionar');
  const puedeCrearOrden = pendiente && !ordenActiva && tienePermiso('ordenes.crear');

  function elegir(nueva) {
    setAccion(nueva);
    setDatos((actual) => ({ ...actual, fecha: mantenimiento.fecha_programada, hora: mantenimiento.hora || '08:00', motivo: '' }));
  }

  async function confirmar() {
    const llamadas = {
      reprogramar: () => mantenimientos.reprogramar(mantenimiento.id, { fecha: datos.fecha, hora: datos.hora || null, motivo: datos.motivo }),
      ejecutar: () => mantenimientos.ejecutar(mantenimiento.id, { fecha_ejecucion: datos.fecha_ejecucion, observacion: datos.observacion || null }),
      cancelar: () => mantenimientos.cancelar(mantenimiento.id, datos.motivo),
    };
    const respuesta = await enviar(llamadas[accion]);
    if (respuesta) {
      const mensajes = { reprogramar: 'Mantenimiento reprogramado.', ejecutar: 'Ejecución registrada.', cancelar: 'Mantenimiento cancelado.' };
      alGuardar(mensajes[accion]);
    }
  }

  const pie = accion ? (
    <>
      <button type="button" className="boton boton--secundario" onClick={() => setAccion(null)}>
        Volver
      </button>
      <button type="button" className={`boton${accion === 'cancelar' ? ' boton--peligro' : ''}`} onClick={confirmar} disabled={enviando}>
        {enviando ? 'Guardando…' : TITULOS[accion]}
      </button>
    </>
  ) : puedeGestionar || puedeCrearOrden ? (
    <>
      {puedeGestionar && (
        <>
          <button type="button" className="boton boton--secundario" onClick={() => elegir('cancelar')}>
            Cancelar visita
          </button>
          <button type="button" className="boton boton--secundario" onClick={() => elegir('reprogramar')}>
            Reprogramar
          </button>
          <button type="button" className={`boton${puedeCrearOrden ? ' boton--secundario' : ''}`} onClick={() => elegir('ejecutar')}>
            Registrar ejecución
          </button>
        </>
      )}
      {puedeCrearOrden && (
        <Link to={`/ordenes/nueva?mantenimiento_id=${mantenimiento.id}`} className="boton">
          Crear orden de trabajo
        </Link>
      )}
    </>
  ) : (
    <button type="button" className="boton boton--secundario" onClick={alCerrar}>
      Cerrar
    </button>
  );

  return (
    <Modal abierto titulo={accion ? TITULOS[accion] : `Mantenimiento · ${mantenimiento.equipo?.codigo ?? ''}`} alCerrar={alCerrar} pie={pie}>
      {!accion && ordenActiva && (
        <Alerta tipo="info">
          Esta visita tiene la orden <Link to={`/ordenes/${ordenActiva.id}`}>{ordenActiva.numero}</Link>. Gestiónela desde la orden.
        </Alerta>
      )}
      {!accion && (
        <>
          <p style={{ marginTop: 0 }}>
            <Insignia tipo={estado.tipo}>{estado.texto}</Insignia>
          </p>
          <Datos
            elementos={[
              ['Ascensor', mantenimiento.equipo ? <Link to={`/equipos/${mantenimiento.equipo.id}`}>{mantenimiento.equipo.codigo}</Link> : null],
              ['Edificio', mantenimiento.equipo?.edificio?.nombre],
              ['Cliente', mantenimiento.equipo?.edificio?.cliente?.nombre],
              ['Plan', mantenimiento.plan?.nombre],
              ['Periodicidad', periodicidad(mantenimiento.periodicidad?.meses)],
              ['Precio de la visita', dinero(mantenimiento.precio)],
              ['Fecha programada', `${fecha(mantenimiento.fecha_programada)}${mantenimiento.hora ? ` · ${mantenimiento.hora}` : ''}`],
              [
                'Fecha según el plan',
                mantenimiento.fecha_teorica !== mantenimiento.fecha_programada ? fecha(mantenimiento.fecha_teorica) : 'La misma',
              ],
              ['Ejecutado el', mantenimiento.fecha_ejecucion ? fecha(mantenimiento.fecha_ejecucion) : null],
            ]}
          />
          {(mantenimiento.motivo || mantenimiento.observacion) && (
            <>
              <div className="separador" />
              {mantenimiento.motivo && <p className="texto-pequeno" style={{ margin: 0 }}>Motivo: {mantenimiento.motivo}</p>}
              {mantenimiento.observacion && <p className="texto-pequeno" style={{ margin: '4px 0 0' }}>Observación: {mantenimiento.observacion}</p>}
            </>
          )}
        </>
      )}

      {accion && (
        <div className="formulario">
          <Alerta>{mensajeError}</Alerta>
          {accion === 'reprogramar' && (
            <>
              <CampoTexto etiqueta="Nueva fecha" tipo="date" obligatorio min={fechaIso()} valor={datos.fecha} alCambiar={cambiar('fecha')} error={error('fecha')} ayuda="Debe ser un día hábil (no domingo ni festivo)." />
              <CampoTexto etiqueta="Hora" tipo="time" min="06:00" max="18:00" step="900" valor={datos.hora} alCambiar={cambiar('hora')} error={error('hora')} ayuda="Jornada de visitas: de 6:00 a 18:00." />
              <CampoArea etiqueta="Motivo" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={3} />
            </>
          )}
          {accion === 'ejecutar' && (
            <>
              <CampoTexto etiqueta="Fecha de ejecución" tipo="date" obligatorio max={fechaIso()} valor={datos.fecha_ejecucion} alCambiar={cambiar('fecha_ejecucion')} error={error('fecha_ejecucion')} ayuda="Las siguientes visitas se recalculan desde esta fecha." />
              <CampoArea etiqueta="Observación" valor={datos.observacion} alCambiar={cambiar('observacion')} error={error('observacion')} rows={3} />
            </>
          )}
          {accion === 'cancelar' && (
            <CampoArea etiqueta="Motivo de la cancelación" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={3} />
          )}
        </div>
      )}
    </Modal>
  );
}

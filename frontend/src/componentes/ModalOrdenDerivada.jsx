import { useNavigate } from 'react-router-dom';
import { useNotificar } from '../contexto/NotificacionesContexto';
import { useFormulario } from '../hooks/useFormulario';
import { fechaIso, PRIORIDADES_ORDEN } from '../utilidades/formato';
import { Alerta, Modal } from './Basicos';
import { CampoArea, CampoSelector, CampoTexto } from './Campos';

/**
 * Orden correctiva que nace de una emergencia o de una cotización aprobada. Los campos vacíos los
 * completa el servidor (fecha, hora, prioridad y descripción con el origen); los técnicos se asignan
 * después en la orden, donde se ve su disponibilidad.
 */
export default function ModalOrdenDerivada({ titulo, ayuda, crear, alCerrar }) {
  const notificar = useNotificar();
  const navegar = useNavigate();
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ fecha: '', hora: '', prioridad: '', descripcion: '' });

  async function alEnviar(evento) {
    evento.preventDefault();
    const limpio = Object.fromEntries(Object.entries(datos).filter(([, v]) => v !== ''));
    const respuesta = await enviar(() => crear(limpio));
    if (respuesta) {
      notificar(respuesta.mensaje || `Orden ${respuesta.datos.numero} creada.`);
      alCerrar(respuesta.datos);
      navegar(`/ordenes/${respuesta.datos.id}`);
    }
  }

  return (
    <Modal
      titulo={titulo}
      abierto
      alCerrar={() => alCerrar(null)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(null)}>
            Cancelar
          </button>
          <button type="submit" form="formulario-orden-derivada" className="boton" disabled={enviando}>
            {enviando ? 'Creando…' : 'Crear orden'}
          </button>
        </>
      }
    >
      <form id="formulario-orden-derivada" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <p className="texto-suave texto-pequeno" style={{ margin: 0 }}>
          {ayuda}
        </p>
        <div className="formulario__fila">
          <CampoTexto etiqueta="Fecha" tipo="date" min={fechaIso()} valor={datos.fecha} alCambiar={cambiar('fecha')} error={error('fecha')} />
          <CampoTexto etiqueta="Hora" tipo="time" valor={datos.hora} alCambiar={cambiar('hora')} error={error('hora')} />
        </div>
        <CampoSelector
          etiqueta="Prioridad"
          vacio="Automática"
          valor={datos.prioridad}
          alCambiar={cambiar('prioridad')}
          error={error('prioridad')}
          opciones={Object.entries(PRIORIDADES_ORDEN).map(([valor, p]) => ({ valor, texto: p.texto }))}
        />
        <CampoArea etiqueta="Descripción" rows={3} valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} placeholder="Si la deja vacía, se toma del origen." />
      </form>
    </Modal>
  );
}

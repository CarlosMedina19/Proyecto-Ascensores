import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Pencil, Power, Trash2 } from 'lucide-react';
import { useNotificar } from '../contexto/NotificacionesContexto';
import { Confirmacion, Insignia } from './Basicos';

/**
 * Columna Estado de las tablas: insignia de estado, editar, activar/desactivar y eliminar.
 * Activar/desactivar y eliminar piden confirmación. Los clics no abren la fila.
 */
export default function AccionesRegistro({
  nombre,
  activo,
  insignia,
  rutaEditar,
  alAlternar,
  alEliminar,
  mensajeDesactivar,
  mensajeEliminar,
  alCambiar,
}) {
  const notificar = useNotificar();
  const [confirmar, setConfirmar] = useState(null);
  const [procesando, setProcesando] = useState(false);

  async function ejecutar() {
    setProcesando(true);
    try {
      const respuesta = await (confirmar === 'eliminar' ? alEliminar() : alAlternar());
      notificar(respuesta?.mensaje || (confirmar === 'eliminar' ? `${nombre} eliminado.` : 'Estado actualizado.'));
      setConfirmar(null);
      alCambiar?.();
    } catch (e) {
      notificar(e.message, 'error');
      setConfirmar(null);
    } finally {
      setProcesando(false);
    }
  }

  return (
    // eslint-disable-next-line jsx-a11y/no-static-element-interactions, jsx-a11y/click-events-have-key-events
    <div className="acciones-registro" onClick={(e) => e.stopPropagation()}>
      {insignia ?? <Insignia tipo={activo ? 'exito' : 'neutro'}>{activo ? 'Activo' : 'Inactivo'}</Insignia>}
      <span className="acciones-registro__botones">
        {rutaEditar && (
          <Link to={rutaEditar} className="boton boton--fantasma" title="Editar" aria-label={`Editar ${nombre}`}>
            <Pencil size={16} />
          </Link>
        )}
        {alAlternar && (
          <button
            type="button"
            className="boton boton--fantasma"
            title={activo ? 'Desactivar' : 'Activar'}
            aria-label={`${activo ? 'Desactivar' : 'Activar'} ${nombre}`}
            onClick={() => setConfirmar('alternar')}
          >
            <Power size={16} />
          </button>
        )}
        {alEliminar && (
          <button type="button" className="boton boton--fantasma acciones-registro__eliminar" title="Eliminar" aria-label={`Eliminar ${nombre}`} onClick={() => setConfirmar('eliminar')}>
            <Trash2 size={16} />
          </button>
        )}
      </span>
      <Confirmacion
        abierto={confirmar === 'alternar'}
        titulo={activo ? `Desactivar ${nombre}` : `Activar ${nombre}`}
        mensaje={activo ? mensajeDesactivar || 'Dejará de estar disponible para registros nuevos. Su información e historial se conservan.' : 'Volverá a estar disponible.'}
        textoConfirmar={activo ? 'Desactivar' : 'Activar'}
        peligro={activo}
        procesando={procesando}
        alConfirmar={ejecutar}
        alCerrar={() => setConfirmar(null)}
      />
      <Confirmacion
        abierto={confirmar === 'eliminar'}
        titulo={`Eliminar ${nombre}`}
        mensaje={mensajeEliminar || 'Esta acción no se puede deshacer.'}
        textoConfirmar="Eliminar"
        peligro
        procesando={procesando}
        alConfirmar={ejecutar}
        alCerrar={() => setConfirmar(null)}
      />
    </div>
  );
}

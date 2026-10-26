import { createContext, useCallback, useContext, useRef, useState } from 'react';
import { CheckCircle2, CircleAlert, Info, LoaderCircle, TriangleAlert } from 'lucide-react';

const NotificacionesContexto = createContext(() => {});

const ICONOS = { exito: CheckCircle2, error: CircleAlert, info: Info, alerta: TriangleAlert, cargando: LoaderCircle };

/**
 * notificar(mensaje, tipo, { id, duracion }):
 * - tipo: 'exito' (por defecto), 'error', 'info', 'alerta' o 'cargando'.
 * - id: con el mismo id la notificación se reemplaza (por ejemplo, de «Revisando…» al resultado).
 * - duracion: milisegundos en pantalla; las de tipo 'cargando' se quedan hasta que se reemplacen.
 */
export function ProveedorNotificaciones({ children }) {
  const [lista, setLista] = useState([]);
  const relojes = useRef({});

  const notificar = useCallback((mensaje, tipo = 'exito', opciones = {}) => {
    const id = opciones.id ?? Date.now() + Math.random();
    const duracion = opciones.duracion ?? (tipo === 'cargando' ? null : 4500);
    clearTimeout(relojes.current[id]);
    setLista((actual) => [...actual.filter((n) => n.id !== id), { id, mensaje, tipo }]);
    if (duracion) {
      relojes.current[id] = setTimeout(() => {
        setLista((actual) => actual.filter((n) => n.id !== id));
        delete relojes.current[id];
      }, duracion);
    }
    return id;
  }, []);

  return (
    <NotificacionesContexto.Provider value={notificar}>
      {children}
      <div className="notificaciones" role="status" aria-live="polite">
        {lista.map(({ id, mensaje, tipo }) => {
          const Icono = ICONOS[tipo] || Info;
          return (
            <div key={id} className={`notificacion notificacion--${tipo}`}>
              <Icono size={18} aria-hidden="true" />
              <span>{mensaje}</span>
            </div>
          );
        })}
      </div>
    </NotificacionesContexto.Provider>
  );
}

export function useNotificar() {
  return useContext(NotificacionesContexto);
}

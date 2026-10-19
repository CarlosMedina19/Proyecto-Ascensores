import { createContext, useCallback, useContext, useState } from 'react';
import { CheckCircle2, CircleAlert, Info } from 'lucide-react';

const NotificacionesContexto = createContext(() => {});

const ICONOS = { exito: CheckCircle2, error: CircleAlert, info: Info };

export function ProveedorNotificaciones({ children }) {
  const [lista, setLista] = useState([]);

  const notificar = useCallback((mensaje, tipo = 'exito') => {
    const id = Date.now() + Math.random();
    setLista((actual) => [...actual, { id, mensaje, tipo }]);
    setTimeout(() => setLista((actual) => actual.filter((n) => n.id !== id)), 4500);
  }, []);

  return (
    <NotificacionesContexto.Provider value={notificar}>
      {children}
      <div className="notificaciones" role="status" aria-live="polite">
        {lista.map(({ id, mensaje, tipo }) => {
          const Icono = ICONOS[tipo] || Info;
          return (
            <div key={id} className={`notificacion notificacion--${tipo}`}>
              <Icono size={18} />
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

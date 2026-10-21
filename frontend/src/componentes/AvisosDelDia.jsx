import { Link } from 'react-router-dom';
import { ChevronRight } from 'lucide-react';

/**
 * Avisos del día al lado del saludo, con aspecto de notificación: ícono, texto corto y enlace.
 * Cada aviso: { icono, texto, detalle?, tono: 'info' | 'alerta' | 'peligro' | 'exito', ruta? }.
 */
export default function AvisosDelDia({ avisos }) {
  const visibles = avisos.filter(Boolean);
  if (!visibles.length) return null;
  return (
    <ul className="avisos-dia" aria-label="Avisos de hoy">
      {visibles.map(({ icono: Icono, texto, detalle, tono = 'info', ruta }) => {
        const contenido = (
          <>
            <span className={`avisos-dia__icono avisos-dia__icono--${tono}`} aria-hidden="true">
              <Icono size={16} />
            </span>
            <span className="avisos-dia__texto">
              <b>{texto}</b>
              {detalle && <small>{detalle}</small>}
            </span>
            {ruta && <ChevronRight size={16} className="avisos-dia__flecha" aria-hidden="true" />}
          </>
        );
        return (
          <li key={texto}>
            {ruta ? (
              <Link to={ruta} className="avisos-dia__item">
                {contenido}
              </Link>
            ) : (
              <div className="avisos-dia__item">{contenido}</div>
            )}
          </li>
        );
      })}
    </ul>
  );
}

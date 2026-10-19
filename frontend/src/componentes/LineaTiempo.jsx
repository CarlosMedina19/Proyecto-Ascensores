import { Link } from 'react-router-dom';
import { fechaCorta, horaCorta, humanizar, tipoEvento } from '../utilidades/formato';

/** Línea de tiempo del historial de equipos (RF-009). */
export default function LineaTiempo({ eventos, conEquipo = false }) {
  return (
    <ul className="linea-tiempo">
      {eventos.map((evento) => (
        <li key={evento.id} className="linea-tiempo__item">
          <time className="linea-tiempo__fecha" dateTime={evento.created_at}>
            {fechaCorta(evento.created_at)}
            <br />
            {horaCorta(evento.created_at)}
          </time>
          <i className={`linea-tiempo__punto linea-tiempo__punto--${tipoEvento(evento.evento)}`} />
          <span>
            <span className="linea-tiempo__titulo">{evento.titulo ?? humanizar(evento.evento)}</span>
            {conEquipo && (
              <>
                {' · '}
                <Link to={`/equipos/${evento.equipo_id}`}>{evento.equipo_codigo}</Link>
              </>
            )}
            <span className="linea-tiempo__meta">{evento.usuario?.nombre || 'Sistema'}</span>
            {evento.descripcion && <span className="linea-tiempo__texto">{evento.descripcion}</span>}
          </span>
        </li>
      ))}
    </ul>
  );
}


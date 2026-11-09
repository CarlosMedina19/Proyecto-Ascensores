// Contenido que se despliega dentro de las filas de edificios y ascensores, para llegar a un
// ascensor sin saltar de sección.
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpDown, Plus } from 'lucide-react';
import { edificios } from '../api/servicios';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { ESTADOS_EQUIPO, numero } from '../utilidades/formato';
import { Alerta, Cargando, Insignia } from './Basicos';

/** Ascensores de un edificio como enlaces directos. */
function ListaAscensores({ equipos, edificioId }) {
  const { tienePermiso } = useAutenticacion();
  return (
    <ul className="despliegue__ascensores">
      {equipos.map((equipo) => (
        <li key={equipo.id}>
          <Link to={`/equipos/${equipo.id}`} className="despliegue__ascensor">
            <ArrowUpDown size={14} aria-hidden="true" />
            <b>{equipo.codigo}</b>
            <span className="texto-suave">{[equipo.marca, equipo.modelo].filter(Boolean).join(' · ')}</span>
            <Insignia tipo={ESTADOS_EQUIPO[equipo.estado]?.tipo}>{ESTADOS_EQUIPO[equipo.estado]?.texto}</Insignia>
          </Link>
        </li>
      ))}
      {!equipos.length && <li className="texto-suave texto-pequeno">Sin ascensores registrados.</li>}
      {tienePermiso('equipos.crear') && (
        <li>
          <Link to={`/equipos/nuevo?edificio_id=${edificioId}`} className="despliegue__agregar">
            <Plus size={14} aria-hidden="true" /> Agregar ascensor
          </Link>
        </li>
      )}
    </ul>
  );
}

/** Ascensores de un edificio (se cargan al desplegar la fila). */
export function AscensoresDelEdificio({ edificioId }) {
  const [equipos, setEquipos] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    edificios
      .obtener(edificioId)
      .then((r) => setEquipos(r.datos.equipos || []))
      .catch((e) => setError(e.message));
  }, [edificioId]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!equipos) return <Cargando texto="Cargando ascensores…" />;
  return (
    <div className="despliegue">
      <ListaAscensores equipos={equipos} edificioId={edificioId} />
    </div>
  );
}

/** Resumen de la ficha del ascensor con accesos directos. */
export function ResumenAscensor({ equipo }) {
  const ficha = equipo.ascensor || {};
  const datos = [
    ['Tipo', ficha.tipo_ascensor_texto || '—'],
    ['Capacidad', numero(ficha.capacidad_kg, ' kg')],
    ['Velocidad', ficha.velocidad_mpm ? `${ficha.velocidad_mpm} m/min` : '—'],
    ['Paradas', ficha.paradas ?? '—'],
    ['Accionamiento', ficha.tipo_traccion || '—'],
    ['Serie', equipo.numero_serie || '—'],
    ['Cliente', equipo.edificio?.cliente?.nombre || '—'],
  ];
  return (
    <div className="despliegue despliegue--ascensor">
      <dl className="despliegue__ficha">
        {datos.map(([etiqueta, valor]) => (
          <div key={etiqueta}>
            <dt>{etiqueta}</dt>
            <dd>{valor}</dd>
          </div>
        ))}
      </dl>
      <div className="despliegue__acciones">
        <Link to={`/equipos/${equipo.id}`} className="boton boton--secundario boton--pequeno">
          Ver ficha e historial
        </Link>
        {equipo.edificio_id && (
          <Link to={`/edificios/${equipo.edificio_id}`} className="boton boton--secundario boton--pequeno">
            Ver edificio
          </Link>
        )}
      </div>
    </div>
  );
}

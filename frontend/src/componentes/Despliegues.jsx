// Contenido que se despliega dentro de las filas de clientes, edificios y ascensores, para llegar a
// un conjunto, edificio o ascensor sin saltar de sección.
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpDown, Building2, ChevronRight, Plus } from 'lucide-react';
import { clientes, edificios } from '../api/servicios';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { ESTADOS_EQUIPO, TIPOS_ASCENSOR, TIPOS_EDIFICIO, numero } from '../utilidades/formato';
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

/** Edificio que se abre para mostrar sus ascensores. */
function EdificioDesplegable({ edificio }) {
  const [abierto, setAbierto] = useState(false);
  return (
    <li className="despliegue__edificio">
      <div className="despliegue__cabecera">
        <button type="button" className="boton-desplegar" aria-expanded={abierto} aria-label={`Desplegar ${edificio.nombre}`} onClick={() => setAbierto((v) => !v)}>
          <ChevronRight size={16} aria-hidden="true" />
        </button>
        <Building2 size={15} aria-hidden="true" className="texto-suave" />
        <Link to={`/edificios/${edificio.id}`} className="despliegue__nombre">
          {edificio.nombre}
        </Link>
        <span className="texto-suave texto-pequeno">{[edificio.direccion, edificio.ciudad].filter(Boolean).join(', ')}</span>
        <span className="despliegue__conteo tabular">
          {edificio.equipos.length} {edificio.equipos.length === 1 ? 'ascensor' : 'ascensores'}
        </span>
        {!edificio.activo && <Insignia>Inactivo</Insignia>}
      </div>
      {abierto && <ListaAscensores equipos={edificio.equipos} edificioId={edificio.id} />}
    </li>
  );
}

/**
 * Árbol del cliente: los tipos que tiene (conjunto, institucional, residencial), sus edificios y
 * los ascensores de cada edificio.
 */
export function ArbolCliente({ clienteId }) {
  const { tienePermiso } = useAutenticacion();
  const [grupos, setGrupos] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    clientes
      .estructura(clienteId)
      .then((r) => setGrupos(r.datos))
      .catch((e) => setError(e.message));
  }, [clienteId]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!grupos) return <Cargando texto="Cargando edificios…" />;

  return (
    <div className="despliegue">
      {grupos.map((grupo) => (
        <section key={grupo.tipo} className="despliegue__grupo" aria-label={TIPOS_EDIFICIO[grupo.tipo]?.plural}>
          <h3 className="despliegue__titulo">
            {TIPOS_EDIFICIO[grupo.tipo]?.plural ?? grupo.nombre}
            <span className="tabular">{grupo.edificios.length}</span>
          </h3>
          <ul className="despliegue__edificios">
            {grupo.edificios.map((edificio) => (
              <EdificioDesplegable key={edificio.id} edificio={edificio} />
            ))}
          </ul>
        </section>
      ))}
      {!grupos.length && <p className="texto-suave texto-pequeno">Este cliente aún no tiene edificios.</p>}
      {tienePermiso('edificios.crear') && (
        <Link to={`/edificios/nuevo?cliente_id=${clienteId}`} className="despliegue__agregar">
          <Plus size={14} aria-hidden="true" /> Agregar edificio
        </Link>
      )}
    </div>
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
    ['Tipo', TIPOS_ASCENSOR[ficha.tipo_ascensor] || '—'],
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

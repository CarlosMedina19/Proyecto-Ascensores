// Contenido que se despliega dentro de las filas de clientes, edificios y ascensores, para llegar a
// un conjunto, edificio o ascensor sin saltar de sección.
import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpDown, Building, Building2, ChevronRight, Plus } from 'lucide-react';
import { clientes, conjuntos, edificios } from '../api/servicios';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { ESTADOS_EQUIPO, TIPOS_EDIFICIO, numero } from '../utilidades/formato';
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

/** Edificios de un conjunto, cada uno desplegable, y el botón para crear otro en ese conjunto. */
function ListaEdificios({ lista, conjuntoId, clienteId, activo = true }) {
  const { tienePermiso } = useAutenticacion();
  const destino = conjuntoId ? `/edificios/nuevo?conjunto_id=${conjuntoId}` : `/edificios/nuevo?cliente_id=${clienteId}`;
  return (
    <ul className="despliegue__edificios">
      {lista.map((edificio) => (
        <EdificioDesplegable key={edificio.id} edificio={edificio} />
      ))}
      {!lista.length && <li className="texto-suave texto-pequeno">Sin edificios registrados.</li>}
      {activo && tienePermiso('edificios.crear') && (
        <li>
          <Link to={destino} className="despliegue__agregar">
            <Plus size={14} aria-hidden="true" /> Crear edificio
          </Link>
        </li>
      )}
    </ul>
  );
}

/** Conjunto, institución o residencia que se abre para mostrar sus edificios. */
function ConjuntoDesplegable({ conjunto }) {
  const [abierto, setAbierto] = useState(false);
  return (
    <li className="despliegue__conjunto">
      <div className="despliegue__cabecera">
        <button type="button" className="boton-desplegar" aria-expanded={abierto} aria-label={`Desplegar ${conjunto.nombre}`} onClick={() => setAbierto((v) => !v)}>
          <ChevronRight size={16} aria-hidden="true" />
        </button>
        <Building2 size={15} aria-hidden="true" className="texto-suave" />
        <Link to={`/conjuntos/${conjunto.id}`} className="despliegue__nombre">
          {conjunto.nombre}
        </Link>
        <span className="texto-suave texto-pequeno">{[conjunto.direccion, conjunto.ciudad].filter(Boolean).join(', ')}</span>
        <span className="despliegue__conteo tabular">
          {conjunto.edificios.length} {conjunto.edificios.length === 1 ? 'edificio' : 'edificios'}
        </span>
        {!conjunto.activo && <Insignia>Inactivo</Insignia>}
      </div>
      {abierto && <ListaEdificios lista={conjunto.edificios} conjuntoId={conjunto.id} activo={conjunto.activo} />}
    </li>
  );
}

/** Tipo que tiene el cliente (Conjuntos, Institucionales o Residenciales) con el botón para crear otro. */
function GrupoTipo({ grupo, clienteId }) {
  const { tienePermiso } = useAutenticacion();
  const [abierto, setAbierto] = useState(false);
  const datos = TIPOS_EDIFICIO[grupo.tipo];
  const plural = datos?.plural ?? grupo.nombre;
  return (
    <section className="despliegue__grupo" aria-label={plural}>
      <div className="despliegue__titulo">
        <button type="button" className="boton-desplegar" aria-expanded={abierto} aria-label={`Desplegar ${plural}`} onClick={() => setAbierto((v) => !v)}>
          <ChevronRight size={16} aria-hidden="true" />
        </button>
        {plural}
        <span className="tabular">{grupo.conjuntos.length}</span>
      </div>
      {abierto && (
        <ul className="despliegue__conjuntos">
          {grupo.conjuntos.map((conjunto) => (
            <ConjuntoDesplegable key={conjunto.id} conjunto={conjunto} />
          ))}
          {grupo.sin_conjunto.length > 0 && (
            <li className="despliegue__conjunto">
              <p className="texto-suave texto-pequeno" style={{ margin: '4px 0' }}>
                Edificios sin conjunto
              </p>
              <ListaEdificios lista={grupo.sin_conjunto} clienteId={clienteId} />
            </li>
          )}
          {tienePermiso('edificios.crear') && (
            <li>
              <Link to={`/conjuntos/nuevo?cliente_id=${clienteId}&tipo=${grupo.tipo}`} className="despliegue__agregar">
                <Plus size={14} aria-hidden="true" /> Crear {(datos?.texto ?? grupo.nombre).toLowerCase()}
              </Link>
            </li>
          )}
        </ul>
      )}
    </section>
  );
}

/**
 * Árbol del cliente: solo los tipos que tiene, sus conjuntos, los edificios de cada conjunto y los
 * ascensores de cada edificio. Cada nivel se despliega y tiene su botón para crear.
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
  if (!grupos) return <Cargando texto="Cargando conjuntos…" />;

  const faltantes = Object.entries(TIPOS_EDIFICIO).filter(([tipo]) => !grupos.some((g) => g.tipo === tipo));

  return (
    <div className="despliegue">
      {grupos.map((grupo) => (
        <GrupoTipo key={grupo.tipo} grupo={grupo} clienteId={clienteId} />
      ))}
      {!grupos.length && <p className="texto-suave texto-pequeno">Este cliente aún no tiene conjuntos, instituciones ni residencias.</p>}
      {tienePermiso('edificios.crear') && faltantes.length > 0 && (
        <div className="despliegue__crear">
          {faltantes.map(([tipo, { texto }]) => (
            <Link key={tipo} to={`/conjuntos/nuevo?cliente_id=${clienteId}&tipo=${tipo}`} className="despliegue__agregar">
              <Plus size={14} aria-hidden="true" /> Crear {texto.toLowerCase()}
            </Link>
          ))}
        </div>
      )}
    </div>
  );
}

/** Edificios de un conjunto con sus ascensores (se cargan al desplegar la fila). */
export function EdificiosDelConjunto({ conjuntoId }) {
  const [conjunto, setConjunto] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    conjuntos
      .obtener(conjuntoId)
      .then((r) => setConjunto(r.datos))
      .catch((e) => setError(e.message));
  }, [conjuntoId]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!conjunto) return <Cargando texto="Cargando edificios…" />;
  return (
    <div className="despliegue">
      <ListaEdificios lista={(conjunto.edificios || []).map((e) => ({ ...e, equipos: e.equipos || [] }))} conjuntoId={conjunto.id} activo={conjunto.activo} />
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

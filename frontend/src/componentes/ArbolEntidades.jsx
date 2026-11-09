// Niveles 2 y 3 del árbol de Clientes: edificios de una entidad y ascensores de un edificio.
// Cada nivel se carga al desplegarlo (bajo demanda).
import { Fragment, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ArrowUpDown, Building, ChevronRight, Plus } from 'lucide-react';
import { edificios as servicioEdificios, equipos } from '../api/servicios';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { fecha } from '../utilidades/formato';
import { Alerta, Cargando, Insignia } from './Basicos';

/** Estado del ascensor con las palabras que se usan en campo. */
const ESTADOS = {
  activo: { texto: 'Operativo', tipo: 'exito' },
  mantenimiento: { texto: 'En mantenimiento', tipo: 'alerta' },
  inactivo: { texto: 'Fuera de servicio', tipo: 'neutro' },
};

function BotonNivel({ abierto, nombre, alAlternar }) {
  return (
    <button type="button" className="boton-desplegar" aria-expanded={abierto} aria-label={`${abierto ? 'Contraer' : 'Desplegar'} ${nombre}`} onClick={alAlternar}>
      <ChevronRight size={16} aria-hidden="true" />
    </button>
  );
}

/** Tercer nivel: ascensores del edificio con su estado y sus mantenimientos. */
function AscensoresDelEdificio({ edificioId, activo }) {
  const { tienePermiso } = useAutenticacion();
  const [lista, setLista] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    equipos
      .listar({ edificio_id: edificioId, por_pagina: 100 })
      .then((r) => setLista(r.datos))
      .catch((e) => setError(e.message));
  }, [edificioId]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!lista) return <Cargando texto="Cargando ascensores…" />;

  return (
    <div className="arbol__nivel arbol__nivel--3">
      {lista.length ? (
        <table className="arbol__tabla">
          <thead>
            <tr>
              <th scope="col">Código</th>
              <th scope="col">Marca / modelo</th>
              <th scope="col">Estado</th>
              <th scope="col">Último mantenimiento</th>
              <th scope="col">Próximo mantenimiento</th>
            </tr>
          </thead>
          <tbody>
            {lista.map((e) => {
              const estado = ESTADOS[e.estado] ?? ESTADOS.activo;
              return (
                <tr key={e.id}>
                  <td>
                    <Link to={`/equipos/${e.id}`} className="arbol__enlace">
                      <ArrowUpDown size={14} aria-hidden="true" /> {e.codigo}
                    </Link>
                  </td>
                  <td>{[e.marca, e.modelo].filter(Boolean).join(' · ') || '—'}</td>
                  <td>
                    <Insignia tipo={estado.tipo}>{estado.texto}</Insignia>
                  </td>
                  <td className="tabular">{e.asignacion_activa?.ultimo_mantenimiento ? fecha(e.asignacion_activa.ultimo_mantenimiento) : '—'}</td>
                  <td className="tabular">{e.proximo_mantenimiento ? fecha(e.proximo_mantenimiento) : '—'}</td>
                </tr>
              );
            })}
          </tbody>
        </table>
      ) : (
        <p className="texto-suave texto-pequeno">Sin ascensores registrados.</p>
      )}
      {activo && tienePermiso('equipos.crear') && (
        <Link to={`/equipos/nuevo?edificio_id=${edificioId}`} className="despliegue__agregar">
          <Plus size={14} aria-hidden="true" /> Agregar ascensor
        </Link>
      )}
    </div>
  );
}

/** Segundo nivel: edificios de la entidad; cada uno se despliega en sus ascensores. */
export function EdificiosDeEntidad({ entidad }) {
  const { tienePermiso } = useAutenticacion();
  const [lista, setLista] = useState(null);
  const [error, setError] = useState('');
  const [abiertos, setAbiertos] = useState(() => new Set());

  useEffect(() => {
    servicioEdificios
      .listar({ conjunto_id: entidad.id, por_pagina: 100 })
      .then((r) => setLista(r.datos))
      .catch((e) => setError(e.message));
  }, [entidad.id]);

  const alternar = (id) =>
    setAbiertos((actual) => {
      const siguiente = new Set(actual);
      if (siguiente.has(id)) siguiente.delete(id);
      else siguiente.add(id);
      return siguiente;
    });

  if (error) return <Alerta>{error}</Alerta>;
  if (!lista) return <Cargando texto="Cargando edificios…" />;

  return (
    <div className="arbol__nivel arbol__nivel--2">
      {lista.length ? (
        <table className="arbol__tabla">
          <thead>
            <tr>
              <th className="arbol__expandir" aria-label="Desplegar" />
              <th scope="col">Edificio</th>
              <th scope="col">Ubicación en el conjunto</th>
              <th scope="col" className="numero">
                Ascensores
              </th>
            </tr>
          </thead>
          <tbody>
            {lista.map((edificio) => {
              const abierto = abiertos.has(edificio.id);
              return (
                <Fragment key={edificio.id}>
                  <tr className={abierto ? 'arbol__fila--abierta' : undefined}>
                    <td className="arbol__expandir">
                      <BotonNivel abierto={abierto} nombre={edificio.nombre} alAlternar={() => alternar(edificio.id)} />
                    </td>
                    <td>
                      <Link to={`/edificios/${edificio.id}`} className="arbol__enlace">
                        <Building size={14} aria-hidden="true" /> {edificio.nombre}
                      </Link>
                      {!edificio.activo && (
                        <>
                          {' '}
                          <Insignia>Inactivo</Insignia>
                        </>
                      )}
                    </td>
                    <td>{[edificio.direccion, edificio.ciudad].filter(Boolean).join(', ') || '—'}</td>
                    <td className="numero tabular">{edificio.equipos_count ?? 0}</td>
                  </tr>
                  {abierto && (
                    <tr className="arbol__detalle">
                      <td colSpan={4}>
                        <AscensoresDelEdificio edificioId={edificio.id} activo={edificio.activo} />
                      </td>
                    </tr>
                  )}
                </Fragment>
              );
            })}
          </tbody>
        </table>
      ) : (
        <p className="texto-suave texto-pequeno">Aún no tiene edificios.</p>
      )}
      {entidad.activo && tienePermiso('edificios.crear') && (
        <Link to={`/edificios/nuevo?conjunto_id=${entidad.id}`} className="despliegue__agregar">
          <Plus size={14} aria-hidden="true" /> Crear edificio
        </Link>
      )}
    </div>
  );
}

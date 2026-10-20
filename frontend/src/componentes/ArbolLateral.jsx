// Árbol del menú lateral: Clientes → tipo de edificio → Edificios → Ascensores.
import { useState } from 'react';
import { NavLink, useLocation } from 'react-router-dom';
import { ArrowUpDown, Building, Building2, ChevronDown } from 'lucide-react';
import { useAutenticacion } from '../contexto/AutenticacionContexto';

function BotonDesplegar({ abierta, texto, alAlternar }) {
  return (
    <button
      type="button"
      className={`lateral__desplegar lateral__desplegar--pequeno${abierta ? ' abierto' : ''}`}
      aria-expanded={abierta}
      aria-label={`${abierta ? 'Contraer' : 'Desplegar'} ${texto}`}
      onClick={alAlternar}
    >
      <ChevronDown size={14} aria-hidden="true" />
    </button>
  );
}

const claseEnlace =
  (nivel) =>
  ({ isActive }) =>
    `lateral__enlace lateral__enlace--hijo lateral__enlace--n${nivel}${isActive ? ' activo' : ''}`;

/**
 * Tipo de edificio (Conjuntos, Institucionales o Residenciales). Se despliega en "Edificios" (la
 * sección de edificios de ese tipo), y "Edificios" se despliega en "Ascensores" (los de ese tipo).
 */
export function RamaTipo({ ruta, texto }) {
  const { tienePermiso } = useAutenticacion();
  const { pathname } = useLocation();
  const rutaAscensores = `${ruta}/ascensores`;
  const [abierta, setAbierta] = useState(() => pathname.startsWith(ruta));
  const [edificiosAbierta, setEdificiosAbierta] = useState(() => pathname.startsWith(rutaAscensores));
  const verAscensores = tienePermiso('equipos.ver');
  const dentro = pathname.startsWith(ruta);

  return (
    <div className="lateral__rama">
      <div className="lateral__fila">
        <button
          type="button"
          className={`lateral__enlace lateral__enlace--hijo lateral__enlace--n1 lateral__alternar${dentro && !abierta ? ' activo' : ''}`}
          aria-expanded={abierta}
          onClick={() => setAbierta((v) => !v)}
        >
          <Building2 size={16} aria-hidden="true" />
          <span className="lateral__texto">{texto}</span>
        </button>
        <BotonDesplegar abierta={abierta} texto={texto} alAlternar={() => setAbierta((v) => !v)} />
      </div>

      {abierta && (
        <div className="lateral__hijos">
          <div className="lateral__rama">
            <div className="lateral__fila">
              <NavLink to={ruta} end className={claseEnlace(2)} aria-label={`Edificios de ${texto.toLowerCase()}`}>
                <Building size={14} aria-hidden="true" />
                <span className="lateral__texto">Edificios</span>
              </NavLink>
              {verAscensores && (
                <BotonDesplegar abierta={edificiosAbierta} texto={`edificios de ${texto.toLowerCase()}`} alAlternar={() => setEdificiosAbierta((v) => !v)} />
              )}
            </div>
            {verAscensores && edificiosAbierta && (
              <div className="lateral__hijos">
                <NavLink to={rutaAscensores} className={claseEnlace(3)} aria-label={`Ascensores de ${texto.toLowerCase()}`}>
                  <ArrowUpDown size={13} aria-hidden="true" />
                  <span className="lateral__texto">Ascensores</span>
                </NavLink>
              </div>
            )}
          </div>
        </div>
      )}
    </div>
  );
}

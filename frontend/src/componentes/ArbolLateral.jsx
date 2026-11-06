// Menú lateral: los tipos dentro de Clientes (Conjuntos, Institucionales y Residenciales).
import { NavLink } from 'react-router-dom';
import { Building2 } from 'lucide-react';

/** Lleva a la página del tipo: todos sus registros con su cliente, y allí se despliegan edificios y ascensores. */
export function RamaTipo({ ruta, texto }) {
  return (
    <NavLink to={ruta} className={({ isActive }) => `lateral__enlace lateral__enlace--hijo lateral__enlace--n1${isActive ? ' activo' : ''}`}>
      <Building2 size={16} aria-hidden="true" />
      <span className="lateral__texto">{texto}</span>
    </NavLink>
  );
}

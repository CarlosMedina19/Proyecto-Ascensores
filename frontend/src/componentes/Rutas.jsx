import { Navigate, useLocation } from 'react-router-dom';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { Cargando, EstadoVacio } from './Basicos';

/** Exige sesión iniciada; si no la hay, envía al inicio de sesión. */
export function RutaPrivada({ children }) {
  const { usuario, cargando } = useAutenticacion();
  const ubicacion = useLocation();

  if (cargando) return <Cargando texto="Verificando sesión…" />;
  if (!usuario) return <Navigate to="/iniciar-sesion" replace state={{ desde: ubicacion.pathname }} />;
  return children;
}

/** Solo para usuarios sin sesión (inicio de sesión, recuperación). */
export function RutaPublica({ children }) {
  const { usuario, cargando } = useAutenticacion();
  if (cargando) return <Cargando />;
  if (usuario) return <Navigate to="/inicio" replace />;
  return children;
}

/** Exige un permiso del rol (RF-002). */
export function ConPermiso({ permiso, children }) {
  const { tienePermiso } = useAutenticacion();
  if (!tienePermiso(permiso)) {
    return (
      <EstadoVacio
        titulo="Acceso restringido"
        descripcion="Su rol no tiene permiso para ver esta sección. Si cree que es un error, comuníquese con el administrador."
      />
    );
  }
  return children;
}

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { guardarToken, obtenerToken } from '../api/cliente';
import { autenticacion } from '../api/servicios';

const AutenticacionContexto = createContext(null);

const MINUTOS_INACTIVIDAD_POR_DEFECTO = 10;

export function ProveedorAutenticacion({ children }) {
  const [usuario, setUsuario] = useState(null);
  const [cargando, setCargando] = useState(Boolean(obtenerToken()));
  const [motivoCierre, setMotivoCierre] = useState('');
  const [minutosInactividad, setMinutosInactividad] = useState(
    Number(sessionStorage.getItem('sigma.minutos')) || MINUTOS_INACTIVIDAD_POR_DEFECTO,
  );

  // Recupera la sesión si hay un token guardado.
  useEffect(() => {
    if (!obtenerToken()) return;
    autenticacion
      .perfil()
      .then((respuesta) => setUsuario(respuesta.datos))
      .catch(() => guardarToken(null))
      .finally(() => setCargando(false));
  }, []);

  // La API responde 401 cuando el token venció por inactividad o el usuario fue desactivado.
  useEffect(() => {
    const alExpirar = () => {
      setUsuario(null);
      setMotivoCierre('Su sesión expiró. Inicie sesión nuevamente.');
    };
    window.addEventListener('sigma:sesion-expirada', alExpirar);
    return () => window.removeEventListener('sigma:sesion-expirada', alExpirar);
  }, []);

  /** `antesDeEntrar` corre con las credenciales ya validadas y antes de mostrar el panel (transición). */
  const iniciarSesion = useCallback(async (correo, password, { antesDeEntrar } = {}) => {
    const respuesta = await autenticacion.iniciarSesion(correo, password);
    guardarToken(respuesta.token_acceso);
    if (antesDeEntrar) await antesDeEntrar();
    const minutos = respuesta.minutos_inactividad || MINUTOS_INACTIVIDAD_POR_DEFECTO;
    sessionStorage.setItem('sigma.minutos', String(minutos));
    setMinutosInactividad(minutos);
    setMotivoCierre('');
    setUsuario(respuesta.usuario);
    return respuesta.usuario;
  }, []);

  const cerrarSesion = useCallback(async (motivo = '') => {
    try {
      if (obtenerToken()) await autenticacion.cerrarSesion();
    } catch {
      // Si el token ya no es válido no hay nada más que cerrar.
    }
    guardarToken(null);
    setMotivoCierre(motivo);
    setUsuario(null);
  }, []);

  const recargarPerfil = useCallback(async () => {
    const respuesta = await autenticacion.perfil();
    setUsuario(respuesta.datos);
    return respuesta.datos;
  }, []);

  const valor = useMemo(() => {
    const permisos = new Set(usuario?.rol?.permisos || []);
    return {
      usuario,
      cargando,
      motivoCierre,
      minutosInactividad,
      iniciarSesion,
      cerrarSesion,
      recargarPerfil,
      tienePermiso: (permiso) => permisos.has(permiso),
      esCliente: usuario?.rol?.nombre === 'cliente',
      esTecnico: usuario?.rol?.nombre === 'tecnico',
    };
  }, [usuario, cargando, motivoCierre, minutosInactividad, iniciarSesion, cerrarSesion, recargarPerfil]);

  return <AutenticacionContexto.Provider value={valor}>{children}</AutenticacionContexto.Provider>;
}

export function useAutenticacion() {
  const contexto = useContext(AutenticacionContexto);
  if (!contexto) throw new Error('useAutenticacion debe usarse dentro de ProveedorAutenticacion');
  return contexto;
}

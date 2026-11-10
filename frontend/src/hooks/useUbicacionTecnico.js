import { useEffect, useRef, useState } from 'react';
import { ordenes } from '../api/servicios';

const CADA_MS = 30 * 1000;

/**
 * Mientras el técnico asignado tiene la orden en proceso, el navegador envía su ubicación cada 30 s
 * para el mapa de Auditoría. Devuelve el estado para mostrarlo en pantalla.
 */
export function useUbicacionTecnico(ordenId, activo) {
  const [estado, setEstado] = useState('inactivo');
  const ultimoEnvio = useRef(0);

  useEffect(() => {
    if (!activo || !ordenId || typeof navigator === 'undefined' || !navigator.geolocation) {
      setEstado(activo ? 'no_disponible' : 'inactivo');
      return undefined;
    }
    setEstado('buscando');
    const seguimiento = navigator.geolocation.watchPosition(
      (posicion) => {
        const ahora = Date.now();
        if (ahora - ultimoEnvio.current < CADA_MS) return;
        ultimoEnvio.current = ahora;
        ordenes
          .enviarUbicacion(ordenId, {
            latitud: posicion.coords.latitude,
            longitud: posicion.coords.longitude,
            precision_metros: Math.round(posicion.coords.accuracy || 0),
            origen: 'web',
          })
          .then(() => setEstado('compartiendo'))
          .catch(() => setEstado('error'));
      },
      (falla) => setEstado(falla.code === 1 ? 'denegado' : 'error'),
      { enableHighAccuracy: true, maximumAge: 15000, timeout: 30000 },
    );
    return () => navigator.geolocation.clearWatch(seguimiento);
  }, [ordenId, activo]);

  return estado;
}

export const TEXTO_UBICACION = {
  buscando: 'Obteniendo su ubicación…',
  compartiendo: 'Compartiendo su ubicación con el administrador mientras el trabajo está en proceso.',
  denegado: 'El navegador no tiene permiso de ubicación: el administrador no verá su posición en el mapa.',
  no_disponible: 'Este navegador no permite compartir la ubicación.',
  error: 'No se pudo enviar la ubicación. Se reintentará automáticamente.',
};

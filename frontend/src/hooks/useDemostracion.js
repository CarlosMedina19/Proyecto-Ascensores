import { useCallback, useEffect, useState } from 'react';

const CLAVE = 'sigma.demostracion';
const EVENTO = 'sigma:demostracion';

function leer() {
  try {
    return localStorage.getItem(CLAVE) === '1';
  } catch {
    return false;
  }
}

/** Activa o desactiva el modo demostración en este navegador. */
export function fijarDemostracion(activo) {
  try {
    if (activo) localStorage.setItem(CLAVE, '1');
    else localStorage.removeItem(CLAVE);
  } catch {
    // Sin almacenamiento local el modo dura solo esta visita.
  }
  window.dispatchEvent(new Event(EVENTO));
}

/** Lee ?demo=1 o ?demo=0 de la dirección (útil para presentar el producto). */
export function aplicarDemostracionDesdeUrl(busqueda = window.location.search) {
  const valor = new URLSearchParams(busqueda).get('demo');
  if (valor === '1' || valor === '0') fijarDemostracion(valor === '1');
}

/**
 * Modo demostración: muestra las referencias del proyecto (SENA, fases, requerimientos) y las cuentas de
 * prueba. Apagado, la aplicación muestra solo textos comerciales.
 */
export function useDemostracion() {
  const [activo, setActivo] = useState(leer);

  useEffect(() => {
    const actualizar = () => setActivo(leer());
    window.addEventListener(EVENTO, actualizar);
    window.addEventListener('storage', actualizar);
    return () => {
      window.removeEventListener(EVENTO, actualizar);
      window.removeEventListener('storage', actualizar);
    };
  }, []);

  const alternar = useCallback(() => fijarDemostracion(!leer()), []);

  return { activo, alternar };
}

/** Cuentas de prueba: solo si el entorno las define (nunca en el código). */
export const CUENTAS_DEMO = (import.meta.env.VITE_DEMO_CUENTAS || '')
  .split(',')
  .map((c) => c.trim())
  .filter(Boolean);
export const CLAVE_DEMO = import.meta.env.VITE_DEMO_CLAVE || '';

import { useCallback, useEffect, useState } from 'react';

const CLAVE = 'sigma.tema';

function temaDelSistema() {
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
}

/** Tema claro/oscuro. Sigue al sistema hasta que el usuario elige uno. */
export function useTema() {
  const [tema, setTema] = useState(() => {
    try {
      return localStorage.getItem(CLAVE) || temaDelSistema();
    } catch {
      return temaDelSistema();
    }
  });

  useEffect(() => {
    document.documentElement.setAttribute('data-tema', tema);
  }, [tema]);

  const alternar = useCallback(() => {
    setTema((actual) => {
      const nuevo = actual === 'oscuro' ? 'claro' : 'oscuro';
      try {
        localStorage.setItem(CLAVE, nuevo);
      } catch {
        // Sin almacenamiento local el tema dura solo esta visita.
      }
      return nuevo;
    });
  }, []);

  return { tema, alternar };
}

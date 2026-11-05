import { useEffect, useState } from 'react';
import { catalogos } from '../api/servicios';

// Tipos de ascensor activos (catálogo de Configuración): se piden una vez por sesión.
let pedido = null;

/** @returns {Record<string, string>} clave => nombre */
export function useTiposAscensor() {
  const [tipos, setTipos] = useState({});
  useEffect(() => {
    pedido ??= catalogos
      .tiposAscensor()
      .then((r) => Object.fromEntries(r.datos.map((t) => [t.clave, t.nombre])))
      .catch(() => {
        pedido = null;
        return {};
      });
    let vigente = true;
    pedido.then((t) => vigente && setTipos(t));
    return () => {
      vigente = false;
    };
  }, []);
  return tipos;
}

/** Tras editar el catálogo en Configuración, los formularios vuelven a pedirlo. */
export function olvidarTiposAscensor() {
  pedido = null;
}

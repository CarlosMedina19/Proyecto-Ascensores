import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Carga un listado paginado de la API y maneja filtros, búsqueda y página.
 * `consultar` recibe los filtros y devuelve { datos, metadatos }.
 */
export function useListado(consultar, filtrosIniciales = {}, porPagina = 15) {
  const [filtros, setFiltros] = useState({ busqueda: '', ...filtrosIniciales });
  const [pagina, setPagina] = useState(1);
  const [respuesta, setRespuesta] = useState({ datos: [], metadatos: null });
  const [cargando, setCargando] = useState(true);
  const [error, setError] = useState('');
  const [busquedaAplicada, setBusquedaAplicada] = useState(filtros.busqueda);
  const ultimaSolicitud = useRef(0);

  // Espera a que el usuario deje de escribir antes de buscar.
  useEffect(() => {
    const temporizador = setTimeout(() => setBusquedaAplicada(filtros.busqueda), 350);
    return () => clearTimeout(temporizador);
  }, [filtros.busqueda]);

  const { busqueda: _omitida, ...otrosFiltros } = filtros;
  const claveFiltros = JSON.stringify(otrosFiltros);

  const recargar = useCallback(async () => {
    const numero = ++ultimaSolicitud.current;
    setCargando(true);
    setError('');
    try {
      const resultado = await consultar({
        ...JSON.parse(claveFiltros),
        busqueda: busquedaAplicada,
        pagina,
        por_pagina: porPagina,
      });
      if (numero === ultimaSolicitud.current) {
        setRespuesta({ datos: resultado.datos || [], metadatos: resultado.metadatos || null });
      }
    } catch (e) {
      if (numero === ultimaSolicitud.current) setError(e.message);
    } finally {
      if (numero === ultimaSolicitud.current) setCargando(false);
    }
  }, [consultar, claveFiltros, busquedaAplicada, pagina, porPagina]);

  useEffect(() => {
    recargar();
  }, [recargar]);

  const cambiarFiltro = useCallback((clave, valor) => {
    setFiltros((actual) => ({ ...actual, [clave]: valor }));
    setPagina(1);
  }, []);

  return {
    filas: respuesta.datos,
    metadatos: respuesta.metadatos,
    cargando,
    error,
    filtros,
    cambiarFiltro,
    setPagina,
    recargar,
  };
}

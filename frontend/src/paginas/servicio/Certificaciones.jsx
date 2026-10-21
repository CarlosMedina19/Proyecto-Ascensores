import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { servicio } from '../../api/servicios';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { filasSegunPantalla } from '../../hooks/useListado';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { fecha } from '../../utilidades/formato';
import { ESTADOS_CERTIFICACION, RESULTADOS_CERTIFICACION, plazoCertificacion } from '../../utilidades/servicio';

const ORDEN = ['vencida', 'no_conforme', 'por_vencer', 'sin_certificar', 'vigente'];

/**
 * Certificación anual de cada ascensor. En Bogotá la revisión general anual la exige el Acuerdo 470 de 2011 y
 * la hace un organismo acreditado por ONAC con la NTC 5926-1.
 */
export default function Certificaciones() {
  const navegar = useNavigate();
  const [respuesta, setRespuesta] = useState(null);
  const [error, setError] = useState('');
  const [filtro, setFiltro] = useState('');
  const [pagina, setPagina] = useState(1);
  const porPagina = filasSegunPantalla(70);

  const cargar = () =>
    servicio
      .certificaciones()
      .then(setRespuesta)
      .catch((e) => setError(e.message));
  useEffect(() => {
    cargar();
  }, []);

  const filas = useMemo(() => {
    const todas = respuesta?.datos || [];
    return todas
      .filter((f) => !filtro || f.estado === filtro)
      .sort((a, b) => ORDEN.indexOf(a.estado) - ORDEN.indexOf(b.estado) || (a.dias_para_vencer ?? 0) - (b.dias_para_vencer ?? 0));
  }, [respuesta, filtro]);

  // Paginación en el navegador: la tabla cabe en la pantalla.
  const ultimaPagina = Math.max(1, Math.ceil(filas.length / porPagina));
  const paginaActual = Math.min(pagina, ultimaPagina);
  const visibles = filas.slice((paginaActual - 1) * porPagina, paginaActual * porPagina);
  const metadatos = {
    pagina_actual: paginaActual,
    ultima_pagina: ultimaPagina,
    total: filas.length,
    desde: filas.length ? (paginaActual - 1) * porPagina + 1 : 0,
    hasta: Math.min(paginaActual * porPagina, filas.length),
  };

  return (
    <>
      <EncabezadoPagina
        titulo="Certificación anual"
        descripcion={['Revisión general anual por organismo acreditado', 'Certificado de cada ascensor', 'Avisos 60, 30 y 15 días antes']}>
        <HerramientasExcel modulo="servicio" alImportar={cargar} />
      </EncabezadoPagina>


      {error && <Alerta>{error}</Alerta>}
      {!respuesta ? (
        <Cargando />
      ) : (
        <>
          <div className="contadores" role="group" aria-label="Filtrar por estado">
            <button type="button" className={`contador${filtro === '' ? ' activo' : ''}`} onClick={() => { setFiltro(''); setPagina(1); }}>
              <b>{respuesta.datos.length}</b> Todos
            </button>
            {ORDEN.map((estado) => (
              <button key={estado} type="button" className={`contador contador--${ESTADOS_CERTIFICACION[estado].tipo}${filtro === estado ? ' activo' : ''}`} onClick={() => { setFiltro(estado); setPagina(1); }}>
                <b>{respuesta.resumen[estado] || 0}</b> {ESTADOS_CERTIFICACION[estado].texto}
              </button>
            ))}
          </div>

          <Tarjeta sinRelleno>
            <Tabla
              filas={visibles}
              claveFila={(f) => f.equipo.id}
              alSeleccionar={(f) => navegar(`/equipos/${f.equipo.id}`)}
              vacio={<EstadoVacio titulo="No hay ascensores en este estado" descripcion="Cambie el filtro o registre la certificación desde la ficha de cada ascensor." />}
              columnas={[
                {
                  clave: 'equipo',
                  titulo: 'Ascensor',
                  render: (f) => (
                    <>
                      <strong>{f.equipo.codigo}</strong>
                      <div className="tabla__secundario">{f.equipo.edificio?.nombre}</div>
                    </>
                  ),
                },
                { clave: 'cliente', titulo: 'Cliente', render: (f) => f.equipo.cliente?.nombre },
                { clave: 'organismo', titulo: 'Organismo', render: (f) => f.certificacion?.organismo || '—' },
                { clave: 'resultado', titulo: 'Resultado', render: (f) => (f.certificacion ? RESULTADOS_CERTIFICACION[f.certificacion.resultado] : '—') },
                { clave: 'vence', titulo: 'Vence', render: (f) => (f.certificacion ? fecha(f.certificacion.fecha_vencimiento) : '—') },
                {
                  clave: 'estado',
                  titulo: 'Estado',
                  render: (f) => (
                    <>
                      <Insignia tipo={ESTADOS_CERTIFICACION[f.estado].tipo}>{ESTADOS_CERTIFICACION[f.estado].texto}</Insignia>
                      {f.certificacion && <div className="tabla__secundario">{plazoCertificacion(f.dias_para_vencer)}</div>}
                    </>
                  ),
                },
              ]}
            />
            <Paginacion metadatos={metadatos} alCambiar={setPagina} />
          </Tarjeta>
          <p className="texto-pequeno texto-suave" style={{ marginTop: 8 }}>
            Referencia en Bogotá: Acuerdo 470 de 2011 y NTC 5926-1, por organismos acreditados por ONAC. En otras ciudades, registre la norma local.
          </p>
        </>
      )}
    </>
  );
}

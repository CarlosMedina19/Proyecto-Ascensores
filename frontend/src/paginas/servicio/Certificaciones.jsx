import { useEffect, useMemo, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { servicio } from '../../api/servicios';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Tabla, Tarjeta } from '../../componentes/Basicos';
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

  return (
    <>
      <EncabezadoPagina
        titulo="Certificación anual"
        descripcion="Revisión general anual de cada ascensor por un organismo de inspección acreditado. SIGMA avisa 60, 30 y 15 días antes del vencimiento."
      />

      <div className="barra-excel">
        <HerramientasExcel modulo="servicio" alImportar={cargar} />
      </div>

      {error && <Alerta>{error}</Alerta>}
      {!respuesta ? (
        <Cargando />
      ) : (
        <>
          <div className="contadores" role="group" aria-label="Filtrar por estado">
            <button type="button" className={`contador${filtro === '' ? ' activo' : ''}`} onClick={() => setFiltro('')}>
              <b>{respuesta.datos.length}</b> Todos
            </button>
            {ORDEN.map((estado) => (
              <button key={estado} type="button" className={`contador contador--${ESTADOS_CERTIFICACION[estado].tipo}${filtro === estado ? ' activo' : ''}`} onClick={() => setFiltro(estado)}>
                <b>{respuesta.resumen[estado] || 0}</b> {ESTADOS_CERTIFICACION[estado].texto}
              </button>
            ))}
          </div>

          <Tarjeta sinRelleno>
            <Tabla
              filas={filas}
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
                      <div className="tabla__secundario">{plazoCertificacion(f.dias_para_vencer)}</div>
                    </>
                  ),
                },
              ]}
            />
          </Tarjeta>
          <p className="texto-pequeno texto-suave" style={{ marginTop: 12 }}>
            Norma de referencia: en Bogotá, Acuerdo 470 de 2011 (revisión general anual obligatoria) con la NTC 5926-1, por organismos acreditados por ONAC. En
            otras ciudades confirme la norma local con la autoridad municipal y regístrela en cada certificación.
          </p>
        </>
      )}
    </>
  );
}

import { useEffect, useState } from 'react';
import { Activity, CalendarCheck, Gauge, Timer } from 'lucide-react';
import { servicio } from '../../api/servicios';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { fecha, numero } from '../../utilidades/formato';
import { duracion } from '../../utilidades/servicio';

const PERIODOS = [
  { dias: 30, texto: 'Últimos 30 días' },
  { dias: 90, texto: 'Últimos 90 días' },
  { dias: 365, texto: 'Último año' },
];

const porcentaje = (valor) => (valor === null || valor === undefined ? '—' : `${Number(valor).toLocaleString('es-CO', { maximumFractionDigits: 2 })} %`);

function Indicador({ icono: Icono, titulo, valor, detalle, definicion, meta }) {
  return (
    <Tarjeta>
      <div className="indicador-servicio">
        <span className="indicador-servicio__icono" aria-hidden="true">
          <Icono size={18} />
        </span>
        <div>
          <h3>{titulo}</h3>
          <b className="tabular">{valor}</b>
          <div className="texto-suave">{detalle}</div>
          {meta && <div className="texto-pequeno indicador-servicio__meta">{meta}</div>}
        </div>
      </div>
      <p className="texto-pequeno texto-suave indicador-servicio__definicion">{definicion}</p>
    </Tarjeta>
  );
}

/** Indicadores del servicio: disponibilidad, MTBF, tiempo medio de respuesta y cumplimiento del plan. */
export default function Indicadores() {
  const [dias, setDias] = useState(90);
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    const hasta = new Date();
    const desde = new Date(hasta.getTime() - (dias - 1) * 86400000);
    const iso = (d) => d.toISOString().slice(0, 10);
    setDatos(null);
    servicio
      .indicadores({ desde: iso(desde), hasta: iso(hasta) })
      .then((r) => setDatos(r.datos))
      .catch((e) => setError(e.message));
  }, [dias]);

  return (
    <>
      <EncabezadoPagina titulo="Indicadores del servicio" descripcion={['Disponibilidad', 'Tiempo entre fallas', 'Respuesta a emergencias', 'Cumplimiento del plan']}>
        <select className="campo__control" value={dias} onChange={(e) => setDias(Number(e.target.value))} aria-label="Período">
          {PERIODOS.map((p) => (
            <option key={p.dias} value={p.dias}>
              {p.texto}
            </option>
          ))}
        </select>
      </EncabezadoPagina>

      {error && <Alerta>{error}</Alerta>}
      {!datos ? (
        <Cargando texto="Calculando indicadores…" />
      ) : (
        <>
          <p className="texto-suave" style={{ marginTop: 0 }}>
            Del {fecha(datos.periodo.desde)} al {fecha(datos.periodo.hasta)} · {datos.ascensores} {datos.ascensores === 1 ? 'ascensor' : 'ascensores'} en servicio.
          </p>
          <div className="rejilla rejilla--2">
            <Indicador
              icono={Activity}
              titulo="Disponibilidad"
              valor={porcentaje(datos.disponibilidad.porcentaje)}
              detalle={`${numero(datos.disponibilidad.horas_detenidas)} horas detenidas por fallas y atrapamientos`}
              meta="Referencia del sector: 99 % o más."
              definicion="Horas en servicio ÷ horas posibles de todos los ascensores en el período. Una falla cuenta desde el reporte hasta la solución."
            />
            <Indicador
              icono={Gauge}
              titulo="Tiempo medio entre fallas (MTBF)"
              valor={datos.mtbf.horas === null ? 'Sin fallas' : `${numero(datos.mtbf.horas)} h`}
              detalle={`${datos.mtbf.fallas} ${datos.mtbf.fallas === 1 ? 'falla' : 'fallas'} en el período`}
              definicion="Horas posibles ÷ número de fallas (emergencias por falla o atrapamiento y órdenes de mantenimiento correctivo). Más alto es mejor."
            />
            <Indicador
              icono={Timer}
              titulo="Tiempo medio de respuesta"
              valor={datos.respuesta.minutos_promedio === null ? 'Sin emergencias' : duracion(datos.respuesta.minutos_promedio)}
              detalle={
                datos.respuesta.atendidas
                  ? `${datos.respuesta.dentro_objetivo} de ${datos.respuesta.atendidas} dentro del tiempo objetivo · ${datos.respuesta.abiertas} ${datos.respuesta.abiertas === 1 ? 'abierta' : 'abiertas'}`
                  : `${datos.respuesta.abiertas} ${datos.respuesta.abiertas === 1 ? 'abierta' : 'abiertas'}`
              }
              definicion="Del reporte de la emergencia a la llegada del técnico. El objetivo se configura por separado para atrapamientos y para otras fallas."
            />
            <Indicador
              icono={CalendarCheck}
              titulo="Cumplimiento del plan"
              valor={porcentaje(datos.cumplimiento_plan.porcentaje)}
              detalle={`${datos.cumplimiento_plan.ejecutados} de ${datos.cumplimiento_plan.programados} mantenimientos preventivos ejecutados`}
              definicion="Mantenimientos ejecutados ÷ mantenimientos programados cuya fecha ya pasó (sin contar los cancelados)."
            />
          </div>
        </>
      )}
    </>
  );
}

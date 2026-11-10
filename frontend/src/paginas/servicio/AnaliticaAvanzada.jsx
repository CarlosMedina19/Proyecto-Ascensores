import { useEffect, useState } from 'react';
import { BrainCircuit, TrendingUp } from 'lucide-react';
import { servicio } from '../../api/servicios';
import { Alerta, Cargando, Insignia, Tabla, Tarjeta } from '../../componentes/Basicos';

const MESES = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const NIVEL = { alto: 'peligro', medio: 'alerta', bajo: 'exito' };

function etiquetaMes(clave) {
  const [anio, mes] = clave.split('-');
  return `${MESES[Number(mes) - 1]} ${anio.slice(2)}`;
}

/** Emergencias por mes (barras), promedio móvil (línea) y pronóstico con su rango (barras claras). */
function GraficoEmergencias({ meses, pronostico }) {
  const puntos = [
    ...meses.map((m) => ({ mes: m.mes, valor: m.emergencias, movil: m.emergencias_promedio_movil, futuro: false })),
    ...pronostico.map((p) => ({ mes: p.mes, valor: p.emergencias, minimo: p.minimo, maximo: p.maximo, futuro: true })),
  ];
  const maximo = Math.max(1, ...puntos.map((p) => p.maximo ?? p.valor));
  const ancho = 720;
  const alto = 220;
  const margen = { arriba: 12, abajo: 28, izquierda: 30 };
  const paso = (ancho - margen.izquierda) / puntos.length;
  const y = (v) => margen.arriba + (alto - margen.arriba - margen.abajo) * (1 - v / maximo);
  const base = y(0);
  const linea = puntos
    .map((p, i) => (p.movil == null ? null : `${margen.izquierda + paso * i + paso / 2},${y(p.movil)}`))
    .filter(Boolean)
    .join(' ');
  const marcas = [0, Math.round(maximo / 2), Math.round(maximo)];

  return (
    <figure className="grafico-analitica">
      <svg viewBox={`0 0 ${ancho} ${alto}`} role="img" aria-label="Emergencias por mes y pronóstico de los próximos meses">
        {marcas.map((m) => (
          <g key={m}>
            <line x1={margen.izquierda} x2={ancho} y1={y(m)} y2={y(m)} className="grafico-analitica__guia" />
            <text x={margen.izquierda - 6} y={y(m) + 4} textAnchor="end" className="grafico-analitica__eje">
              {m}
            </text>
          </g>
        ))}
        {puntos.map((p, i) => {
          const x = margen.izquierda + paso * i + paso * 0.2;
          const anchoBarra = paso * 0.6;
          return (
            <g key={p.mes}>
              {p.futuro && p.maximo > p.minimo && (
                <rect x={x + anchoBarra / 2 - 1.5} width={3} y={y(p.maximo)} height={Math.max(0, y(p.minimo) - y(p.maximo))} className="grafico-analitica__rango" />
              )}
              <rect x={x} width={anchoBarra} y={y(p.valor)} height={Math.max(0, base - y(p.valor))} rx={3} className={p.futuro ? 'grafico-analitica__barra grafico-analitica__barra--futuro' : 'grafico-analitica__barra'}>
                <title>{`${etiquetaMes(p.mes)}: ${p.valor} emergencia(s)${p.futuro ? ` estimadas (entre ${p.minimo} y ${p.maximo})` : ''}`}</title>
              </rect>
              <text x={x + anchoBarra / 2} y={alto - 8} textAnchor="middle" className="grafico-analitica__eje">
                {etiquetaMes(p.mes)}
              </text>
            </g>
          );
        })}
        {linea && <polyline points={linea} className="grafico-analitica__linea" />}
      </svg>
      <figcaption className="grafico-analitica__leyenda">
        <span>
          <i className="grafico-analitica__muestra" /> Emergencias del mes
        </span>
        <span>
          <i className="grafico-analitica__muestra grafico-analitica__muestra--linea" /> Promedio móvil de 3 meses
        </span>
        <span>
          <i className="grafico-analitica__muestra grafico-analitica__muestra--futuro" /> Pronóstico y rango probable
        </span>
      </figcaption>
    </figure>
  );
}

/**
 * Analítica avanzada: la calcula el microservicio en Python (Pandas, NumPy y scikit-learn) con los datos
 * que Laravel autoriza para el usuario. Si el servicio no está disponible, el resto de la página sigue igual.
 */
export default function AnaliticaAvanzada() {
  const [resumen, setResumen] = useState(null);
  const [predicciones, setPredicciones] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.all([servicio.analiticaResumen(), servicio.analiticaPredicciones()])
      .then(([r, p]) => {
        setResumen(r.datos);
        setPredicciones(p.datos);
      })
      .catch((e) => setError(e.message));
  }, []);

  return (
    <Tarjeta
      titulo={
        <>
          <BrainCircuit size={16} aria-hidden="true" /> Analítica avanzada · últimos 12 meses
        </>
      }
    >
      {error ? (
        <Alerta tipo="info">{error}</Alerta>
      ) : !resumen || !predicciones ? (
        <Cargando texto="Calculando tendencias y riesgo…" />
      ) : (
        <div className="analitica">
          <div>
            <h3 className="analitica__subtitulo">
              <TrendingUp size={15} aria-hidden="true" /> Emergencias y pronóstico
            </h3>
            <GraficoEmergencias meses={resumen.meses || []} pronostico={predicciones.pronostico || []} />
            <p className="texto-pequeno texto-suave">
              {predicciones.metodo}. Total del período: {resumen.totales?.emergencias ?? 0} emergencias, {resumen.totales?.preventivos ?? 0} preventivos y {resumen.totales?.correctivos ?? 0}{' '}
              correctivos finalizados.
            </p>
          </div>
          <div>
            <h3 className="analitica__subtitulo">Ascensores con mayor riesgo de falla</h3>
            <Tabla
              filas={(predicciones.riesgo || []).slice(0, 8)}
              claveFila="equipo_id"
              vacio={<p className="texto-suave">Aún no hay ascensores para evaluar.</p>}
              columnas={[
                {
                  clave: 'codigo',
                  titulo: 'Ascensor',
                  render: (r) => (
                    <>
                      <strong>{r.codigo}</strong>
                      <div className="tabla__secundario">{r.edificio}</div>
                    </>
                  ),
                },
                { clave: 'puntaje', titulo: 'Riesgo', render: (r) => <Insignia tipo={NIVEL[r.nivel]}>{`${Math.round(r.puntaje)} / 100 · ${r.nivel}`}</Insignia> },
                { clave: 'motivos', titulo: 'Por qué', render: (r) => <span className="texto-pequeno">{r.motivos.join(' · ')}</span> },
              ]}
            />
          </div>
        </div>
      )}
    </Tarjeta>
  );
}

import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { AlertTriangle, ArrowRight, ArrowUpDown, CalendarCheck, FileText, Plus, Wrench } from 'lucide-react';
import { panel as servicioPanel, resumen as servicioResumen } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EstadoVacio, Insignia, Tarjeta } from '../../componentes/Basicos';
import CorteAscensor from '../../componentes/CorteAscensor';
import LineaTiempo from '../../componentes/LineaTiempo';
import { ESTADOS_EQUIPO, ESTADOS_ORDEN, SERVICIOS_ORDEN, dinero, fecha, fechaIso, humanizar, iniciales } from '../../utilidades/formato';

const COLORES_ESTADO = {
  activo: 'var(--color-exito)',
  mantenimiento: 'var(--color-alerta)',
  inactivo: 'var(--color-texto-tenue)',
};

const formatoDiaFestivo = new Intl.DateTimeFormat('es-CO', { weekday: 'short', day: 'numeric', month: 'short' });

function fechaLarga() {
  const texto = new Intl.DateTimeFormat('es-CO', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function saludo() {
  const hora = new Date().getHours();
  if (hora < 12) return 'Buenos días';
  if (hora < 19) return 'Buenas tardes';
  return 'Buenas noches';
}

/** Línea de tendencia bajo cada indicador. Los valores nulos se omiten. */
function Tendencia({ valores, color }) {
  const puntos = valores.map((v, i) => [i, v]).filter(([, v]) => v !== null && v !== undefined);
  if (puntos.length < 2) return <div className="indicador__tendencia" aria-hidden="true" />;
  const maximo = Math.max(...puntos.map(([, v]) => v));
  const minimo = Math.min(...puntos.map(([, v]) => v));
  const rango = maximo - minimo || 1;
  const ancho = 300;
  const alto = 40;
  const x = (i) => (i / (valores.length - 1)) * ancho;
  const y = (v) => alto - 4 - ((v - minimo) / rango) * (alto - 10);
  const linea = puntos.map(([i, v]) => `${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' ');
  const area = `${x(puntos[0][0])},${alto} ${linea} ${x(puntos[puntos.length - 1][0])},${alto}`;

  return (
    <svg className="indicador__tendencia" viewBox={`0 0 ${ancho} ${alto}`} preserveAspectRatio="none" aria-hidden="true">
      <polygon points={area} fill={color} opacity="0.12" />
      <polyline points={linea} fill="none" stroke={color} strokeWidth="2" vectorEffect="non-scaling-stroke" />
    </svg>
  );
}

function Indicador({ icono: Icono, tono, etiqueta, valor, detalle, tipoDetalle, serie }) {
  const colores = {
    exito: 'var(--color-exito)',
    primario: 'var(--color-primario)',
    peligro: 'var(--color-peligro)',
    cian: 'var(--color-cian)',
  };
  return (
    <div className="tarjeta indicador">
      <span className="indicador__cabecera">
        <span className={`indicador__icono indicador__icono--${tono}`}>
          <Icono size={16} aria-hidden="true" />
        </span>
        {etiqueta}
      </span>
      <span className="indicador__valor tabular">{valor}</span>
      <span className={`indicador__detalle${tipoDetalle ? ` indicador__detalle--${tipoDetalle}` : ''}`}>{detalle}</span>
      <Tendencia valores={serie || []} color={colores[tono]} />
    </div>
  );
}

/** Programados frente a ejecutados por semana; la semana actual se resalta con el tercer color de la gráfica. */
function GraficoSemanas({ semanas }) {
  // Tope múltiplo de 3 para que las cuatro líneas guía queden en números enteros y parejos.
  const maximo = Math.max(3, ...semanas.map((s) => s.programados));
  const tope = Math.ceil(maximo / 3) * 3;
  const lineas = [0, tope / 3, (tope * 2) / 3, tope];
  const alto = 220;
  const y = (v) => alto - (v / tope) * alto;

  return (
    <div className="grafico-semanas">
      <div className="grafico-semanas__ejes" aria-hidden="true">
        {lineas
          .slice()
          .reverse()
          .map((v) => (
            <span key={v} style={{ top: y(v) }}>
              {v}
            </span>
          ))}
      </div>
      <div className="grafico-semanas__area">
        {lineas.map((v) => (
          <i key={v} className="grafico-semanas__linea" style={{ top: y(v) }} aria-hidden="true" />
        ))}
        {semanas.map((s) => (
          <div key={s.inicio} className={`grafico-semanas__grupo${s.actual ? ' actual' : ''}`}>
            <div className="grafico-semanas__barras" role="img" aria-label={`Semana ${s.semana}: ${s.ejecutados} ejecutados de ${s.programados} programados`}>
              <span className="grafico-semanas__barra grafico-semanas__barra--programados" style={{ height: `${(s.programados / tope) * 100}%` }} />
              <span
                className={`grafico-semanas__barra ${s.actual ? 'grafico-semanas__barra--actual' : 'grafico-semanas__barra--ejecutados'}`}
                style={{ height: `${(s.ejecutados / tope) * 100}%` }}
              >
                {s.actual && <b>{`${s.ejecutados}/${s.programados}`}</b>}
              </span>
            </div>
            <span className="grafico-semanas__etiqueta">S{s.semana}</span>
          </div>
        ))}
      </div>
    </div>
  );
}

/** Barra apilada con la distribución de ascensores por estado. */
function DistribucionEstados({ conteo }) {
  const total = Object.values(conteo).reduce((suma, valor) => suma + valor, 0);
  if (!total) return <EstadoVacio titulo="Sin ascensores registrados" />;

  return (
    <div>
      <div
        className="distribucion"
        role="img"
        aria-label={Object.entries(conteo)
          .map(([estado, valor]) => `${ESTADOS_EQUIPO[estado]?.texto}: ${valor}`)
          .join(', ')}
      >
        {Object.entries(conteo).map(([estado, valor]) =>
          valor ? <div key={estado} style={{ width: `${(valor / total) * 100}%`, background: COLORES_ESTADO[estado] }} /> : null,
        )}
      </div>
      <div className="leyenda" style={{ marginTop: 14 }}>
        {Object.entries(conteo).map(([estado, valor]) => (
          <span key={estado}>
            <i style={{ background: COLORES_ESTADO[estado] }} />
            {ESTADOS_EQUIPO[estado]?.texto} <strong className="tabular">{valor}</strong>
            <span className="texto-suave tabular">({Math.round((valor / total) * 100)} %)</span>
          </span>
        ))}
      </div>
    </div>
  );
}

/** Insignia de un elemento de la cola: estado de la orden o visita del plan sin orden. */
function estadoCola(elemento) {
  if (elemento.tipo === 'visita') return { texto: 'Sin orden', tipo: 'alerta' };
  return ESTADOS_ORDEN[elemento.estado] ?? { texto: humanizar(elemento.estado), tipo: 'neutro' };
}

/** Zona del esquema según el estado: atrasado en rojo, en mantenimiento en ámbar. */
function zonaAscensor(ascensor) {
  if (ascensor.atrasado) return 'M';
  if (ascensor.estado === 'mantenimiento') return 'R';
  if (ascensor.estado === 'activo') return 'B';
  return 'P';
}

function EstadoPorAscensor({ ascensores }) {
  const navegar = useNavigate();
  if (!ascensores.length) return <EstadoVacio titulo="Sin ascensores registrados" />;

  return (
    <div className="ascensores-estado">
      {ascensores.map((a) => {
        const zona = zonaAscensor(a);
        const pisos = Math.min(Math.max(a.paradas || 7, 4), 8);
        // Fase 4: con planilla, cada zona muestra el resultado de su sección en la última inspección.
        const secciones = a.ultima_revision?.secciones;
        const deSeccion = (clave) => secciones?.[clave] || 'P';
        const zonas = secciones
          ? { maquina: deSeccion('cuarto_maquinas'), cabina: deSeccion('cabina'), contrapeso: deSeccion('contrapeso'), puertasCabina: deSeccion('puertas'), hueco: deSeccion('hueco'), foso: deSeccion('foso') }
          : { maquina: zona, cabina: zona, contrapeso: zona, puertasCabina: zona, hueco: zona, foso: zona };
        const peor = secciones && !a.atrasado ? (Object.values(secciones).includes('M') ? 'M' : Object.values(secciones).includes('R') ? 'R' : 'B') : zona;
        const color = { B: 'exito', R: 'alerta', M: 'peligro', P: 'neutro' }[peor];
        return (
          <button key={a.id} type="button" className="ascensor-estado" onClick={() => navegar(`/equipos/${a.id}`)}>
            <span className="ascensor-estado__cabecera">
              <b>{a.edificio}</b>
              <i className={`punto punto--${color}`} aria-hidden="true" />
            </span>
            <span className="ascensor-estado__codigo">{a.codigo}</span>
            <CorteAscensor
              compacto
              pisos={pisos}
              zonas={zonas}
              puertasPiso={Array(pisos).fill(zonas.puertasCabina)}
              etiqueta={`Ascensor ${a.codigo}: ${a.atrasado ? 'mantenimiento atrasado' : ESTADOS_EQUIPO[a.estado]?.texto}`}
            />
            <span className="ascensor-estado__pie">
              {a.atrasado ? <span className="texto-peligro">Mantenimiento atrasado</span> : `Próximo: ${fecha(a.proximo_mantenimiento)}`}
            </span>
          </button>
        );
      })}
    </div>
  );
}

export default function PanelOperacion({ nombre }) {
  const { tienePermiso } = useAutenticacion();
  const navegar = useNavigate();
  const [resumen, setResumen] = useState(null);
  const [datos, setDatos] = useState(null);
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.all([servicioResumen.obtener(), servicioPanel.obtener()])
      .then(([r, p]) => {
        setResumen(r.datos);
        setDatos(p.datos);
      })
      .catch((e) => setError(e.message));
  }, []);

  if (error) return <Alerta>{error}</Alerta>;
  if (!datos || !resumen) return <Cargando />;

  const { franja, indicadores, semanas, alertas, cola_hoy: cola, estado_ascensores: ascensores, tecnicos } = datos;
  const { al_dia: alDia, ascensores_activos: activos, atrasados, correctivos, contratos } = indicadores;
  const ordenesHoy = cola.filter((c) => c.tipo === 'orden').length;
  const hoyIso = fechaIso();
  const actual = semanas[semanas.length - 1];
  const festivo = franja.proximo_festivo;
  const variacion = alDia.variacion;

  return (
    <>
      <div className="pagina__encabezado">
        <div>
          <p className="etiqueta-superior">{fechaLarga()}</p>
          <h1 className="pagina__titulo">
            {saludo()}
            {nombre ? `, ${nombre}` : ''}.{' '}
            {ordenesHoy ? (
              <>
                Hoy hay <span className="resalte">{ordenesHoy} {ordenesHoy === 1 ? 'orden' : 'órdenes'}</span> de trabajo
              </>
            ) : (
              'Hoy no hay órdenes de trabajo'
            )}
            {atrasados.total ? (
              <>
                {' '}
                y <span className="resalte">{atrasados.total} {atrasados.total === 1 ? 'visita del plan atrasada' : 'visitas del plan atrasadas'}</span>.
              </>
            ) : (
              '.'
            )}
          </h1>
          <p className="pagina__descripcion">Operación de mantenimiento: órdenes, técnicos, ascensores, planes y contratos.</p>
        </div>
        <div className="pagina__acciones">
          {tienePermiso('equipos.crear') && (
            <Link to="/equipos/nuevo" className="boton boton--secundario">
              <Plus size={16} /> Registrar ascensor
            </Link>
          )}
          {tienePermiso('ordenes.crear') && (
            <Link to="/ordenes/nueva" className="boton boton--secundario">
              <Plus size={16} /> Crear orden de trabajo
            </Link>
          )}
          {tienePermiso('mantenimientos.ver') && (
            <Link to="/calendario" className="boton">
              Ver calendario <ArrowRight size={16} />
            </Link>
          )}
        </div>
      </div>

      <div className="franja-estado">
        <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
          <span className="en-vivo" aria-hidden="true" />
          <b>
            {franja.tecnicos_en_campo} {franja.tecnicos_en_campo === 1 ? 'técnico en campo' : 'técnicos en campo'}
          </b>
          <span className="texto-suave">
            de {franja.tecnicos_activos} {franja.tecnicos_activos === 1 ? 'activo' : 'activos'}
          </span>
        </span>
        <span className="franja-estado__separador" />
        <span>
          Próximo festivo:{' '}
          <b>{festivo ? `${formatoDiaFestivo.format(new Date(`${festivo.fecha}T00:00:00`)).replace(/\./g, '')}` : '—'}</b>
          {festivo && <span className="texto-suave"> · {festivo.nombre}</span>}
        </span>
        <span className="franja-estado__separador" />
        <span>
          Cumplimiento semanal{' '}
          {franja.cumplimiento_semana.programados ? (
            <b className="tabular">
              {franja.cumplimiento_semana.ejecutados} de {franja.cumplimiento_semana.programados} visitas
            </b>
          ) : (
            <b>sin visitas programadas</b>
          )}
        </span>
      </div>

      <div className="rejilla">
        <div className="rejilla rejilla--4">
          <Indicador
            icono={CalendarCheck}
            tono="exito"
            etiqueta="Mantenimientos al día"
            valor={alDia.porcentaje === null ? '—' : `${Math.round(alDia.porcentaje)} %`}
            detalle={
              variacion === null
                ? 'Visitas vencidas de los últimos 90 días'
                : `${variacion >= 0 ? '▲' : '▼'} ${Math.abs(variacion)} pts vs. el mes anterior`
            }
            tipoDetalle={variacion === null ? null : variacion >= 0 ? 'sube' : 'baja'}
            serie={alDia.serie.map((s) => s.valor)}
          />
          <Indicador
            icono={ArrowUpDown}
            tono="primario"
            etiqueta="Ascensores activos"
            valor={activos.total}
            detalle={`en ${activos.edificios} ${activos.edificios === 1 ? 'edificio' : 'edificios'} · ${activos.registrados} registrados`}
            serie={activos.serie.map((s) => s.valor)}
          />
          <Indicador
            icono={Wrench}
            tono="peligro"
            etiqueta="Correctivos abiertos"
            valor={correctivos.abiertos}
            detalle={`▲ ${correctivos.esta_semana} esta semana${correctivos.criticos ? ` · ${correctivos.criticos} ${correctivos.criticos === 1 ? 'crítico' : 'críticos'}` : ''}`}
            tipoDetalle={correctivos.esta_semana ? 'baja' : null}
            serie={correctivos.serie.map((s) => s.valor)}
          />
          <Indicador
            icono={FileText}
            tono="cian"
            etiqueta="Contratos vigentes"
            valor={dinero(contratos.valor)}
            detalle={`${contratos.vigentes} ${contratos.vigentes === 1 ? 'vigente' : 'vigentes'} · ${contratos.por_vencer} por vencer`}
            tipoDetalle={contratos.por_vencer ? 'baja' : null}
          />
        </div>

        <div className="rejilla panel-operacion">
          <Tarjeta sinRelleno>
            <div className="tarjeta__cuerpo">
              <div className="panel-operacion__titulo">
                <div>
                  <h2 className="tarjeta__titulo" style={{ marginBottom: 2 }}>
                    Mantenimientos por semana
                  </h2>
                  <p className="texto-pequeno texto-suave" style={{ margin: 0 }}>
                    Programados frente a ejecutados · semana {actual.semana} en curso
                  </p>
                </div>
                <div className="leyenda">
                  <span>
                    <i style={{ background: 'var(--grafica-1)' }} /> Programados
                  </span>
                  <span>
                    <i style={{ background: 'var(--grafica-2)' }} /> Ejecutados
                  </span>
                  <span>
                    <i style={{ background: 'var(--grafica-3)' }} /> Semana actual
                  </span>
                </div>
              </div>
            </div>
            <div className="separador" style={{ margin: 0 }} />
            <div className="tarjeta__cuerpo">
              <GraficoSemanas semanas={semanas} />
            </div>
          </Tarjeta>

          <div className="rejilla" style={{ alignContent: 'start' }}>
            {alertas.map((alerta) => (
              <button key={`${alerta.enlace}-${alerta.titulo}`} type="button" className={`aviso aviso--${alerta.tipo}`} onClick={() => navegar(alerta.enlace)}>
                {alerta.tipo === 'peligro' ? <AlertTriangle size={18} aria-hidden="true" /> : <FileText size={18} aria-hidden="true" />}
                <span>
                  <b>{alerta.titulo}</b>
                  <small>{alerta.detalle}</small>
                </span>
              </button>
            ))}

            <Tarjeta sinRelleno>
              <div className="tarjeta__cuerpo panel-operacion__titulo" style={{ paddingBottom: 12 }}>
                <h2 className="tarjeta__titulo" style={{ margin: 0 }}>
                  Cola de hoy
                </h2>
                <span className="etiqueta-mono">
                  {ordenesHoy} {ordenesHoy === 1 ? 'orden' : 'órdenes'}
                  {cola.length > ordenesHoy && ` · ${cola.length - ordenesHoy} sin orden`}
                </span>
              </div>
              {cola.length ? (
                <ul className="cola">
                  {cola.map((elemento) => {
                    const estado = estadoCola(elemento);
                    const destino = elemento.tipo === 'orden' ? `/ordenes/${elemento.id}` : `/calendario?fecha=${hoyIso}&vista=dia`;
                    return (
                      <li key={`${elemento.tipo}-${elemento.id}`}>
                        <button type="button" className="cola__item" onClick={() => navegar(destino)}>
                          <span className="cola__hora tabular">{elemento.hora || '—'}</span>
                          <span className="cola__texto">
                            <b>
                              {elemento.equipo} · {elemento.edificio}
                            </b>
                            <small>
                              {SERVICIOS_ORDEN[elemento.servicio]?.texto}
                              {elemento.descripcion && ` · ${elemento.descripcion}`}
                              {elemento.tecnicos?.length > 0 && ` · ${elemento.tecnicos.join(', ')}`}
                            </small>
                          </span>
                          <Insignia tipo={elemento.prioridad === 'critica' ? 'peligro' : estado.tipo}>
                            {elemento.prioridad === 'critica' ? 'Crítica' : estado.texto}
                          </Insignia>
                        </button>
                      </li>
                    );
                  })}
                </ul>
              ) : (
                <EstadoVacio titulo="No hay trabajos programados para hoy" descripcion="Las órdenes y visitas del día aparecen aquí." />
              )}
            </Tarjeta>
          </div>
        </div>

        <div className="rejilla panel-operacion">
          <Tarjeta sinRelleno>
            <div className="tarjeta__cuerpo panel-operacion__titulo" style={{ paddingBottom: 12 }}>
              <h2 className="tarjeta__titulo" style={{ margin: 0 }}>
                Estado por ascensor
              </h2>
              <span className="etiqueta-mono">última inspección · toca para abrir</span>
            </div>
            <div className="separador" style={{ margin: 0 }} />
            <div className="tarjeta__cuerpo">
              <EstadoPorAscensor ascensores={ascensores} />
            </div>
          </Tarjeta>

          <div className="rejilla" style={{ alignContent: 'start' }}>
            <Tarjeta sinRelleno>
              <div className="tarjeta__cuerpo panel-operacion__titulo" style={{ paddingBottom: 12 }}>
                <h2 className="tarjeta__titulo" style={{ margin: 0 }}>
                  Técnicos
                </h2>
                <span className="etiqueta-mono">ocupación de hoy</span>
              </div>
              {tecnicos.length ? (
                <ul className="tecnicos">
                  {tecnicos.map((t) => (
                    <li key={t.id}>
                      <span className="avatar" aria-hidden="true">
                        {iniciales(t.nombre)}
                      </span>
                      <span className="tecnicos__texto">
                        <b>{t.nombre}</b>
                        <small>{t.estado}</small>
                        <span className="tecnicos__barra" aria-hidden="true">
                          <i style={{ width: `${t.ocupacion ?? 0}%` }} />
                        </span>
                      </span>
                      <span className="tecnicos__porcentaje tabular">{t.ocupacion === null ? '—' : `${t.ocupacion}%`}</span>
                    </li>
                  ))}
                </ul>
              ) : (
                <EstadoVacio titulo="No hay técnicos activos" />
              )}
              <p className="texto-pequeno texto-suave tecnicos__nota">Ocupación: horas de órdenes de hoy sobre una jornada de 8 horas.</p>
            </Tarjeta>

            <Tarjeta titulo="Estado de los ascensores">
              <DistribucionEstados
                conteo={{ activo: resumen.equipos.activo, mantenimiento: resumen.equipos.mantenimiento, inactivo: resumen.equipos.inactivo }}
              />
            </Tarjeta>
          </div>
        </div>

        <Tarjeta titulo="Últimos eventos de los ascensores">
          {resumen.ultimos_eventos.length ? <LineaTiempo eventos={resumen.ultimos_eventos} conEquipo /> : <EstadoVacio titulo="Aún no hay eventos" />}
        </Tarjeta>
      </div>
    </>
  );
}

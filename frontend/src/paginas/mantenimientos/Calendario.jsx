import { useCallback, useEffect, useMemo, useState } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { festivos as servicioFestivos, mantenimientos, ordenes } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, ListaDescripcion } from '../../componentes/Basicos';
import { ESTADOS_ORDEN, diasHasta, estadoMantenimiento, fechaIso } from '../../utilidades/formato';
import ModalMantenimiento from './ModalMantenimiento';

const HORA_INICIO = 6;
const HORA_FIN = 19;
const ALTO_HORA = 60;
const DURACION_VISITA = 90;
const MAXIMO_POR_DIA = 3;
const DIAS_CORTOS = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
const DIAS_SEMANA = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
const VISTAS = [
  { valor: 'dia', texto: 'Día' },
  { valor: 'semana', texto: 'Semana' },
  { valor: 'mes', texto: 'Mes' },
];
const LEYENDA = [
  { clase: 'preventivo', texto: 'Preventivo' },
  { clase: 'correctivo', texto: 'Correctivo' },
  { clase: 'inspeccion', texto: 'Inspección' },
  { clase: 'visita', texto: 'Visita del plan sin orden' },
];

const formatoMes = new Intl.DateTimeFormat('es-CO', { month: 'long', year: 'numeric' });
const formatoDia = new Intl.DateTimeFormat('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });
const formatoCorto = new Intl.DateTimeFormat('es-CO', { day: 'numeric', month: 'short' });

const inicioDelDia = (dia) => new Date(dia.getFullYear(), dia.getMonth(), dia.getDate());

function sumarDias(dia, dias) {
  const nuevo = new Date(dia);
  nuevo.setDate(nuevo.getDate() + dias);
  return nuevo;
}

function lunesDe(dia) {
  return sumarDias(inicioDelDia(dia), -((dia.getDay() + 6) % 7));
}

/** Días de la cuadrícula del mes: de lunes a domingo, completando las semanas. */
function diasDelMes(mes) {
  const primero = new Date(mes.getFullYear(), mes.getMonth(), 1);
  const ultimo = new Date(mes.getFullYear(), mes.getMonth() + 1, 0);
  const dias = [];
  for (let dia = lunesDe(primero); dia <= sumarDias(lunesDe(ultimo), 6); dia = sumarDias(dia, 1)) dias.push(dia);
  return dias;
}

function capitalizar(texto) {
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

function minutosDe(hora) {
  const [h, m] = hora.split(':').map(Number);
  return h * 60 + m;
}

function nombreCorto(nombre = '') {
  const [primero, segundo] = nombre.split(' ');
  return segundo ? `${primero} ${segundo[0]}.` : primero;
}

/** Orden de trabajo como evento del calendario. */
function desdeOrden(o) {
  const tecnicos = (o.tecnicos || []).map((t) => nombreCorto(t.nombre)).join(', ');
  const atrasada = !['finalizada', 'cancelada'].includes(o.estado) && diasHasta(o.fecha) < 0;
  return {
    clave: `o${o.id}`,
    tipo: 'orden',
    id: o.id,
    fecha: o.fecha,
    hora: o.hora,
    duracion: o.duracion_estimada,
    clase: o.servicio,
    titulo: `${o.equipo?.codigo ?? ''} · ${o.edificio?.nombre ?? ''}`,
    detalle: `${o.hora} · ${tecnicos || 'Sin técnico'}`,
    estado: atrasada ? 'Atrasada' : (ESTADOS_ORDEN[o.estado]?.texto ?? o.estado),
    finalizada: o.estado === 'finalizada',
    critica: o.prioridad === 'critica',
  };
}

/** Visita del plan que aún no tiene orden. */
function desdeVisita(m) {
  return {
    clave: `v${m.id}`,
    tipo: 'visita',
    datos: m,
    fecha: m.fecha_programada,
    hora: m.hora,
    duracion: DURACION_VISITA,
    clase: 'visita',
    titulo: `${m.equipo?.codigo ?? ''} · ${m.equipo?.edificio?.nombre ?? ''}`,
    detalle: `${m.hora ?? ''} · ${m.plan?.nombre ?? 'Preventivo'} · sin orden`,
    estado: estadoMantenimiento(m).texto,
    finalizada: m.estado === 'ejecutado',
  };
}

/** Reparte en carriles los eventos que se cruzan en el mismo día. */
function conCarriles(eventos) {
  const ordenados = eventos.slice().sort((a, b) => minutosDe(a.hora) - minutosDe(b.hora));
  const finDeCarril = [];
  const ubicados = ordenados.map((evento) => {
    const inicio = minutosDe(evento.hora);
    let carril = finDeCarril.findIndex((fin) => fin <= inicio);
    if (carril === -1) carril = finDeCarril.length;
    finDeCarril[carril] = inicio + evento.duracion;
    return { evento, carril };
  });
  return ubicados.map((u) => ({ ...u, carriles: Math.max(finDeCarril.length, 1) }));
}

function Evento({ evento, carril = 0, carriles = 1, alAbrir }) {
  const inicio = minutosDe(evento.hora) - HORA_INICIO * 60;
  const clases = ['agenda__evento', `agenda__evento--${evento.clase}`];
  if (evento.finalizada) clases.push('agenda__evento--finalizada');
  // Bloques de menos de 75 minutos no alcanzan para tres líneas.
  if (evento.duracion < 75) clases.push('agenda__evento--corto');
  return (
    <button
      type="button"
      className={clases.join(' ')}
      style={{
        top: (inicio / 60) * ALTO_HORA,
        height: Math.max((evento.duracion / 60) * ALTO_HORA - 4, 26),
        left: `calc(${(carril / carriles) * 100}% + 4px)`,
        width: `calc(${100 / carriles}% - 8px)`,
      }}
      onClick={() => alAbrir(evento)}
      title={`${evento.titulo} · ${evento.detalle} · ${evento.estado}`}
    >
      <b>
        {evento.critica && '● '}
        {evento.titulo}
      </b>
      <span>{evento.detalle}</span>
      <small>{evento.estado}</small>
    </button>
  );
}

/** Vista de día o de semana: columnas por día con la rejilla de horas. */
function VistaHoras({ dias, porDia, festivos, alAbrir }) {
  const [ahora, setAhora] = useState(() => new Date());
  useEffect(() => {
    const temporizador = setInterval(() => setAhora(new Date()), 60000);
    return () => clearInterval(temporizador);
  }, []);

  const hoy = fechaIso(ahora);
  const minutoActual = ahora.getHours() * 60 + ahora.getMinutes() - HORA_INICIO * 60;
  const horas = Array.from({ length: HORA_FIN - HORA_INICIO }, (_, i) => HORA_INICIO + i);
  const sinHora = dias.flatMap((dia) => (porDia[fechaIso(dia)] || []).filter((e) => !e.hora));

  return (
    <div className="agenda" style={{ '--columnas': dias.length }}>
      <div className="agenda__cabecera">
        <span />
        {dias.map((dia) => {
          const clave = fechaIso(dia);
          return (
            <div key={clave} className={`agenda__dia${clave === hoy ? ' hoy' : ''}`}>
              <small>{DIAS_CORTOS[dia.getDay()]}</small>
              <b className="tabular">{dia.getDate()}</b>
              {festivos[clave] && (
                <span className="agenda__festivo" title={festivos[clave]}>
                  Festivo
                </span>
              )}
            </div>
          );
        })}
      </div>

      {sinHora.length > 0 && (
        <div className="agenda__sin-hora">
          <span className="agenda__hora-etiqueta">Sin hora</span>
          {dias.map((dia) => (
            <div key={fechaIso(dia)}>
              {(porDia[fechaIso(dia)] || [])
                .filter((e) => !e.hora)
                .map((e) => (
                  <button key={e.clave} type="button" className={`calendario__evento calendario__evento--${e.clase}`} onClick={() => alAbrir(e)}>
                    <b>{e.titulo}</b>
                  </button>
                ))}
            </div>
          ))}
        </div>
      )}

      <div className="agenda__cuerpo" style={{ height: horas.length * ALTO_HORA }}>
        <div className="agenda__horas" aria-hidden="true">
          {horas.map((h) => (
            <span key={h} style={{ top: (h - HORA_INICIO) * ALTO_HORA }}>
              {h}:00
            </span>
          ))}
        </div>
        {dias.map((dia) => {
          const clave = fechaIso(dia);
          const eventos = (porDia[clave] || []).filter((e) => e.hora);
          const clases = ['agenda__columna'];
          if (clave === hoy) clases.push('hoy');
          if (festivos[clave] || dia.getDay() === 0) clases.push('inhabil');
          return (
            <div key={clave} className={clases.join(' ')} role="group" aria-label={`${capitalizar(formatoDia.format(dia))}: ${eventos.length} eventos`}>
              {horas.map((h) => (
                <i key={h} className="agenda__linea" style={{ top: (h - HORA_INICIO) * ALTO_HORA }} aria-hidden="true" />
              ))}
              {conCarriles(eventos).map(({ evento, carril, carriles }) => (
                <Evento key={evento.clave} evento={evento} carril={carril} carriles={carriles} alAbrir={alAbrir} />
              ))}
              {clave === hoy && minutoActual >= 0 && minutoActual <= horas.length * 60 && (
                <i className="agenda__ahora" style={{ top: (minutoActual / 60) * ALTO_HORA }} aria-label="Hora actual" />
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
}

function VistaMes({ mes, porDia, festivos, alAbrir, alVerDia }) {
  const dias = diasDelMes(mes);
  const hoy = fechaIso();

  return (
    <div className="calendario" role="grid" aria-label={`Agenda de ${formatoMes.format(mes)}`}>
      {DIAS_SEMANA.map((dia) => (
        <div key={dia} className="calendario__dia-semana" role="columnheader">
          {dia}
        </div>
      ))}
      {dias.map((dia) => {
        const clave = fechaIso(dia);
        const eventos = porDia[clave] || [];
        const clases = ['calendario__celda'];
        if (dia.getMonth() !== mes.getMonth()) clases.push('calendario__celda--otro-mes');
        if (dia.getDay() === 0 || festivos[clave]) clases.push('calendario__celda--domingo');
        if (clave === hoy) clases.push('calendario__celda--hoy');
        return (
          <div key={clave} className={clases.join(' ')} role="gridcell" aria-label={`${clave}: ${eventos.length} eventos`}>
            <button type="button" className="calendario__numero tabular" onClick={() => alVerDia(dia)} title="Ver el día">
              {dia.getDate()}
            </button>
            {festivos[clave] && <span className="calendario__festivo">{festivos[clave]}</span>}
            {eventos.slice(0, MAXIMO_POR_DIA).map((e) => (
              <button
                key={e.clave}
                type="button"
                className={`calendario__evento calendario__evento--${e.clase}`}
                onClick={() => alAbrir(e)}
                title={`${e.titulo} · ${e.detalle} · ${e.estado}`}
              >
                {e.hora && <span className="tabular">{e.hora} </span>}
                <b>{e.titulo.split(' · ')[0]}</b> <span>{e.titulo.split(' · ')[1]}</span>
              </button>
            ))}
            {eventos.length > MAXIMO_POR_DIA && (
              <button type="button" className="calendario__mas" onClick={() => alVerDia(dia)}>
                +{eventos.length - MAXIMO_POR_DIA} más
              </button>
            )}
          </div>
        );
      })}
    </div>
  );
}

/**
 * RF-017, RF-018 y RF-023: calendario (agenda) por día, semana o mes con las órdenes de trabajo
 * y las visitas del plan que aún no tienen orden. El técnico solo ve sus órdenes.
 */
export default function Calendario() {
  const notificar = useNotificar();
  const navegar = useNavigate();
  const { tienePermiso, esTecnico } = useAutenticacion();
  const [parametros] = useSearchParams();
  const [vista, setVista] = useState(() => (VISTAS.some((v) => v.valor === parametros.get('vista')) ? parametros.get('vista') : 'semana'));
  const [ancla, setAncla] = useState(() => {
    const fecha = parametros.get('fecha');
    return fecha && /^\d{4}-\d{2}-\d{2}$/.test(fecha) ? new Date(`${fecha}T00:00:00`) : inicioDelDia(new Date());
  });
  const [mostrar, setMostrar] = useState('todo');
  const [eventos, setEventos] = useState(null);
  const [festivos, setFestivos] = useState({});
  const [error, setError] = useState('');
  const [visita, setVisita] = useState(null);

  const verOrdenes = tienePermiso('ordenes.ver');
  // RF-023: el técnico solo ve las órdenes que tiene asignadas.
  const verVisitas = tienePermiso('mantenimientos.ver') && !esTecnico;

  const { desde, hasta, diasVisibles } = useMemo(() => {
    if (vista === 'dia') return { desde: ancla, hasta: ancla, diasVisibles: [ancla] };
    if (vista === 'semana') {
      const lunes = lunesDe(ancla);
      const semana = Array.from({ length: 7 }, (_, i) => sumarDias(lunes, i));
      return { desde: lunes, hasta: semana[6], diasVisibles: semana };
    }
    const dias = diasDelMes(ancla);
    return { desde: dias[0], hasta: dias[dias.length - 1], diasVisibles: dias };
  }, [vista, ancla]);

  const cargar = useCallback(() => {
    setError('');
    const rango = { desde: fechaIso(desde), hasta: fechaIso(hasta) };
    Promise.all([
      verOrdenes && mostrar !== 'visitas' ? ordenes.agenda(rango).then((r) => r.datos.map(desdeOrden)) : [],
      verVisitas && mostrar !== 'ordenes' ? mantenimientos.listar({ ...rango, sin_orden: 1 }).then((r) => r.datos.map(desdeVisita)) : [],
    ])
      .then(([deOrdenes, deVisitas]) => setEventos([...deOrdenes, ...deVisitas]))
      .catch((e) => setError(e.message));
  }, [desde, hasta, verOrdenes, verVisitas, mostrar]);

  useEffect(cargar, [cargar]);

  useEffect(() => {
    const anios = [...new Set([desde.getFullYear(), hasta.getFullYear()])];
    Promise.all(anios.map((anio) => servicioFestivos.listar(anio).catch(() => ({ datos: [] })))).then((respuestas) => {
      const mapa = {};
      respuestas.forEach((r) => (Array.isArray(r?.datos) ? r.datos : []).forEach((f) => (mapa[f.fecha] = f.nombre)));
      setFestivos(mapa);
    });
  }, [desde, hasta]);

  const porDia = useMemo(() => {
    const grupos = {};
    (eventos || []).forEach((e) => (grupos[e.fecha] ||= []).push(e));
    Object.values(grupos).forEach((lista) => lista.sort((a, b) => (a.hora || '').localeCompare(b.hora || '')));
    return grupos;
  }, [eventos]);

  function abrir(evento) {
    if (evento.tipo === 'orden') navegar(`/ordenes/${evento.id}`);
    else setVisita(evento.datos);
  }

  function mover(delta) {
    setAncla((actual) => {
      if (vista === 'dia') return sumarDias(actual, delta);
      if (vista === 'semana') return sumarDias(actual, 7 * delta);
      return new Date(actual.getFullYear(), actual.getMonth() + delta, 1);
    });
  }

  // En la semana se oculta el domingo si no tiene nada programado (no es día hábil).
  const domingo = vista === 'semana' ? diasVisibles[6] : null;
  const domingoVacio = domingo && !(porDia[fechaIso(domingo)] || []).length;
  const columnas = vista === 'semana' && domingoVacio ? diasVisibles.slice(0, 6) : diasVisibles;

  const total = (eventos || []).filter((e) => vista !== 'mes' || e.fecha.slice(0, 7) === fechaIso(ancla).slice(0, 7)).length;
  const rango =
    vista === 'dia'
      ? capitalizar(formatoDia.format(ancla))
      : vista === 'semana'
        ? `${formatoCorto.format(desde)} – ${formatoCorto.format(hasta)}`
        : capitalizar(formatoMes.format(ancla));

  return (
    <>
      <div className="pagina__encabezado">
        <div>
          <p className="etiqueta-superior">
            {formatoMes.format(ancla).toUpperCase()} · {total} {total === 1 ? 'EVENTO' : 'EVENTOS'}
          </p>
          <h1 className="pagina__titulo">Calendario</h1>
          <ListaDescripcion
            elementos={
              esTecnico
                ? ['Sus órdenes asignadas', 'Toque una orden para avanzar su estado']
                : ['Sin cruces de horario por técnico', 'Domingos y festivos pasan al siguiente día hábil']
            }
          />
        </div>
        <div className="pagina__acciones">
          <div className="segmento" role="group" aria-label="Vista del calendario">
            {VISTAS.map((v) => (
              <button key={v.valor} type="button" aria-pressed={vista === v.valor} onClick={() => setVista(v.valor)}>
                {v.texto}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="agenda__barra">
        <div className="calendario__navegacion">
          <button type="button" className="boton-icono" onClick={() => mover(-1)} aria-label="Anterior">
            <ChevronLeft size={18} />
          </button>
          <button type="button" className="boton-icono" onClick={() => mover(1)} aria-label="Siguiente">
            <ChevronRight size={18} />
          </button>
          <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setAncla(inicioDelDia(new Date()))}>
            Hoy
          </button>
          <h2 className="calendario__mes">{rango}</h2>
        </div>
        <div className="leyenda">
          {LEYENDA.filter((l) => verVisitas || l.clase !== 'visita').map((l) => (
            <span key={l.clase}>
              <i className={`leyenda__color leyenda__color--${l.clase}`} />
              {l.texto}
            </span>
          ))}
        </div>
        <span style={{ flex: 1 }} />
        {vista === 'semana' && domingoVacio && <span className="texto-pequeno texto-suave">Domingo {domingo.getDate()} sin programación</span>}
        {verOrdenes && verVisitas && (
          <select className="campo__control" style={{ width: 'auto' }} value={mostrar} onChange={(e) => setMostrar(e.target.value)} aria-label="Mostrar">
            <option value="todo">Órdenes y visitas</option>
            <option value="ordenes">Solo órdenes</option>
            <option value="visitas">Solo visitas sin orden</option>
          </select>
        )}
      </div>

      {error && <Alerta>{error}</Alerta>}

      <section className="tarjeta" style={{ overflow: 'hidden' }}>
        {eventos === null ? (
          <Cargando />
        ) : vista === 'mes' ? (
          <VistaMes
            mes={ancla}
            porDia={porDia}
            festivos={festivos}
            alAbrir={abrir}
            alVerDia={(dia) => {
              setAncla(dia);
              setVista('dia');
            }}
          />
        ) : (
          <div className="agenda__desplazable">
            <VistaHoras dias={columnas} porDia={porDia} festivos={festivos} alAbrir={abrir} />
          </div>
        )}
      </section>

      {visita && (
        <ModalMantenimiento
          mantenimiento={visita}
          alCerrar={() => setVisita(null)}
          alGuardar={(mensaje) => {
            setVisita(null);
            notificar(mensaje);
            cargar();
          }}
        />
      )}
    </>
  );
}

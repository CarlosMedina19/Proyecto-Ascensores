import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { ArrowRight, ArrowUpDown, Wrench } from 'lucide-react';
import { equipos as servicioEquipos, mantenimientos as servicioMantenimientos } from '../api/servicios';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { Alerta, Cargando, EstadoVacio, Insignia, Tabla, Tarjeta } from '../componentes/Basicos';
import { ESTADOS_EQUIPO, diasHasta, estadoMantenimiento, fecha, fechaIso, nombreSaludo } from '../utilidades/formato';
import PanelOperacion from './inicio/PanelOperacion';

function Estadistica({ icono: Icono, valor, etiqueta, detalle, tono = 'primario' }) {
  const tonos = {
    primario: ['var(--color-primario-suave)', 'var(--color-primario)'],
    exito: ['var(--color-exito-suave)', 'var(--color-exito)'],
    alerta: ['var(--color-alerta-suave)', 'var(--color-alerta)'],
    cian: ['var(--color-cian-suave)', 'var(--color-cian)'],
  };
  const [fondo, color] = tonos[tono];
  return (
    <div className="tarjeta estadistica">
      <span className="estadistica__cabecera">
        <span className="estadistica__icono" style={{ background: fondo, color }}>
          <Icono size={15} aria-hidden="true" />
        </span>
        {etiqueta}
      </span>
      <span className="estadistica__valor">{valor}</span>
      {detalle && <span className="estadistica__detalle">{detalle}</span>}
    </div>
  );
}

/** RF-017: visitas pendientes de los próximos 30 días (incluye las atrasadas del último mes). */
function ProximosMantenimientos() {
  const [lista, setLista] = useState(null);
  const navegar = useNavigate();

  useEffect(() => {
    const hoy = new Date();
    const desde = new Date(hoy);
    desde.setDate(hoy.getDate() - 30);
    const hasta = new Date(hoy);
    hasta.setDate(hoy.getDate() + 30);
    servicioMantenimientos
      .listar({ desde: fechaIso(desde), hasta: fechaIso(hasta) })
      .then((r) => setLista(r.datos.filter((m) => m.estado === 'programado' || m.estado === 'reprogramado')))
      .catch(() => setLista([]));
  }, []);

  return (
    <Tarjeta
      titulo="Próximos mantenimientos"
      acciones={
        <Link to="/calendario" className="boton boton--fantasma boton--pequeno">
          Ver calendario <ArrowRight size={14} />
        </Link>
      }
    >
      {lista === null ? (
        <Cargando />
      ) : lista.length ? (
        <ul className="visitas">
          {lista.slice(0, 6).map((m) => {
            const estado = estadoMantenimiento(m);
            const dias = diasHasta(m.fecha_programada);
            return (
              <li key={m.id}>
                <button type="button" className="visitas__boton" onClick={() => navegar(`/equipos/${m.equipo_id}`)}>
                  <span>
                    <b>{m.equipo?.codigo}</b> <span className="texto-suave">· {m.equipo?.edificio?.nombre}</span>
                    <span className="tabla__secundario">
                      {fecha(m.fecha_programada)} · {m.plan?.nombre}
                    </span>
                  </span>
                  <Insignia tipo={estado.tipo}>{dias < 0 ? estado.texto : dias === 0 ? 'Hoy' : `En ${dias} días`}</Insignia>
                </button>
              </li>
            );
          })}
        </ul>
      ) : (
        <EstadoVacio titulo="Sin visitas en los próximos 30 días" />
      )}
    </Tarjeta>
  );
}

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

/** Inicio para cliente y técnico: resumen de los ascensores que puede consultar. */
function PanelEquipos({ nombre }) {
  const { esCliente, usuario, tienePermiso } = useAutenticacion();
  const [lista, setLista] = useState(null);
  const [error, setError] = useState('');
  const navegar = useNavigate();

  useEffect(() => {
    servicioEquipos
      .listar({ por_pagina: 100 })
      .then((respuesta) => setLista(respuesta.datos))
      .catch((e) => setError(e.message));
  }, []);

  if (error) return <Alerta>{error}</Alerta>;
  if (!lista) return <Cargando />;

  const conteo = { activo: 0, mantenimiento: 0, inactivo: 0 };
  lista.forEach((equipo) => {
    conteo[equipo.estado] = (conteo[equipo.estado] || 0) + 1;
  });

  return (
    <>
      <div className="pagina__encabezado">
        <div>
          <p className="etiqueta-superior">{esCliente ? `Portal del cliente · ${usuario.cliente?.nombre ?? ''}` : fechaLarga()}</p>
          <h1 className="pagina__titulo">
            {saludo()}{nombre ? `, ${nombre}` : ''}. {esCliente ? 'Tiene' : 'Hay'} <span className="resalte">{conteo.activo} ascensores</span> en servicio.
          </h1>
          <p className="pagina__descripcion">
            {esCliente
              ? 'Consulte la ficha técnica y el historial de los ascensores de sus edificios.'
              : 'Consulte la ficha técnica de cada equipo y registre los eventos de sus visitas en el historial.'}
          </p>
        </div>
      </div>
      <div className="rejilla">
        <div className="rejilla rejilla--3">
          <Estadistica icono={ArrowUpDown} valor={lista.length} etiqueta={esCliente ? 'Mis ascensores' : 'Ascensores'} />
          <Estadistica icono={ArrowUpDown} valor={conteo.activo} etiqueta="En servicio" tono="exito" />
          <Estadistica icono={Wrench} valor={conteo.mantenimiento} etiqueta="En mantenimiento" tono="alerta" />
        </div>
        {tienePermiso('mantenimientos.ver') && <ProximosMantenimientos />}
        <Tarjeta sinRelleno>
          <div className="tarjeta__cuerpo" style={{ paddingBottom: 0 }}>
            <h2 className="tarjeta__titulo">{esCliente ? 'Mis ascensores' : 'Ascensores'}</h2>
          </div>
          <Tabla
            filas={lista.slice(0, 10)}
            alSeleccionar={(equipo) => navegar(`/equipos/${equipo.id}`)}
            columnas={[
              { clave: 'codigo', titulo: 'Código', render: (e) => <strong>{e.codigo}</strong> },
              { clave: 'edificio', titulo: 'Edificio', render: (e) => e.edificio?.nombre },
              { clave: 'ubicacion', titulo: 'Ubicación' },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (e) => <Insignia tipo={ESTADOS_EQUIPO[e.estado]?.tipo}>{ESTADOS_EQUIPO[e.estado]?.texto}</Insignia>,
              },
            ]}
            vacio={<EstadoVacio titulo="No hay ascensores asociados" />}
          />
        </Tarjeta>
      </div>
    </>
  );
}

export default function Inicio() {
  const { usuario, tienePermiso } = useAutenticacion();
  const nombre = nombreSaludo(usuario?.nombre);

  if (tienePermiso('resumen.ver')) return <PanelOperacion nombre={nombre} />;
  if (tienePermiso('equipos.ver')) return <PanelEquipos nombre={nombre} />;
  return <EstadoVacio titulo="Bienvenido a SIGMA" descripcion="Su rol aún no tiene módulos asignados." />;
}

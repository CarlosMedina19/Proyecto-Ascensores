import { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { clientes, edificios, equipos, mantenimientos, ordenes } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Datos, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto } from '../../componentes/Campos';
import { PRIORIDADES_ORDEN, SERVICIOS_ORDEN, fecha, fechaIso, periodicidad } from '../../utilidades/formato';
import SelectorTecnicos from './SelectorTecnicos';

const DURACIONES = [30, 45, 60, 90, 120, 180, 240, 480].map((m) => ({
  valor: String(m),
  texto: m < 60 ? `${m} minutos` : m % 60 ? `${Math.floor(m / 60)} h ${m % 60} min` : `${m / 60} ${m === 60 ? 'hora' : 'horas'}`,
}));

/**
 * RF-025: nueva orden de trabajo. Desde una visita del plan (?mantenimiento_id=) toma el
 * ascensor, la fecha y la hora; desde un ascensor (?equipo_id=) lo deja seleccionado.
 */
export default function FormularioOrden() {
  const [parametros] = useSearchParams();
  const navegar = useNavigate();
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const mantenimientoId = parametros.get('mantenimiento_id');
  const [visita, setVisita] = useState(null);
  const [listaClientes, setListaClientes] = useState(null);
  const [listaEdificios, setListaEdificios] = useState([]);
  const [listaEquipos, setListaEquipos] = useState([]);
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    servicio: 'correctivo',
    cliente_id: '',
    edificio_id: '',
    equipo_id: '',
    fecha: fechaIso(),
    hora: '08:00',
    duracion_estimada: '90',
    prioridad: 'media',
    descripcion: '',
    observaciones: '',
    tecnicos: [],
  });

  useEffect(() => {
    clientes
      .listar({ por_pagina: 100, estado: 1 })
      .then((r) => setListaClientes(r.datos))
      .catch(() => setListaClientes([]));
  }, []);

  // Desde una visita programada: se toman sus datos.
  useEffect(() => {
    if (!mantenimientoId) return;
    mantenimientos.obtener(mantenimientoId).then(({ datos: m }) => {
      setVisita(m);
      setDatos((actual) => ({
        ...actual,
        servicio: 'preventivo',
        fecha: m.fecha_programada,
        hora: m.hora || '08:00',
        descripcion: actual.descripcion || `Mantenimiento preventivo · ${m.plan?.nombre ?? ''}`.trim(),
      }));
    });
  }, [mantenimientoId, setDatos]);

  // Desde un ascensor: se selecciona su cliente, edificio y equipo.
  useEffect(() => {
    const equipoId = parametros.get('equipo_id');
    if (!equipoId || mantenimientoId) return;
    equipos.obtener(equipoId).then(({ datos: e }) =>
      setDatos((actual) => ({
        ...actual,
        cliente_id: String(e.edificio?.cliente_id ?? e.edificio?.cliente?.id ?? ''),
        edificio_id: String(e.edificio_id),
        equipo_id: String(e.id),
      })),
    );
  }, [parametros, mantenimientoId, setDatos]);

  useEffect(() => {
    if (!datos.cliente_id) return setListaEdificios([]);
    edificios
      .listar({ cliente_id: datos.cliente_id, por_pagina: 100 })
      .then((r) => setListaEdificios(r.datos))
      .catch(() => setListaEdificios([]));
    return undefined;
  }, [datos.cliente_id]);

  useEffect(() => {
    if (!datos.edificio_id) return setListaEquipos([]);
    equipos
      .listar({ edificio_id: datos.edificio_id, por_pagina: 100 })
      .then((r) => setListaEquipos(r.datos))
      .catch(() => setListaEquipos([]));
    return undefined;
  }, [datos.edificio_id]);

  async function guardar(evento) {
    evento.preventDefault();
    const cuerpo = {
      fecha: datos.fecha,
      hora: datos.hora,
      duracion_estimada: Number(datos.duracion_estimada),
      prioridad: datos.prioridad,
      descripcion: datos.descripcion || null,
      observaciones: datos.observaciones || null,
      tecnicos: datos.tecnicos.length ? datos.tecnicos : undefined,
    };
    if (mantenimientoId) {
      cuerpo.mantenimiento_id = Number(mantenimientoId);
    } else {
      Object.assign(cuerpo, {
        servicio: datos.servicio,
        cliente_id: datos.cliente_id ? Number(datos.cliente_id) : null,
        edificio_id: datos.edificio_id ? Number(datos.edificio_id) : null,
        equipo_id: datos.equipo_id ? Number(datos.equipo_id) : null,
      });
    }

    const respuesta = await enviar(() => ordenes.crear(cuerpo));
    if (respuesta) {
      notificar(`Orden ${respuesta.datos.numero} creada.`);
      navegar(`/ordenes/${respuesta.datos.id}`, { replace: true });
    }
  }

  if (listaClientes === null || (mantenimientoId && !visita)) return <Cargando />;

  return (
    <>
      <EncabezadoPagina titulo="Nueva orden de trabajo" migas={<Link to="/ordenes">Órdenes</Link>} />
      <form className="formulario" onSubmit={guardar} noValidate>
        <Alerta>{mensajeError}</Alerta>

        <Tarjeta titulo="Servicio y ascensor">
          {visita ? (
            <>
              <Alerta tipo="info">Orden preventiva para la visita programada del plan. Al finalizarla, la visita queda ejecutada y se recalcula la siguiente.</Alerta>
              <Datos
                elementos={[
                  ['Ascensor', visita.equipo?.codigo],
                  ['Edificio', visita.equipo?.edificio?.nombre],
                  ['Cliente', visita.equipo?.edificio?.cliente?.nombre],
                  ['Plan', `${visita.plan?.nombre ?? ''} · ${periodicidad(visita.periodicidad?.meses)}`],
                  ['Visita programada', `${fecha(visita.fecha_programada)} · ${visita.hora ?? ''}`],
                ]}
              />
            </>
          ) : (
            <div className="formulario">
              <CampoSelector
                etiqueta="Servicio"
                obligatorio
                vacio={null}
                valor={datos.servicio}
                alCambiar={cambiar('servicio')}
                error={error('servicio')}
                opciones={Object.entries(SERVICIOS_ORDEN).map(([valor, { texto }]) => ({ valor, texto }))}
                ayuda={datos.servicio === 'preventivo' ? 'Para las visitas del plan, cree la orden desde el calendario.' : undefined}
              />
              <div className="formulario__fila formulario__fila--3">
                <CampoSelector
                  etiqueta="Cliente"
                  obligatorio
                  valor={datos.cliente_id}
                  alCambiar={(v) => setDatos((a) => ({ ...a, cliente_id: v, edificio_id: '', equipo_id: '' }))}
                  error={error('cliente_id')}
                  opciones={listaClientes.map((c) => ({ valor: String(c.id), texto: c.nombre }))}
                />
                <CampoSelector
                  etiqueta="Edificio"
                  obligatorio
                  valor={datos.edificio_id}
                  alCambiar={(v) => setDatos((a) => ({ ...a, edificio_id: v, equipo_id: '' }))}
                  error={error('edificio_id')}
                  disabled={!datos.cliente_id}
                  opciones={listaEdificios.map((e) => ({ valor: String(e.id), texto: e.nombre }))}
                />
                <CampoSelector
                  etiqueta="Ascensor"
                  obligatorio
                  valor={datos.equipo_id}
                  alCambiar={cambiar('equipo_id')}
                  error={error('equipo_id')}
                  disabled={!datos.edificio_id}
                  opciones={listaEquipos.map((e) => ({ valor: String(e.id), texto: `${e.codigo}${e.ubicacion ? ` · ${e.ubicacion}` : ''}` }))}
                />
              </div>
            </div>
          )}
        </Tarjeta>

        <Tarjeta titulo="Fecha y prioridad">
          <div className="formulario__fila formulario__fila--3">
            <CampoTexto etiqueta="Fecha" tipo="date" obligatorio min={fechaIso()} valor={datos.fecha} alCambiar={cambiar('fecha')} error={error('fecha')} />
            <CampoTexto etiqueta="Hora" tipo="time" obligatorio step="900" valor={datos.hora} alCambiar={cambiar('hora')} error={error('hora')} />
            <CampoSelector etiqueta="Duración estimada" vacio={null} valor={datos.duracion_estimada} alCambiar={cambiar('duracion_estimada')} error={error('duracion_estimada')} opciones={DURACIONES} />
          </div>
          <CampoSelector
            etiqueta="Prioridad"
            vacio={null}
            valor={datos.prioridad}
            alCambiar={cambiar('prioridad')}
            error={error('prioridad')}
            opciones={Object.entries(PRIORIDADES_ORDEN).map(([valor, { texto }]) => ({ valor, texto }))}
            ayuda={datos.prioridad === 'critica' ? 'Una orden crítica avisa de inmediato a los coordinadores.' : undefined}
          />
        </Tarjeta>

        <Tarjeta titulo="Descripción">
          <div className="formulario">
            <CampoArea etiqueta="Trabajo a realizar" valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} rows={3} placeholder="Falla reportada, solicitud del cliente…" />
            <CampoArea etiqueta="Observaciones" valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} rows={2} />
          </div>
        </Tarjeta>

        {tienePermiso('ordenes.asignar') && (
          <Tarjeta titulo={`Técnicos (${datos.tecnicos.length})`}>
            <p className="texto-pequeno texto-suave" style={{ marginTop: 0 }}>
              Opcional. Sin técnicos la orden queda <b>programada</b>; con al menos uno pasa a <b>asignada</b> y se le avisa.
            </p>
            {error('tecnicos') && <Alerta>{error('tecnicos')}</Alerta>}
            <SelectorTecnicos
              fecha={datos.fecha}
              hora={datos.hora}
              duracion={datos.duracion_estimada}
              seleccion={datos.tecnicos}
              alCambiar={(lista) => setDatos((a) => ({ ...a, tecnicos: lista }))}
            />
          </Tarjeta>
        )}

        <div className="formulario__acciones">
          <button type="button" className="boton boton--secundario" onClick={() => navegar(-1)}>
            Cancelar
          </button>
          <button type="submit" className="boton" disabled={enviando}>
            {enviando ? 'Guardando…' : 'Crear orden'}
          </button>
        </div>
      </form>
    </>
  );
}

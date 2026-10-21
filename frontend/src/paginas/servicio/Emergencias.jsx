import { useEffect, useState } from 'react';
import { Siren, Search } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { equipos, servicio, tecnicos } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useFormulario } from '../../hooks/useFormulario';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Modal, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto } from '../../componentes/Campos';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { fechaHora } from '../../utilidades/formato';
import { ESTADOS_EMERGENCIA, TIPOS_EMERGENCIA, duracion } from '../../utilidades/servicio';

/** Semáforo del tiempo de respuesta frente al objetivo. */
export function TiempoRespuesta({ emergencia }) {
  const { minutos_respuesta: minutos, minutos_objetivo: objetivo, dentro_objetivo: dentro, llegada_en: llegada } = emergencia;
  if (minutos === null || minutos === undefined) return <span className="texto-suave">—</span>;
  const tipo = dentro === false ? 'peligro' : llegada ? 'exito' : minutos > objetivo * 0.75 ? 'alerta' : 'info';
  return (
    <Insignia tipo={tipo}>
      {duracion(minutos)}
      {llegada ? '' : ' en curso'} · objetivo {duracion(objetivo)}
    </Insignia>
  );
}

/** Emergencias y atrapamientos 24/7: las abiertas primero, con su tiempo de respuesta. */
export default function Emergencias() {
  const { tienePermiso } = useAutenticacion();
  const navegar = useNavigate();
  const [reportar, setReportar] = useState(false);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(servicio.emergencias, { estado: 'abiertas' });

  // Las abiertas se actualizan solas cada 30 segundos.
  useEffect(() => {
    if (filtros.estado !== 'abiertas') return undefined;
    const reloj = setInterval(recargar, 30000);
    return () => clearInterval(reloj);
  }, [filtros.estado, recargar]);

  return (
    <>
      <EncabezadoPagina
        titulo="Emergencias"
        descripcion={['Atrapamientos y fallas', 'Reporte desde el panel, la app o el QR', 'Tiempo de llegada frente al objetivo']}
      >
        <HerramientasExcel modulo="servicio" alImportar={recargar} />
        {tienePermiso('emergencias.reportar') && (
          <button type="button" className="boton boton--peligro" onClick={() => setReportar(true)} data-recorrido="emergencia">
            <Siren size={16} aria-hidden="true" /> Reportar emergencia
          </button>
        )}
      </EncabezadoPagina>


      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por número, ascensor o ubicación"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar emergencias"
            />
          </div>
          <select className="campo__control" value={filtros.estado} onChange={(e) => cambiarFiltro('estado', e.target.value)} aria-label="Estado">
            <option value="abiertas">Abiertas</option>
            <option value="">Todas</option>
            <option value="resuelta">Resueltas</option>
            <option value="cancelada">Canceladas</option>
          </select>
        </div>
        {error && (
          <div style={{ padding: 16 }}>
            <Alerta>{error}</Alerta>
          </div>
        )}
        {cargando && !filas.length ? (
          <Cargando />
        ) : (
          <Tabla
            filas={filas}
            alSeleccionar={(e) => navegar(`/emergencias/${e.id}`)}
            vacio={
              <EstadoVacio
                titulo={filtros.estado === 'abiertas' ? 'No hay emergencias abiertas' : 'No hay emergencias para mostrar'}
                descripcion="Cuando alguien reporte una falla o un atrapamiento, aparecerá aquí con su tiempo de respuesta."
              />
            }
            columnas={[
              {
                clave: 'numero',
                titulo: 'Emergencia',
                render: (e) => (
                  <>
                    <strong>{e.numero}</strong>
                    <div className="tabla__secundario">{TIPOS_EMERGENCIA[e.tipo]}</div>
                  </>
                ),
              },
              {
                clave: 'equipo',
                titulo: 'Ascensor',
                render: (e) => (
                  <>
                    {e.equipo?.codigo}
                    <div className="tabla__secundario">{e.equipo?.edificio?.nombre}</div>
                  </>
                ),
              },
              { clave: 'reportada', titulo: 'Reportada', render: (e) => fechaHora(e.reportada_en) },
              { clave: 'tecnico', titulo: 'Técnico', render: (e) => e.tecnico?.nombre || <span className="texto-suave">Sin asignar</span> },
              { clave: 'respuesta', titulo: 'Respuesta', render: (e) => <TiempoRespuesta emergencia={e} /> },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (e) => <Insignia tipo={ESTADOS_EMERGENCIA[e.estado]?.tipo}>{ESTADOS_EMERGENCIA[e.estado]?.texto}</Insignia>,
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>

      {reportar && (
        <ModalReportarEmergencia
          alCerrar={(emergencia) => {
            setReportar(false);
            if (emergencia) navegar(`/emergencias/${emergencia.id}`);
          }}
        />
      )}
    </>
  );
}

/** Reporte desde el panel. Si se abre desde la ficha del ascensor, llega con el ascensor elegido. */
export function ModalReportarEmergencia({ equipo, alCerrar }) {
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const [opcionesEquipo, setOpcionesEquipo] = useState([]);
  const [opcionesTecnico, setOpcionesTecnico] = useState([]);
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    equipo_id: equipo ? String(equipo.id) : '',
    tipo: 'falla',
    descripcion: '',
    reportante_nombre: '',
    reportante_telefono: '',
    tecnico_id: '',
  });

  useEffect(() => {
    if (!equipo) {
      equipos
        .listar({ por_pagina: 100 })
        .then((r) => setOpcionesEquipo(r.datos))
        .catch(() => {});
    }
    if (tienePermiso('emergencias.gestionar')) {
      tecnicos
        .listar({ por_pagina: 100, activo: 1 })
        .then((r) => setOpcionesTecnico(r.datos))
        .catch(() => {});
    }
  }, [equipo, tienePermiso]);

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() =>
      servicio.reportarEmergencia({
        ...datos,
        equipo_id: datos.equipo_id ? Number(datos.equipo_id) : null,
        tecnico_id: datos.tecnico_id ? Number(datos.tecnico_id) : null,
      }),
    );
    if (respuesta) {
      notificar(respuesta.mensaje);
      alCerrar(respuesta.datos);
    }
  }

  return (
    <Modal
      titulo="Reportar emergencia"
      abierto
      alCerrar={() => alCerrar(null)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(null)}>
            Cancelar
          </button>
          <button type="submit" form="formulario-emergencia" className="boton boton--peligro" disabled={enviando}>
            {enviando ? 'Reportando…' : 'Reportar emergencia'}
          </button>
        </>
      }
    >
      <form id="formulario-emergencia" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        {equipo ? (
          <p className="texto-suave" style={{ margin: 0 }}>
            Ascensor <b>{equipo.codigo}</b> · {equipo.ubicacion || equipo.edificio?.nombre}
          </p>
        ) : (
          <CampoSelector
            etiqueta="Ascensor"
            obligatorio
            valor={datos.equipo_id}
            alCambiar={cambiar('equipo_id')}
            error={error('equipo_id')}
            opciones={opcionesEquipo.map((e) => ({ valor: String(e.id), texto: `${e.codigo} · ${e.edificio?.nombre || e.ubicacion || ''}` }))}
          />
        )}
        <CampoSelector
          etiqueta="Qué pasa"
          obligatorio
          vacio={null}
          valor={datos.tipo}
          alCambiar={cambiar('tipo')}
          error={error('tipo')}
          opciones={Object.entries(TIPOS_EMERGENCIA).map(([valor, texto]) => ({ valor, texto }))}
          ayuda={datos.tipo === 'atrapamiento' ? 'Con personas atrapadas el tiempo objetivo de llegada es más corto.' : undefined}
        />
        <CampoArea etiqueta="Descripción" valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} rows={3} placeholder="Piso, ruido, puertas, mensajes del tablero…" />
        <div className="formulario__fila">
          <CampoTexto etiqueta="Quién reporta" valor={datos.reportante_nombre} alCambiar={cambiar('reportante_nombre')} error={error('reportante_nombre')} placeholder="Portería Torre A" />
          <CampoTexto etiqueta="Teléfono" inputMode="tel" valor={datos.reportante_telefono} alCambiar={cambiar('reportante_telefono')} error={error('reportante_telefono')} />
        </div>
        {opcionesTecnico.length > 0 && (
          <CampoSelector
            etiqueta="Técnico que atiende"
            valor={datos.tecnico_id}
            alCambiar={cambiar('tecnico_id')}
            error={error('tecnico_id')}
            vacio="Asignar después"
            opciones={opcionesTecnico.filter((t) => t.id).map((t) => ({ valor: String(t.id), texto: t.nombre }))}
          />
        )}
      </form>
    </Modal>
  );
}

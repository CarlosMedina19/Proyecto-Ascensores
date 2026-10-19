import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Pencil, Power } from 'lucide-react';
import { ordenes, tecnicos } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, Confirmacion, Datos, EncabezadoPagina, EstadoVacio, Insignia, Tabla, Tarjeta } from '../../componentes/Basicos';
import { ESTADOS_ORDEN, PRIORIDADES_ORDEN, SERVICIOS_ORDEN, fecha, fechaHora, fechaIso } from '../../utilidades/formato';
import { EstadoTecnico } from './ListaTecnicos';

export default function DetalleTecnico() {
  const { id } = useParams();
  const navegar = useNavigate();
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const [tecnico, setTecnico] = useState(null);
  const [agenda, setAgenda] = useState(null);
  const [error, setError] = useState('');
  const [confirmar, setConfirmar] = useState(false);
  const [procesando, setProcesando] = useState(false);

  const cargar = useCallback(() => {
    tecnicos
      .obtener(id)
      .then((r) => {
        setTecnico(r.datos);
        if (r.datos.id && tienePermiso('ordenes.ver')) {
          const hoy = new Date();
          const hasta = new Date(hoy);
          hasta.setDate(hoy.getDate() + 30);
          ordenes
            .agenda({ desde: fechaIso(hoy), hasta: fechaIso(hasta), tecnico_id: r.datos.id })
            .then((a) => setAgenda(a.datos))
            .catch(() => setAgenda([]));
        } else {
          setAgenda([]);
        }
      })
      .catch((e) => setError(e.message));
  }, [id, tienePermiso]);

  useEffect(cargar, [cargar]);

  async function alternarEstado() {
    setProcesando(true);
    try {
      await tecnicos.cambiarEstado(id, !tecnico.activo);
      notificar(tecnico.activo ? 'Técnico desactivado.' : 'Técnico activado.');
      cargar();
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setProcesando(false);
      setConfirmar(false);
    }
  }

  if (error) return <Alerta>{error}</Alerta>;
  if (!tecnico) return <Cargando />;

  const puedeEditar = tienePermiso('tecnicos.editar');

  return (
    <>
      <EncabezadoPagina
        titulo={tecnico.nombre}
        migas={<Link to="/tecnicos">Técnicos</Link>}
        descripcion={
          <>
            {tecnico.correo} · <EstadoTecnico tecnico={tecnico} />
          </>
        }
      >
        {puedeEditar && (
          <button type="button" className="boton boton--secundario" onClick={() => setConfirmar(true)}>
            <Power size={16} /> {tecnico.activo ? 'Desactivar' : 'Activar'}
          </button>
        )}
        {puedeEditar && (
          <Link to={`/tecnicos/${id}/editar`} className="boton">
            <Pencil size={16} /> {tecnico.perfil_completo ? 'Editar perfil' : 'Completar perfil'}
          </Link>
        )}
      </EncabezadoPagina>

      {!tecnico.perfil_completo && (
        <Alerta tipo="alerta">Este técnico no tiene perfil (documento y especialidades). No puede recibir órdenes hasta completarlo.</Alerta>
      )}

      <div className="rejilla rejilla--2" style={{ alignItems: 'start' }}>
        <Tarjeta titulo="Datos del técnico">
          <Datos
            elementos={[
              ['Documento', tecnico.numero_documento ? `${tecnico.tipo_documento} ${tecnico.numero_documento}` : null],
              ['Teléfono', tecnico.telefono],
              ['Disponibilidad', tecnico.disponibilidad === 'disponible' ? 'Disponible' : tecnico.disponibilidad ? 'No disponible' : null],
              ['Motivo', tecnico.motivo_no_disponible],
              ['Disponible desde', tecnico.no_disponible_hasta ? fecha(tecnico.no_disponible_hasta) : null],
              ['Órdenes activas', tecnico.ordenes_activas ?? 0],
              ['Último acceso', fechaHora(tecnico.ultimo_acceso_en)],
            ]}
          />
          {tecnico.especialidades.length > 0 && (
            <>
              <p className="etiqueta-superior" style={{ marginTop: 16 }}>
                Especialidades
              </p>
              <div className="precios">
                {tecnico.especialidades.map((e) => (
                  <span key={e.id} className="precios__item">
                    {e.nombre}
                  </span>
                ))}
              </div>
            </>
          )}
          {tecnico.observaciones && <p style={{ marginBottom: 0, whiteSpace: 'pre-line' }}>{tecnico.observaciones}</p>}
        </Tarjeta>

        <Tarjeta sinRelleno>
          <div className="tarjeta__cuerpo" style={{ paddingBottom: 0 }}>
            <h2 className="tarjeta__titulo">Agenda de los próximos 30 días</h2>
          </div>
          {agenda === null ? (
            <Cargando />
          ) : (
            <Tabla
              filas={agenda}
              alSeleccionar={(o) => navegar(`/ordenes/${o.id}`)}
              vacio={<EstadoVacio titulo="Sin órdenes asignadas" />}
              columnas={[
                {
                  clave: 'fecha',
                  titulo: 'Fecha',
                  render: (o) => (
                    <>
                      <strong className="tabular">{fecha(o.fecha)}</strong>
                      <div className="tabla__secundario tabular">
                        {o.hora} – {o.fin_estimado}
                      </div>
                    </>
                  ),
                },
                {
                  clave: 'orden',
                  titulo: 'Orden',
                  render: (o) => (
                    <>
                      <span className="tabla__codigo">{o.numero}</span>
                      <div className="tabla__secundario">
                        {o.equipo?.codigo} · {o.edificio?.nombre}
                      </div>
                    </>
                  ),
                },
                { clave: 'servicio', titulo: 'Servicio', render: (o) => <Insignia tipo={SERVICIOS_ORDEN[o.servicio]?.tipo}>{SERVICIOS_ORDEN[o.servicio]?.texto}</Insignia> },
                { clave: 'prioridad', titulo: 'Prioridad', render: (o) => <Insignia tipo={PRIORIDADES_ORDEN[o.prioridad]?.tipo}>{PRIORIDADES_ORDEN[o.prioridad]?.texto}</Insignia> },
                { clave: 'estado', titulo: 'Estado', render: (o) => <Insignia tipo={ESTADOS_ORDEN[o.estado]?.tipo}>{ESTADOS_ORDEN[o.estado]?.texto}</Insignia> },
              ]}
            />
          )}
        </Tarjeta>
      </div>

      <Confirmacion
        abierto={confirmar}
        titulo={tecnico.activo ? 'Desactivar técnico' : 'Activar técnico'}
        mensaje={
          tecnico.activo
            ? `El técnico pierde el acceso y deja de recibir órdenes.${tecnico.ordenes_activas ? ` Tiene ${tecnico.ordenes_activas} órdenes activas que conviene reasignar.` : ''}`
            : 'El técnico recupera el acceso y puede recibir órdenes si está disponible.'
        }
        textoConfirmar={tecnico.activo ? 'Desactivar' : 'Activar'}
        peligro={tecnico.activo}
        procesando={procesando}
        alConfirmar={alternarEstado}
        alCerrar={() => setConfirmar(false)}
      />
    </>
  );
}

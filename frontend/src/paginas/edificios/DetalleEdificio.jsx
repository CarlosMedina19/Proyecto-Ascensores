import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Pencil, Plus, Power, Trash2 } from 'lucide-react';
import { edificios } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, Confirmacion, Datos, EncabezadoPagina, EstadoVacio, Insignia, Tabla, Tarjeta } from '../../componentes/Basicos';
import { ESTADOS_EQUIPO, fechaHora } from '../../utilidades/formato';

export default function DetalleEdificio() {
  const { id } = useParams();
  const navegar = useNavigate();
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const [edificio, setEdificio] = useState(null);
  const [error, setError] = useState('');
  const [accion, setAccion] = useState(null); // 'eliminar' | 'estado'
  const [procesando, setProcesando] = useState(false);

  const cargar = useCallback(() => {
    edificios
      .obtener(id)
      .then((r) => setEdificio(r.datos))
      .catch((e) => setError(e.message));
  }, [id]);

  useEffect(cargar, [cargar]);

  async function confirmar() {
    setProcesando(true);
    try {
      if (accion === 'eliminar') {
        await edificios.eliminar(id);
        notificar('Edificio eliminado.');
        navegar('/edificios', { replace: true });
        return;
      }
      await edificios.actualizar(id, { activo: !edificio.activo });
      notificar(edificio.activo ? 'Edificio desactivado.' : 'Edificio activado.');
      cargar();
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setProcesando(false);
      setAccion(null);
    }
  }

  if (error) return <Alerta>{error}</Alerta>;
  if (!edificio) return <Cargando />;

  const lista = edificio.equipos || [];

  return (
    <>
      <EncabezadoPagina
        titulo={edificio.nombre}
        migas={<Link to="/edificios">Edificios</Link>}
        descripcion={
          <>
            <Link to={`/clientes/${edificio.cliente_id}`}>{edificio.cliente?.nombre}</Link> ·{' '}
            <Insignia tipo={edificio.activo ? 'exito' : 'neutro'}>{edificio.activo ? 'Activo' : 'Inactivo'}</Insignia>
          </>
        }
      >
        {tienePermiso('edificios.eliminar') && (
          <button type="button" className="boton boton--secundario" onClick={() => setAccion('eliminar')}>
            <Trash2 size={16} /> Eliminar
          </button>
        )}
        {tienePermiso('edificios.editar') && (
          <>
            <button type="button" className="boton boton--secundario" onClick={() => setAccion('estado')}>
              <Power size={16} /> {edificio.activo ? 'Desactivar' : 'Activar'}
            </button>
            <Link to={`/edificios/${id}/editar`} className="boton">
              <Pencil size={16} /> Editar
            </Link>
          </>
        )}
      </EncabezadoPagina>

      <div className="rejilla">
        <div className="rejilla rejilla--2">
          <Tarjeta titulo="Ubicación">
            <Datos
              elementos={[
                ['Dirección', edificio.direccion],
                ['Ciudad', edificio.ciudad],
                ['Departamento', edificio.departamento],
                ['Código postal', edificio.codigo_postal],
                ['Pisos', edificio.pisos],
                ['Registrado', fechaHora(edificio.created_at)],
              ]}
            />
            {edificio.observaciones && (
              <>
                <div className="separador" />
                <div className="texto-pequeno texto-suave">Observaciones</div>
                <p style={{ margin: '4px 0 0' }}>{edificio.observaciones}</p>
              </>
            )}
          </Tarjeta>
          <Tarjeta titulo="Contacto del edificio">
            <Datos
              columnas={2}
              elementos={[
                ['Nombre', edificio.nombre_contacto],
                ['Teléfono', edificio.telefono_contacto],
                ['Correo', edificio.correo_contacto],
              ]}
            />
          </Tarjeta>
        </div>

        <Tarjeta sinRelleno>
          <div className="tarjeta__cuerpo" style={{ paddingBottom: 0 }}>
            <h2 className="tarjeta__titulo">
              <span>Ascensores ({lista.length})</span>
              {tienePermiso('equipos.crear') && edificio.activo && (
                <Link to={`/equipos/nuevo?edificio_id=${id}`} className="boton boton--secundario boton--pequeno">
                  <Plus size={15} /> Nuevo ascensor
                </Link>
              )}
            </h2>
          </div>
          <Tabla
            filas={lista}
            alSeleccionar={(equipo) => navegar(`/equipos/${equipo.id}`)}
            vacio={<EstadoVacio titulo="El edificio no tiene ascensores registrados" descripcion="Registre el primer ascensor desde el botón de este edificio." />}
            columnas={[
              { clave: 'codigo', titulo: 'Código', render: (e) => <strong>{e.codigo}</strong> },
              { clave: 'marca', titulo: 'Marca / modelo', render: (e) => [e.marca, e.modelo].filter(Boolean).join(' · ') || '—' },
              { clave: 'ubicacion', titulo: 'Ubicación' },
              {
                clave: 'capacidad',
                titulo: 'Capacidad',
                render: (e) => (e.ascensor?.capacidad_kg ? `${e.ascensor.capacidad_kg} kg` : '—'),
              },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (e) => <Insignia tipo={ESTADOS_EQUIPO[e.estado]?.tipo}>{ESTADOS_EQUIPO[e.estado]?.texto}</Insignia>,
              },
            ]}
          />
        </Tarjeta>
      </div>

      <Confirmacion
        abierto={accion !== null}
        titulo={accion === 'eliminar' ? 'Eliminar edificio' : edificio.activo ? 'Desactivar edificio' : 'Activar edificio'}
        mensaje={
          accion === 'eliminar'
            ? 'Un edificio con ascensores activos no se puede eliminar; en ese caso desactívelo.'
            : edificio.activo
              ? 'Un edificio inactivo no admite nuevos ascensores. Su historial se conserva.'
              : 'El edificio volverá a admitir ascensores.'
        }
        textoConfirmar={accion === 'eliminar' ? 'Eliminar' : 'Confirmar'}
        peligro={accion === 'eliminar'}
        procesando={procesando}
        alConfirmar={confirmar}
        alCerrar={() => setAccion(null)}
      />
    </>
  );
}

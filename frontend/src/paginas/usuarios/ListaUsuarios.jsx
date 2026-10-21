import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Pencil, Plus, Power, Search } from 'lucide-react';
import { roles, usuarios } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, Confirmacion, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { fechaHora, nombreRol } from '../../utilidades/formato';

export default function ListaUsuarios() {
  const { tienePermiso, usuario: usuarioActual } = useAutenticacion();
  const notificar = useNotificar();
  const [opcionesRoles, setOpcionesRoles] = useState([]);
  const [seleccionado, setSeleccionado] = useState(null);
  const [procesando, setProcesando] = useState(false);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(usuarios.listar, {
    rol_id: '',
    activo: '',
  });

  useEffect(() => {
    roles
      .listar()
      .then((r) => setOpcionesRoles(r.datos))
      .catch(() => {});
  }, []);

  async function alternarEstado() {
    setProcesando(true);
    try {
      const respuesta = await usuarios.alternarEstado(seleccionado.id);
      notificar(respuesta.datos.activo ? 'Usuario activado.' : 'Usuario desactivado. Sus sesiones abiertas se cerraron.');
      recargar();
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setProcesando(false);
      setSeleccionado(null);
    }
  }

  const puedeEditar = tienePermiso('usuarios.editar');

  return (
    <>
      <EncabezadoPagina titulo="Usuarios" descripcion="Cuentas de acceso al sistema. Los usuarios no se eliminan: se desactivan y conservan su historial.">
        {tienePermiso('usuarios.crear') && (
          <Link to="/usuarios/nuevo" className="boton">
            <Plus size={16} /> Nuevo usuario
          </Link>
        )}
      </EncabezadoPagina>

      <div className="barra-excel">
        <HerramientasExcel modulo="usuarios" alImportar={recargar} />
      </div>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por nombre o correo"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar usuarios"
            />
          </div>
          <select className="campo__control" value={filtros.rol_id} onChange={(e) => cambiarFiltro('rol_id', e.target.value)} aria-label="Rol">
            <option value="">Todos los roles</option>
            {opcionesRoles.map((rol) => (
              <option key={rol.id} value={rol.id}>
                {nombreRol(rol.nombre)}
              </option>
            ))}
          </select>
          <select className="campo__control" value={filtros.activo} onChange={(e) => cambiarFiltro('activo', e.target.value)} aria-label="Estado">
            <option value="">Todos los estados</option>
            <option value="1">Activos</option>
            <option value="0">Inactivos</option>
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
            vacio={<EstadoVacio titulo="No se encontraron usuarios" />}
            columnas={[
              {
                clave: 'nombre',
                titulo: 'Usuario',
                render: (u) => (
                  <>
                    <strong>{u.nombre}</strong>
                    <div className="tabla__secundario">{u.correo}</div>
                  </>
                ),
              },
              {
                clave: 'rol',
                titulo: 'Rol',
                render: (u) => (
                  <>
                    {nombreRol(u.rol?.nombre)}
                    {u.cliente && <div className="tabla__secundario">{u.cliente.nombre}</div>}
                  </>
                ),
              },
              {
                clave: 'verificado',
                titulo: 'Correo',
                render: (u) => (u.correo_verificado_en ? <Insignia tipo="info">Verificado</Insignia> : <Insignia>Sin verificar</Insignia>),
              },
              { clave: 'ultimo_acceso_en', titulo: 'Último acceso', render: (u) => fechaHora(u.ultimo_acceso_en) },
              {
                clave: 'activo',
                titulo: 'Estado',
                render: (u) => <Insignia tipo={u.activo ? 'exito' : 'neutro'}>{u.activo ? 'Activo' : 'Inactivo'}</Insignia>,
              },
              {
                clave: 'acciones',
                titulo: <span className="oculto-visual">Acciones</span>,
                clase: 'tabla__acciones',
                render: (u) =>
                  puedeEditar && (
                    <>
                      <Link to={`/usuarios/${u.id}/editar`} className="boton boton--fantasma" title="Editar" aria-label={`Editar ${u.nombre}`}>
                        <Pencil size={16} />
                      </Link>
                      {u.id !== usuarioActual.id && (
                        <button
                          type="button"
                          className="boton boton--fantasma"
                          title={u.activo ? 'Desactivar' : 'Activar'}
                          aria-label={`${u.activo ? 'Desactivar' : 'Activar'} ${u.nombre}`}
                          onClick={() => setSeleccionado(u)}
                        >
                          <Power size={16} />
                        </button>
                      )}
                    </>
                  ),
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>

      <Confirmacion
        abierto={Boolean(seleccionado)}
        titulo={seleccionado?.activo ? 'Desactivar usuario' : 'Activar usuario'}
        mensaje={
          seleccionado?.activo
            ? `${seleccionado?.nombre} no podrá ingresar al sistema y sus sesiones abiertas se cerrarán. Su historial se conserva.`
            : `${seleccionado?.nombre} podrá volver a ingresar al sistema.`
        }
        textoConfirmar={seleccionado?.activo ? 'Desactivar' : 'Activar'}
        peligro={seleccionado?.activo}
        procesando={procesando}
        alConfirmar={alternarEstado}
        alCerrar={() => setSeleccionado(null)}
      />
    </>
  );
}

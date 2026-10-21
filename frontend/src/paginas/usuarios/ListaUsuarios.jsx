import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { roles, usuarios } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import AccionesRegistro from '../../componentes/AccionesRegistro';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { fechaHora, nombreRol } from '../../utilidades/formato';

export default function ListaUsuarios() {
  const { tienePermiso, usuario: usuarioActual } = useAutenticacion();
  const [opcionesRoles, setOpcionesRoles] = useState([]);
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

  const puedeEditar = tienePermiso('usuarios.editar');

  return (
    <>
      <EncabezadoPagina titulo="Usuarios" descripcion={['Cuentas de acceso al sistema', 'Activar, desactivar o eliminar', 'El historial se conserva']}>
        <HerramientasExcel modulo="usuarios" alImportar={recargar} />
        {tienePermiso('usuarios.crear') && (
          <Link to="/usuarios/nuevo" className="boton">
            <Plus size={16} /> Crear usuario
          </Link>
        )}
      </EncabezadoPagina>


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
                render: (u) => (
                  <AccionesRegistro
                    nombre={u.nombre}
                    activo={u.activo}
                    rutaEditar={puedeEditar ? `/usuarios/${u.id}/editar` : null}
                    alAlternar={puedeEditar && u.id !== usuarioActual.id ? () => usuarios.alternarEstado(u.id) : null}
                    alEliminar={puedeEditar && u.id !== usuarioActual.id ? () => usuarios.eliminar(u.id) : null}
                    mensajeDesactivar={`${u.nombre} no podrá ingresar al sistema y sus sesiones abiertas se cerrarán. Su historial se conserva.`}
                    mensajeEliminar={`La cuenta de ${u.nombre} se elimina y no podrá ingresar. Las órdenes, el historial y la auditoría conservan su nombre.`}
                    alCambiar={recargar}
                  />
                ),
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>

    </>
  );
}

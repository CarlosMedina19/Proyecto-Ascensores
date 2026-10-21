import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { tecnicos, usuarios } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import AccionesRegistro from '../../componentes/AccionesRegistro';
import { iniciales } from '../../utilidades/formato';

/** Disponibilidad del técnico para recibir órdenes (RF-022). */
export function EstadoTecnico({ tecnico }) {
  if (!tecnico.activo) return <Insignia tipo="neutro">Inactivo</Insignia>;
  if (!tecnico.perfil_completo) return <Insignia tipo="alerta">Perfil incompleto</Insignia>;
  if (tecnico.disponibilidad !== 'disponible') return <Insignia tipo="peligro">No disponible</Insignia>;
  return <Insignia tipo="exito">Disponible</Insignia>;
}

export default function ListaTecnicos() {
  const { tienePermiso } = useAutenticacion();
  const navegar = useNavigate();
  const [especialidades, setEspecialidades] = useState([]);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(tecnicos.listar, {
    disponibilidad: '',
    especialidad_id: '',
    activo: '',
  });

  useEffect(() => {
    tecnicos
      .especialidades()
      .then((r) => setEspecialidades(r.datos))
      .catch(() => {});
  }, []);

  return (
    <>
      <EncabezadoPagina titulo="Técnicos" descripcion={['Especialidades', 'Disponibilidad y estado', 'Solo los activos y disponibles reciben órdenes']}>
        {tienePermiso('tecnicos.crear') && (
          <Link to="/tecnicos/nuevo" className="boton">
            <Plus size={16} /> Registrar técnico
          </Link>
        )}
      </EncabezadoPagina>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por nombre, correo o documento"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar técnicos"
            />
          </div>
          <select className="campo__control" value={filtros.especialidad_id} onChange={(e) => cambiarFiltro('especialidad_id', e.target.value)} aria-label="Especialidad">
            <option value="">Todas las especialidades</option>
            {especialidades.map((e) => (
              <option key={e.id} value={e.id}>
                {e.nombre}
              </option>
            ))}
          </select>
          <select className="campo__control" value={filtros.disponibilidad} onChange={(e) => cambiarFiltro('disponibilidad', e.target.value)} aria-label="Disponibilidad">
            <option value="">Cualquier disponibilidad</option>
            <option value="disponible">Disponibles</option>
            <option value="no_disponible">No disponibles</option>
          </select>
          <select className="campo__control" value={filtros.activo} onChange={(e) => cambiarFiltro('activo', e.target.value)} aria-label="Estado">
            <option value="">Activos e inactivos</option>
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
            claveFila="usuario_id"
            alSeleccionar={(t) => navegar(`/tecnicos/${t.usuario_id}`)}
            vacio={<EstadoVacio titulo="No se encontraron técnicos" />}
            columnas={[
              {
                clave: 'nombre',
                titulo: 'Técnico',
                render: (t) => (
                  <span className="equipo-nombre">
                    <span className="avatar" aria-hidden="true">
                      {iniciales(t.nombre)}
                    </span>
                    <span>
                      <strong>{t.nombre}</strong>
                      <div className="tabla__secundario">{t.correo}</div>
                    </span>
                  </span>
                ),
              },
              {
                clave: 'documento',
                titulo: 'Documento',
                render: (t) => (t.numero_documento ? `${t.tipo_documento} ${t.numero_documento}` : '—'),
              },
              {
                clave: 'especialidades',
                titulo: 'Especialidades',
                render: (t) =>
                  t.especialidades.length ? (
                    <div className="precios">
                      {t.especialidades.map((e) => (
                        <span key={e.id} className="precios__item">
                          {e.nombre}
                        </span>
                      ))}
                    </div>
                  ) : (
                    <span className="texto-suave">—</span>
                  ),
              },
              { clave: 'telefono', titulo: 'Teléfono' },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (t) => (
                  <AccionesRegistro
                    nombre={t.nombre}
                    activo={t.activo}
                    insignia={<EstadoTecnico tecnico={t} />}
                    rutaEditar={tienePermiso('tecnicos.editar') ? `/tecnicos/${t.usuario_id}/editar` : null}
                    alAlternar={tienePermiso('tecnicos.editar') ? () => tecnicos.cambiarEstado(t.usuario_id, !t.activo) : null}
                    alEliminar={tienePermiso('usuarios.editar') ? () => usuarios.eliminar(t.usuario_id) : null}
                    mensajeDesactivar="El técnico no podrá ingresar ni recibir órdenes nuevas. Sus órdenes e informes se conservan."
                    mensajeEliminar="La cuenta del técnico se elimina y no podrá ingresar. Sus órdenes, firmas e informes conservan su nombre."
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

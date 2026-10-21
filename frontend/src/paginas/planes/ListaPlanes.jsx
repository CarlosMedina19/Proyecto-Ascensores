import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { planes } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import AccionesRegistro from '../../componentes/AccionesRegistro';
import { dinero } from '../../utilidades/formato';

/** Precios del plan como "1 m · $ 380.000". */
export function PreciosPlan({ precios = [] }) {
  if (!precios.length) return <span className="texto-suave">Sin precios</span>;
  return (
    <div className="precios">
      {precios
        .slice()
        .sort((a, b) => (a.periodicidad?.meses ?? 0) - (b.periodicidad?.meses ?? 0))
        .map((precio) => (
          <span key={precio.periodicidad_id} className="precios__item">
            <b>{precio.periodicidad?.nombre ?? `${precio.periodicidad?.meses} m`}</b> {dinero(precio.precio)}
          </span>
        ))}
    </div>
  );
}

export default function ListaPlanes() {
  const { tienePermiso } = useAutenticacion();
  const navegar = useNavigate();
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(planes.listar, { activo: '' });

  return (
    <>
      <EncabezadoPagina
        titulo="Planes de mantenimiento"
        descripcion={['Servicios incluidos', 'Precio por visita', 'Periodicidad de 1 a 6 meses']}
      >
        {tienePermiso('planes.crear') && (
          <Link to="/planes/nuevo" className="boton">
            <Plus size={16} /> Crear plan
          </Link>
        )}
      </EncabezadoPagina>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar plan por nombre"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar planes"
            />
          </div>
          <select className="campo__control" value={filtros.activo} onChange={(e) => cambiarFiltro('activo', e.target.value)} aria-label="Estado">
            <option value="">Todos</option>
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
            alSeleccionar={(plan) => navegar(`/planes/${plan.id}`)}
            vacio={<EstadoVacio titulo="No hay planes registrados" />}
            columnas={[
              {
                clave: 'nombre',
                titulo: 'Plan',
                render: (p) => (
                  <>
                    <strong>{p.nombre}</strong>
                    <div className="tabla__secundario">{p.servicios_incluidos.length} servicios incluidos</div>
                  </>
                ),
              },
              { clave: 'precios', titulo: 'Precio por visita según periodicidad', render: (p) => <PreciosPlan precios={p.precios} /> },
              { clave: 'asignaciones', titulo: 'Ascensores', clase: 'tabular', render: (p) => p.asignaciones_activas_count ?? 0 },
              {
                clave: 'activo',
                titulo: 'Estado',
                render: (p) => (
                  <AccionesRegistro
                    nombre={`el plan ${p.nombre}`}
                    activo={p.activo}
                    rutaEditar={tienePermiso('planes.editar') ? `/planes/${p.id}/editar` : null}
                    alAlternar={tienePermiso('planes.editar') ? () => planes.alternarEstado(p.id) : null}
                    alEliminar={tienePermiso('planes.eliminar') ? () => planes.eliminar(p.id) : null}
                    mensajeDesactivar="Un plan inactivo no se puede asignar a ascensores nuevos. Las asignaciones vigentes siguen igual."
                    mensajeEliminar="Solo se puede eliminar un plan sin ascensores asignados."
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

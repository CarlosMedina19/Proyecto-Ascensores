import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { clientes, edificios } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import AccionesRegistro from '../../componentes/AccionesRegistro';
import { AscensoresDelEdificio } from '../../componentes/Despliegues';
import { TIPOS_EDIFICIO } from '../../utilidades/formato';

/**
 * Edificios de todos los clientes, o solo los de un tipo (conjuntos, institucionales o
 * residenciales) cuando se abre desde el menú de Clientes. Cada fila despliega sus ascensores.
 */
export default function ListaEdificios({ tipo }) {
  const { tienePermiso, esCliente } = useAutenticacion();
  const navegar = useNavigate();
  const [opcionesClientes, setOpcionesClientes] = useState([]);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(edificios.listar, {
    cliente_id: '',
    activo: '',
    tipo: tipo || '',
  });
  const grupo = TIPOS_EDIFICIO[tipo];

  useEffect(() => {
    if (esCliente || !tienePermiso('clientes.ver')) return;
    clientes
      .listar({ por_pagina: 100 })
      .then((r) => setOpcionesClientes(r.datos))
      .catch(() => {});
  }, [esCliente, tienePermiso]);

  return (
    <>
      <EncabezadoPagina
        titulo={grupo ? grupo.plural : esCliente ? 'Mis edificios' : 'Edificios'}
        descripcion={
          grupo
            ? [`Edificios de tipo ${grupo.texto.toLowerCase()}`, 'Dirección, ciudad y contacto', 'Despliegue una fila para ver sus ascensores']
            : ['Dirección, ciudad y contacto', 'Despliegue una fila para ver sus ascensores']
        }
        migas={grupo ? <Link to="/clientes">Clientes</Link> : undefined}
      >
        {tienePermiso('edificios.crear') && (
          <Link to={tipo ? `/edificios/nuevo?tipo=${tipo}` : '/edificios/nuevo'} className="boton">
            <Plus size={16} /> Registrar edificio
          </Link>
        )}
      </EncabezadoPagina>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por nombre, dirección o ciudad"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar edificios"
            />
          </div>
          {opcionesClientes.length > 0 && (
            <select className="campo__control" value={filtros.cliente_id} onChange={(e) => cambiarFiltro('cliente_id', e.target.value)} aria-label="Cliente">
              <option value="">Todos los clientes</option>
              {opcionesClientes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.nombre}
                </option>
              ))}
            </select>
          )}
          {!grupo && (
            <select className="campo__control" value={filtros.tipo} onChange={(e) => cambiarFiltro('tipo', e.target.value)} aria-label="Tipo de edificio">
              <option value="">Todos los tipos</option>
              {Object.entries(TIPOS_EDIFICIO).map(([valor, { texto }]) => (
                <option key={valor} value={valor}>
                  {texto}
                </option>
              ))}
            </select>
          )}
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
            alSeleccionar={(edificio) => navegar(`/edificios/${edificio.id}`)}
            expandir={(edificio) => <AscensoresDelEdificio edificioId={edificio.id} />}
            etiquetaExpandir="Ver ascensores de"
            vacio={<EstadoVacio titulo={grupo ? `No hay edificios de tipo ${grupo.texto.toLowerCase()}` : 'No se encontraron edificios'} />}
            columnas={[
              {
                clave: 'nombre',
                titulo: 'Edificio',
                render: (e) => (
                  <>
                    <strong>{e.nombre}</strong>
                    <div className="tabla__secundario">
                      {[e.cliente?.nombre, !grupo && TIPOS_EDIFICIO[e.tipo]?.texto].filter(Boolean).join(' · ')}
                    </div>
                  </>
                ),
              },
              {
                clave: 'direccion',
                titulo: 'Dirección',
                render: (e) => (
                  <>
                    {e.direccion}
                    <div className="tabla__secundario">
                      {[e.ciudad, e.departamento].filter(Boolean).join(', ')}
                    </div>
                  </>
                ),
              },
              {
                clave: 'contacto',
                titulo: 'Contacto',
                render: (e) => (
                  <>
                    {e.nombre_contacto || '—'}
                    <div className="tabla__secundario">{e.telefono_contacto || e.correo_contacto}</div>
                  </>
                ),
              },
              { clave: 'equipos_count', titulo: 'Ascensores' },
              {
                clave: 'activo',
                titulo: 'Estado',
                render: (e) => (
                  <AccionesRegistro
                    nombre={e.nombre}
                    activo={e.activo}
                    rutaEditar={tienePermiso('edificios.editar') ? `/edificios/${e.id}/editar` : null}
                    alAlternar={tienePermiso('edificios.editar') ? () => edificios.alternarEstado(e.id) : null}
                    alEliminar={tienePermiso('edificios.eliminar') ? () => edificios.eliminar(e.id) : null}
                    mensajeEliminar="Solo se puede eliminar un edificio sin ascensores activos. Si los tiene, desactívelo."
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

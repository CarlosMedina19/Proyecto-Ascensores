import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { clientes, conjuntos } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoBuscador } from '../../componentes/Campos';
import AccionesRegistro from '../../componentes/AccionesRegistro';
import { TIPOS_EDIFICIO } from '../../utilidades/formato';

/** Conjuntos, instituciones o residencias (según el submenú de Clientes) con sus edificios y ascensores. */
export default function ListaConjuntos({ tipo }) {
  const { tienePermiso, esCliente } = useAutenticacion();
  const navegar = useNavigate();
  const [opcionesClientes, setOpcionesClientes] = useState([]);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(conjuntos.listar, { cliente_id: '', activo: '', tipo });
  const grupo = TIPOS_EDIFICIO[tipo];

  useEffect(() => {
    if (esCliente || !tienePermiso('clientes.ver')) return;
    clientes
      .listar({ por_pagina: 100 })
      .then((r) => setOpcionesClientes(r.datos.map((c) => ({ valor: String(c.id), texto: c.nombre, detalle: c.nit || c.numero_documento }))))
      .catch(() => {});
  }, [esCliente, tienePermiso]);

  return (
    <>
      <EncabezadoPagina
        titulo={grupo.plural}
        descripcion={[`Cada ${grupo.texto.toLowerCase()} agrupa edificios de un cliente`, 'Sus edificios heredan la dirección y el contacto', 'Abra uno para ver sus edificios y ascensores']}
        migas={<Link to="/clientes">Clientes</Link>}
      >
        <Link to={`${grupo.ruta}/ascensores`} className="boton boton--secundario">
          Ver ascensores
        </Link>
        {tienePermiso('edificios.crear') && (
          <Link to={`/conjuntos/nuevo?tipo=${tipo}`} className="boton">
            <Plus size={16} /> Registrar {grupo.texto.toLowerCase()}
          </Link>
        )}
      </EncabezadoPagina>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por nombre, cliente, dirección o ciudad"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label={`Buscar ${grupo.plural.toLowerCase()}`}
            />
          </div>
          {opcionesClientes.length > 0 && (
            <div className="filtros__combo">
              <CampoBuscador valor={filtros.cliente_id} alCambiar={(v) => cambiarFiltro('cliente_id', v)} opciones={opcionesClientes} placeholder="Todos los clientes" aria-label="Cliente" />
            </div>
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
            alSeleccionar={(c) => navegar(`/conjuntos/${c.id}`)}
            vacio={<EstadoVacio titulo={`No hay ${grupo.plural.toLowerCase()} registrados`} descripcion="Registre uno y luego agregue sus edificios." />}
            columnas={[
              {
                clave: 'nombre',
                titulo: grupo.texto,
                render: (c) => (
                  <>
                    <strong>{c.nombre}</strong>
                    <div className="tabla__secundario">{c.cliente?.nombre}</div>
                  </>
                ),
              },
              {
                clave: 'direccion',
                titulo: 'Dirección',
                render: (c) => (
                  <>
                    {c.direccion}
                    <div className="tabla__secundario">{[c.ciudad, c.departamento].filter(Boolean).join(', ')}</div>
                  </>
                ),
              },
              { clave: 'edificios_count', titulo: 'Edificios', clase: 'numero' },
              { clave: 'equipos_count', titulo: 'Ascensores', clase: 'numero' },
              {
                clave: 'activo',
                titulo: 'Estado',
                render: (c) => (
                  <AccionesRegistro
                    nombre={c.nombre}
                    activo={c.activo}
                    rutaEditar={tienePermiso('edificios.editar') ? `/conjuntos/${c.id}/editar` : null}
                    alAlternar={tienePermiso('edificios.editar') ? () => conjuntos.alternarEstado(c.id) : null}
                    alEliminar={tienePermiso('edificios.eliminar') ? () => conjuntos.eliminar(c.id) : null}
                    mensajeEliminar="Solo se puede eliminar sin edificios. Si los tiene, desactívelo."
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

import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { clientes, edificios } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';

export default function ListaEdificios() {
  const { tienePermiso, esCliente } = useAutenticacion();
  const navegar = useNavigate();
  const [opcionesClientes, setOpcionesClientes] = useState([]);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina } = useListado(edificios.listar, {
    cliente_id: '',
    activo: '',
  });

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
        titulo={esCliente ? 'Mis edificios' : 'Edificios'}
        descripcion="Edificios de cada cliente con su dirección, ciudad y contacto."
      >
        {tienePermiso('edificios.crear') && (
          <Link to="/edificios/nuevo" className="boton">
            <Plus size={16} /> Nuevo edificio
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
            vacio={<EstadoVacio titulo="No se encontraron edificios" />}
            columnas={[
              {
                clave: 'nombre',
                titulo: 'Edificio',
                render: (e) => (
                  <>
                    <strong>{e.nombre}</strong>
                    <div className="tabla__secundario">{e.cliente?.nombre}</div>
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
                render: (e) => <Insignia tipo={e.activo ? 'exito' : 'neutro'}>{e.activo ? 'Activo' : 'Inactivo'}</Insignia>,
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>
    </>
  );
}

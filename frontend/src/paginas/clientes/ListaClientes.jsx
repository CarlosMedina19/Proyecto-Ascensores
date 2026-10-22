import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { clientes } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import AccionesRegistro from '../../componentes/AccionesRegistro';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { ArbolCliente } from '../../componentes/Despliegues';
import { TIPOS_CLIENTE } from '../../utilidades/formato';

export default function ListaClientes() {
  const { tienePermiso, esCliente } = useAutenticacion();
  const navegar = useNavigate();
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(clientes.listar, {
    tipo: '',
    estado: '',
  });

  return (
    <>
      <EncabezadoPagina
        titulo={esCliente ? 'Mi empresa' : 'Clientes'}
        descripcion={['Personas naturales y jurídicas', 'Conjuntos, institucionales y residenciales', 'Despliegue un cliente para ver sus ascensores']}
      >
        <HerramientasExcel modulo="clientes" alImportar={recargar} filtros={filtros} />
        {tienePermiso('clientes.crear') && (
          <Link to="/clientes/nuevo" className="boton">
            <Plus size={16} /> Registrar cliente
          </Link>
        )}
      </EncabezadoPagina>


      <Tarjeta sinRelleno>
        {!esCliente && (
          <div className="filtros">
            <div className="filtros__busqueda">
              <Search size={16} aria-hidden="true" />
              <input
                className="campo__control"
                placeholder="Buscar por nombre, NIT, documento o correo"
                value={filtros.busqueda}
                onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
                aria-label="Buscar clientes"
              />
            </div>
            <select className="campo__control" value={filtros.tipo} onChange={(e) => cambiarFiltro('tipo', e.target.value)} aria-label="Tipo de persona">
              <option value="">Todos los tipos</option>
              <option value="juridica">Persona jurídica</option>
              <option value="natural">Persona natural</option>
            </select>
            <select className="campo__control" value={filtros.estado} onChange={(e) => cambiarFiltro('estado', e.target.value)} aria-label="Estado">
              <option value="">Todos los estados</option>
              <option value="1">Activos</option>
              <option value="0">Inactivos</option>
            </select>
          </div>
        )}

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
            alSeleccionar={(cliente) => navegar(`/clientes/${cliente.id}`)}
            expandir={(cliente) => <ArbolCliente clienteId={cliente.id} />}
            etiquetaExpandir="Ver edificios de"
            vacio={<EstadoVacio titulo="No se encontraron clientes" descripcion="Ajuste los filtros, registre un cliente o impórtelos desde Excel con la plantilla de SIGMA." />}
            columnas={[
              {
                clave: 'nombre',
                titulo: 'Cliente',
                render: (c) => (
                  <>
                    <strong>{c.nombre}</strong>
                    <div className="tabla__secundario">{TIPOS_CLIENTE[c.tipo]}</div>
                  </>
                ),
              },
              {
                clave: 'documento',
                titulo: 'NIT / Documento',
                render: (c) => (c.tipo === 'juridica' ? c.nit : `${c.tipo_documento || ''} ${c.numero_documento || ''}`.trim()) || '—',
              },
              {
                clave: 'contacto',
                titulo: 'Contacto',
                render: (c) => (
                  <>
                    {c.correo || '—'}
                    <div className="tabla__secundario">{c.telefono}</div>
                  </>
                ),
              },
              { clave: 'edificios_count', titulo: 'Edificios' },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (c) => (
                  <AccionesRegistro
                    nombre={c.nombre}
                    activo={c.estado}
                    rutaEditar={tienePermiso('clientes.editar') ? `/clientes/${c.id}/editar` : null}
                    alAlternar={tienePermiso('clientes.editar') ? () => clientes.alternarEstado(c.id) : null}
                    alEliminar={tienePermiso('clientes.eliminar') ? () => clientes.eliminar(c.id) : null}
                    mensajeEliminar="Solo se puede eliminar un cliente sin edificios. Si tiene edificios, desactívelo."
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

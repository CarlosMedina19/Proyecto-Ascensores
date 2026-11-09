import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { clientes, conjuntos } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import AccionesRegistro from '../../componentes/AccionesRegistro';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { EdificiosDeEntidad } from '../../componentes/ArbolEntidades';
import { TIPOS_CLIENTE, TIPOS_EDIFICIO } from '../../utilidades/formato';

/** Pestañas del módulo: las tres categorías de entidades y la lista de clientes. */
const PESTANAS = [
  ...Object.entries(TIPOS_EDIFICIO).map(([tipo, t]) => ({ vista: tipo, texto: t.plural, ruta: t.ruta })),
  { vista: 'clientes', texto: 'Todos los clientes', ruta: '/clientes/todos' },
];

/** NIT o documento del cliente (persona jurídica o natural). */
const documento = (c) => (c?.tipo === 'juridica' ? c.nit : `${c?.tipo_documento || ''} ${c?.numero_documento || ''}`.trim()) || '—';

/**
 * Clientes por categoría: Conjuntos, Instituciones y Residencias. Cada entidad se despliega en sus
 * edificios y cada edificio en sus ascensores (se cargan al desplegar). «Todos los clientes» lista
 * las personas y empresas dueñas.
 */
export default function ListaClientes({ vista = 'conjunto' }) {
  const { esCliente } = useAutenticacion();
  // Tras importar desde Excel la tabla se vuelve a montar para cargar los datos nuevos.
  const [version, setVersion] = useState(0);

  return (
    <>
      <EncabezadoPagina
        titulo={esCliente ? 'Mi empresa' : 'Clientes'}
        descripcion={['Conjuntos, instituciones y residencias', 'Despliegue una entidad para ver sus edificios', 'Despliegue un edificio para ver sus ascensores']}
      >
        <BotonesEncabezado vista={vista} alImportar={() => setVersion((v) => v + 1)} />
      </EncabezadoPagina>

      <nav className="pestanas-modulo" aria-label="Categorías de clientes">
        {PESTANAS.filter((p) => !esCliente || p.vista !== 'clientes').map((p) => (
          <Link key={p.vista} to={p.ruta} className="pestanas-modulo__enlace" aria-current={p.vista === vista ? 'page' : undefined}>
            {p.texto}
          </Link>
        ))}
      </nav>

      {vista === 'clientes' ? <TablaClientes key={`clientes-${version}`} /> : <TablaEntidades key={`${vista}-${version}`} tipo={vista} />}
    </>
  );
}

function BotonesEncabezado({ vista, alImportar }) {
  const { tienePermiso } = useAutenticacion();
  const tipo = TIPOS_EDIFICIO[vista];
  return (
    <>
      <HerramientasExcel modulo="clientes" alImportar={alImportar} />
      {tipo && tienePermiso('edificios.crear') && (
        <Link to={`/conjuntos/nuevo?tipo=${vista}`} className="boton">
          <Plus size={16} /> Registrar {tipo.texto.toLowerCase()}
        </Link>
      )}
      {tienePermiso('clientes.crear') && (
        <Link to="/clientes/nuevo" className={tipo ? 'boton boton--secundario' : 'boton'}>
          <Plus size={16} /> Registrar cliente
        </Link>
      )}
    </>
  );
}

/** Primer nivel: solo las entidades de la categoría (sin edificios ni ascensores hasta desplegarlas). */
function TablaEntidades({ tipo }) {
  const { tienePermiso } = useAutenticacion();
  const navegar = useNavigate();
  const grupo = TIPOS_EDIFICIO[tipo];
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(conjuntos.listar, { tipo, activo: '' });

  return (
    <Tarjeta sinRelleno>
      <div className="filtros">
        <div className="filtros__busqueda">
          <Search size={16} aria-hidden="true" />
          <input
            className="campo__control"
            placeholder="Buscar por nombre o por cliente"
            value={filtros.busqueda}
            onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
            aria-label={`Buscar en ${grupo.plural.toLowerCase()}`}
          />
        </div>
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
          alSeleccionar={(e) => navegar(`/conjuntos/${e.id}`)}
          expandir={(e) => <EdificiosDeEntidad entidad={e} />}
          etiquetaExpandir="Ver edificios de"
          vacio={<EstadoVacio titulo={`No hay ${grupo.plural.toLowerCase()} para mostrar`} descripcion="Ajuste la búsqueda o registre uno nuevo." />}
          columnas={[
            {
              clave: 'nombre',
              titulo: grupo.texto,
              render: (e) => (
                <>
                  <strong>{e.nombre}</strong>
                  <div className="tabla__secundario">{[e.direccion, e.ciudad].filter(Boolean).join(', ')}</div>
                </>
              ),
            },
            {
              clave: 'cliente',
              titulo: 'Cliente (dueño o responsable)',
              render: (e) => (
                <>
                  {e.cliente?.nombre ?? '—'}
                  <div className="tabla__secundario">{TIPOS_CLIENTE[e.cliente?.tipo]}</div>
                </>
              ),
            },
            { clave: 'documento', titulo: 'NIT / Documento', render: (e) => documento(e.cliente) },
            {
              clave: 'contacto',
              titulo: 'Contacto',
              render: (e) => (
                <>
                  {e.nombre_contacto || e.cliente?.correo || '—'}
                  <div className="tabla__secundario">{e.telefono_contacto || e.correo_contacto || e.cliente?.telefono}</div>
                </>
              ),
            },
            { clave: 'edificios_count', titulo: 'Edificios', clase: 'numero' },
            { clave: 'equipos_count', titulo: 'Ascensores', clase: 'numero' },
            {
              clave: 'estado',
              titulo: 'Estado',
              render: (e) => (
                <AccionesRegistro
                  nombre={e.nombre}
                  activo={e.activo}
                  rutaEditar={tienePermiso('edificios.editar') ? `/conjuntos/${e.id}/editar` : null}
                  alAlternar={tienePermiso('edificios.editar') ? () => conjuntos.alternarEstado(e.id) : null}
                  alEliminar={tienePermiso('edificios.eliminar') ? () => conjuntos.eliminar(e.id) : null}
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
  );
}

/** Personas y empresas dueñas, con las mismas columnas y acciones de siempre. */
function TablaClientes() {
  const { tienePermiso } = useAutenticacion();
  const navegar = useNavigate();
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(clientes.listar, { tipo: '', estado: '' });

  return (
    <Tarjeta sinRelleno>
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
            { clave: 'documento', titulo: 'NIT / Documento', render: documento },
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
  );
}

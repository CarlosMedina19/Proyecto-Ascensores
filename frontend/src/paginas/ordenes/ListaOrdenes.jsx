import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { ordenes } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { ESTADOS_ORDEN, PRIORIDADES_ORDEN, SERVICIOS_ORDEN, fecha } from '../../utilidades/formato';

/** Convierte el filtro "activas" de la lista en el parámetro de la API. */
function consultar(filtros) {
  const { estado, ...resto } = filtros;
  return ordenes.listar(estado === 'activas' ? { ...resto, activas: 1 } : filtros);
}

export default function ListaOrdenes() {
  const { tienePermiso, esTecnico } = useAutenticacion();
  const navegar = useNavigate();
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(consultar, {
    estado: 'activas',
    servicio: '',
    prioridad: '',
    desde: '',
    hasta: '',
  });

  return (
    <>
      <EncabezadoPagina
        titulo={esTecnico ? 'Mis órdenes' : 'Órdenes de trabajo'}
        descripcion={
          esTecnico
            ? 'Órdenes que tiene asignadas. Avance su estado desde el detalle de cada orden.'
            : 'Preventivos, correctivos e inspecciones con su prioridad, técnicos, estado y tiempos.'
        }
      >
        {tienePermiso('ordenes.crear') && (
          <Link to="/ordenes/nueva" className="boton">
            <Plus size={16} /> Crear orden de trabajo
          </Link>
        )}
      </EncabezadoPagina>

      <div className="barra-excel">
        <HerramientasExcel modulo="ordenes" alImportar={recargar} />
      </div>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por número, ascensor o edificio"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar órdenes"
            />
          </div>
          <select className="campo__control" value={filtros.estado} onChange={(e) => cambiarFiltro('estado', e.target.value)} aria-label="Estado">
            <option value="activas">Activas</option>
            <option value="">Todos los estados</option>
            {Object.entries(ESTADOS_ORDEN).map(([valor, { texto }]) => (
              <option key={valor} value={valor}>
                {texto}
              </option>
            ))}
          </select>
          <select className="campo__control" value={filtros.servicio} onChange={(e) => cambiarFiltro('servicio', e.target.value)} aria-label="Servicio">
            <option value="">Todos los servicios</option>
            {Object.entries(SERVICIOS_ORDEN).map(([valor, { texto }]) => (
              <option key={valor} value={valor}>
                {texto}
              </option>
            ))}
          </select>
          <select className="campo__control" value={filtros.prioridad} onChange={(e) => cambiarFiltro('prioridad', e.target.value)} aria-label="Prioridad">
            <option value="">Todas las prioridades</option>
            {Object.entries(PRIORIDADES_ORDEN).map(([valor, { texto }]) => (
              <option key={valor} value={valor}>
                {texto}
              </option>
            ))}
          </select>
          <input className="campo__control" style={{ width: 'auto' }} type="date" value={filtros.desde} onChange={(e) => cambiarFiltro('desde', e.target.value)} aria-label="Desde" />
          <input className="campo__control" style={{ width: 'auto' }} type="date" value={filtros.hasta} onChange={(e) => cambiarFiltro('hasta', e.target.value)} aria-label="Hasta" />
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
            alSeleccionar={(o) => navegar(`/ordenes/${o.id}`)}
            vacio={<EstadoVacio titulo="No se encontraron órdenes" />}
            columnas={[
              {
                clave: 'numero',
                titulo: 'Orden',
                render: (o) => (
                  <>
                    <strong className="tabla__codigo">{o.numero}</strong>
                    <div className="tabla__secundario">
                      {o.equipo?.codigo} · {o.edificio?.nombre}
                    </div>
                  </>
                ),
              },
              {
                clave: 'fecha',
                titulo: 'Fecha',
                render: (o) => (
                  <>
                    {fecha(o.fecha)}
                    <div className="tabla__secundario tabular">
                      {o.hora} – {o.fin_estimado}
                    </div>
                  </>
                ),
              },
              { clave: 'servicio', titulo: 'Servicio', render: (o) => <Insignia tipo={SERVICIOS_ORDEN[o.servicio]?.tipo}>{SERVICIOS_ORDEN[o.servicio]?.texto}</Insignia> },
              { clave: 'prioridad', titulo: 'Prioridad', render: (o) => <Insignia tipo={PRIORIDADES_ORDEN[o.prioridad]?.tipo}>{PRIORIDADES_ORDEN[o.prioridad]?.texto}</Insignia> },
              {
                clave: 'tecnicos',
                titulo: 'Técnicos',
                render: (o) => (o.tecnicos?.length ? o.tecnicos.map((t) => t.nombre).join(', ') : <span className="texto-suave">Sin asignar</span>),
              },
              { clave: 'estado', titulo: 'Estado', render: (o) => <Insignia tipo={ESTADOS_ORDEN[o.estado]?.tipo}>{ESTADOS_ORDEN[o.estado]?.texto}</Insignia> },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>
    </>
  );
}

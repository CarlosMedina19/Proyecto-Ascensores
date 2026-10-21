import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { equipos } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { ResumenAscensor } from '../../componentes/Despliegues';
import { ESTADOS_EQUIPO, TIPOS_EDIFICIO, numero } from '../../utilidades/formato';

/** Todos los ascensores, o solo los de un tipo de edificio cuando se abre desde Clientes en el menú. */
export default function ListaEquipos({ tipo }) {
  const { tienePermiso, esCliente } = useAutenticacion();
  const navegar = useNavigate();
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(equipos.listar, {
    estado: '',
    tipo_edificio: tipo || '',
  });
  const grupo = TIPOS_EDIFICIO[tipo];

  return (
    <>
      <EncabezadoPagina
        titulo={grupo ? `Ascensores · ${grupo.plural}` : esCliente ? 'Mis ascensores' : 'Ascensores'}
        descripcion={
          grupo
            ? `Ascensores de los edificios de tipo ${grupo.texto.toLowerCase()}, con su ficha técnica, estado e historial.`
            : 'Ascensores registrados con su ficha técnica, estado e historial.'
        }
        migas={grupo ? <Link to={grupo.ruta}>{grupo.plural}</Link> : undefined}
      >
        {tienePermiso('equipos.crear') && (
          <Link to="/equipos/nuevo" className="boton">
            <Plus size={16} /> Registrar ascensor
          </Link>
        )}
      </EncabezadoPagina>

      <div className="barra-excel">
        <HerramientasExcel modulo="ascensores" alImportar={recargar} />
      </div>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por código, marca, modelo, serie o ubicación"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar ascensores"
            />
          </div>
          <select className="campo__control" value={filtros.estado} onChange={(e) => cambiarFiltro('estado', e.target.value)} aria-label="Estado">
            <option value="">Todos los estados</option>
            {Object.entries(ESTADOS_EQUIPO).map(([valor, { texto }]) => (
              <option key={valor} value={valor}>
                {texto}
              </option>
            ))}
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
            alSeleccionar={(equipo) => navegar(`/equipos/${equipo.id}`)}
            expandir={(equipo) => <ResumenAscensor equipo={equipo} />}
            etiquetaExpandir="Ver resumen de"
            vacio={<EstadoVacio titulo="No se encontraron ascensores" descripcion="Ajuste los filtros, registre un ascensor o impórtelos desde Excel con la plantilla de SIGMA." />}
            columnas={[
              {
                clave: 'codigo',
                titulo: 'Código',
                render: (e) => (
                  <>
                    <strong>{e.codigo}</strong>
                    <div className="tabla__secundario">{[e.marca, e.modelo].filter(Boolean).join(' · ')}</div>
                  </>
                ),
              },
              {
                clave: 'edificio',
                titulo: 'Edificio',
                render: (e) => (
                  <>
                    {e.edificio?.nombre}
                    <div className="tabla__secundario">{e.edificio?.cliente?.nombre}</div>
                  </>
                ),
              },
              { clave: 'ubicacion', titulo: 'Ubicación' },
              {
                clave: 'ficha',
                titulo: 'Capacidad · Paradas',
                render: (e) =>
                  e.ascensor ? `${numero(e.ascensor.capacidad_kg, ' kg')} · ${e.ascensor.paradas ?? '—'}` : '—',
              },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (e) => <Insignia tipo={ESTADOS_EQUIPO[e.estado]?.tipo}>{ESTADOS_EQUIPO[e.estado]?.texto}</Insignia>,
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>
    </>
  );
}

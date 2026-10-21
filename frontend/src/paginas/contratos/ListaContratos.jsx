import { Link, useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { contratos } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { ESTADOS_CONTRATO, ESTADOS_RENOVACION, dinero, fecha } from '../../utilidades/formato';

/** "Vence en 12 días" con color según la cercanía (RF-020). */
export function VencimientoContrato({ contrato }) {
  const dias = contrato.dias_para_vencer;
  if (contrato.estado !== 'vigente' || dias === null || dias === undefined) return null;
  if (dias < 0) return <Insignia tipo="peligro">Venció hace {Math.abs(dias)} días</Insignia>;
  const tipo = dias <= 30 ? 'alerta' : 'neutro';
  return <Insignia tipo={tipo}>{dias === 0 ? 'Vence hoy' : `Vence en ${dias} días`}</Insignia>;
}

export default function ListaContratos() {
  const { tienePermiso, esCliente } = useAutenticacion();
  const navegar = useNavigate();
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina } = useListado(contratos.listar, {
    estado: '',
    por_vencer: '',
  });

  return (
    <>
      <EncabezadoPagina
        titulo={esCliente ? 'Mis contratos' : 'Contratos'}
        descripcion={['Vigencia y valor', 'Ascensores incluidos', 'Seguimiento de la renovación']}
      >
        {tienePermiso('contratos.crear') && (
          <Link to="/contratos/nuevo" className="boton">
            <Plus size={16} /> Crear contrato
          </Link>
        )}
      </EncabezadoPagina>

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por número o cliente"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar contratos"
            />
          </div>
          <select className="campo__control" value={filtros.estado} onChange={(e) => cambiarFiltro('estado', e.target.value)} aria-label="Estado">
            <option value="">Todos los estados</option>
            {Object.entries(ESTADOS_CONTRATO).map(([valor, { texto }]) => (
              <option key={valor} value={valor}>
                {texto}
              </option>
            ))}
          </select>
          <select className="campo__control" value={filtros.por_vencer} onChange={(e) => cambiarFiltro('por_vencer', e.target.value)} aria-label="Vencimiento">
            <option value="">Cualquier vencimiento</option>
            <option value="30">Vencen en 30 días</option>
            <option value="60">Vencen en 60 días</option>
            <option value="90">Vencen en 90 días</option>
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
            alSeleccionar={(contrato) => navegar(`/contratos/${contrato.id}`)}
            vacio={<EstadoVacio titulo="No se encontraron contratos" descripcion="Ajuste los filtros o cree un contrato para un cliente con ascensores registrados." />}
            columnas={[
              {
                clave: 'numero',
                titulo: 'Contrato',
                render: (c) => (
                  <>
                    <strong className="tabla__codigo">{c.numero}</strong>
                    <div className="tabla__secundario">{c.cliente?.nombre}</div>
                  </>
                ),
              },
              {
                clave: 'vigencia',
                titulo: 'Vigencia',
                render: (c) => (
                  <>
                    {fecha(c.fecha_inicio)} – {fecha(c.fecha_fin)}
                    <div style={{ marginTop: 4 }}>
                      <VencimientoContrato contrato={c} />
                    </div>
                  </>
                ),
              },
              { clave: 'valor', titulo: 'Valor', clase: 'tabular', render: (c) => dinero(c.valor) },
              { clave: 'equipos', titulo: 'Ascensores', clase: 'tabular', render: (c) => c.equipos_count ?? '—' },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (c) => (
                  <>
                    <Insignia tipo={ESTADOS_CONTRATO[c.estado]?.tipo}>{ESTADOS_CONTRATO[c.estado]?.texto}</Insignia>
                    {c.estado === 'vigente' && <div className="tabla__secundario">{ESTADOS_RENOVACION[c.estado_renovacion]}</div>}
                  </>
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

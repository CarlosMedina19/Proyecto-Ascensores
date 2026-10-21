import { useEffect, useState } from 'react';
import { Eye } from 'lucide-react';
import { auditoria } from '../../api/servicios';
import { filasSegunPantalla, useListado } from '../../hooks/useListado';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Modal, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { ACCIONES_AUDITORIA, fechaHora, humanizar } from '../../utilidades/formato';

function tipoAccion(accion) {
  if (accion === 'eliminado' || accion === 'desactivado' || accion === 'cuenta_bloqueada') return 'peligro';
  if (accion === 'creado' || accion === 'activado') return 'exito';
  if (accion?.includes('sesion') || accion?.includes('contrasena')) return 'info';
  return 'alerta';
}

export default function ListaAuditoria() {
  const [modulos, setModulos] = useState([]);
  const [detalle, setDetalle] = useState(null);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina } = useListado(
    auditoria.listar,
    { modulo: '', accion: '', fecha_desde: '', fecha_hasta: '' },
    filasSegunPantalla(42),
  );

  useEffect(() => {
    auditoria
      .modulos()
      .then((r) => setModulos(r.datos))
      .catch(() => {});
  }, []);

  return (
    <>
      <EncabezadoPagina
        titulo="Auditoría"
        descripcion={['Usuario, acción y módulo', 'Fecha, IP y cambios', 'Los registros no se editan ni se eliminan']}
      />

      <Tarjeta sinRelleno>
        <div className="filtros">
          <select className="campo__control" value={filtros.modulo} onChange={(e) => cambiarFiltro('modulo', e.target.value)} aria-label="Módulo">
            <option value="">Todos los módulos</option>
            {modulos.map((modulo) => (
              <option key={modulo} value={modulo}>
                {humanizar(modulo)}
              </option>
            ))}
          </select>
          <select className="campo__control" value={filtros.accion} onChange={(e) => cambiarFiltro('accion', e.target.value)} aria-label="Acción">
            <option value="">Todas las acciones</option>
            {Object.entries(ACCIONES_AUDITORIA).map(([valor, texto]) => (
              <option key={valor} value={valor}>
                {texto}
              </option>
            ))}
          </select>
          <label className="texto-pequeno texto-suave" htmlFor="fecha-desde">
            Desde
          </label>
          <input id="fecha-desde" type="date" className="campo__control" value={filtros.fecha_desde} onChange={(e) => cambiarFiltro('fecha_desde', e.target.value)} />
          <label className="texto-pequeno texto-suave" htmlFor="fecha-hasta">
            Hasta
          </label>
          <input id="fecha-hasta" type="date" className="campo__control" value={filtros.fecha_hasta} onChange={(e) => cambiarFiltro('fecha_hasta', e.target.value)} />
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
            vacio={<EstadoVacio titulo="No hay registros para los filtros seleccionados" descripcion="Amplíe el rango de fechas o quite filtros." />}
            columnas={[
              { clave: 'created_at', titulo: 'Fecha', render: (r) => fechaHora(r.created_at) },
              { clave: 'usuario', titulo: 'Usuario', render: (r) => r.usuario?.nombre || 'Sistema' },
              {
                clave: 'accion',
                titulo: 'Acción',
                render: (r) => <Insignia tipo={tipoAccion(r.accion)}>{ACCIONES_AUDITORIA[r.accion] || humanizar(r.accion)}</Insignia>,
              },
              { clave: 'modulo', titulo: 'Módulo', render: (r) => humanizar(r.modulo || '—') },
              { clave: 'registro', titulo: 'Registro', render: (r) => `${r.modelo} #${r.modelo_id}` },
              { clave: 'ip', titulo: 'IP', render: (r) => <span className="codigo">{r.ip || '—'}</span> },
              {
                clave: 'ver',
                titulo: <span className="oculto-visual">Detalle</span>,
                clase: 'tabla__acciones',
                render: (r) =>
                  r.cambios && (
                    <button type="button" className="boton boton--fantasma" onClick={() => setDetalle(r)} aria-label="Ver cambios">
                      <Eye size={16} />
                    </button>
                  ),
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>

      <Modal
        abierto={Boolean(detalle)}
        titulo="Cambios registrados"
        ancho
        alCerrar={() => setDetalle(null)}
        pie={
          <button type="button" className="boton boton--secundario" onClick={() => setDetalle(null)}>
            Cerrar
          </button>
        }
      >
        {detalle && (
          <table className="tabla">
            <thead>
              <tr>
                <th>Campo</th>
                <th>Valor</th>
              </tr>
            </thead>
            <tbody>
              {Object.entries(detalle.cambios || {}).map(([campo, valor]) => (
                <tr key={campo}>
                  <td className="codigo" style={{ border: 'none' }}>
                    {campo}
                  </td>
                  <td style={{ wordBreak: 'break-word' }}>{typeof valor === 'object' ? JSON.stringify(valor) : String(valor ?? '—')}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </Modal>
    </>
  );
}

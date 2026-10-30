import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus, Search } from 'lucide-react';
import { inventario } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useFormulario } from '../../hooks/useFormulario';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Modal, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto } from '../../componentes/Campos';
import AccionesRegistro from '../../componentes/AccionesRegistro';
import { dinero } from '../../utilidades/formato';

export const UNIDADES = { unidad: 'Unidad', metro: 'Metro', litro: 'Litro', kilogramo: 'Kilogramo', juego: 'Juego', caja: 'Caja' };

/** Cantidad con hasta dos decimales y la unidad: "2,5 litros" se muestra "2,5 litro". */
export const cantidad = (valor, unidad) => `${Number(valor ?? 0).toLocaleString('es-CO', { maximumFractionDigits: 2 })}${unidad ? ` ${unidad}` : ''}`;

export function EstadoStock({ repuesto }) {
  if (!repuesto.activo) return <Insignia tipo="neutro">Inactivo</Insignia>;
  if (repuesto.stock <= 0) return <Insignia tipo="peligro">Sin stock</Insignia>;
  if (repuesto.bajo_minimo) return <Insignia tipo="alerta">Bajo el mínimo</Insignia>;
  return <Insignia tipo="exito">Disponible</Insignia>;
}

/** RF-040 a RF-043: repuestos con su stock, alertas de mínimo y valor del inventario. */
export default function Inventario() {
  const { tienePermiso } = useAutenticacion();
  const navegar = useNavigate();
  const [editando, setEditando] = useState(null);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar, respuesta } = useListado(inventario.listar, { estado: 'activos', bajo_minimo: '' });
  const resumen = respuesta?.resumen;
  const gestiona = tienePermiso('inventario.gestionar');

  return (
    <>
      <EncabezadoPagina titulo="Inventario de repuestos" descripcion={['Stock y stock mínimo', 'Entradas, salidas y ajustes', 'Consumo en las órdenes']}>
        {gestiona && (
          <button type="button" className="boton" onClick={() => setEditando({})}>
            <Plus size={16} aria-hidden="true" /> Registrar repuesto
          </button>
        )}
      </EncabezadoPagina>

      {resumen && (
        <Tarjeta>
          <div className="cartera__resumen">
            <div className="cartera__cifra">
              <small>Repuestos activos</small>
              <b>{resumen.repuestos}</b>
            </div>
            <div className="cartera__cifra">
              <small>Bajo el mínimo</small>
              <b style={{ color: resumen.bajo_minimo > 0 ? 'var(--color-alerta)' : undefined }}>{resumen.bajo_minimo}</b>
            </div>
            <div className="cartera__cifra">
              <small>Sin stock</small>
              <b style={{ color: resumen.sin_stock > 0 ? 'var(--color-peligro)' : undefined }}>{resumen.sin_stock}</b>
            </div>
            <div className="cartera__cifra">
              <small>Valor del inventario</small>
              <b>{dinero(resumen.valor)}</b>
            </div>
          </div>
        </Tarjeta>
      )}

      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por código, nombre, marca o referencia"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar repuestos"
            />
          </div>
          <select className="campo__control" value={filtros.estado} onChange={(e) => cambiarFiltro('estado', e.target.value)} aria-label="Estado">
            <option value="activos">Activos</option>
            <option value="inactivos">Inactivos</option>
            <option value="">Todos</option>
          </select>
          <select className="campo__control" value={filtros.bajo_minimo} onChange={(e) => cambiarFiltro('bajo_minimo', e.target.value)} aria-label="Stock">
            <option value="">Todo el stock</option>
            <option value="1">Bajo el mínimo</option>
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
            alSeleccionar={(r) => navegar(`/inventario/${r.id}`)}
            vacio={<EstadoVacio titulo="No hay repuestos para mostrar" descripcion={gestiona ? 'Registre los repuestos con su stock y su stock mínimo.' : 'Ajuste los filtros.'} />}
            columnas={[
              {
                clave: 'codigo',
                titulo: 'Repuesto',
                render: (r) => (
                  <>
                    <strong>{r.nombre}</strong>
                    <div className="tabla__secundario">{[r.codigo, r.marca, r.referencia].filter(Boolean).join(' · ')}</div>
                  </>
                ),
              },
              {
                clave: 'stock',
                titulo: 'Stock',
                clase: 'numero',
                render: (r) => (
                  <>
                    <strong>{cantidad(r.stock, r.unidad)}</strong>
                    <div className="tabla__secundario">Mínimo {cantidad(r.stock_minimo)}</div>
                  </>
                ),
              },
              { clave: 'precio', titulo: 'Precio', clase: 'numero', render: (r) => dinero(r.precio) },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (r) => (
                  <AccionesRegistro
                    nombre={`el repuesto ${r.nombre}`}
                    activo={r.activo}
                    insignia={<EstadoStock repuesto={r} />}
                    alAlternar={gestiona ? () => inventario.alternarEstado(r.id) : null}
                    alEliminar={gestiona ? () => inventario.eliminar(r.id) : null}
                    mensajeDesactivar="Un repuesto inactivo no se puede usar en órdenes nuevas. Su historial de movimientos se conserva."
                    mensajeEliminar="Solo se puede eliminar un repuesto sin movimientos; si los tiene, desactívelo."
                    alCambiar={recargar}
                  />
                ),
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>

      {editando && (
        <ModalRepuesto
          repuesto={editando.id ? editando : null}
          alCerrar={(guardado) => {
            setEditando(null);
            if (guardado) recargar();
          }}
        />
      )}
    </>
  );
}

/** Registrar o editar un repuesto. El stock inicial solo se pide al registrarlo (después cambia con movimientos). */
export function ModalRepuesto({ repuesto, alCerrar }) {
  const notificar = useNotificar();
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    codigo: repuesto?.codigo || '',
    nombre: repuesto?.nombre || '',
    marca: repuesto?.marca || '',
    referencia: repuesto?.referencia || '',
    unidad: repuesto?.unidad || 'unidad',
    precio: repuesto ? String(repuesto.precio) : '',
    stock: '',
    stock_minimo: repuesto ? String(repuesto.stock_minimo) : '',
    observaciones: repuesto?.observaciones || '',
  });

  async function alEnviar(evento) {
    evento.preventDefault();
    const { stock, ...resto } = datos;
    const cuerpo = { ...resto, precio: datos.precio === '' ? null : Number(datos.precio), stock_minimo: datos.stock_minimo === '' ? 0 : Number(datos.stock_minimo) };
    if (!repuesto) cuerpo.stock = stock === '' ? 0 : Number(stock);
    const r = await enviar(() => (repuesto ? inventario.actualizar(repuesto.id, cuerpo) : inventario.crear(cuerpo)));
    if (r) {
      notificar(r.mensaje);
      alCerrar(r.datos);
    }
  }

  return (
    <Modal
      titulo={repuesto ? `Editar ${repuesto.nombre}` : 'Registrar repuesto'}
      abierto
      alCerrar={() => alCerrar(null)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(null)}>
            Cancelar
          </button>
          <button type="submit" form="formulario-repuesto" className="boton" disabled={enviando}>
            {enviando ? 'Guardando…' : 'Guardar repuesto'}
          </button>
        </>
      }
    >
      <form id="formulario-repuesto" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <div className="formulario__fila">
          <CampoTexto etiqueta="Código" obligatorio valor={datos.codigo} alCambiar={cambiar('codigo')} error={error('codigo')} placeholder="ZAP-001" />
          <CampoTexto etiqueta="Nombre" obligatorio valor={datos.nombre} alCambiar={cambiar('nombre')} error={error('nombre')} placeholder="Zapata de cabina" />
        </div>
        <div className="formulario__fila">
          <CampoTexto etiqueta="Marca" valor={datos.marca} alCambiar={cambiar('marca')} error={error('marca')} />
          <CampoTexto etiqueta="Referencia" valor={datos.referencia} alCambiar={cambiar('referencia')} error={error('referencia')} />
        </div>
        <div className="formulario__fila">
          <CampoSelector
            etiqueta="Unidad"
            obligatorio
            vacio={null}
            valor={datos.unidad}
            alCambiar={cambiar('unidad')}
            error={error('unidad')}
            opciones={Object.entries(UNIDADES).map(([valor, texto]) => ({ valor, texto }))}
          />
          <CampoTexto etiqueta="Precio unitario (COP)" obligatorio inputMode="decimal" valor={datos.precio} alCambiar={cambiar('precio')} error={error('precio')} />
        </div>
        <div className="formulario__fila">
          {!repuesto && (
            <CampoTexto etiqueta="Stock inicial" inputMode="decimal" valor={datos.stock} alCambiar={cambiar('stock')} error={error('stock')} ayuda="Queda como una entrada de inventario." />
          )}
          <CampoTexto etiqueta="Stock mínimo" inputMode="decimal" valor={datos.stock_minimo} alCambiar={cambiar('stock_minimo')} error={error('stock_minimo')} ayuda="Al llegar a este valor se avisa al coordinador." />
        </div>
        <CampoArea etiqueta="Observaciones" rows={2} valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} />
      </form>
    </Modal>
  );
}

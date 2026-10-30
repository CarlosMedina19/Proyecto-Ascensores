import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowDownToLine, ArrowUpFromLine, Pencil, Scale, Undo2 } from 'lucide-react';
import { inventario } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Datos, EncabezadoPagina, EstadoVacio, Insignia, Modal, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoTexto } from '../../componentes/Campos';
import { dinero, fechaHora } from '../../utilidades/formato';
import { EstadoStock, ModalRepuesto, UNIDADES, cantidad } from './Inventario';

const TIPOS = {
  entrada: { texto: 'Entrada', tipo: 'exito', icono: ArrowDownToLine },
  salida: { texto: 'Salida', tipo: 'alerta', icono: ArrowUpFromLine },
  devolucion: { texto: 'Devolución', tipo: 'info', icono: Undo2 },
  ajuste: { texto: 'Ajuste', tipo: 'cian', icono: Scale },
  consumo: { texto: 'Consumo en orden', tipo: 'peligro', icono: ArrowUpFromLine },
};

/** Ficha del repuesto con su historial de movimientos (RF-041). Los movimientos no se editan: se corrigen con un ajuste. */
export default function DetalleRepuesto() {
  const { id } = useParams();
  const { tienePermiso } = useAutenticacion();
  const [repuesto, setRepuesto] = useState(null);
  const [movimientos, setMovimientos] = useState(null);
  const [pagina, setPagina] = useState(1);
  const [error, setError] = useState('');
  const [movimiento, setMovimiento] = useState(null);
  const [editando, setEditando] = useState(false);

  const cargar = useCallback(() => {
    inventario
      .ver(id)
      .then((r) => setRepuesto(r.datos))
      .catch((e) => setError(e.message));
    inventario
      .movimientos(id, { pagina, por_pagina: 10 })
      .then(setMovimientos)
      .catch((e) => setError(e.message));
  }, [id, pagina]);
  useEffect(cargar, [cargar]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!repuesto) return <Cargando />;

  const registra = tienePermiso('inventario.movimientos');
  return (
    <>
      <EncabezadoPagina titulo={repuesto.nombre} descripcion={`${repuesto.codigo} · ${UNIDADES[repuesto.unidad] || repuesto.unidad}`} migas={<Link to="/inventario">Inventario</Link>}>
        {tienePermiso('inventario.gestionar') && (
          <button type="button" className="boton boton--secundario" onClick={() => setEditando(true)}>
            <Pencil size={16} aria-hidden="true" /> Editar
          </button>
        )}
        {registra &&
          ['entrada', 'salida', 'devolucion', 'ajuste'].map((t, i) => {
            const Icono = TIPOS[t].icono;
            return (
              <button key={t} type="button" className={i === 0 ? 'boton' : 'boton boton--secundario'} onClick={() => setMovimiento(t)} disabled={!repuesto.activo && t !== 'ajuste'}>
                <Icono size={16} aria-hidden="true" /> {TIPOS[t].texto}
              </button>
            );
          })}
      </EncabezadoPagina>

      {repuesto.bajo_minimo && repuesto.activo && <Alerta tipo="alerta">El stock está en el mínimo o por debajo. Se avisó al coordinador para reponerlo.</Alerta>}

      <div className="rejilla rejilla--2" style={{ marginBottom: 16 }}>
        <Tarjeta titulo="Stock">
          <div className="emergencia__estado">
            <EstadoStock repuesto={repuesto} />
          </div>
          <Datos
            elementos={[
              ['Stock actual', <strong key="s">{cantidad(repuesto.stock, repuesto.unidad)}</strong>],
              ['Stock mínimo', cantidad(repuesto.stock_minimo, repuesto.unidad)],
              ['Precio unitario', dinero(repuesto.precio)],
              ['Valor en stock', dinero(repuesto.stock * repuesto.precio)],
            ]}
          />
        </Tarjeta>
        <Tarjeta titulo="Datos del repuesto">
          <Datos
            elementos={[
              ['Código', repuesto.codigo],
              ['Marca', repuesto.marca || '—'],
              ['Referencia', repuesto.referencia || '—'],
              ['Estado', <Insignia key="e" tipo={repuesto.activo ? 'exito' : 'neutro'}>{repuesto.activo ? 'Activo' : 'Inactivo'}</Insignia>],
              ['Observaciones', repuesto.observaciones || '—'],
            ]}
          />
        </Tarjeta>
      </div>

      <Tarjeta titulo="Movimientos" sinRelleno>
        {!movimientos ? (
          <Cargando />
        ) : (
          <>
            <Tabla
              filas={movimientos.datos}
              vacio={<EstadoVacio titulo="Sin movimientos" descripcion="Las entradas, salidas, ajustes y consumos en órdenes aparecen aquí." />}
              columnas={[
                { clave: 'fecha', titulo: 'Fecha', render: (m) => fechaHora(m.fecha) },
                { clave: 'tipo', titulo: 'Tipo', render: (m) => <Insignia tipo={TIPOS[m.tipo]?.tipo}>{TIPOS[m.tipo]?.texto || m.tipo}</Insignia> },
                {
                  clave: 'variacion',
                  titulo: 'Cantidad',
                  clase: 'numero',
                  render: (m) => (
                    <strong style={{ color: m.variacion < 0 ? 'var(--color-peligro)' : 'var(--color-exito)' }}>
                      {m.variacion > 0 ? '+' : '−'}
                      {cantidad(Math.abs(m.variacion))}
                    </strong>
                  ),
                },
                { clave: 'stock', titulo: 'Stock resultante', clase: 'numero', render: (m) => cantidad(m.stock_resultante) },
                {
                  clave: 'motivo',
                  titulo: 'Motivo',
                  render: (m) => (
                    <>
                      {m.motivo}
                      <div className="tabla__secundario">
                        {m.orden && <Link to={`/ordenes/${m.orden.id}`}>{m.orden.numero}</Link>}
                        {m.orden && m.usuario ? ' · ' : ''}
                        {m.usuario}
                      </div>
                    </>
                  ),
                },
              ]}
            />
            <Paginacion metadatos={movimientos.metadatos} alCambiar={setPagina} />
          </>
        )}
      </Tarjeta>

      {movimiento && (
        <ModalMovimiento
          repuesto={repuesto}
          tipo={movimiento}
          alCerrar={(guardo) => {
            setMovimiento(null);
            if (guardo) cargar();
          }}
        />
      )}
      {editando && (
        <ModalRepuesto
          repuesto={repuesto}
          alCerrar={(guardado) => {
            setEditando(false);
            if (guardado) cargar();
          }}
        />
      )}
    </>
  );
}

function ModalMovimiento({ repuesto, tipo, alCerrar }) {
  const notificar = useNotificar();
  const ajuste = tipo === 'ajuste';
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ cantidad: '', stock_contado: '', motivo: '' });

  async function alEnviar(evento) {
    evento.preventDefault();
    const cuerpo = ajuste
      ? { tipo, stock_contado: datos.stock_contado === '' ? null : Number(datos.stock_contado), motivo: datos.motivo }
      : { tipo, cantidad: datos.cantidad === '' ? null : Number(datos.cantidad), motivo: datos.motivo };
    const r = await enviar(() => inventario.registrarMovimiento(repuesto.id, cuerpo));
    if (r) {
      notificar(r.mensaje);
      alCerrar(true);
    }
  }

  const ayuda = {
    entrada: 'Compra o recepción de repuestos: suma al stock.',
    salida: 'Salida que no es de una orden (préstamo, daño, traslado): resta del stock, sin superar lo disponible.',
    devolucion: 'Repuesto que regresa al almacén: suma al stock.',
    ajuste: 'Corrige el stock con el conteo físico. Es la forma de corregir errores: los movimientos no se editan.',
  }[tipo];

  return (
    <Modal
      titulo={`${TIPOS[tipo].texto} de ${repuesto.nombre}`}
      abierto
      alCerrar={() => alCerrar(false)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(false)}>
            Cancelar
          </button>
          <button type="submit" form="formulario-movimiento" className="boton" disabled={enviando}>
            {enviando ? 'Registrando…' : `Registrar ${TIPOS[tipo].texto.toLowerCase()}`}
          </button>
        </>
      }
    >
      <form id="formulario-movimiento" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <p className="texto-suave texto-pequeno" style={{ margin: 0 }}>
          {ayuda} Stock actual: <b>{cantidad(repuesto.stock, repuesto.unidad)}</b>.
        </p>
        {ajuste ? (
          <CampoTexto etiqueta="Stock contado" obligatorio inputMode="decimal" valor={datos.stock_contado} alCambiar={cambiar('stock_contado')} error={error('stock_contado')} />
        ) : (
          <CampoTexto etiqueta={`Cantidad (${repuesto.unidad})`} obligatorio inputMode="decimal" valor={datos.cantidad} alCambiar={cambiar('cantidad')} error={error('cantidad')} />
        )}
        <CampoArea etiqueta="Motivo" obligatorio rows={2} valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} placeholder="Factura de compra 1234, conteo físico del mes…" />
      </form>
    </Modal>
  );
}

import { useCallback, useEffect, useState } from 'react';
import { Plus, Search } from 'lucide-react';
import { cartera, clientes } from '../../api/servicios';
import { useListado } from '../../hooks/useListado';
import { useFormulario } from '../../hooks/useFormulario';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Modal, Paginacion, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoSelector, CampoTexto } from '../../componentes/Campos';
import HerramientasExcel from '../../componentes/HerramientasExcel';
import { dinero, fecha, fechaIso } from '../../utilidades/formato';

const ESTADOS = {
  pendiente: { texto: 'Pendiente', tipo: 'info' },
  parcial: { texto: 'Abono', tipo: 'alerta' },
  pagada: { texto: 'Pagada', tipo: 'exito' },
};
const MEDIOS = [
  { valor: 'transferencia', texto: 'Transferencia' },
  { valor: 'consignacion', texto: 'Consignación' },
  { valor: 'cheque', texto: 'Cheque' },
  { valor: 'efectivo', texto: 'Efectivo' },
];
const TRAMOS = [
  ['corriente', 'Corriente', 'var(--color-exito)'],
  ['1_30', '1 a 30 días', 'color-mix(in srgb, var(--color-alerta) 55%, var(--color-superficie-media))'],
  ['31_60', '31 a 60 días', 'var(--color-alerta)'],
  ['61_90', '61 a 90 días', 'color-mix(in srgb, var(--color-peligro) 65%, var(--color-superficie-media))'],
  ['mas_90', 'Más de 90 días', 'var(--color-peligro)'],
];

function estadoFactura(factura) {
  if (factura.saldo > 0 && factura.dias_mora > 0) return { texto: `${factura.dias_mora} días de mora`, tipo: factura.dias_mora > 30 ? 'peligro' : 'alerta' };
  return ESTADOS[factura.estado] || { texto: factura.estado, tipo: 'neutro' };
}

/** Cartera mínima: saldo por antigüedad, facturas y pagos. La facturación completa llega en la fase 5. */
export default function Cartera() {
  const { tienePermiso, esCliente } = useAutenticacion();
  const [resumen, setResumen] = useState(null);
  const [facturaPago, setFacturaPago] = useState(null);
  const [nuevaFactura, setNuevaFactura] = useState(false);
  const { filas, metadatos, cargando, error, filtros, cambiarFiltro, setPagina, recargar } = useListado(cartera.facturas, { estado: '' });

  const cargarResumen = useCallback(() => {
    cartera
      .resumen()
      .then((r) => setResumen(r.datos))
      .catch(() => setResumen(null));
  }, []);
  useEffect(cargarResumen, [cargarResumen]);

  const actualizar = () => {
    recargar();
    cargarResumen();
  };

  return (
    <>
      <EncabezadoPagina
        titulo={esCliente ? 'Mi estado de cuenta' : 'Cartera'}
        descripcion={['Facturas pendientes', 'Pagos registrados', 'Saldo por antigüedad']}
      >
        <HerramientasExcel modulo="cartera" alImportar={actualizar} />
        {tienePermiso('facturas.crear') && (
          <button type="button" className="boton" onClick={() => setNuevaFactura(true)}>
            <Plus size={16} aria-hidden="true" /> Registrar factura
          </button>
        )}
      </EncabezadoPagina>

      {resumen ? <ResumenCartera resumen={resumen} /> : <Cargando texto="Cargando saldos…" />}


      <Tarjeta sinRelleno>
        <div className="filtros">
          <div className="filtros__busqueda">
            <Search size={16} aria-hidden="true" />
            <input
              className="campo__control"
              placeholder="Buscar por número de factura o cliente"
              value={filtros.busqueda}
              onChange={(e) => cambiarFiltro('busqueda', e.target.value)}
              aria-label="Buscar facturas"
            />
          </div>
          <select className="campo__control" value={filtros.estado} onChange={(e) => cambiarFiltro('estado', e.target.value)} aria-label="Estado">
            <option value="">Todas las facturas</option>
            <option value="vencida">Vencidas</option>
            <option value="pendiente">Pendientes</option>
            <option value="parcial">Con abono</option>
            <option value="pagada">Pagadas</option>
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
            alSeleccionar={tienePermiso('pagos.registrar') ? (f) => f.saldo > 0 && setFacturaPago(f) : undefined}
            vacio={
              <EstadoVacio
                titulo="No hay facturas para mostrar"
                descripcion={tienePermiso('facturas.crear') ? 'Registre una factura o impórtelas desde Excel con la plantilla de SIGMA.' : 'Cuando se emitan facturas, aparecerán aquí.'}
              />
            }
            columnas={[
              {
                clave: 'numero',
                titulo: 'Factura',
                render: (f) => (
                  <>
                    <strong>{f.numero}</strong>
                    <div className="tabla__secundario">{f.concepto || 'Sin concepto'}</div>
                  </>
                ),
              },
              ...(esCliente
                ? []
                : [
                    {
                      clave: 'cliente',
                      titulo: 'Cliente',
                      render: (f) => (
                        <>
                          {f.cliente?.nombre}
                          <div className="tabla__secundario">{f.cliente?.codigo}</div>
                        </>
                      ),
                    },
                  ]),
              { clave: 'emision', titulo: 'Emisión', render: (f) => fecha(f.fecha_emision) },
              { clave: 'vencimiento', titulo: 'Vence', render: (f) => fecha(f.fecha_vencimiento) },
              { clave: 'valor', titulo: 'Valor', clase: 'numero', render: (f) => dinero(f.valor) },
              { clave: 'saldo', titulo: 'Saldo', clase: 'numero', render: (f) => <strong>{dinero(f.saldo)}</strong> },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (f) => {
                  const e = estadoFactura(f);
                  return <Insignia tipo={e.tipo}>{e.texto}</Insignia>;
                },
              },
            ]}
          />
        )}
        <Paginacion metadatos={metadatos} alCambiar={setPagina} />
      </Tarjeta>

      {facturaPago && (
        <ModalPago
          factura={facturaPago}
          alCerrar={(guardo) => {
            setFacturaPago(null);
            if (guardo) actualizar();
          }}
        />
      )}
      {nuevaFactura && (
        <ModalFactura
          alCerrar={(guardo) => {
            setNuevaFactura(false);
            if (guardo) actualizar();
          }}
        />
      )}
    </>
  );
}

function ResumenCartera({ resumen }) {
  const total = resumen.total || 0;
  return (
    <Tarjeta>
      <div className="cartera__resumen">
        <div className="cartera__cifra">
          <small>Cartera total</small>
          <b>{dinero(total)}</b>
        </div>
        <div className="cartera__cifra">
          <small>Vencida</small>
          <b style={{ color: resumen.vencida > 0 ? 'var(--color-peligro)' : undefined }}>{dinero(resumen.vencida)}</b>
        </div>
        <div className="cartera__cifra">
          <small>Recaudo del mes</small>
          <b>{dinero(resumen.recaudo_mes)}</b>
        </div>
        <div className="cartera__cifra">
          <small>Clientes en mora</small>
          <b>{resumen.clientes_en_mora}</b>
        </div>
      </div>
      <div className="cartera__tramos" role="img" aria-label="Cartera por antigüedad">
        {TRAMOS.map(([clave, texto, color]) =>
          resumen.tramos[clave] > 0 ? <i key={clave} style={{ flexGrow: resumen.tramos[clave], background: color }} title={`${texto}: ${dinero(resumen.tramos[clave])}`} /> : null,
        )}
        {total === 0 && <i style={{ flexGrow: 1, background: 'var(--color-superficie-media)' }} />}
      </div>
      <div className="cartera__leyenda">
        {TRAMOS.map(([clave, texto, color]) => (
          <div key={clave}>
            <span>
              <i style={{ background: color }} />
              {texto}
            </span>
            <b>{dinero(resumen.tramos[clave])}</b>
          </div>
        ))}
      </div>
    </Tarjeta>
  );
}

function ModalPago({ factura, alCerrar }) {
  const notificar = useNotificar();
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    factura_id: factura.id,
    valor: String(Math.round(factura.saldo)),
    fecha: fechaIso(),
    medio: 'transferencia',
    referencia: '',
  });

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() => cartera.registrarPago({ ...datos, valor: Number(String(datos.valor).replace(/\D/g, '')) }));
    if (respuesta) {
      notificar(respuesta.mensaje);
      alCerrar(true);
    }
  }

  return (
    <Modal
      titulo={`Registrar pago · ${factura.numero}`}
      abierto
      alCerrar={() => alCerrar(false)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(false)}>
            Cancelar
          </button>
          <button type="submit" form="formulario-pago" className="boton" disabled={enviando}>
            {enviando ? 'Registrando…' : 'Registrar pago'}
          </button>
        </>
      }
    >
      <form id="formulario-pago" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <p className="texto-suave" style={{ margin: 0 }}>
          {factura.cliente?.nombre} · saldo pendiente <b>{dinero(factura.saldo)}</b>. El valor no puede superar el saldo.
        </p>
        <div className="formulario__fila">
          <CampoTexto etiqueta="Valor recibido" obligatorio inputMode="numeric" valor={datos.valor} alCambiar={cambiar('valor')} error={error('valor')} />
          <CampoTexto etiqueta="Fecha del pago" obligatorio tipo="date" max={fechaIso()} valor={datos.fecha} alCambiar={cambiar('fecha')} error={error('fecha')} />
        </div>
        <div className="formulario__fila">
          <CampoSelector etiqueta="Medio de pago" obligatorio vacio={null} valor={datos.medio} alCambiar={cambiar('medio')} error={error('medio')} opciones={MEDIOS} />
          <CampoTexto etiqueta="Referencia" obligatorio valor={datos.referencia} alCambiar={cambiar('referencia')} error={error('referencia')} placeholder="Número de la transacción o del recibo" />
        </div>
      </form>
    </Modal>
  );
}

function ModalFactura({ alCerrar }) {
  const notificar = useNotificar();
  const [opciones, setOpciones] = useState([]);
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    cliente_id: '',
    numero: '',
    concepto: '',
    fecha_emision: fechaIso(),
    fecha_vencimiento: '',
    valor: '',
  });

  useEffect(() => {
    clientes
      .listar({ por_pagina: 100, estado: 1 })
      .then((r) => setOpciones(r.datos))
      .catch(() => {});
  }, []);

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() =>
      cartera.crearFactura({ ...datos, cliente_id: datos.cliente_id ? Number(datos.cliente_id) : null, valor: Number(String(datos.valor).replace(/\D/g, '')) || null }),
    );
    if (respuesta) {
      notificar(respuesta.mensaje);
      alCerrar(true);
    }
  }

  return (
    <Modal
      titulo="Registrar factura"
      abierto
      alCerrar={() => alCerrar(false)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(false)}>
            Cancelar
          </button>
          <button type="submit" form="formulario-factura" className="boton" disabled={enviando}>
            {enviando ? 'Registrando…' : 'Registrar factura'}
          </button>
        </>
      }
    >
      <form id="formulario-factura" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <CampoSelector
          etiqueta="Cliente"
          obligatorio
          valor={datos.cliente_id}
          alCambiar={cambiar('cliente_id')}
          error={error('cliente_id')}
          opciones={opciones.map((c) => ({ valor: String(c.id), texto: `${c.nombre} · ${c.codigo}` }))}
        />
        <div className="formulario__fila">
          <CampoTexto etiqueta="Número de factura" obligatorio valor={datos.numero} alCambiar={cambiar('numero')} error={error('numero')} placeholder="FV-2210" />
          <CampoTexto etiqueta="Valor" obligatorio inputMode="numeric" valor={datos.valor} alCambiar={cambiar('valor')} error={error('valor')} placeholder="850000" />
        </div>
        <CampoTexto etiqueta="Concepto" valor={datos.concepto} alCambiar={cambiar('concepto')} error={error('concepto')} placeholder="Mantenimiento preventivo de octubre" />
        <div className="formulario__fila">
          <CampoTexto etiqueta="Fecha de emisión" obligatorio tipo="date" valor={datos.fecha_emision} alCambiar={cambiar('fecha_emision')} error={error('fecha_emision')} />
          <CampoTexto etiqueta="Fecha de vencimiento" obligatorio tipo="date" valor={datos.fecha_vencimiento} alCambiar={cambiar('fecha_vencimiento')} error={error('fecha_vencimiento')} />
        </div>
      </form>
    </Modal>
  );
}

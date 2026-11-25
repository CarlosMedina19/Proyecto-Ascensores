import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { CheckCircle2, FileText, Landmark } from 'lucide-react';
import { cartera } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, EstadoVacio, Insignia, Modal, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoTexto } from '../../componentes/Campos';
import { dinero, fecha, fechaHora } from '../../utilidades/formato';
import { ESTADOS_PAGO, iniciarPago } from './pagoEnLinea';

const PASOS = [
  [FileText, 'Elija la factura', 'Paga el saldo completo o un abono.'],
  [Landmark, 'Pague por PSE', 'Elige su banco y autoriza el débito.'],
  [CheckCircle2, 'Listo', 'El pago queda en su estado de cuenta.'],
];

/** Sección del cliente para pagar sus facturas en línea (PSE con Wompi o, sin llaves, la pasarela de prueba). */
export default function PagarEnLinea() {
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [configuracion, setConfiguracion] = useState(null);
  const [facturas, setFacturas] = useState(null);
  const [historial, setHistorial] = useState([]);
  const [error, setError] = useState('');
  const [aPagar, setAPagar] = useState(null);

  const cargar = useCallback(() => {
    Promise.all([cartera.pseConfiguracion(), cartera.facturas({ con_saldo: 1, por_pagina: 50 }), cartera.pagosEnLinea()])
      .then(([c, f, h]) => {
        setConfiguracion(c.datos);
        setFacturas([...f.datos].sort((a, b) => String(a.fecha_vencimiento).localeCompare(String(b.fecha_vencimiento))));
        setHistorial(h.datos);
      })
      .catch((e) => setError(e.message));
  }, []);
  useEffect(cargar, [cargar]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!configuracion || !facturas) return <Cargando />;

  const disponible = configuracion.habilitado || configuracion.simulado;
  const saldo = facturas.reduce((suma, f) => suma + Number(f.saldo), 0);
  const vencidas = facturas.filter((f) => f.dias_mora > 0);

  return (
    <>
      <EncabezadoPagina titulo="Pagar en línea" descripcion={['Facturas con saldo', 'Pago por PSE', 'Historial de pagos']} />
      <div className="pila-pagina">
        {configuracion.simulado && (
          <Alerta tipo="info">
            <b>Modo demostración.</b> La pasarela de pago es simulada: no se cobra dinero ni se conecta con ningún banco. El pago que apruebe se registra en la factura con la referencia SIMULADO-… para ver el proceso completo.
          </Alerta>
        )}
        {configuracion.habilitado && configuracion.pruebas && (
          <Alerta tipo="info">Pago por PSE en modo de pruebas (Wompi sandbox): no se cobra dinero real. En el banco de pruebas elija «Banco que aprueba» para simular un pago exitoso.</Alerta>
        )}

        <Tarjeta>
          <div className="cartera__resumen">
            <div className="cartera__cifra">
              <small>Saldo por pagar</small>
              <b>{dinero(saldo)}</b>
            </div>
            <div className="cartera__cifra">
              <small>Facturas con saldo</small>
              <b>{facturas.length}</b>
            </div>
            <div className="cartera__cifra">
              <small>Vencidas</small>
              <b style={{ color: vencidas.length ? 'var(--color-peligro)' : undefined }}>{vencidas.length}</b>
            </div>
          </div>
          <ol className="pago-pasos" aria-label="Cómo funciona el pago en línea">
            {PASOS.map(([Icono, titulo, texto], i) => (
              <li key={titulo}>
                <span className="pago-pasos__icono">
                  <Icono size={18} aria-hidden="true" />
                </span>
                <span>
                  <b>
                    {i + 1}. {titulo}
                  </b>
                  <small>{texto}</small>
                </span>
              </li>
            ))}
          </ol>
        </Tarjeta>

        {!disponible && (
          <Alerta tipo="alerta">El pago en línea aún no está disponible. Puede pagar por transferencia o consignación y enviar el soporte a cartera.</Alerta>
        )}

        <Tarjeta titulo="Facturas por pagar" sinRelleno>
          <Tabla
            filas={facturas}
            vacio={<EstadoVacio titulo="Está al día" descripcion="No tiene facturas con saldo pendiente." />}
            columnas={[
              {
                clave: 'factura',
                titulo: 'Factura',
                render: (f) => (
                  <>
                    <strong>{f.numero}</strong>
                    <div className="tabla__secundario">{f.concepto || 'Sin concepto'}</div>
                  </>
                ),
              },
              {
                clave: 'vence',
                titulo: 'Vence',
                render: (f) => (
                  <>
                    {fecha(f.fecha_vencimiento)}
                    {f.dias_mora > 0 && (
                      <div>
                        <Insignia tipo={f.dias_mora > 30 ? 'peligro' : 'alerta'}>{f.dias_mora} días de mora</Insignia>
                      </div>
                    )}
                  </>
                ),
              },
              { clave: 'valor', titulo: 'Valor', clase: 'numero', render: (f) => dinero(f.valor) },
              { clave: 'saldo', titulo: 'Saldo', clase: 'numero', render: (f) => <strong>{dinero(f.saldo)}</strong> },
              {
                clave: 'pagar',
                titulo: 'Pago en línea',
                render: (f) => (
                  <button type="button" className="boton boton--pequeno" disabled={!disponible} onClick={() => setAPagar(f)}>
                    <Landmark size={14} aria-hidden="true" /> Pagar
                  </button>
                ),
              },
            ]}
          />
        </Tarjeta>

        <Tarjeta titulo="Mis pagos en línea" sinRelleno>
          <Tabla
            filas={historial}
            claveFila="referencia"
            vacio={<EstadoVacio titulo="Aún no hay pagos en línea" descripcion="Aquí verá cada pago que haga por PSE y su estado." />}
            columnas={[
              { clave: 'fecha', titulo: 'Fecha', render: (p) => fechaHora(p.creado_en) },
              { clave: 'factura', titulo: 'Factura', render: (p) => p.factura.numero },
              { clave: 'monto', titulo: 'Valor', clase: 'numero', render: (p) => dinero(p.monto) },
              {
                clave: 'estado',
                titulo: 'Estado',
                render: (p) => {
                  const estado = ESTADOS_PAGO[p.estado] || ESTADOS_PAGO.pendiente;
                  return (
                    <>
                      <Insignia tipo={estado.tipo}>{estado.texto}</Insignia>
                      {p.simulado && <div className="tabla__secundario">Simulación{p.banco ? ` · ${p.banco}` : ''}</div>}
                    </>
                  );
                },
              },
              { clave: 'referencia', titulo: 'Referencia', render: (p) => <span className="tabular texto-pequeno">{p.referencia}</span> },
              {
                clave: 'accion',
                titulo: 'Detalle',
                render: (p) =>
                  p.estado === 'pendiente' && p.simulado && configuracion.simulado ? (
                    <Link className="boton boton--secundario boton--pequeno" to={`/pagos/simulador?referencia=${encodeURIComponent(p.referencia)}`}>
                      Continuar
                    </Link>
                  ) : p.transaccion_id ? (
                    <Link className="boton boton--fantasma boton--pequeno" to={`/pagos/resultado?id=${encodeURIComponent(p.transaccion_id)}`}>
                      Ver detalle
                    </Link>
                  ) : null,
              },
            ]}
          />
        </Tarjeta>
      </div>

      <ModalPago
        factura={aPagar}
        alCerrar={() => setAPagar(null)}
        alContinuar={async (monto) => {
          try {
            await iniciarPago(aPagar, monto, navegar);
          } catch (e) {
            notificar(e.message, 'error');
            throw e;
          }
        }}
      />
    </>
  );
}

/** Valor a pagar: el saldo completo o un abono. */
function ModalPago({ factura, alCerrar, alContinuar }) {
  const [abono, setAbono] = useState(false);
  const [valor, setValor] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    setAbono(false);
    setValor('');
    setError('');
    setEnviando(false);
  }, [factura]);

  if (!factura) return null;
  const saldo = Number(factura.saldo);

  async function continuar() {
    const monto = abono ? Math.round(Number(valor)) : null;
    if (abono && !(monto >= 1 && monto <= saldo)) {
      setError(`Escriba un valor entre ${dinero(1)} y ${dinero(saldo)}.`);
      return;
    }
    setEnviando(true);
    try {
      await alContinuar(monto);
    } catch {
      setEnviando(false);
    }
  }

  return (
    <Modal
      titulo={`Pagar la factura ${factura.numero}`}
      abierto
      alCerrar={alCerrar}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar}>
            Cancelar
          </button>
          <button type="button" className="boton" onClick={continuar} disabled={enviando}>
            <Landmark size={16} aria-hidden="true" /> {enviando ? 'Abriendo la pasarela…' : 'Continuar al pago'}
          </button>
        </>
      }
    >
      <fieldset className="pago-valor">
        <legend>¿Cuánto va a pagar?</legend>
        <label className={`pago-valor__opcion${!abono ? ' pago-valor__opcion--activa' : ''}`}>
          <input type="radio" name="valor" checked={!abono} onChange={() => setAbono(false)} />
          <span>
            <b>Saldo completo</b>
            <small>{dinero(saldo)}</small>
          </span>
        </label>
        <label className={`pago-valor__opcion${abono ? ' pago-valor__opcion--activa' : ''}`}>
          <input type="radio" name="valor" checked={abono} onChange={() => setAbono(true)} />
          <span>
            <b>Otro valor (abono)</b>
            <small>Hasta {dinero(saldo)}</small>
          </span>
        </label>
      </fieldset>
      {abono && (
        <CampoTexto etiqueta="Valor a pagar (COP)" tipo="number" min="1" max={saldo} step="1" inputMode="numeric" valor={valor} alCambiar={setValor} error={error} autoFocus />
      )}
      <p className="texto-pequeno">{factura.concepto || 'Sin concepto'} · vence el {fecha(factura.fecha_vencimiento)}</p>
    </Modal>
  );
}

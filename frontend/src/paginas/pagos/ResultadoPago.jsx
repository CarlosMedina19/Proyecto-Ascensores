import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { CheckCircle2, Clock, FlaskConical, XCircle } from 'lucide-react';
import { cartera } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { dinero } from '../../utilidades/formato';

const ESTADOS = {
  aprobado: { icono: CheckCircle2, clase: 'exito', titulo: 'Pago aprobado', texto: 'El pago quedó registrado en la factura.' },
  pendiente: { icono: Clock, clase: 'alerta', titulo: 'Pago en proceso', texto: 'El banco aún no confirma el pago. SIGMA lo registrará apenas el banco lo apruebe.' },
  rechazado: { icono: XCircle, clase: 'peligro', titulo: 'Pago rechazado', texto: 'El banco no aprobó el pago. La factura sigue con su saldo; puede intentarlo de nuevo.' },
  anulado: { icono: XCircle, clase: 'peligro', titulo: 'Pago anulado', texto: 'La transacción se anuló. La factura sigue con su saldo.' },
  error: { icono: XCircle, clase: 'peligro', titulo: 'No se completó el pago', texto: 'Hubo un error en la transacción. Puede intentarlo de nuevo.' },
};

/**
 * Wompi devuelve aquí con ?id=<transacción>; el servidor consulta el estado real en Wompi. La pasarela de
 * prueba también llega aquí (transacciones SIM-…, que el servidor lee de SIGMA).
 */
export default function ResultadoPago() {
  const { esCliente } = useAutenticacion();
  const volver = esCliente ? { ruta: '/pagos', texto: 'Pagar en línea' } : { ruta: '/cartera', texto: 'Cartera' };
  const [parametros] = useSearchParams();
  const transaccion = parametros.get('id');
  const [resultado, setResultado] = useState(null);
  const [error, setError] = useState(transaccion ? '' : 'No llegó el número de la transacción.');

  useEffect(() => {
    if (!transaccion) return;
    cartera
      .confirmarPse(transaccion)
      .then((r) => setResultado(r.datos))
      .catch((e) => setError(e.message));
  }, [transaccion]);

  const estado = resultado ? ESTADOS[resultado.estado] ?? ESTADOS.pendiente : null;
  const Icono = estado?.icono;

  return (
    <>
      <EncabezadoPagina titulo="Pago por PSE" migas={<Link to={volver.ruta}>{volver.texto}</Link>} />
      {error && <Alerta>{error}</Alerta>}
      {!error && !resultado && <Cargando />}
      {resultado && (
        <Tarjeta>
          <div className={`resultado-pago resultado-pago--${estado.clase}`}>
            <Icono size={44} aria-hidden="true" />
            {resultado.simulado && (
              <span className="pasarela__sello">
                <FlaskConical size={14} aria-hidden="true" /> Pago de prueba · no se cobró dinero
              </span>
            )}
            <h2>{estado.titulo}</h2>
            <p>{resultado.mensaje && resultado.estado !== 'aprobado' ? `${estado.texto} (${resultado.mensaje})` : estado.texto}</p>
            <dl>
              <dt>Factura</dt>
              <dd>{resultado.factura.numero}</dd>
              <dt>Valor</dt>
              <dd>{dinero(resultado.monto)}</dd>
              <dt>Saldo de la factura</dt>
              <dd>{dinero(resultado.factura.saldo)}</dd>
              {resultado.banco && (
                <>
                  <dt>Banco</dt>
                  <dd>{resultado.banco}</dd>
                </>
              )}
              <dt>Referencia</dt>
              <dd className="tabular">{resultado.referencia}</dd>
            </dl>
            <div className="pasarela__acciones">
              {resultado.simulado && resultado.estado === 'pendiente' && (
                <Link to={`/pagos/simulador?referencia=${encodeURIComponent(resultado.referencia)}`} className="boton boton--secundario">
                  Volver al banco de prueba
                </Link>
              )}
              <Link to={volver.ruta} className="boton">
                Volver a {volver.texto.toLowerCase()}
              </Link>
            </div>
          </div>
        </Tarjeta>
      )}
    </>
  );
}

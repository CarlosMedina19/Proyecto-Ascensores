import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { CheckCircle2, Clock, XCircle } from 'lucide-react';
import { cartera } from '../../api/servicios';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { dinero } from '../../utilidades/formato';

const ESTADOS = {
  aprobado: { icono: CheckCircle2, clase: 'exito', titulo: 'Pago aprobado', texto: 'El pago quedó registrado en la factura.' },
  pendiente: { icono: Clock, clase: 'alerta', titulo: 'Pago en proceso', texto: 'El banco aún no confirma el pago. SIGMA lo registrará apenas Wompi lo apruebe.' },
  rechazado: { icono: XCircle, clase: 'peligro', titulo: 'Pago rechazado', texto: 'El banco no aprobó el pago. La factura sigue con su saldo; puede intentarlo de nuevo.' },
  anulado: { icono: XCircle, clase: 'peligro', titulo: 'Pago anulado', texto: 'La transacción se anuló. La factura sigue con su saldo.' },
  error: { icono: XCircle, clase: 'peligro', titulo: 'No se completó el pago', texto: 'Hubo un error en la transacción. Puede intentarlo de nuevo.' },
};

/** Wompi devuelve aquí con ?id=<transacción>; el servidor consulta el estado real en Wompi. */
export default function ResultadoPago() {
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
      <EncabezadoPagina titulo="Pago por PSE" migas={<Link to="/cartera">Cartera</Link>} />
      {error && <Alerta>{error}</Alerta>}
      {!error && !resultado && <Cargando />}
      {resultado && (
        <Tarjeta>
          <div className={`resultado-pago resultado-pago--${estado.clase}`}>
            <Icono size={44} aria-hidden="true" />
            <h2>{estado.titulo}</h2>
            <p>{resultado.mensaje && resultado.estado !== 'aprobado' ? `${estado.texto} (${resultado.mensaje})` : estado.texto}</p>
            <dl>
              <dt>Factura</dt>
              <dd>{resultado.factura.numero}</dd>
              <dt>Valor</dt>
              <dd>{dinero(resultado.monto)}</dd>
              <dt>Saldo de la factura</dt>
              <dd>{dinero(resultado.factura.saldo)}</dd>
              <dt>Referencia</dt>
              <dd className="tabular">{resultado.referencia}</dd>
            </dl>
            <Link to="/cartera" className="boton">
              Volver a la cartera
            </Link>
          </div>
        </Tarjeta>
      )}
    </>
  );
}

import { useEffect, useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { FlaskConical, Landmark } from 'lucide-react';
import { cartera } from '../../api/servicios';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';
import { dinero } from '../../utilidades/formato';

// Bancos ficticios: la pasarela es de demostración y no se conecta con ninguna entidad real.
const BANCOS = ['Banco de Prueba Andino', 'Banco Demostración del Valle', 'Cooperativa Financiera Ejemplo', 'Banco Digital de Pruebas'].map((b) => ({ valor: b, texto: b }));
const PERSONAS = [
  { valor: 'juridica', texto: 'Persona jurídica (empresa)' },
  { valor: 'natural', texto: 'Persona natural' },
];
const DOCUMENTOS = [
  { valor: 'NIT', texto: 'NIT' },
  { valor: 'CC', texto: 'Cédula de ciudadanía' },
  { valor: 'CE', texto: 'Cédula de extranjería' },
];
const RESPUESTAS = [
  ['aprobado', 'Aprobar el pago', 'boton'],
  ['rechazado', 'Rechazar el pago', 'boton boton--peligro'],
  ['pendiente', 'Dejarlo pendiente', 'boton boton--secundario'],
];
const PAUSA_DEL_BANCO = 1200;

/**
 * Pasarela de pago de prueba: el mismo recorrido que PSE (datos → banco → resultado) sin cobrar dinero.
 * Solo existe mientras no haya llaves de Wompi y el servidor tenga SIGMA_PAGO_SIMULADO=true.
 */
export default function SimuladorPago() {
  const [parametros] = useSearchParams();
  const referencia = parametros.get('referencia');
  const navegar = useNavigate();
  const [pago, setPago] = useState(null);
  const [error, setError] = useState(referencia ? '' : 'Falta la referencia del pago.');
  const [paso, setPaso] = useState(1);
  const [datos, setDatos] = useState({ persona: 'juridica', banco: '', documento_tipo: 'NIT', documento: '', correo: '', acepta: false });
  const [errores, setErrores] = useState({});
  const [respondiendo, setRespondiendo] = useState('');

  useEffect(() => {
    if (!referencia) return;
    cartera
      .pagoEnLinea(referencia)
      .then((r) => {
        setPago(r.datos);
        setDatos((d) => ({ ...d, correo: r.datos.correo || '' }));
      })
      .catch((e) => setError(e.message));
  }, [referencia]);

  const cambiar = (campo) => (valor) => setDatos((d) => ({ ...d, [campo]: valor }));

  function irAlBanco(evento) {
    evento.preventDefault();
    const nuevos = {};
    if (!datos.banco) nuevos.banco = 'Elija su banco.';
    if (!/^\d{5,12}$/.test(datos.documento.trim())) nuevos.documento = 'Escriba el número de documento, solo números (5 a 12 dígitos).';
    if (!/^\S+@\S+\.\S+$/.test(datos.correo.trim())) nuevos.correo = 'Escriba un correo válido.';
    if (!datos.acepta) nuevos.acepta = 'Confirme que entiende que es una transacción de prueba.';
    setErrores(nuevos);
    if (!Object.keys(nuevos).length) setPaso(2);
  }

  async function responder(resultado) {
    setRespondiendo(resultado);
    setError('');
    try {
      await new Promise((listo) => setTimeout(listo, PAUSA_DEL_BANCO));
      const r = await cartera.simularPago(referencia, { resultado, banco: datos.banco });
      navegar(`/pagos/resultado?id=${encodeURIComponent(r.datos.transaccion_id)}`, { replace: true });
    } catch (e) {
      setError(e.message);
      setRespondiendo('');
    }
  }

  const migas = <Link to="/pagos">Pagar en línea</Link>;
  if (!pago) {
    return (
      <>
        <EncabezadoPagina titulo="Pasarela de pago de prueba" migas={migas} />
        {error ? <Alerta>{error}</Alerta> : <Cargando />}
      </>
    );
  }

  const terminado = pago.estado !== 'pendiente';
  return (
    <>
      <EncabezadoPagina titulo="Pasarela de pago de prueba" migas={migas} />
      <Alerta>{error}</Alerta>
      <div className="pasarela">
        <Tarjeta>
          <div className="pasarela__resumen">
            <span className="pasarela__sello">
              <FlaskConical size={14} aria-hidden="true" /> Simulación · no se cobra dinero
            </span>
            <small>Total a pagar</small>
            <b className="pasarela__total tabular">{dinero(pago.monto)}</b>
            <dl>
              <dt>Comercio</dt>
              <dd>SIGMA · Mantenimiento de ascensores</dd>
              {pago.cliente && (
                <>
                  <dt>Pagador</dt>
                  <dd>{pago.cliente}</dd>
                </>
              )}
              <dt>Factura</dt>
              <dd>{pago.factura.numero}</dd>
              {pago.factura.concepto && (
                <>
                  <dt>Concepto</dt>
                  <dd>{pago.factura.concepto}</dd>
                </>
              )}
              <dt>Referencia</dt>
              <dd className="tabular">{pago.referencia}</dd>
            </dl>
          </div>
        </Tarjeta>

        <Tarjeta>
          <ol className="pasarela__pasos">
            {['Datos del pago', 'Banco', 'Resultado'].map((texto, i) => (
              <li key={texto} className={paso === i + 1 ? 'pasarela__paso--activo' : paso > i + 1 ? 'pasarela__paso--hecho' : ''} aria-current={paso === i + 1 ? 'step' : undefined}>
                <span>{i + 1}</span>
                {texto}
              </li>
            ))}
          </ol>

          {terminado ? (
            <div className="pasarela__fin">
              <p>Este pago ya terminó. Para pagar de nuevo, inicie otro pago desde la factura.</p>
              {pago.transaccion_id && (
                <Link className="boton" to={`/pagos/resultado?id=${encodeURIComponent(pago.transaccion_id)}`}>
                  Ver el resultado
                </Link>
              )}
            </div>
          ) : !pago.simulado ? (
            <Alerta tipo="alerta">La pasarela de prueba no está activa en este servidor.</Alerta>
          ) : paso === 1 ? (
            <form className="pasarela__formulario" onSubmit={irAlBanco} noValidate>
              <div className="pasarela__medio">
                <Landmark size={20} aria-hidden="true" />
                <span>
                  <b>PSE</b>
                  <small>Débito desde su cuenta de ahorros o corriente</small>
                </span>
              </div>
              <CampoSelector etiqueta="Tipo de persona" valor={datos.persona} alCambiar={cambiar('persona')} opciones={PERSONAS} vacio={null} />
              <CampoSelector etiqueta="Banco" obligatorio valor={datos.banco} alCambiar={cambiar('banco')} opciones={BANCOS} vacio="Elija su banco…" error={errores.banco} />
              <div className="formulario__fila">
                <CampoSelector etiqueta="Tipo de documento" valor={datos.documento_tipo} alCambiar={cambiar('documento_tipo')} opciones={DOCUMENTOS} vacio={null} />
                <CampoTexto etiqueta="Número de documento" obligatorio inputMode="numeric" valor={datos.documento} alCambiar={cambiar('documento')} error={errores.documento} />
              </div>
              <CampoTexto etiqueta="Correo para el comprobante" obligatorio tipo="email" valor={datos.correo} alCambiar={cambiar('correo')} error={errores.correo} />
              <Casilla etiqueta="Entiendo que es una transacción de prueba: no se debita dinero de ninguna cuenta." valor={datos.acepta} alCambiar={cambiar('acepta')} />
              {errores.acepta && <p className="campo__error">{errores.acepta}</p>}
              <div className="pasarela__acciones">
                <Link className="boton boton--secundario" to="/pagos">
                  Cancelar
                </Link>
                <button type="submit" className="boton">
                  Ir al banco
                </button>
              </div>
            </form>
          ) : (
            <div className="pasarela__banco">
              <div className="pasarela__banco-encabezado">
                <Landmark size={22} aria-hidden="true" />
                <span>
                  <b>{datos.banco}</b>
                  <small>Portal del banco · simulado</small>
                </span>
              </div>
              <p>
                Aquí el cliente iniciaría sesión en su banco y autorizaría el débito de <b>{dinero(pago.monto)}</b> a favor de SIGMA. En esta demostración usted elige qué responde el banco:
              </p>
              {respondiendo ? (
                <Cargando texto="Procesando con el banco…" />
              ) : (
                <div className="pasarela__respuestas">
                  {RESPUESTAS.map(([valor, texto, clase]) => (
                    <button key={valor} type="button" className={clase} onClick={() => responder(valor)}>
                      {texto}
                    </button>
                  ))}
                </div>
              )}
              {!respondiendo && (
                <button type="button" className="boton boton--fantasma boton--pequeno" onClick={() => setPaso(1)}>
                  Volver a los datos del pago
                </button>
              )}
            </div>
          )}
        </Tarjeta>
      </div>
    </>
  );
}

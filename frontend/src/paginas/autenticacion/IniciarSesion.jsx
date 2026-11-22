import { useState } from 'react';
import { ArrowLeft, ArrowRight, MailCheck, ShieldCheck } from 'lucide-react';
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { CampoContrasena, CampoTexto } from '../../componentes/Campos';
import { Alerta } from '../../componentes/Basicos';
import { ErrorApi } from '../../api/cliente';
import DisenoAcceso from './DisenoAcceso';
import { cerrarPuertas } from '../../componentes/TransicionPuertas';
import { CLAVE_DEMO, CUENTAS_DEMO, useDemostracion } from '../../hooks/useDemostracion';
import { CampoCodigo, ReenviarCodigo } from '../../componentes/CodigoVerificacion';

/** Segundo paso: el código de 6 dígitos que llegó al correo. */
function PasoCodigo({ verificacion, alVolver }) {
  const { verificarCodigo } = useAutenticacion();
  const [codigo, setCodigo] = useState('');
  const [error, setError] = useState('');
  const [aviso, setAviso] = useState(verificacion.mensaje);
  const [enviando, setEnviando] = useState(false);
  const navegar = useNavigate();
  const ubicacion = useLocation();

  async function alEnviar(evento) {
    evento.preventDefault();
    setEnviando(true);
    setError('');
    try {
      await verificarCodigo(verificacion.desafio, codigo, { antesDeEntrar: cerrarPuertas });
      navegar(ubicacion.state?.desde || '/inicio', { replace: true });
    } catch (e) {
      setError(e instanceof ErrorApi ? e.errores.codigo?.[0] || e.message : e.message);
      setCodigo('');
    } finally {
      setEnviando(false);
    }
  }

  return (
    <>
      <h2>Verificación en dos pasos</h2>
      <p className="acceso__apoyo">
        <MailCheck size={15} aria-hidden="true" style={{ verticalAlign: '-2px' }} /> Escriba el código de 6 dígitos que enviamos a <b>{verificacion.destino}</b>. Vence en {verificacion.minutos} minutos.
      </p>
      <form className="formulario" onSubmit={alEnviar} noValidate>
        {aviso && !error && <Alerta tipo="info">{aviso}</Alerta>}
        <CampoCodigo valor={codigo} alCambiar={setCodigo} error={error} ayuda="Si no lo ve, revise la carpeta de spam o «Promociones»." />
        <button type="submit" className="boton boton--bloque boton--alto" disabled={enviando || codigo.length !== 6}>
          {enviando ? 'Verificando…' : 'Verificar e ingresar'} <ArrowRight size={16} />
        </button>
        <div className="acceso__acciones-codigo">
          <button type="button" className="boton boton--fantasma boton--pequeno" onClick={alVolver}>
            <ArrowLeft size={14} aria-hidden="true" /> Volver
          </button>
          <ReenviarCodigo desafio={verificacion.desafio} alReenviar={(r) => { setError(''); setAviso(r.mensaje); }} alError={(e) => setError(e.errores?.codigo?.[0] || e.message)} />
        </div>
      </form>
    </>
  );
}

export default function IniciarSesion() {
  const { iniciarSesion, motivoCierre } = useAutenticacion();
  const [correo, setCorreo] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const navegar = useNavigate();
  const ubicacion = useLocation();
  const [parametros] = useSearchParams();
  const { activo: demostracion } = useDemostracion();
  const [verificacion, setVerificacion] = useState(null);

  async function alEnviar(evento) {
    evento.preventDefault();
    setEnviando(true);
    setError('');
    setErrores({});
    try {
      const resultado = await iniciarSesion(correo.trim(), password, { antesDeEntrar: cerrarPuertas });
      if (resultado?.requiere_verificacion) {
        setVerificacion(resultado);
        setPassword('');
        return;
      }
      navegar(ubicacion.state?.desde || '/inicio', { replace: true });
    } catch (e) {
      setError(e.message);
      if (e instanceof ErrorApi) setErrores(e.errores);
    } finally {
      setEnviando(false);
    }
  }

  if (verificacion) {
    return (
      <DisenoAcceso>
        <PasoCodigo verificacion={verificacion} alVolver={() => setVerificacion(null)} />
      </DisenoAcceso>
    );
  }

  return (
    <DisenoAcceso>
      <h2>Ingrese a SIGMA</h2>
      <p className="acceso__apoyo">Use el correo y la contraseña de su cuenta.</p>

      <form className="formulario" onSubmit={alEnviar} noValidate>
        {parametros.get('verificado') && <Alerta tipo="exito">Su correo fue verificado. Ya puede iniciar sesión.</Alerta>}
        {parametros.get('restablecida') && <Alerta tipo="exito">Contraseña restablecida. Inicie sesión con la nueva contraseña.</Alerta>}
        {motivoCierre && !error && <Alerta tipo="info">{motivoCierre}</Alerta>}
        <Alerta>{error}</Alerta>

        <CampoTexto
          etiqueta="Correo electrónico"
          tipo="email"
          valor={correo}
          alCambiar={setCorreo}
          error={errores.correo?.[0]}
          autoComplete="username"
          autoFocus
          obligatorio
        />
        <CampoContrasena
          valor={password}
          alCambiar={setPassword}
          error={errores.password?.[0]}
          autoComplete="current-password"
          obligatorio
        />

        <div style={{ textAlign: 'right' }} className="texto-pequeno">
          <Link to="/recuperar-contrasena">¿Olvidó su contraseña?</Link>
        </div>
        <button type="submit" className="boton boton--bloque boton--alto" disabled={enviando || !correo || !password}>
          {enviando ? 'Ingresando…' : 'Ingresar'} <ArrowRight size={16} />
        </button>
        <p className="acceso__nota">
          <ShieldCheck size={13} style={{ flex: 'none', marginTop: 2 }} /> La sesión se cierra tras 10 minutos sin actividad. Cinco intentos
          fallidos bloquean la cuenta durante 15 minutos.
        </p>
        {demostracion && CUENTAS_DEMO.length > 0 && (
          <div className="acceso__demo">
            <span className="texto-pequeno texto-suave">Cuentas de demostración. Elija una para llenar el formulario.</span>
            <div className="acceso__demo-cuentas">
              {CUENTAS_DEMO.map((cuenta) => (
                <button
                  key={cuenta}
                  type="button"
                  className="boton boton--secundario boton--pequeno"
                  onClick={() => {
                    setCorreo(cuenta);
                    setPassword(CLAVE_DEMO);
                  }}
                >
                  {cuenta.split('@')[0]}
                </button>
              ))}
            </div>
          </div>
        )}
      </form>
    </DisenoAcceso>
  );
}

import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Mail, MessageSquareText, Smartphone } from 'lucide-react';
import { autenticacion } from '../../api/servicios';
import { ErrorApi } from '../../api/cliente';
import { CampoContrasena, CampoTexto } from '../../componentes/Campos';
import { Alerta } from '../../componentes/Basicos';
import { CampoCodigo, ReenviarCodigo } from '../../componentes/CodigoVerificacion';
import DisenoAcceso from './DisenoAcceso';

/** Recuperación por correo: enlace de un solo uso que vence en 60 minutos. */
function PorCorreo() {
  const [correo, setCorreo] = useState('');
  const [mensaje, setMensaje] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  async function alEnviar(evento) {
    evento.preventDefault();
    setEnviando(true);
    setError('');
    try {
      const respuesta = await autenticacion.recuperarContrasena(correo.trim());
      setMensaje(respuesta.mensaje);
    } catch (e) {
      setError(e.message);
    } finally {
      setEnviando(false);
    }
  }

  if (mensaje) {
    return (
      <div className="formulario">
        <Alerta tipo="exito">{mensaje}</Alerta>
        <Link to="/iniciar-sesion" className="boton boton--secundario boton--bloque">
          Volver al inicio de sesión
        </Link>
      </div>
    );
  }

  return (
    <form className="formulario" onSubmit={alEnviar}>
      <p className="acceso__apoyo">Le enviaremos un enlace para crear una nueva contraseña. El enlace vence en 60 minutos.</p>
      <Alerta>{error}</Alerta>
      <CampoTexto etiqueta="Correo electrónico" tipo="email" valor={correo} alCambiar={setCorreo} autoFocus obligatorio />
      <button type="submit" className="boton boton--bloque" disabled={enviando || !correo}>
        {enviando ? 'Enviando…' : 'Enviar enlace'}
      </button>
    </form>
  );
}

/** Aviso del modo de demostración: sin operador de SMS, el mensaje se muestra aquí. */
function SmsSimulado({ solicitud }) {
  if (solicitud?.codigo_demo) {
    return (
      <div className="acceso__sms-demo" role="status">
        <span>
          <MessageSquareText size={14} aria-hidden="true" style={{ verticalAlign: '-2px' }} /> SMS simulado para {solicitud.destino} (modo de demostración, sin operador):
        </span>
        <b>{solicitud.codigo_demo}</b>
      </div>
    );
  }
  return solicitud?.nota ? <Alerta tipo="info">{solicitud.nota}</Alerta> : null;
}

/** Recuperación con el número de celular: código por SMS y contraseña nueva en la misma pantalla. */
function PorCelular() {
  const navegar = useNavigate();
  const [telefono, setTelefono] = useState('');
  const [solicitud, setSolicitud] = useState(null);
  const [datos, setDatos] = useState({ codigo: '', password: '', password_confirmation: '' });
  const [error, setError] = useState('');
  const [errores, setErrores] = useState({});
  const [enviando, setEnviando] = useState(false);
  const cambiar = (campo) => (valor) => setDatos((d) => ({ ...d, [campo]: valor }));

  async function ejecutar(accion) {
    setEnviando(true);
    setError('');
    setErrores({});
    try {
      await accion();
    } catch (e) {
      if (e instanceof ErrorApi && Object.keys(e.errores).length) setErrores(e.errores);
      else setError(e.message);
    } finally {
      setEnviando(false);
    }
  }

  const pedirCodigo = (evento) => {
    evento.preventDefault();
    ejecutar(async () => setSolicitud(await autenticacion.recuperarConTelefono(telefono.trim())));
  };

  const restablecer = (evento) => {
    evento.preventDefault();
    ejecutar(async () => {
      await autenticacion.restablecerConTelefono({ desafio: solicitud.desafio, ...datos });
      navegar('/iniciar-sesion?restablecida=1', { replace: true });
    });
  };

  if (!solicitud) {
    return (
      <form className="formulario" onSubmit={pedirCodigo} noValidate>
        <p className="acceso__apoyo">Escriba el celular registrado en su cuenta: le enviaremos un SMS con un código de 6 dígitos que vence en 10 minutos.</p>
        <Alerta>{error}</Alerta>
        <CampoTexto
          etiqueta="Número de celular"
          tipo="tel"
          valor={telefono}
          alCambiar={setTelefono}
          error={errores.telefono?.[0]}
          placeholder="300 123 4567"
          inputMode="tel"
          autoComplete="tel-national"
          autoFocus
          obligatorio
        />
        <button type="submit" className="boton boton--bloque" disabled={enviando || telefono.replace(/\D/g, '').length < 10}>
          {enviando ? 'Enviando…' : 'Enviar código por SMS'}
        </button>
      </form>
    );
  }

  return (
    <form className="formulario" onSubmit={restablecer} noValidate>
      <Alerta tipo="info">{solicitud.mensaje}</Alerta>
      <SmsSimulado solicitud={solicitud} />
      <Alerta>{error}</Alerta>
      <CampoCodigo valor={datos.codigo} alCambiar={cambiar('codigo')} error={errores.codigo?.[0]} etiqueta="Código del SMS" />
      <CampoContrasena
        etiqueta="Nueva contraseña"
        obligatorio
        valor={datos.password}
        alCambiar={cambiar('password')}
        error={errores.password?.[0]}
        autoComplete="new-password"
        ayuda="Mínimo 8 caracteres, con letras y números."
      />
      <CampoContrasena etiqueta="Confirmar nueva contraseña" obligatorio valor={datos.password_confirmation} alCambiar={cambiar('password_confirmation')} autoComplete="new-password" />
      <button type="submit" className="boton boton--bloque" disabled={enviando || datos.codigo.length !== 6 || !datos.password}>
        {enviando ? 'Guardando…' : 'Restablecer contraseña'}
      </button>
      <div className="acceso__acciones-codigo">
        <button type="button" className="boton boton--fantasma boton--pequeno" onClick={() => setSolicitud(null)}>
          Cambiar número
        </button>
        <ReenviarCodigo desafio={solicitud.desafio} proposito="recuperacion" alReenviar={(r) => setSolicitud((s) => ({ ...s, ...r }))} alError={(e) => setError(e.errores?.codigo?.[0] || e.message)} />
      </div>
    </form>
  );
}

export default function RecuperarContrasena() {
  const [metodo, setMetodo] = useState('correo');
  // Sin operador de SMS (producción sin Twilio ni n8n) solo queda la recuperación por correo.
  const [conCelular, setConCelular] = useState(true);

  useEffect(() => {
    let vigente = true;
    autenticacion
      .opcionesAcceso()
      .then((r) => {
        if (vigente && r?.datos?.sms_disponible === false) {
          setConCelular(false);
          setMetodo('correo');
        }
      })
      .catch(() => {});
    return () => {
      vigente = false;
    };
  }, []);

  return (
    <DisenoAcceso>
      <h2>Recuperar contraseña</h2>
      {conCelular && (
        <div className="acceso__pestanas" role="tablist" aria-label="Cómo recuperar la contraseña">
          <button type="button" role="tab" aria-selected={metodo === 'correo'} onClick={() => setMetodo('correo')}>
            <Mail size={15} aria-hidden="true" /> Por correo
          </button>
          <button type="button" role="tab" aria-selected={metodo === 'celular'} onClick={() => setMetodo('celular')}>
            <Smartphone size={15} aria-hidden="true" /> Por celular
          </button>
        </div>
      )}
      {metodo === 'celular' && conCelular ? <PorCelular /> : <PorCorreo />}
      <div style={{ textAlign: 'center', marginTop: 12 }} className="texto-pequeno">
        <Link to="/iniciar-sesion">Volver al inicio de sesión</Link>
      </div>
    </DisenoAcceso>
  );
}

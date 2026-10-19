import { useState } from 'react';
import { ArrowRight, ShieldCheck } from 'lucide-react';
import { Link, useLocation, useNavigate, useSearchParams } from 'react-router-dom';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { CampoContrasena, CampoTexto } from '../../componentes/Campos';
import { Alerta } from '../../componentes/Basicos';
import { ErrorApi } from '../../api/cliente';
import DisenoAcceso from './DisenoAcceso';

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

  async function alEnviar(evento) {
    evento.preventDefault();
    setEnviando(true);
    setError('');
    setErrores({});
    try {
      await iniciarSesion(correo.trim(), password);
      navegar(ubicacion.state?.desde || '/inicio', { replace: true });
    } catch (e) {
      setError(e.message);
      if (e instanceof ErrorApi) setErrores(e.errores);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <DisenoAcceso>
      <p className="etiqueta-superior">Bienvenido de nuevo</p>
      <h2>Ingresar</h2>

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
      </form>
    </DisenoAcceso>
  );
}

import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { autenticacion } from '../../api/servicios';
import { useFormulario } from '../../hooks/useFormulario';
import { CampoContrasena, CampoTexto } from '../../componentes/Campos';
import { Alerta } from '../../componentes/Basicos';
import DisenoAcceso from './DisenoAcceso';

export default function RestablecerContrasena() {
  const [parametros] = useSearchParams();
  const navegar = useNavigate();
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    correo: parametros.get('correo') || '',
    token: parametros.get('token') || '',
    password: '',
    password_confirmation: '',
  });

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() => autenticacion.restablecerContrasena(datos));
    if (respuesta) navegar('/iniciar-sesion?restablecida=1', { replace: true });
  }

  if (!datos.token) {
    return (
      <DisenoAcceso>
        <h2>Enlace no válido</h2>
        <p className="texto-suave">El enlace de recuperación está incompleto. Solicite uno nuevo.</p>
        <Link to="/recuperar-contrasena" className="boton boton--bloque">
          Solicitar nuevo enlace
        </Link>
      </DisenoAcceso>
    );
  }

  return (
    <DisenoAcceso>
      <h2>Nueva contraseña</h2>
      <p className="acceso__apoyo">Use al menos 8 caracteres, con letras y números.</p>
      <form className="formulario" onSubmit={alEnviar}>
        <Alerta>{error('token') || (mensajeError && !error('password') ? mensajeError : '')}</Alerta>
        <CampoTexto etiqueta="Correo electrónico" tipo="email" valor={datos.correo} alCambiar={cambiar('correo')} error={error('correo')} obligatorio />
        <CampoContrasena etiqueta="Nueva contraseña" valor={datos.password} alCambiar={cambiar('password')} error={error('password')} autoComplete="new-password" obligatorio />
        <CampoContrasena
          etiqueta="Confirmar contraseña"
          valor={datos.password_confirmation}
          alCambiar={cambiar('password_confirmation')}
          autoComplete="new-password"
          obligatorio
        />
        <button type="submit" className="boton boton--bloque" disabled={enviando}>
          {enviando ? 'Guardando…' : 'Guardar contraseña'}
        </button>
        {error('token') && (
          <Link to="/recuperar-contrasena" className="texto-pequeno" style={{ textAlign: 'center' }}>
            Solicitar un nuevo enlace
          </Link>
        )}
      </form>
    </DisenoAcceso>
  );
}

import { useState } from 'react';
import { Link } from 'react-router-dom';
import { autenticacion } from '../../api/servicios';
import { CampoTexto } from '../../componentes/Campos';
import { Alerta } from '../../componentes/Basicos';
import DisenoAcceso from './DisenoAcceso';

export default function RecuperarContrasena() {
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

  return (
    <DisenoAcceso>
      <p className="etiqueta-superior">Acceso</p>
      <h2>Recuperar contraseña</h2>
      <p className="texto-suave" style={{ marginTop: -8 }}>Le enviaremos un enlace para crear una nueva contraseña. El enlace vence en 60 minutos.</p>

      {mensaje ? (
        <div className="formulario">
          <Alerta tipo="exito">{mensaje}</Alerta>
          <Link to="/iniciar-sesion" className="boton boton--secundario boton--bloque">
            Volver al inicio de sesión
          </Link>
        </div>
      ) : (
        <form className="formulario" onSubmit={alEnviar}>
          <Alerta>{error}</Alerta>
          <CampoTexto etiqueta="Correo electrónico" tipo="email" valor={correo} alCambiar={setCorreo} autoFocus obligatorio />
          <button type="submit" className="boton boton--bloque" disabled={enviando || !correo}>
            {enviando ? 'Enviando…' : 'Enviar enlace'}
          </button>
          <div style={{ textAlign: 'center' }} className="texto-pequeno">
            <Link to="/iniciar-sesion">Volver al inicio de sesión</Link>
          </div>
        </form>
      )}
    </DisenoAcceso>
  );
}

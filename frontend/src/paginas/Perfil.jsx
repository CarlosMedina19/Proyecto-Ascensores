import { KeyRound } from "lucide-react";
import { autenticacion } from "../api/servicios";
import { useAutenticacion } from "../contexto/AutenticacionContexto";
import { useNotificar } from "../contexto/NotificacionesContexto";
import { useFormulario } from "../hooks/useFormulario";
import {
  Alerta,
  Datos,
  EncabezadoPagina,
  Insignia,
  Tarjeta,
} from "../componentes/Basicos";
import { CampoContrasena, Casilla } from "../componentes/Campos";
import { useDemostracion } from "../hooks/useDemostracion";
import { fechaHora, nombreRol } from "../utilidades/formato";

export default function Perfil() {
  const { usuario } = useAutenticacion();
  const notificar = useNotificar();
  const {
    datos,
    setDatos,
    cambiar,
    errores,
    error,
    mensajeError,
    enviando,
    enviar,
  } = useFormulario({
    contrasena_actual: "",
    password: "",
    password_confirmation: "",
  });

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() =>
      autenticacion.cambiarContrasena(datos),
    );
    if (respuesta) {
      notificar(respuesta.mensaje);
      setDatos({
        contrasena_actual: "",
        password: "",
        password_confirmation: "",
      });
    }
  }

  return (
    <>
      <EncabezadoPagina
        titulo="Mi perfil"
        descripcion={[
          "Datos de su cuenta",
          "Cambio de contraseña",
          "Presentaciones",
        ]}
      />
      <div className="rejilla rejilla--2" style={{ alignItems: "start" }}>
        <div className="rejilla">
          <Tarjeta titulo="Datos de la cuenta">
            <Datos
              elementos={[
                ["Nombre", usuario.nombre],
                ["Correo", usuario.correo],
                ["Rol", nombreRol(usuario.rol?.nombre)],
                ["Cliente", usuario.cliente?.nombre],
                [
                  "Verificación",
                  usuario.correo_verificado_en ? (
                    <Insignia tipo="info">Verificado</Insignia>
                  ) : (
                    <Insignia>Sin verificar</Insignia>
                  ),
                ],
                ["Último acceso", fechaHora(usuario.ultimo_acceso_en)],
              ]}
            />
          </Tarjeta>
          <TarjetaDemostracion />
        </div>
        <Tarjeta titulo="Cambiar contraseña">
          <form className="formulario" onSubmit={alEnviar} noValidate>
            {mensajeError && !Object.keys(errores).length && (
              <Alerta>{mensajeError}</Alerta>
            )}
            <CampoContrasena
              etiqueta="Contraseña actual"
              obligatorio
              valor={datos.contrasena_actual}
              alCambiar={cambiar("contrasena_actual")}
              error={error("contrasena_actual")}
              autoComplete="current-password"
            />
            <CampoContrasena
              etiqueta="Nueva contraseña"
              obligatorio
              valor={datos.password}
              alCambiar={cambiar("password")}
              error={error("password")}
              autoComplete="new-password"
              ayuda="Mínimo 8 caracteres, con letras y números."
            />
            <CampoContrasena
              etiqueta="Confirmar nueva contraseña"
              obligatorio
              valor={datos.password_confirmation}
              alCambiar={cambiar("password_confirmation")}
              autoComplete="new-password"
            />
            <div className="formulario__acciones">
              <button type="submit" className="boton" disabled={enviando}>
                <KeyRound size={16} />{" "}
                {enviando ? "Guardando…" : "Cambiar contraseña"}
              </button>
            </div>
            <span className="campo__ayuda">
              Al cambiarla se cerrarán sus sesiones abiertas en otros
              dispositivos.
            </span>
          </form>
        </Tarjeta>
      </div>
    </>
  );
}

/** Presentaciones: muestra u oculta las referencias del proyecto formativo y las cuentas de prueba. */
function TarjetaDemostracion() {
  const { activo, alternar } = useDemostracion();
  return (
    <Tarjeta titulo="Presentaciones">
      <Casilla
        etiqueta="Modo demostración"
        valor={activo}
        alCambiar={alternar}
      />
      <span className="campo__ayuda">
        Muestra las referencias del proyecto formativo y las cuentas de prueba
        en el inicio de sesión. Desactívelo antes de presentar SIGMA a un
        cliente. Solo afecta a este navegador.
      </span>
    </Tarjeta>
  );
}

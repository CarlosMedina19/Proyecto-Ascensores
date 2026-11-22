import { useState } from "react";
import { KeyRound, ShieldCheck, Smartphone } from "lucide-react";
import { autenticacion } from "../api/servicios";
import { useAutenticacion } from "../contexto/AutenticacionContexto";
import { useNotificar } from "../contexto/NotificacionesContexto";
import { useFormulario } from "../hooks/useFormulario";
import {
  Alerta,
  Datos,
  EncabezadoPagina,
  Insignia,
  Modal,
  Tarjeta,
} from "../componentes/Basicos";
import { CampoContrasena, CampoTexto, Casilla } from "../componentes/Campos";
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
          <TarjetaSeguridad />
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

/** Celular (para recuperar la contraseña por SMS) y verificación en dos pasos con un código al correo. */
function TarjetaSeguridad() {
  const { usuario, recargarPerfil } = useAutenticacion();
  const notificar = useNotificar();
  const [telefono, setTelefono] = useState(usuario.telefono || "");
  const [errorTelefono, setErrorTelefono] = useState("");
  const [guardando, setGuardando] = useState(false);
  const [confirmar, setConfirmar] = useState(false);
  const [password, setPassword] = useState("");
  const [errorClave, setErrorClave] = useState("");

  async function guardarTelefono(evento) {
    evento.preventDefault();
    setGuardando(true);
    setErrorTelefono("");
    try {
      await autenticacion.actualizarPerfil({ telefono: telefono.trim() || null });
      await recargarPerfil();
      notificar("Celular actualizado.");
    } catch (e) {
      setErrorTelefono(e.errores?.telefono?.[0] || e.message);
    } finally {
      setGuardando(false);
    }
  }

  async function cambiarDobleFactor() {
    setGuardando(true);
    setErrorClave("");
    try {
      const respuesta = await autenticacion.cambiarDobleFactor(!usuario.doble_factor, password);
      await recargarPerfil();
      notificar(respuesta.mensaje);
      setConfirmar(false);
      setPassword("");
    } catch (e) {
      setErrorClave(e.errores?.password?.[0] || e.message);
    } finally {
      setGuardando(false);
    }
  }

  return (
    <Tarjeta titulo="Seguridad de la cuenta">
      <form className="formulario" onSubmit={guardarTelefono} noValidate>
        <CampoTexto
          etiqueta="Celular"
          tipo="tel"
          valor={telefono}
          alCambiar={setTelefono}
          error={errorTelefono}
          placeholder="300 123 4567"
          inputMode="tel"
          ayuda="Con este número puede recuperar la contraseña por SMS."
        />
        <div className="formulario__acciones">
          <button type="submit" className="boton boton--secundario" disabled={guardando || telefono === (usuario.telefono || "")}>
            <Smartphone size={16} /> Guardar celular
          </button>
        </div>
      </form>
      <div className="perfil__doble-factor">
        <div>
          <b>Verificación en dos pasos</b>{" "}
          {usuario.doble_factor ? <Insignia tipo="exito">Activa</Insignia> : <Insignia>Inactiva</Insignia>}
          <p className="campo__ayuda">
            Después de la contraseña le pediremos un código de 6 dígitos enviado a <b>{usuario.correo}</b>. Actívela solo si ese correo es real y lo revisa.
          </p>
        </div>
        <button type="button" className={`boton${usuario.doble_factor ? " boton--secundario" : ""}`} onClick={() => setConfirmar(true)}>
          <ShieldCheck size={16} /> {usuario.doble_factor ? "Desactivar" : "Activar"}
        </button>
      </div>
      <Modal
        abierto={confirmar}
        titulo={usuario.doble_factor ? "Desactivar la verificación en dos pasos" : "Activar la verificación en dos pasos"}
        alCerrar={() => !guardando && setConfirmar(false)}
        pie={
          <>
            <button type="button" className="boton boton--secundario" onClick={() => setConfirmar(false)} disabled={guardando}>
              Cancelar
            </button>
            <button type="button" className="boton" onClick={cambiarDobleFactor} disabled={guardando || !password}>
              {guardando ? "Guardando…" : "Confirmar"}
            </button>
          </>
        }
      >
        <form
          className="formulario"
          onSubmit={(e) => {
            e.preventDefault();
            if (password) cambiarDobleFactor();
          }}
        >
          <p style={{ margin: 0 }}>Escriba su contraseña para confirmar el cambio.</p>
          <CampoContrasena etiqueta="Su contraseña" obligatorio valor={password} alCambiar={setPassword} error={errorClave} autoComplete="current-password" autoFocus />
        </form>
      </Modal>
    </Tarjeta>
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

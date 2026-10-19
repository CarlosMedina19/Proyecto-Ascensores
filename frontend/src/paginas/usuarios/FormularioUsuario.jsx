import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { clientes, roles, usuarios } from '../../api/servicios';
import { limpiarVacios, useFormulario } from '../../hooks/useFormulario';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoContrasena, CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';
import { nombreRol } from '../../utilidades/formato';

export default function FormularioUsuario() {
  const { id } = useParams();
  const editando = Boolean(id);
  const navegar = useNavigate();
  const notificar = useNotificar();
  const { usuario: usuarioActual } = useAutenticacion();
  const [cargando, setCargando] = useState(true);
  const [opcionesRoles, setOpcionesRoles] = useState([]);
  const [opcionesClientes, setOpcionesClientes] = useState([]);
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    nombre: '',
    correo: '',
    password: '',
    rol_id: '',
    cliente_id: '',
    activo: true,
  });

  useEffect(() => {
    const solicitudes = [
      roles.listar().then((r) => setOpcionesRoles(r.datos)),
      clientes.listar({ por_pagina: 100 }).then((r) => setOpcionesClientes(r.datos)),
    ];
    if (editando) {
      solicitudes.push(
        usuarios.obtener(id).then(({ datos: u }) =>
          setDatos({
            nombre: u.nombre,
            correo: u.correo,
            password: '',
            rol_id: String(u.rol_id ?? ''),
            cliente_id: String(u.cliente_id ?? ''),
            activo: u.activo,
          }),
        ),
      );
    }
    Promise.allSettled(solicitudes).finally(() => setCargando(false));
  }, [editando, id, setDatos]);

  const rolSeleccionado = opcionesRoles.find((rol) => String(rol.id) === String(datos.rol_id));
  const esRolCliente = rolSeleccionado?.nombre === 'cliente';
  const esPropio = editando && Number(id) === usuarioActual.id;

  async function alEnviar(evento) {
    evento.preventDefault();
    const cuerpo = limpiarVacios({
      ...datos,
      rol_id: datos.rol_id ? Number(datos.rol_id) : '',
      cliente_id: esRolCliente && datos.cliente_id ? Number(datos.cliente_id) : '',
    });
    if (editando && !datos.password) delete cuerpo.password;
    const respuesta = await enviar(() => (editando ? usuarios.actualizar(id, cuerpo) : usuarios.crear(cuerpo)));
    if (respuesta) {
      notificar(editando ? 'Usuario actualizado.' : 'Usuario creado. Se envió un correo para verificar la cuenta.');
      navegar('/usuarios');
    }
  }

  if (cargando) return <Cargando />;

  return (
    <>
      <EncabezadoPagina titulo={editando ? 'Editar usuario' : 'Nuevo usuario'} migas={<Link to="/usuarios">Usuarios</Link>} />
      <Tarjeta>
        <form className="formulario" onSubmit={alEnviar} noValidate style={{ maxWidth: 720 }}>
          <Alerta>{mensajeError}</Alerta>
          <div className="formulario__fila">
            <CampoTexto etiqueta="Nombre completo" obligatorio valor={datos.nombre} alCambiar={cambiar('nombre')} error={error('nombre')} />
            <CampoTexto etiqueta="Correo electrónico" obligatorio tipo="email" valor={datos.correo} alCambiar={cambiar('correo')} error={error('correo')} />
          </div>
          <div className="formulario__fila">
            <CampoSelector
              etiqueta="Rol"
              obligatorio
              valor={datos.rol_id}
              alCambiar={cambiar('rol_id')}
              error={error('rol_id')}
              disabled={esPropio}
              ayuda={esPropio ? 'No puede cambiar su propio rol.' : 'Cada usuario tiene un solo rol.'}
              opciones={opcionesRoles.map((rol) => ({ valor: String(rol.id), texto: nombreRol(rol.nombre) }))}
            />
            {esRolCliente && (
              <CampoSelector
                etiqueta="Cliente asociado"
                obligatorio
                valor={datos.cliente_id}
                alCambiar={cambiar('cliente_id')}
                error={error('cliente_id')}
                ayuda="El usuario solo verá la información de este cliente."
                opciones={opcionesClientes.map((c) => ({ valor: String(c.id), texto: c.nombre }))}
              />
            )}
          </div>
          <CampoContrasena
            etiqueta={editando ? 'Nueva contraseña' : 'Contraseña'}
            obligatorio={!editando}
            valor={datos.password}
            alCambiar={cambiar('password')}
            error={error('password')}
            autoComplete="new-password"
            ayuda={editando ? 'Déjela vacía para conservar la actual. Si la cambia, se cerrarán las sesiones del usuario.' : 'Mínimo 8 caracteres, con letras y números.'}
          />
          {!esPropio && <Casilla etiqueta="Usuario activo" valor={datos.activo} alCambiar={cambiar('activo')} />}

          <div className="formulario__acciones">
            <button type="button" className="boton boton--secundario" onClick={() => navegar(-1)}>
              Cancelar
            </button>
            <button type="submit" className="boton" disabled={enviando}>
              {enviando ? 'Guardando…' : 'Guardar usuario'}
            </button>
          </div>
        </form>
      </Tarjeta>
    </>
  );
}

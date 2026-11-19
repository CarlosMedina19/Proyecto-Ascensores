import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Eye, EyeOff, Plus } from 'lucide-react';
import { roles } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, EncabezadoPagina, Modal, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoTexto } from '../../componentes/Campos';
import { nombreRol } from '../../utilidades/formato';

function ModalNuevoRol({ abierto, alCerrar, alCrear }) {
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ nombre: '', descripcion: '' });

  async function guardar() {
    const respuesta = await enviar(() => roles.crear(datos));
    if (respuesta) {
      setDatos({ nombre: '', descripcion: '' });
      alCrear(respuesta.datos);
    }
  }

  return (
    <Modal
      abierto={abierto}
      titulo="Nuevo rol"
      alCerrar={alCerrar}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar}>
            Cancelar
          </button>
          <button type="button" className="boton" onClick={guardar} disabled={enviando}>
            {enviando ? 'Creando…' : 'Crear rol'}
          </button>
        </>
      }
    >
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <CampoTexto
          etiqueta="Nombre técnico"
          obligatorio
          valor={datos.nombre}
          alCambiar={(v) => cambiar('nombre')(v.toLowerCase())}
          error={error('nombre')}
          ayuda="Minúsculas, sin espacios. Ej. auxiliar_bodega"
        />
        <CampoTexto etiqueta="Descripción" valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} />
      </div>
    </Modal>
  );
}

/** Contraseña de la cuenta principal del rol: oculta por defecto; verla exige confirmar la del administrador. */
function ContrasenaRol({ rol, disponible, valor, alPedir, alOcultar }) {
  const visible = valor !== undefined;
  if (!disponible) return <span className="texto-suave">—</span>;
  return (
    <span className="columna-credencial">
      <span className="codigo">{visible ? valor || 'No registrada' : '••••••••'}</span>
      <button
        type="button"
        className="boton boton--fantasma boton--pequeno"
        onClick={(evento) => {
          evento.stopPropagation();
          if (visible) alOcultar(rol.id);
          else alPedir(rol);
        }}
        aria-label={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
        aria-pressed={visible}
        title={visible ? 'Ocultar contraseña' : 'Mostrar contraseña'}
      >
        {visible ? <EyeOff size={15} /> : <Eye size={15} />}
      </button>
    </span>
  );
}

/** Confirmación con la contraseña del administrador antes de revelar una credencial. */
function ModalConfirmarClave({ rol, alCerrar, alRevelar }) {
  const [clave, setClave] = useState('');
  const [error, setError] = useState('');
  const [enviando, setEnviando] = useState(false);

  useEffect(() => {
    setClave('');
    setError('');
  }, [rol]);

  async function confirmar(evento) {
    evento.preventDefault();
    if (!clave) return;
    setEnviando(true);
    try {
      const r = await roles.credencial(rol.id, clave);
      alRevelar(rol.id, r.datos?.contrasena ?? '');
    } catch (e) {
      setError(e.errores?.password?.[0] || e.message);
    } finally {
      setEnviando(false);
    }
  }

  return (
    <Modal
      abierto={Boolean(rol)}
      titulo="Confirme su contraseña"
      alCerrar={alCerrar}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar}>
            Cancelar
          </button>
          <button type="submit" form="confirmar-clave" className="boton" disabled={!clave || enviando}>
            {enviando ? 'Verificando…' : 'Ver contraseña'}
          </button>
        </>
      }
    >
      <form id="confirmar-clave" className="formulario" onSubmit={confirmar}>
        <p className="texto-suave" style={{ margin: 0 }}>
          Para ver la contraseña de la cuenta principal del rol {rol ? nombreRol(rol.nombre) : ''} escriba la suya. La consulta queda en la auditoría.
        </p>
        <Alerta>{error}</Alerta>
        <label className="campo" htmlFor="clave-administrador">
          <span className="campo__etiqueta">Su contraseña</span>
          <input id="clave-administrador" className="campo__control" type="password" autoComplete="current-password" autoFocus value={clave} onChange={(e) => setClave(e.target.value)} />
        </label>
      </form>
    </Modal>
  );
}

export default function ListaRoles() {
  const { tienePermiso, usuario } = useAutenticacion();
  const esAdministrador = usuario?.rol?.nombre === 'administrador';
  const [credenciales, setCredenciales] = useState({});
  const [credencialesActivas, setCredencialesActivas] = useState(false);
  const [reveladas, setReveladas] = useState({});
  const [confirmando, setConfirmando] = useState(null);
  const notificar = useNotificar();
  const navegar = useNavigate();
  const [lista, setLista] = useState(null);
  const [error, setError] = useState('');
  const [modal, setModal] = useState(false);

  const cargar = useCallback(() => {
    roles
      .listar()
      .then((r) => setLista(r.datos))
      .catch((e) => setError(e.message));
  }, []);

  useEffect(cargar, [cargar]);

  // Columnas de credenciales: solo para el administrador.
  useEffect(() => {
    if (!esAdministrador) return;
    roles
      .credenciales()
      .then((r) => {
        setCredencialesActivas(r.habilitado !== false);
        setCredenciales(Object.fromEntries((r.datos || []).map((c) => [c.rol_id, c])));
      })
      .catch(() => {});
  }, [esAdministrador]);

  return (
    <>
      <EncabezadoPagina titulo="Roles y permisos" descripcion={['Un rol por usuario', 'Los permisos del rol definen a qué accede']}>
        {tienePermiso('roles.crear') && (
          <button type="button" className="boton" onClick={() => setModal(true)}>
            <Plus size={16} /> Crear rol
          </button>
        )}
      </EncabezadoPagina>

      <Alerta>{error}</Alerta>
      <Tarjeta sinRelleno>
        {!lista ? (
          <Cargando />
        ) : (
          <Tabla
            filas={lista}
            alSeleccionar={(rol) => navegar(`/roles/${rol.id}`)}
            columnas={[
              {
                clave: 'nombre',
                titulo: 'Rol',
                render: (r) => (
                  <>
                    <strong>{nombreRol(r.nombre)}</strong>
                    <div className="tabla__secundario">{r.descripcion}</div>
                  </>
                ),
              },
              { clave: 'permisos', titulo: 'Permisos', render: (r) => r.permisos?.length ?? 0 },
              { clave: 'usuarios_count', titulo: 'Usuarios' },
              ...(esAdministrador && credencialesActivas
                ? [
                    { clave: 'correo', titulo: 'Correo', render: (r) => credenciales[r.id]?.correo || <span className="texto-suave">Sin usuarios</span> },
                    {
                      clave: 'contrasena',
                      titulo: 'Contraseña',
                      render: (r) => (
                        <ContrasenaRol
                          rol={r}
                          disponible={Boolean(credenciales[r.id]?.correo)}
                          valor={reveladas[r.id]}
                          alPedir={setConfirmando}
                          alOcultar={(id) =>
                            setReveladas((actual) => {
                              const { [id]: _oculta, ...resto } = actual;
                              return resto;
                            })
                          }
                        />
                      ),
                    },
                  ]
                : []),
            ]}
          />
        )}
      </Tarjeta>

      <ModalConfirmarClave
        rol={confirmando}
        alCerrar={() => setConfirmando(null)}
        alRevelar={(id, valor) => {
          setReveladas((actual) => ({ ...actual, [id]: valor }));
          setConfirmando(null);
        }}
      />

      <ModalNuevoRol
        abierto={modal}
        alCerrar={() => setModal(false)}
        alCrear={(rol) => {
          setModal(false);
          notificar('Rol creado. Asígnele permisos.');
          navegar(`/roles/${rol.id}`);
        }}
      />
    </>
  );
}

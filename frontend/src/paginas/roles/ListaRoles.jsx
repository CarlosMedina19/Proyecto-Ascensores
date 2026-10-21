import { useCallback, useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Plus } from 'lucide-react';
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

export default function ListaRoles() {
  const { tienePermiso } = useAutenticacion();
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

  return (
    <>
      <EncabezadoPagina titulo="Roles y permisos" descripcion="Cada usuario tiene un rol; los permisos del rol definen a qué funciones accede.">
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
            ]}
          />
        )}
      </Tarjeta>

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

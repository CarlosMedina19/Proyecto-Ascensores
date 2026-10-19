import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { Mail, Pencil, Phone, Plus, Trash2, UserPlus } from 'lucide-react';
import { clientes } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Confirmacion, Datos, EncabezadoPagina, EstadoVacio, Insignia, Modal, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoTexto } from '../../componentes/Campos';
import { fechaHora, TIPOS_CLIENTE } from '../../utilidades/formato';

function ModalContacto({ clienteId, abierto, alCerrar, alGuardar }) {
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ nombre: '', cargo: '', telefono: '', correo: '' });

  async function guardar() {
    const respuesta = await enviar(() => clientes.agregarContacto(clienteId, datos));
    if (respuesta) {
      setDatos({ nombre: '', cargo: '', telefono: '', correo: '' });
      alGuardar();
    }
  }

  return (
    <Modal
      abierto={abierto}
      titulo="Agregar contacto"
      alCerrar={alCerrar}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar}>
            Cancelar
          </button>
          <button type="button" className="boton" onClick={guardar} disabled={enviando}>
            {enviando ? 'Guardando…' : 'Agregar'}
          </button>
        </>
      }
    >
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <CampoTexto etiqueta="Nombre" obligatorio valor={datos.nombre} alCambiar={cambiar('nombre')} error={error('nombre')} />
        <CampoTexto etiqueta="Cargo" valor={datos.cargo} alCambiar={cambiar('cargo')} error={error('cargo')} />
        <div className="formulario__fila">
          <CampoTexto etiqueta="Teléfono" tipo="tel" valor={datos.telefono} alCambiar={cambiar('telefono')} error={error('telefono')} />
          <CampoTexto etiqueta="Correo" tipo="email" valor={datos.correo} alCambiar={cambiar('correo')} error={error('correo')} />
        </div>
        <span className="campo__ayuda">Registre al menos el teléfono o el correo.</span>
      </div>
    </Modal>
  );
}

export default function DetalleCliente() {
  const { id } = useParams();
  const navegar = useNavigate();
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const [cliente, setCliente] = useState(null);
  const [error, setError] = useState('');
  const [modalContacto, setModalContacto] = useState(false);
  const [confirmarEliminar, setConfirmarEliminar] = useState(false);
  const [eliminando, setEliminando] = useState(false);

  const cargar = useCallback(() => {
    clientes
      .obtener(id)
      .then((respuesta) => setCliente(respuesta.datos))
      .catch((e) => setError(e.message));
  }, [id]);

  useEffect(cargar, [cargar]);

  async function eliminar() {
    setEliminando(true);
    try {
      await clientes.eliminar(id);
      notificar('Cliente eliminado.');
      navegar('/clientes', { replace: true });
    } catch (e) {
      notificar(e.message, 'error');
      setConfirmarEliminar(false);
    } finally {
      setEliminando(false);
    }
  }

  if (error) return <Alerta>{error}</Alerta>;
  if (!cliente) return <Cargando />;

  const edificios = cliente.edificios || [];

  return (
    <>
      <EncabezadoPagina
        titulo={cliente.nombre}
        migas={<Link to="/clientes">Clientes</Link>}
        descripcion={
          <>
            {TIPOS_CLIENTE[cliente.tipo]} ·{' '}
            <Insignia tipo={cliente.estado ? 'exito' : 'neutro'}>{cliente.estado ? 'Activo' : 'Inactivo'}</Insignia>
          </>
        }
      >
        {tienePermiso('clientes.eliminar') && (
          <button type="button" className="boton boton--secundario" onClick={() => setConfirmarEliminar(true)}>
            <Trash2 size={16} /> Eliminar
          </button>
        )}
        {tienePermiso('clientes.editar') && (
          <Link to={`/clientes/${id}/editar`} className="boton">
            <Pencil size={16} /> Editar
          </Link>
        )}
      </EncabezadoPagina>

      <div className="rejilla">
        <div className="rejilla rejilla--2">
          <Tarjeta titulo="Datos del cliente">
            <Datos
              elementos={[
                [cliente.tipo === 'juridica' ? 'NIT' : 'Documento', cliente.tipo === 'juridica' ? cliente.nit : `${cliente.tipo_documento || ''} ${cliente.numero_documento || ''}`],
                ['Correo', cliente.correo],
                ['Teléfono', cliente.telefono],
                ['Dirección', cliente.direccion],
                ['Régimen tributario', cliente.regimen_tributario],
                ['Actividad económica', cliente.actividad_economica],
                ['Registrado', fechaHora(cliente.created_at)],
                ['Identificador', <span key="uuid" className="codigo">{cliente.identificador_uuid}</span>],
              ]}
            />
            {cliente.observaciones && (
              <>
                <div className="separador" />
                <div className="texto-pequeno texto-suave">Observaciones</div>
                <p style={{ margin: '4px 0 0' }}>{cliente.observaciones}</p>
              </>
            )}
          </Tarjeta>

          <Tarjeta
            titulo="Contactos"
            acciones={
              tienePermiso('clientes.contactos.crear') && (
                <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setModalContacto(true)}>
                  <UserPlus size={15} /> Agregar
                </button>
              )
            }
          >
            {cliente.contactos?.length ? (
              <div className="rejilla" style={{ gap: 12 }}>
                {cliente.contactos.map((contacto) => (
                  <div key={contacto.id} style={{ borderBottom: '1px solid var(--color-borde)', paddingBottom: 10 }}>
                    <strong>{contacto.nombre}</strong> {contacto.cargo && <span className="texto-suave">· {contacto.cargo}</span>}
                    <div className="texto-pequeno texto-suave" style={{ display: 'flex', gap: 14, marginTop: 2, flexWrap: 'wrap' }}>
                      {contacto.telefono && (
                        <span>
                          <Phone size={13} style={{ verticalAlign: -2 }} /> {contacto.telefono}
                        </span>
                      )}
                      {contacto.correo && (
                        <span>
                          <Mail size={13} style={{ verticalAlign: -2 }} /> {contacto.correo}
                        </span>
                      )}
                    </div>
                  </div>
                ))}
              </div>
            ) : (
              <EstadoVacio titulo="Sin contactos registrados" />
            )}
          </Tarjeta>
        </div>

        <Tarjeta sinRelleno>
          <div className="tarjeta__cuerpo" style={{ paddingBottom: 0 }}>
            <h2 className="tarjeta__titulo">
              <span>Edificios ({edificios.length})</span>
              {tienePermiso('edificios.crear') && (
                <Link to={`/edificios/nuevo?cliente_id=${id}`} className="boton boton--secundario boton--pequeno">
                  <Plus size={15} /> Nuevo edificio
                </Link>
              )}
            </h2>
          </div>
          <Tabla
            filas={edificios}
            alSeleccionar={(edificio) => navegar(`/edificios/${edificio.id}`)}
            vacio={<EstadoVacio titulo="El cliente no tiene edificios" />}
            columnas={[
              { clave: 'nombre', titulo: 'Edificio', render: (e) => <strong>{e.nombre}</strong> },
              { clave: 'direccion', titulo: 'Dirección' },
              { clave: 'ciudad', titulo: 'Ciudad' },
              {
                clave: 'activo',
                titulo: 'Estado',
                render: (e) => <Insignia tipo={e.activo ? 'exito' : 'neutro'}>{e.activo ? 'Activo' : 'Inactivo'}</Insignia>,
              },
            ]}
          />
        </Tarjeta>
      </div>

      <ModalContacto
        clienteId={id}
        abierto={modalContacto}
        alCerrar={() => setModalContacto(false)}
        alGuardar={() => {
          setModalContacto(false);
          notificar('Contacto agregado.');
          cargar();
        }}
      />
      <Confirmacion
        abierto={confirmarEliminar}
        titulo="Eliminar cliente"
        mensaje="Solo se pueden eliminar clientes sin edificios. Si el cliente tiene historial, desactívelo desde Editar."
        textoConfirmar="Eliminar"
        peligro
        procesando={eliminando}
        alConfirmar={eliminar}
        alCerrar={() => setConfirmarEliminar(false)}
      />
    </>
  );
}

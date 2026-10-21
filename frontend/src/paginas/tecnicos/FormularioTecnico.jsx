import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { tecnicos } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoContrasena, CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';

const TIPOS_DOCUMENTO = [
  { valor: 'CC', texto: 'Cédula de ciudadanía (CC)' },
  { valor: 'CE', texto: 'Cédula de extranjería (CE)' },
  { valor: 'PA', texto: 'Pasaporte (PA)' },
  { valor: 'PEP', texto: 'Permiso especial de permanencia (PEP)' },
];

/**
 * RF-022: registrar un técnico (crea su cuenta) o completar/editar su perfil.
 */
export default function FormularioTecnico() {
  const { id } = useParams();
  const editando = Boolean(id);
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [catalogo, setCatalogo] = useState(null);
  const [nombre, setNombre] = useState('');
  const { datos, setDatos, cambiar, error, errores, mensajeError, enviando, enviar } = useFormulario({
    nombre: '',
    correo: '',
    password: '',
    tipo_documento: 'CC',
    numero_documento: '',
    telefono: '',
    especialidades: [],
    disponibilidad: 'disponible',
    motivo_no_disponible: '',
    no_disponible_hasta: '',
    observaciones: '',
  });

  useEffect(() => {
    tecnicos
      .especialidades()
      .then((r) => setCatalogo(r.datos))
      .catch(() => setCatalogo([]));
  }, []);

  useEffect(() => {
    if (!editando) return;
    tecnicos.obtener(id).then(({ datos: t }) => {
      setNombre(t.nombre);
      setDatos((actual) => ({
        ...actual,
        tipo_documento: t.tipo_documento || 'CC',
        numero_documento: t.numero_documento || '',
        telefono: t.telefono || '',
        especialidades: t.especialidades.map((e) => e.id),
        disponibilidad: t.disponibilidad || 'disponible',
        motivo_no_disponible: t.motivo_no_disponible || '',
        no_disponible_hasta: t.no_disponible_hasta || '',
        observaciones: t.observaciones || '',
      }));
    });
  }, [editando, id, setDatos]);

  function alternarEspecialidad(especialidadId, marcada) {
    setDatos((actual) => ({
      ...actual,
      especialidades: marcada ? [...actual.especialidades, especialidadId] : actual.especialidades.filter((e) => e !== especialidadId),
    }));
  }

  async function guardar(evento) {
    evento.preventDefault();
    const perfil = {
      tipo_documento: datos.tipo_documento,
      numero_documento: datos.numero_documento,
      telefono: datos.telefono || null,
      especialidades: datos.especialidades,
      disponibilidad: datos.disponibilidad,
      motivo_no_disponible: datos.disponibilidad === 'no_disponible' ? datos.motivo_no_disponible : null,
      no_disponible_hasta: datos.disponibilidad === 'no_disponible' && datos.no_disponible_hasta ? datos.no_disponible_hasta : null,
      observaciones: datos.observaciones || null,
    };
    const respuesta = await enviar(() =>
      editando
        ? tecnicos.guardarPerfil(id, perfil)
        : tecnicos.crear({ ...perfil, nombre: datos.nombre, correo: datos.correo, password: datos.password }),
    );
    if (respuesta) {
      notificar(editando ? 'Perfil del técnico actualizado.' : 'Técnico registrado.');
      navegar(`/tecnicos/${respuesta.datos.usuario_id}`, { replace: true });
    }
  }

  if (catalogo === null || (editando && !nombre)) return <Cargando />;

  return (
    <>
      <EncabezadoPagina
        titulo={editando ? `Perfil de ${nombre}` : 'Nuevo técnico'}
        migas={<Link to="/tecnicos">Técnicos</Link>}
        descripcion={editando ? null : ['Crea la cuenta del técnico', 'Ingresa a la app y al panel']}
      />
      <form className="formulario" onSubmit={guardar} noValidate>
        <Alerta>{mensajeError}</Alerta>

        {!editando && (
          <Tarjeta titulo="Cuenta de acceso">
            <div className="formulario__fila formulario__fila--3">
              <CampoTexto etiqueta="Nombre completo" obligatorio valor={datos.nombre} alCambiar={cambiar('nombre')} error={error('nombre')} />
              <CampoTexto etiqueta="Correo" tipo="email" obligatorio valor={datos.correo} alCambiar={cambiar('correo')} error={error('correo')} />
              <CampoContrasena obligatorio valor={datos.password} alCambiar={cambiar('password')} error={error('password')} ayuda="Mínimo 8 caracteres con letras y números." />
            </div>
          </Tarjeta>
        )}

        <Tarjeta titulo="Datos personales">
          <div className="formulario__fila formulario__fila--3">
            <CampoSelector etiqueta="Tipo de documento" obligatorio vacio={null} valor={datos.tipo_documento} alCambiar={cambiar('tipo_documento')} error={error('tipo_documento')} opciones={TIPOS_DOCUMENTO} />
            <CampoTexto etiqueta="Número de documento" obligatorio valor={datos.numero_documento} alCambiar={cambiar('numero_documento')} error={error('numero_documento')} />
            <CampoTexto etiqueta="Teléfono" tipo="tel" valor={datos.telefono} alCambiar={cambiar('telefono')} error={error('telefono')} />
          </div>
        </Tarjeta>

        <Tarjeta titulo={`Especialidades (${datos.especialidades.length})`}>
          {(error('especialidades') || errores['especialidades.0']) && <Alerta>{error('especialidades') || errores['especialidades.0'][0]}</Alerta>}
          <div className="lista-seleccion">
            {catalogo.map((e) => (
              <Casilla key={e.id} etiqueta={e.nombre} valor={datos.especialidades.includes(e.id)} alCambiar={(marcada) => alternarEspecialidad(e.id, marcada)} />
            ))}
          </div>
        </Tarjeta>

        <Tarjeta titulo="Disponibilidad">
          <div className="formulario">
            <Casilla
              etiqueta="Disponible para recibir órdenes"
              valor={datos.disponibilidad === 'disponible'}
              alCambiar={(marcada) => cambiar('disponibilidad')(marcada ? 'disponible' : 'no_disponible')}
            />
            {datos.disponibilidad === 'no_disponible' && (
              <div className="formulario__fila">
                <CampoTexto etiqueta="Motivo" obligatorio valor={datos.motivo_no_disponible} alCambiar={cambiar('motivo_no_disponible')} error={error('motivo_no_disponible')} placeholder="Vacaciones, incapacidad, capacitación…" />
                <CampoTexto etiqueta="Disponible de nuevo desde" tipo="date" valor={datos.no_disponible_hasta} alCambiar={cambiar('no_disponible_hasta')} error={error('no_disponible_hasta')} />
              </div>
            )}
            <CampoArea etiqueta="Observaciones" valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} rows={2} />
          </div>
        </Tarjeta>

        <div className="formulario__acciones">
          <Link to={editando ? `/tecnicos/${id}` : '/tecnicos'} className="boton boton--secundario">
            Cancelar
          </Link>
          <button type="submit" className="boton" disabled={enviando}>
            {enviando ? 'Guardando…' : editando ? 'Guardar perfil' : 'Registrar técnico'}
          </button>
        </div>
      </form>
    </>
  );
}

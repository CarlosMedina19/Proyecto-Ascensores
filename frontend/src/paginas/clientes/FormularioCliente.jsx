import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { clientes } from '../../api/servicios';
import { limpiarVacios, useFormulario } from '../../hooks/useFormulario';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';
import { REGIMENES_TRIBUTARIOS, TIPOS_DOCUMENTO } from '../../utilidades/formato';

const VACIO = {
  tipo: 'juridica',
  nombre: '',
  tipo_documento: 'NIT',
  numero_documento: '',
  nit: '',
  direccion: '',
  telefono: '',
  correo: '',
  regimen_tributario: '',
  actividad_economica: '',
  estado: true,
  observaciones: '',
};

export default function FormularioCliente() {
  const { id } = useParams();
  const editando = Boolean(id);
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [cargando, setCargando] = useState(editando);
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario(VACIO);

  useEffect(() => {
    if (!editando) return;
    clientes
      .obtener(id)
      .then(({ datos: cliente }) => setDatos(Object.fromEntries(Object.keys(VACIO).map((clave) => [clave, cliente[clave] ?? VACIO[clave]]))))
      .finally(() => setCargando(false));
  }, [editando, id, setDatos]);

  const esJuridica = datos.tipo === 'juridica';

  async function alEnviar(evento) {
    evento.preventDefault();
    const cuerpo = limpiarVacios({
      ...datos,
      // Una persona jurídica se identifica con NIT; una natural, con documento.
      nit: esJuridica ? datos.nit : '',
      tipo_documento: esJuridica ? 'NIT' : datos.tipo_documento,
      numero_documento: esJuridica ? '' : datos.numero_documento,
    });
    const respuesta = await enviar(() => (editando ? clientes.actualizar(id, cuerpo) : clientes.crear(cuerpo)));
    if (respuesta) {
      notificar(editando ? 'Cliente actualizado.' : 'Cliente registrado.');
      navegar(`/clientes/${respuesta.datos.id}`);
    }
  }

  if (cargando) return <Cargando />;

  return (
    <>
      <EncabezadoPagina
        titulo={editando ? 'Editar cliente' : 'Nuevo cliente'}
        migas={<Link to="/clientes">Clientes</Link>}
      />
      <Tarjeta>
        <form className="formulario" onSubmit={alEnviar} noValidate>
          <Alerta>{mensajeError}</Alerta>

          <div className="formulario__fila">
            <CampoSelector
              etiqueta="Tipo de persona"
              obligatorio
              vacio={null}
              valor={datos.tipo}
              alCambiar={(valor) => setDatos((d) => ({ ...d, tipo: valor, tipo_documento: valor === 'juridica' ? 'NIT' : 'CC' }))}
              error={error('tipo')}
              opciones={[
                { valor: 'juridica', texto: 'Persona jurídica' },
                { valor: 'natural', texto: 'Persona natural' },
              ]}
            />
            <CampoTexto
              etiqueta={esJuridica ? 'Razón social' : 'Nombre completo'}
              obligatorio
              valor={datos.nombre}
              alCambiar={cambiar('nombre')}
              error={error('nombre')}
            />
          </div>

          {esJuridica ? (
            <CampoTexto
              etiqueta="NIT"
              obligatorio
              valor={datos.nit}
              alCambiar={cambiar('nit')}
              error={error('nit')}
              ayuda="Solo números y, opcionalmente, el dígito de verificación. Ej. 900123456-1"
            />
          ) : (
            <div className="formulario__fila">
              <CampoSelector
                etiqueta="Tipo de documento"
                obligatorio
                valor={datos.tipo_documento}
                alCambiar={cambiar('tipo_documento')}
                error={error('tipo_documento')}
                opciones={TIPOS_DOCUMENTO.filter((t) => t.valor !== 'NIT')}
              />
              <CampoTexto
                etiqueta="Número de documento"
                obligatorio
                valor={datos.numero_documento}
                alCambiar={cambiar('numero_documento')}
                error={error('numero_documento')}
              />
            </div>
          )}

          <div className="formulario__seccion">Contacto</div>
          <div className="formulario__fila--3 formulario__fila">
            <CampoTexto etiqueta="Dirección" valor={datos.direccion} alCambiar={cambiar('direccion')} error={error('direccion')} />
            <CampoTexto etiqueta="Teléfono" tipo="tel" valor={datos.telefono} alCambiar={cambiar('telefono')} error={error('telefono')} />
            <CampoTexto etiqueta="Correo electrónico" tipo="email" valor={datos.correo} alCambiar={cambiar('correo')} error={error('correo')} />
          </div>

          <div className="formulario__seccion">Información tributaria</div>
          <div className="formulario__fila">
            <CampoSelector
              etiqueta="Régimen tributario"
              valor={datos.regimen_tributario}
              alCambiar={cambiar('regimen_tributario')}
              error={error('regimen_tributario')}
              opciones={REGIMENES_TRIBUTARIOS.map((r) => ({ valor: r, texto: r }))}
            />
            <CampoTexto
              etiqueta="Actividad económica (CIIU)"
              valor={datos.actividad_economica}
              alCambiar={cambiar('actividad_economica')}
              error={error('actividad_economica')}
            />
          </div>

          <CampoArea etiqueta="Observaciones" valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} />
          <Casilla etiqueta="Cliente activo" valor={datos.estado} alCambiar={cambiar('estado')} />
          {!datos.estado && (
            <Alerta tipo="alerta">Un cliente inactivo no podrá tener contratos ni órdenes nuevas.</Alerta>
          )}

          <div className="formulario__acciones">
            <button type="button" className="boton boton--secundario" onClick={() => navegar(-1)}>
              Cancelar
            </button>
            <button type="submit" className="boton" disabled={enviando}>
              {enviando ? 'Guardando…' : 'Guardar cliente'}
            </button>
          </div>
        </form>
      </Tarjeta>
    </>
  );
}

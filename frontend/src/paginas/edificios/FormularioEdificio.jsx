import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { clientes, edificios } from '../../api/servicios';
import { limpiarVacios, useFormulario } from '../../hooks/useFormulario';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';
import { TIPOS_EDIFICIO } from '../../utilidades/formato';

const VACIO = {
  cliente_id: '',
  tipo: '',
  nombre: '',
  direccion: '',
  ciudad: '',
  departamento: '',
  codigo_postal: '',
  pisos: '',
  nombre_contacto: '',
  telefono_contacto: '',
  correo_contacto: '',
  observaciones: '',
  activo: true,
};

export default function FormularioEdificio() {
  const { id } = useParams();
  const [parametros] = useSearchParams();
  const editando = Boolean(id);
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [cargando, setCargando] = useState(true);
  const [opcionesClientes, setOpcionesClientes] = useState([]);
  const { datos, setDatos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    ...VACIO,
    cliente_id: parametros.get('cliente_id') || '',
    tipo: TIPOS_EDIFICIO[parametros.get('tipo')] ? parametros.get('tipo') : '',
  });

  useEffect(() => {
    const solicitudes = [clientes.listar({ por_pagina: 100 }).then((r) => setOpcionesClientes(r.datos))];
    if (editando) {
      solicitudes.push(
        edificios
          .obtener(id)
          .then(({ datos: edificio }) =>
            setDatos(Object.fromEntries(Object.keys(VACIO).map((clave) => [clave, edificio[clave] ?? VACIO[clave]]))),
          ),
      );
    }
    Promise.allSettled(solicitudes).finally(() => setCargando(false));
  }, [editando, id, setDatos]);

  async function alEnviar(evento) {
    evento.preventDefault();
    const cuerpo = limpiarVacios({
      ...datos,
      cliente_id: datos.cliente_id ? Number(datos.cliente_id) : '',
      pisos: datos.pisos ? Number(datos.pisos) : '',
    });
    const respuesta = await enviar(() => (editando ? edificios.actualizar(id, cuerpo) : edificios.crear(cuerpo)));
    if (respuesta) {
      notificar(editando ? 'Edificio actualizado.' : 'Edificio registrado.');
      navegar(`/edificios/${respuesta.datos.id}`);
    }
  }

  if (cargando) return <Cargando />;

  return (
    <>
      <EncabezadoPagina titulo={editando ? 'Editar edificio' : 'Nuevo edificio'} migas={<Link to="/edificios">Edificios</Link>} />
      <Tarjeta>
        <form className="formulario" onSubmit={alEnviar} noValidate>
          <Alerta>{mensajeError}</Alerta>

          <div className="formulario__fila formulario__fila--3">
            <CampoSelector
              etiqueta="Cliente"
              obligatorio
              valor={String(datos.cliente_id)}
              alCambiar={cambiar('cliente_id')}
              error={error('cliente_id')}
              opciones={opcionesClientes.map((c) => ({ valor: String(c.id), texto: c.nombre }))}
            />
            <CampoSelector
              etiqueta="Tipo"
              obligatorio
              valor={datos.tipo}
              alCambiar={cambiar('tipo')}
              error={error('tipo')}
              opciones={Object.entries(TIPOS_EDIFICIO).map(([valor, { texto }]) => ({ valor, texto }))}
            />
            <CampoTexto etiqueta="Nombre del edificio" obligatorio valor={datos.nombre} alCambiar={cambiar('nombre')} error={error('nombre')} />
          </div>

          <div className="formulario__seccion">Ubicación</div>
          <div className="formulario__fila formulario__fila--direccion">
            <CampoTexto etiqueta="Dirección" obligatorio valor={datos.direccion} alCambiar={cambiar('direccion')} error={error('direccion')} />
            <CampoTexto etiqueta="Número de pisos" tipo="number" min="1" valor={datos.pisos} alCambiar={cambiar('pisos')} error={error('pisos')} />
          </div>
          <div className="formulario__fila formulario__fila--3">
            <CampoTexto etiqueta="Ciudad" obligatorio valor={datos.ciudad} alCambiar={cambiar('ciudad')} error={error('ciudad')} />
            <CampoTexto etiqueta="Departamento" valor={datos.departamento} alCambiar={cambiar('departamento')} error={error('departamento')} />
            <CampoTexto etiqueta="Código postal" valor={datos.codigo_postal} alCambiar={cambiar('codigo_postal')} error={error('codigo_postal')} />
          </div>

          <div className="formulario__seccion">Contacto del edificio</div>
          <div className="formulario__fila formulario__fila--3">
            <CampoTexto etiqueta="Nombre" obligatorio valor={datos.nombre_contacto} alCambiar={cambiar('nombre_contacto')} error={error('nombre_contacto')} />
            <CampoTexto etiqueta="Teléfono" tipo="tel" valor={datos.telefono_contacto} alCambiar={cambiar('telefono_contacto')} error={error('telefono_contacto')} />
            <CampoTexto etiqueta="Correo" tipo="email" valor={datos.correo_contacto} alCambiar={cambiar('correo_contacto')} error={error('correo_contacto')} />
          </div>
          <span className="campo__ayuda">Cada edificio debe tener al menos un contacto con teléfono o correo.</span>

          <CampoArea etiqueta="Observaciones" rows={2} valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} />
          {editando && <Casilla etiqueta="Edificio activo" valor={datos.activo} alCambiar={cambiar('activo')} />}

          <div className="formulario__acciones">
            <button type="button" className="boton boton--secundario" onClick={() => navegar(-1)}>
              Cancelar
            </button>
            <button type="submit" className="boton" disabled={enviando}>
              {enviando ? 'Guardando…' : 'Guardar edificio'}
            </button>
          </div>
        </form>
      </Tarjeta>
    </>
  );
}

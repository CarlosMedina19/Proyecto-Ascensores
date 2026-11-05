import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import { clientes, conjuntos } from '../../api/servicios';
import { limpiarVacios, useFormulario } from '../../hooks/useFormulario';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoBuscador, CampoSelector, CampoTexto, Casilla } from '../../componentes/Campos';
import CamposUbicacion from '../../componentes/CamposUbicacion';
import { TIPOS_EDIFICIO } from '../../utilidades/formato';

const VACIO = {
  cliente_id: '',
  tipo: '',
  nombre: '',
  direccion: '',
  ciudad: '',
  departamento: '',
  codigo_postal: '',
  nombre_contacto: '',
  telefono_contacto: '',
  correo_contacto: '',
  observaciones: '',
  activo: true,
};

/** Conjunto, institución o residencia: lo que se registre aquí lo heredan sus edificios. */
export default function FormularioConjunto() {
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
  const grupo = TIPOS_EDIFICIO[datos.tipo];
  const nombreTipo = grupo ? grupo.texto.toLowerCase() : 'conjunto';

  useEffect(() => {
    const solicitudes = [
      clientes
        .listar({ por_pagina: 100 })
        .then((r) => setOpcionesClientes(r.datos.map((c) => ({ valor: String(c.id), texto: c.nombre, detalle: c.nit || c.numero_documento })))),
    ];
    if (editando) {
      solicitudes.push(conjuntos.obtener(id).then(({ datos: c }) => setDatos(Object.fromEntries(Object.keys(VACIO).map((k) => [k, c[k] ?? VACIO[k]])))));
    }
    Promise.allSettled(solicitudes).finally(() => setCargando(false));
  }, [editando, id, setDatos]);

  async function alEnviar(evento) {
    evento.preventDefault();
    const cuerpo = limpiarVacios({ ...datos, cliente_id: datos.cliente_id ? Number(datos.cliente_id) : '' });
    const r = await enviar(() => (editando ? conjuntos.actualizar(id, cuerpo) : conjuntos.crear(cuerpo)));
    if (r) {
      notificar(editando ? 'Datos actualizados.' : `${grupo?.texto ?? 'Conjunto'} registrado.`);
      navegar(`/conjuntos/${r.datos.id}`);
    }
  }

  if (cargando) return <Cargando />;

  return (
    <>
      <EncabezadoPagina
        titulo={editando ? `Editar ${datos.nombre}` : `Nuevo ${nombreTipo}`}
        migas={grupo ? <Link to={grupo.ruta}>{grupo.plural}</Link> : <Link to="/clientes">Clientes</Link>}
      />
      <Tarjeta>
        <form className="formulario" onSubmit={alEnviar} noValidate>
          <Alerta>{mensajeError}</Alerta>
          <div className="formulario__fila formulario__fila--3">
            <CampoBuscador
              etiqueta="Cliente"
              obligatorio
              valor={datos.cliente_id}
              alCambiar={cambiar('cliente_id')}
              error={error('cliente_id')}
              opciones={opcionesClientes}
              placeholder="Busque el cliente por nombre o NIT"
            />
            <CampoSelector
              etiqueta="Tipo"
              obligatorio
              valor={datos.tipo}
              alCambiar={cambiar('tipo')}
              error={error('tipo')}
              opciones={Object.entries(TIPOS_EDIFICIO).map(([valor, { texto }]) => ({ valor, texto }))}
            />
            <CampoTexto etiqueta="Nombre" obligatorio valor={datos.nombre} alCambiar={cambiar('nombre')} error={error('nombre')} placeholder="Conjunto Residencial Los Pinos" />
          </div>

          <div className="formulario__seccion">Ubicación</div>
          <div className="formulario__fila formulario__fila--3">
            <CamposUbicacion
              departamento={datos.departamento}
              ciudad={datos.ciudad}
              errores={{ departamento: error('departamento'), ciudad: error('ciudad') }}
              alCambiar={(u) => setDatos((d) => ({ ...d, ...u }))}
            />
            <CampoTexto etiqueta="Código postal" valor={datos.codigo_postal} alCambiar={cambiar('codigo_postal')} error={error('codigo_postal')} />
          </div>
          <CampoTexto etiqueta="Dirección" obligatorio valor={datos.direccion} alCambiar={cambiar('direccion')} error={error('direccion')} />

          <div className="formulario__seccion">Contacto (administración)</div>
          <div className="formulario__fila formulario__fila--3">
            <CampoTexto etiqueta="Nombre" valor={datos.nombre_contacto} alCambiar={cambiar('nombre_contacto')} error={error('nombre_contacto')} />
            <CampoTexto etiqueta="Teléfono" tipo="tel" valor={datos.telefono_contacto} alCambiar={cambiar('telefono_contacto')} error={error('telefono_contacto')} />
            <CampoTexto etiqueta="Correo" tipo="email" valor={datos.correo_contacto} alCambiar={cambiar('correo_contacto')} error={error('correo_contacto')} />
          </div>
          <span className="campo__ayuda">Los edificios nuevos de este {nombreTipo} toman esta dirección y este contacto; en cada edificio se pueden cambiar.</span>

          <CampoArea etiqueta="Observaciones" rows={2} valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} />
          {editando && <Casilla etiqueta="Activo" valor={datos.activo} alCambiar={cambiar('activo')} />}

          <div className="formulario__acciones">
            <button type="button" className="boton boton--secundario" onClick={() => navegar(-1)}>
              Cancelar
            </button>
            <button type="submit" className="boton" disabled={enviando}>
              {enviando ? 'Guardando…' : 'Guardar'}
            </button>
          </div>
        </form>
      </Tarjeta>
    </>
  );
}

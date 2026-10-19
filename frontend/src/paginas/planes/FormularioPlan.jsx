import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { configuracion, planes } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, EncabezadoPagina, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoTexto, Casilla } from '../../componentes/Campos';
import { periodicidad } from '../../utilidades/formato';

/**
 * RF-010 y RF-012: plan con nombre, servicios incluidos y un precio por periodicidad.
 * Al editar, los precios no se tocan aquí: se cambian con motivo desde el detalle (RF-015).
 */
export default function FormularioPlan() {
  const { id } = useParams();
  const editando = Boolean(id);
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [periodicidades, setPeriodicidades] = useState(null);
  const [precios, setPrecios] = useState({});
  const [cargado, setCargado] = useState(!editando);
  const { datos, setDatos, cambiar, error, errores, mensajeError, enviando, enviar } = useFormulario({
    nombre: '',
    descripcion: '',
    servicios: '',
    activo: true,
  });

  useEffect(() => {
    configuracion
      .periodicidades()
      .then((r) => setPeriodicidades(r.datos.filter((p) => p.activo)))
      .catch(() => setPeriodicidades([]));
  }, []);

  useEffect(() => {
    if (!editando) return;
    planes.obtener(id).then(({ datos: plan }) => {
      setDatos({
        nombre: plan.nombre,
        descripcion: plan.descripcion ?? '',
        servicios: plan.servicios_incluidos.join('\n'),
        activo: plan.activo,
      });
      setCargado(true);
    });
  }, [editando, id, setDatos]);

  // Los precios se envían en el orden de las periodicidades con valor; así se ubica cada error.
  const conPrecio = (periodicidades || []).filter((p) => precios[p.id] !== undefined && precios[p.id] !== '');
  const errorPrecio = (periodicidadId) => {
    const indice = conPrecio.findIndex((p) => p.id === periodicidadId);
    return indice >= 0 ? errores[`precios.${indice}.precio`]?.[0] : undefined;
  };

  async function guardar(evento) {
    evento.preventDefault();
    const cuerpo = {
      nombre: datos.nombre,
      descripcion: datos.descripcion || null,
      servicios_incluidos: datos.servicios
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean),
      activo: datos.activo,
    };
    if (!editando) {
      cuerpo.precios = conPrecio.map((p) => ({ periodicidad_id: p.id, precio: Number(precios[p.id]) }));
    }

    const respuesta = await enviar(() => (editando ? planes.actualizar(id, cuerpo) : planes.crear(cuerpo)));
    if (respuesta) {
      notificar(editando ? 'Plan actualizado.' : 'Plan creado.');
      navegar(`/planes/${respuesta.datos.id}`, { replace: true });
    }
  }

  if (!cargado || periodicidades === null) return <Cargando />;

  return (
    <>
      <EncabezadoPagina titulo={editando ? 'Editar plan' : 'Nuevo plan'} migas={<Link to="/planes">Planes</Link>} />
      <form className="formulario" onSubmit={guardar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <Tarjeta titulo="Datos del plan">
          <div className="formulario">
            <CampoTexto etiqueta="Nombre" obligatorio valor={datos.nombre} alCambiar={cambiar('nombre')} error={error('nombre')} maxLength={150} />
            <CampoArea etiqueta="Descripción" valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} rows={2} />
            <CampoArea
              etiqueta="Servicios incluidos"
              ayuda="Escriba un servicio por línea."
              valor={datos.servicios}
              alCambiar={cambiar('servicios')}
              error={error('servicios_incluidos') || errores['servicios_incluidos.0']?.[0]}
              rows={5}
            />
            <Casilla etiqueta="Plan activo (disponible para asignar)" valor={datos.activo} alCambiar={cambiar('activo')} />
          </div>
        </Tarjeta>

        {editando ? (
          <Alerta tipo="info">Los precios se cambian desde el detalle del plan con «Cambiar precios», indicando el motivo.</Alerta>
        ) : (
          <Tarjeta titulo="Precio por visita según la periodicidad">
            <p className="texto-pequeno texto-suave" style={{ marginTop: 0 }}>
              Deje vacía una periodicidad si el plan no se ofrece con ella. Debe indicar al menos un precio.
            </p>
            {error('precios') && <Alerta>{error('precios')}</Alerta>}
            <div className="formulario__fila formulario__fila--3">
              {periodicidades.map((p) => (
                <CampoTexto
                  key={p.id}
                  etiqueta={periodicidad(p.meses)}
                  tipo="number"
                  min="1"
                  step="1000"
                  inputMode="numeric"
                  placeholder="$"
                  valor={precios[p.id] ?? ''}
                  alCambiar={(valor) => setPrecios((actual) => ({ ...actual, [p.id]: valor }))}
                  error={errorPrecio(p.id)}
                />
              ))}
            </div>
          </Tarjeta>
        )}

        <div className="formulario__acciones">
          <Link to={editando ? `/planes/${id}` : '/planes'} className="boton boton--secundario">
            Cancelar
          </Link>
          <button type="submit" className="boton" disabled={enviando}>
            {enviando ? 'Guardando…' : 'Guardar plan'}
          </button>
        </div>
      </form>
    </>
  );
}

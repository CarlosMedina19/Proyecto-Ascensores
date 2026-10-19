import { useCallback, useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { BadgeDollarSign, Check, Pencil, Trash2 } from 'lucide-react';
import { configuracion, planes } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Confirmacion, EncabezadoPagina, EstadoVacio, Insignia, Modal, Tabla, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoTexto } from '../../componentes/Campos';
import { dinero, fechaHora, periodicidad } from '../../utilidades/formato';

/** RF-015: cambio de precios del catálogo con motivo obligatorio. */
function ModalPrecios({ plan, periodicidades, abierto, alCerrar, alGuardar }) {
  const inicial = () => Object.fromEntries(plan.precios.map((p) => [p.periodicidad_id, p.precio ? String(Number(p.precio)) : '']));
  const [precios, setPrecios] = useState(inicial);
  const { datos, setDatos, cambiar, error, errores, mensajeError, enviando, enviar } = useFormulario({ motivo: '' });

  const conPrecio = periodicidades.filter((p) => precios[p.id] !== undefined && precios[p.id] !== '');

  async function guardar() {
    const respuesta = await enviar(() =>
      planes.actualizarPrecios(plan.id, {
        precios: conPrecio.map((p) => ({ periodicidad_id: p.id, precio: Number(precios[p.id]) })),
        motivo: datos.motivo,
      }),
    );
    if (respuesta) {
      setDatos({ motivo: '' });
      alGuardar(respuesta.mensaje);
    }
  }

  return (
    <Modal
      abierto={abierto}
      titulo="Cambiar precios del plan"
      alCerrar={alCerrar}
      ancho
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar}>
            Cancelar
          </button>
          <button type="button" className="boton" onClick={guardar} disabled={enviando}>
            {enviando ? 'Guardando…' : 'Guardar precios'}
          </button>
        </>
      }
    >
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <Alerta tipo="info">
          El nuevo precio aplica a las asignaciones que se hagan desde ahora. Los ascensores que ya tienen el plan conservan su precio
          pactado.
        </Alerta>
        <div className="formulario__fila formulario__fila--3">
          {periodicidades.map((p) => {
            const indice = conPrecio.findIndex((c) => c.id === p.id);
            return (
              <CampoTexto
                key={p.id}
                etiqueta={periodicidad(p.meses)}
                tipo="number"
                min="1"
                step="1000"
                valor={precios[p.id] ?? ''}
                alCambiar={(valor) => setPrecios((actual) => ({ ...actual, [p.id]: valor }))}
                error={indice >= 0 ? errores[`precios.${indice}.precio`]?.[0] : undefined}
              />
            );
          })}
        </div>
        <CampoArea etiqueta="Motivo del cambio" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={2} />
      </div>
    </Modal>
  );
}

export default function DetallePlan() {
  const { id } = useParams();
  const navegar = useNavigate();
  const notificar = useNotificar();
  const { tienePermiso } = useAutenticacion();
  const [plan, setPlan] = useState(null);
  const [periodicidades, setPeriodicidades] = useState([]);
  const [historial, setHistorial] = useState(null);
  const [error, setError] = useState('');
  const [modalPrecios, setModalPrecios] = useState(false);
  const [confirmarEliminar, setConfirmarEliminar] = useState(false);
  const [procesando, setProcesando] = useState(false);

  const cargar = useCallback(() => {
    planes
      .obtener(id)
      .then((r) => setPlan(r.datos))
      .catch((e) => setError(e.message));
    planes
      .historialPrecios(id, { por_pagina: 50 })
      .then((r) => setHistorial(r.datos))
      .catch(() => setHistorial([]));
  }, [id]);

  useEffect(cargar, [cargar]);
  useEffect(() => {
    configuracion
      .periodicidades()
      .then((r) => setPeriodicidades(r.datos.filter((p) => p.activo)))
      .catch(() => {});
  }, []);

  async function eliminar() {
    setProcesando(true);
    try {
      await planes.eliminar(id);
      notificar('Plan eliminado.');
      navegar('/planes', { replace: true });
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setProcesando(false);
      setConfirmarEliminar(false);
    }
  }

  if (error) return <Alerta>{error}</Alerta>;
  if (!plan) return <Cargando />;

  const precios = plan.precios.slice().sort((a, b) => a.periodicidad.meses - b.periodicidad.meses);

  return (
    <>
      <EncabezadoPagina
        titulo={plan.nombre}
        migas={<Link to="/planes">Planes</Link>}
        descripcion={
          <>
            <Insignia tipo={plan.activo ? 'exito' : 'neutro'}>{plan.activo ? 'Activo' : 'Inactivo'}</Insignia> ·{' '}
            {plan.asignaciones_activas_count ?? 0} ascensores con este plan
          </>
        }
      >
        {tienePermiso('planes.eliminar') && (
          <button type="button" className="boton boton--secundario" onClick={() => setConfirmarEliminar(true)}>
            <Trash2 size={16} /> Eliminar
          </button>
        )}
        {tienePermiso('precios.editar') && (
          <button type="button" className="boton boton--secundario" onClick={() => setModalPrecios(true)} disabled={!periodicidades.length}>
            <BadgeDollarSign size={16} /> Cambiar precios
          </button>
        )}
        {tienePermiso('planes.editar') && (
          <Link to={`/planes/${id}/editar`} className="boton">
            <Pencil size={16} /> Editar
          </Link>
        )}
      </EncabezadoPagina>

      <div className="rejilla rejilla--2" style={{ alignItems: 'start' }}>
        <Tarjeta titulo="Servicios incluidos">
          {plan.descripcion && <p style={{ marginTop: 0 }}>{plan.descripcion}</p>}
          {plan.servicios_incluidos.length ? (
            <ul className="lista-verificacion">
              {plan.servicios_incluidos.map((servicio) => (
                <li key={servicio}>
                  <Check size={15} aria-hidden="true" /> {servicio}
                </li>
              ))}
            </ul>
          ) : (
            <EstadoVacio titulo="Sin servicios registrados" />
          )}
        </Tarjeta>

        <Tarjeta sinRelleno>
          <div className="tarjeta__cuerpo" style={{ paddingBottom: 0 }}>
            <h2 className="tarjeta__titulo">Precio por visita</h2>
          </div>
          <Tabla
            filas={precios}
            claveFila="periodicidad_id"
            columnas={[
              { clave: 'periodicidad', titulo: 'Periodicidad', render: (p) => periodicidad(p.periodicidad.meses) },
              { clave: 'precio', titulo: 'Precio', clase: 'tabular', render: (p) => <strong>{dinero(p.precio)}</strong> },
              { clave: 'actualizado', titulo: 'Última actualización', render: (p) => fechaHora(p.updated_at) },
            ]}
            vacio={<EstadoVacio titulo="El plan no tiene precios" />}
          />
        </Tarjeta>
      </div>

      <div style={{ marginTop: 16 }}>
        <Tarjeta sinRelleno>
          <div className="tarjeta__cuerpo" style={{ paddingBottom: 0 }}>
            <h2 className="tarjeta__titulo">Historial de precios</h2>
          </div>
          {historial === null ? (
            <Cargando />
          ) : (
            <Tabla
              filas={historial}
              columnas={[
                { clave: 'fecha', titulo: 'Fecha', render: (h) => fechaHora(h.created_at) },
                { clave: 'periodicidad', titulo: 'Periodicidad', render: (h) => periodicidad(h.periodicidad?.meses) },
                {
                  clave: 'cambio',
                  titulo: 'Cambio',
                  clase: 'tabular',
                  render: (h) =>
                    h.precio_anterior ? (
                      <>
                        <span className="texto-suave">{dinero(h.precio_anterior)}</span> → <strong>{dinero(h.precio_nuevo)}</strong>
                      </>
                    ) : (
                      <strong>{dinero(h.precio_nuevo)}</strong>
                    ),
                },
                { clave: 'motivo', titulo: 'Motivo' },
                { clave: 'usuario', titulo: 'Usuario', render: (h) => h.usuario?.nombre ?? 'Sistema' },
              ]}
              vacio={<EstadoVacio titulo="Sin cambios de precio" />}
            />
          )}
        </Tarjeta>
      </div>

      {modalPrecios && (
        <ModalPrecios
          plan={plan}
          periodicidades={periodicidades}
          abierto
          alCerrar={() => setModalPrecios(false)}
          alGuardar={(mensaje) => {
            setModalPrecios(false);
            notificar(mensaje || 'Precios actualizados.');
            cargar();
          }}
        />
      )}
      <Confirmacion
        abierto={confirmarEliminar}
        titulo="Eliminar plan"
        mensaje="El plan deja de estar disponible. No se puede eliminar si algún ascensor lo tiene activo."
        textoConfirmar="Eliminar"
        peligro
        procesando={procesando}
        alConfirmar={eliminar}
        alCerrar={() => setConfirmarEliminar(false)}
      />
    </>
  );
}

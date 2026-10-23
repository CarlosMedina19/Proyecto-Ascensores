import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { CircleCheck, ClipboardList, MapPin, Phone, UserRoundCheck, XCircle } from 'lucide-react';
import { servicio, tecnicos } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando, Datos, EncabezadoPagina, Insignia, Modal, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoSelector } from '../../componentes/Campos';
import ModalOrdenDerivada from '../../componentes/ModalOrdenDerivada';
import { fechaHora } from '../../utilidades/formato';
import { ESTADOS_EMERGENCIA, ORIGENES_EMERGENCIA, TIPOS_EMERGENCIA, duracion } from '../../utilidades/servicio';
import { TiempoRespuesta } from './Emergencias';

const ACCIONES = {
  asignada: { texto: 'Asignar técnico', icono: UserRoundCheck },
  en_sitio: { texto: 'Marcar llegada al sitio', icono: MapPin },
  resuelta: { texto: 'Registrar solución', icono: CircleCheck },
  cancelada: { texto: 'Cancelar emergencia', icono: XCircle },
};

/** Seguimiento de una emergencia: tiempo frente al objetivo, línea de tiempo y acciones según el estado. */
export default function DetalleEmergencia() {
  const { id } = useParams();
  const { tienePermiso, usuario } = useAutenticacion();
  const [emergencia, setEmergencia] = useState(null);
  const [error, setError] = useState('');
  const [accion, setAccion] = useState(null);
  const [creandoOrden, setCreandoOrden] = useState(false);

  const cargar = useCallback(() => {
    servicio
      .emergencia(id)
      .then((r) => setEmergencia(r.datos))
      .catch((e) => setError(e.message));
  }, [id]);
  useEffect(cargar, [cargar]);

  // Mientras está abierta, el cronómetro se actualiza cada 30 segundos.
  useEffect(() => {
    if (!emergencia || ['resuelta', 'cancelada'].includes(emergencia.estado)) return undefined;
    const reloj = setInterval(cargar, 30000);
    return () => clearInterval(reloj);
  }, [emergencia, cargar]);

  if (error) return <Alerta>{error}</Alerta>;
  if (!emergencia) return <Cargando />;

  const gestiona = tienePermiso('emergencias.gestionar');
  const atiende = tienePermiso('emergencias.atender') && (!emergencia.tecnico || emergencia.tecnico.nombre === usuario?.nombre);
  const acciones = emergencia.siguientes.filter((s) => gestiona || (atiende && ['en_sitio', 'resuelta'].includes(s)));
  const estado = ESTADOS_EMERGENCIA[emergencia.estado];
  const puedeCrearOrden = tienePermiso('ordenes.crear') && emergencia.estado !== 'cancelada' && !emergencia.orden;

  return (
    <>
      <EncabezadoPagina
        titulo={`${emergencia.numero} · ${TIPOS_EMERGENCIA[emergencia.tipo]}`}
        descripcion={`${emergencia.equipo?.codigo} · ${emergencia.equipo?.ubicacion || emergencia.equipo?.edificio?.nombre || ''}`}
        migas={<Link to="/emergencias">Emergencias</Link>}
      >
        {acciones.map((s, i) => {
          const a = ACCIONES[s];
          const Icono = a.icono;
          // Solo el siguiente paso natural es el botón principal.
          const clase = i === 0 && s !== 'cancelada' ? 'boton' : 'boton boton--secundario';
          return (
            <button key={s} type="button" className={clase} onClick={() => setAccion(s)}>
              <Icono size={16} aria-hidden="true" /> {a.texto}
            </button>
          );
        })}
        {puedeCrearOrden && (
          <button type="button" className="boton boton--secundario" onClick={() => setCreandoOrden(true)}>
            <ClipboardList size={16} aria-hidden="true" /> Crear orden de trabajo
          </button>
        )}
      </EncabezadoPagina>

      {emergencia.personas_atrapadas && emergencia.estado !== 'resuelta' && emergencia.estado !== 'cancelada' && (
        <Alerta>Hay personas atrapadas. Mantenga la comunicación con ellas por el citófono de la cabina hasta que llegue el técnico.</Alerta>
      )}

      <div className="rejilla rejilla--2" style={{ marginBottom: 16 }}>
        <Tarjeta titulo="Estado">
          <div className="emergencia__estado">
            <Insignia tipo={estado?.tipo}>{estado?.texto}</Insignia>
            <TiempoRespuesta emergencia={emergencia} />
          </div>
          <Datos
            elementos={[
              ['Reportada', fechaHora(emergencia.reportada_en)],
              ['Origen', ORIGENES_EMERGENCIA[emergencia.origen]],
              ['Llegada del técnico', emergencia.llegada_en ? fechaHora(emergencia.llegada_en) : 'Pendiente'],
              ['Objetivo de llegada', duracion(emergencia.minutos_objetivo)],
              ['Resuelta', emergencia.resuelta_en ? fechaHora(emergencia.resuelta_en) : '—'],
              ['Técnico', emergencia.tecnico ? emergencia.tecnico.nombre : 'Sin asignar'],
              ['Orden de trabajo', emergencia.orden ? <Link key="o" to={`/ordenes/${emergencia.orden.id}`}>{emergencia.orden.numero}</Link> : '—'],
            ]}
          />
          {emergencia.solucion && (
            <p style={{ marginBottom: 0 }}>
              <b>Solución:</b> {emergencia.solucion}
            </p>
          )}
        </Tarjeta>
        <Tarjeta titulo="Reporte">
          <Datos
            columnas={1}
            elementos={[
              ['Ascensor', <Link key="a" to={`/equipos/${emergencia.equipo?.id}`}>{emergencia.equipo?.codigo}{emergencia.equipo?.nombre ? ` · ${emergencia.equipo.nombre}` : ''}</Link>],
              ['Cliente', emergencia.equipo?.cliente?.nombre || '—'],
              ['Quién reportó', emergencia.reportante_nombre || '—'],
              [
                'Teléfono',
                emergencia.reportante_telefono ? (
                  <span key="t">
                    <Phone size={13} aria-hidden="true" /> {emergencia.reportante_telefono}
                  </span>
                ) : (
                  '—'
                ),
              ],
              ['Descripción', emergencia.descripcion || '—'],
            ]}
          />
        </Tarjeta>
      </div>

      <Tarjeta titulo="Seguimiento">
        <ol className="seguimiento">
          {emergencia.seguimiento.map((s, i) => (
            <li key={i}>
              <Insignia tipo={ESTADOS_EMERGENCIA[s.estado]?.tipo}>{ESTADOS_EMERGENCIA[s.estado]?.texto}</Insignia>
              <div>
                {s.nota && <div>{s.nota}</div>}
                <div className="texto-pequeno texto-suave">
                  {fechaHora(s.fecha)}
                  {s.usuario ? ` · ${s.usuario}` : ''}
                </div>
              </div>
            </li>
          ))}
        </ol>
      </Tarjeta>

      {creandoOrden && (
        <ModalOrdenDerivada
          titulo={`Orden de trabajo para ${emergencia.numero}`}
          ayuda={
            ['resuelta'].includes(emergencia.estado)
              ? 'Mantenimiento correctivo para la reparación de fondo. Si no elige fecha, queda para el siguiente día hábil a las 08:00.'
              : 'Mantenimiento correctivo para hoy con prioridad crítica, mientras la emergencia sigue abierta.'
          }
          crear={(datos) => servicio.ordenDesdeEmergencia(emergencia.id, datos)}
          alCerrar={() => setCreandoOrden(false)}
        />
      )}

      {accion && (
        <ModalAccion
          emergencia={emergencia}
          accion={accion}
          alCerrar={(nueva) => {
            setAccion(null);
            if (nueva) setEmergencia(nueva);
          }}
        />
      )}
    </>
  );
}

function ModalAccion({ emergencia, accion, alCerrar }) {
  const notificar = useNotificar();
  const [opciones, setOpciones] = useState([]);
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ tecnico_id: '', nota: '', solucion: '' });

  useEffect(() => {
    if (accion !== 'asignada') return;
    tecnicos
      .listar({ por_pagina: 100, activo: 1 })
      .then((r) => setOpciones(r.datos.filter((t) => t.id)))
      .catch(() => {});
  }, [accion]);

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() =>
      servicio.cambiarEstadoEmergencia(emergencia.id, {
        estado: accion,
        tecnico_id: datos.tecnico_id ? Number(datos.tecnico_id) : null,
        nota: datos.nota || null,
        solucion: datos.solucion || null,
      }),
    );
    if (respuesta) {
      notificar(respuesta.mensaje);
      alCerrar(respuesta.datos);
    }
  }

  const titulo = ACCIONES[accion].texto;
  return (
    <Modal
      titulo={titulo}
      abierto
      alCerrar={() => alCerrar(null)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(null)}>
            Volver
          </button>
          <button type="submit" form="formulario-accion" className="boton" disabled={enviando}>
            {enviando ? 'Guardando…' : titulo}
          </button>
        </>
      }
    >
      <form id="formulario-accion" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        {accion === 'asignada' && (
          <CampoSelector
            etiqueta="Técnico"
            obligatorio
            valor={datos.tecnico_id}
            alCambiar={cambiar('tecnico_id')}
            error={error('tecnico_id')}
            opciones={opciones.map((t) => ({ valor: String(t.id), texto: `${t.nombre}${t.disponibilidad && t.disponibilidad !== 'disponible' ? ' (no disponible)' : ''}` }))}
          />
        )}
        {accion === 'resuelta' && (
          <CampoArea etiqueta="Qué se hizo" obligatorio rows={4} valor={datos.solucion} alCambiar={cambiar('solucion')} error={error('solucion')} placeholder="Rescate de pasajeros, ajuste del operador de puertas, cambio de fusible…" />
        )}
        {accion === 'cancelada' && (
          <CampoArea etiqueta="Motivo de la cancelación" obligatorio rows={3} valor={datos.nota} alCambiar={cambiar('nota')} error={error('nota')} placeholder="Reporte duplicado, falsa alarma…" />
        )}
        {(accion === 'asignada' || accion === 'en_sitio') && (
          <CampoArea etiqueta="Nota" rows={2} valor={datos.nota} alCambiar={cambiar('nota')} error={error('nota')} />
        )}
      </form>
    </Modal>
  );
}

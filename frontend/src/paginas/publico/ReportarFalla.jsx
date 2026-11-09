import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { AlertTriangle, CircleCheck, LogIn, Siren, Wrench } from 'lucide-react';
import { publico, servicio } from '../../api/servicios';
import { useAutenticacion } from '../../contexto/AutenticacionContexto';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Cargando } from '../../componentes/Basicos';
import { CampoArea } from '../../componentes/Campos';
import { Marca } from '../../componentes/Marca';
import { TIPOS_EMERGENCIA } from '../../utilidades/servicio';
import { fecha } from '../../utilidades/formato';

const ESTADOS = {
  activo: { texto: 'En servicio', clase: 'exito', detalle: 'El ascensor funciona con normalidad.' },
  mantenimiento: { texto: 'En mantenimiento', clase: 'alerta', detalle: 'El ascensor está en mantenimiento. Use otro ascensor o las escaleras.' },
  falla: { texto: 'Falla reportada', clase: 'peligro', detalle: 'Ya se reportó una falla y un técnico la está atendiendo.' },
  inactivo: { texto: 'Fuera de servicio', clase: 'neutro', detalle: 'El ascensor está fuera de servicio.' },
};

/** Estado del ascensor: lo único que ve cualquier persona que escanee el QR. */
function EstadoAscensor({ ascensor }) {
  const estado = ESTADOS[ascensor.estado] ?? ESTADOS.activo;
  const datos = [
    ['Tipo', ascensor.tipo],
    ['Capacidad', [ascensor.capacidad_personas && `${ascensor.capacidad_personas} personas`, ascensor.capacidad_kg && `${ascensor.capacidad_kg} kg`].filter(Boolean).join(' · ')],
    ['Paradas', ascensor.paradas],
    ['Último mantenimiento', ascensor.ultimo_mantenimiento ? fecha(ascensor.ultimo_mantenimiento) : null],
    ['Próximo mantenimiento', ascensor.proximo_mantenimiento ? fecha(ascensor.proximo_mantenimiento) : null],
    ['Certificación anual', ascensor.certificacion ? `${ascensor.certificacion.vigente ? 'Vigente' : 'Vencida'} · ${fecha(ascensor.certificacion.vence)}` : null],
  ].filter(([, valor]) => valor);

  return (
    <>
      <div>
        <h1 className="reporte-publico__titulo">Ascensor {ascensor.codigo}</h1>
        <p className="texto-suave" style={{ margin: 0 }}>
          {[ascensor.nombre, ascensor.edificio, ascensor.direccion].filter(Boolean).join(' · ')}
        </p>
      </div>
      <div className={`estado-qr estado-qr--${estado.clase}`} role="status">
        <b>{estado.texto}</b>
        <span>{estado.detalle}</span>
      </div>
      {datos.length > 0 && (
        <dl className="estado-qr__datos">
          {datos.map(([etiqueta, valor]) => (
            <div key={etiqueta}>
              <dt>{etiqueta}</dt>
              <dd>{valor}</dd>
            </div>
          ))}
        </dl>
      )}
    </>
  );
}

/** Reporte de una anomalía por el técnico (igual que una emergencia, ya con el ascensor elegido). */
function ReporteAnomalia({ equipoId, alVolver }) {
  const [recibido, setRecibido] = useState(null);
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ tipo: '', descripcion: '' });

  async function alEnviar(evento) {
    evento.preventDefault();
    const r = await enviar(() => servicio.reportarEmergencia({ equipo_id: equipoId, tipo: datos.tipo, personas_atrapadas: datos.tipo === 'atrapamiento', descripcion: datos.descripcion || null }));
    if (r) setRecibido(r);
  }

  if (recibido) {
    return (
      <div className="reporte-publico__recibido" role="status">
        <CircleCheck size={40} aria-hidden="true" />
        <h1>Anomalía reportada</h1>
        <p>{recibido.mensaje}</p>
        <Link to={`/emergencias/${recibido.datos.id}`} className="boton boton--bloque">
          Ver el seguimiento
        </Link>
      </div>
    );
  }

  return (
    <form className="formulario" onSubmit={alEnviar} noValidate>
      <h2 className="reporte-publico__titulo">Reportar anomalía</h2>
      <Alerta>{mensajeError}</Alerta>
      <fieldset className="reporte-publico__tipos">
        <legend>¿Qué pasa?</legend>
        {Object.entries(TIPOS_EMERGENCIA).map(([valor, texto]) => (
          <label key={valor} className={`reporte-publico__tipo${datos.tipo === valor ? ' activo' : ''}${valor === 'atrapamiento' ? ' urgente' : ''}`}>
            <input type="radio" name="tipo" value={valor} checked={datos.tipo === valor} onChange={() => cambiar('tipo')(valor)} />
            {valor === 'atrapamiento' && <Siren size={18} aria-hidden="true" />}
            {texto}
          </label>
        ))}
        {error('tipo') && <span className="campo__error">Elija qué pasa con el ascensor.</span>}
      </fieldset>
      <CampoArea etiqueta="Detalles" rows={3} valor={datos.descripcion} alCambiar={cambiar('descripcion')} error={error('descripcion')} placeholder="Piso, ruido, puertas…" />
      <button type="submit" className="boton boton--bloque boton--alto" disabled={enviando || !datos.tipo}>
        {enviando ? 'Enviando…' : 'Enviar reporte'}
      </button>
      <button type="button" className="boton boton--secundario boton--bloque" onClick={alVolver}>
        Volver
      </button>
    </form>
  );
}

/** Opciones del técnico con sesión: iniciar el mantenimiento o reportar una anomalía. */
function OpcionesTecnico({ codigo }) {
  const navegar = useNavigate();
  const notificar = useNotificar();
  const [qr, setQr] = useState(null);
  const [error, setError] = useState('');
  const [iniciando, setIniciando] = useState(false);
  const [reportando, setReportando] = useState(false);

  useEffect(() => {
    publico
      .tecnico(codigo)
      .then((r) => setQr(r.datos))
      .catch((e) => setError(e.message));
  }, [codigo]);

  async function iniciar() {
    setIniciando(true);
    setError('');
    try {
      const r = await publico.iniciarMantenimiento(codigo);
      notificar(r.mensaje);
      navegar(`/ordenes/${r.datos.id}`);
    } catch (e) {
      setError(e.message);
      setIniciando(false);
    }
  }

  if (!qr) return error ? <Alerta>{error}</Alerta> : <Cargando />;
  if (reportando) return <ReporteAnomalia equipoId={qr.equipo_id} alVolver={() => setReportando(false)} />;

  return (
    <div className="opciones-qr">
      <Alerta>{error}</Alerta>
      <button type="button" className="boton boton--bloque boton--alto" onClick={iniciar} disabled={iniciando}>
        <Wrench size={18} aria-hidden="true" /> {iniciando ? 'Iniciando…' : 'Realizar mantenimiento'}
      </button>
      {qr.orden && (
        <p className="texto-pequeno texto-suave" style={{ margin: '-4px 0 0', textAlign: 'center' }}>
          Continúa su orden {qr.orden.numero}.
        </p>
      )}
      <button type="button" className="boton boton--peligro boton--bloque boton--alto" onClick={() => setReportando(true)}>
        <AlertTriangle size={18} aria-hidden="true" /> Reportar anomalía
      </button>
    </div>
  );
}

/**
 * Página que abre el QR pegado en la cabina. Cualquier persona ve el estado del ascensor; un técnico
 * con sesión puede iniciar el mantenimiento o reportar una anomalía.
 */
export default function ReportarFalla() {
  const { codigo } = useParams();
  const { usuario, esTecnico, cargando } = useAutenticacion();
  const [ascensor, setAscensor] = useState(null);
  const [noExiste, setNoExiste] = useState(false);

  useEffect(() => {
    publico
      .ascensor(codigo)
      .then((r) => setAscensor(r.datos))
      .catch(() => setNoExiste(true));
  }, [codigo]);

  return (
    <main className="reporte-publico">
      <div className="reporte-publico__caja">
        <Marca subtitulo="Estado del ascensor" />
        {noExiste ? (
          <Alerta>No encontramos este ascensor. Revise que el código QR esté completo o comuníquese con la administración del edificio.</Alerta>
        ) : !ascensor || cargando ? (
          <Cargando />
        ) : (
          <>
            <EstadoAscensor ascensor={ascensor} />
            {esTecnico ? (
              <OpcionesTecnico codigo={codigo} />
            ) : (
              !usuario && (
                <Link to="/iniciar-sesion" state={{ desde: `/reportar/${codigo}` }} className="reporte-publico__acceso">
                  <LogIn size={15} aria-hidden="true" /> ¿Es técnico de SIGMA? Inicie sesión
                </Link>
              )
            )}
          </>
        )}
      </div>
    </main>
  );
}

import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import QRCode from 'qrcode';
import { Download, FileText, Paperclip, Plus, Printer, Siren } from 'lucide-react';
import { servicio } from '../api/servicios';
import { useAutenticacion } from '../contexto/AutenticacionContexto';
import { useNotificar } from '../contexto/NotificacionesContexto';
import { useFormulario } from '../hooks/useFormulario';
import { Alerta, Cargando, EstadoVacio, Insignia, Modal, Tarjeta } from './Basicos';
import { CampoArea, CampoSelector, CampoTexto } from './Campos';
import { fecha, fechaHora, fechaIso } from '../utilidades/formato';
import { ESTADOS_CERTIFICACION, ESTADOS_EMERGENCIA, RESULTADOS_CERTIFICACION, TIPOS_EMERGENCIA, plazoCertificacion } from '../utilidades/servicio';
import { ModalReportarEmergencia } from '../paginas/servicio/Emergencias';

/** Certificación anual del ascensor: la vigente arriba y el historial debajo. */
export function CertificacionAscensor({ equipo }) {
  const { tienePermiso } = useAutenticacion();
  const notificar = useNotificar();
  const [lista, setLista] = useState(null);
  const [registrar, setRegistrar] = useState(false);
  const [error, setError] = useState('');

  const cargar = useCallback(() => {
    servicio
      .certificacionesEquipo(equipo.id)
      .then((r) => setLista(r.datos))
      .catch((e) => setError(e.message));
  }, [equipo.id]);
  useEffect(cargar, [cargar]);

  async function adjuntar(certificacion, archivo) {
    try {
      const r = await servicio.adjuntarCertificado(certificacion.id, archivo);
      notificar(r.mensaje);
      cargar();
    } catch (e) {
      notificar(e.message, 'error');
    }
  }

  const vigente = lista?.[0];
  const estado = vigente ? ESTADOS_CERTIFICACION[vigente.estado] : ESTADOS_CERTIFICACION.sin_certificar;

  return (
    <Tarjeta
      titulo="Certificación anual"
      acciones={
        tienePermiso('certificaciones.gestionar') && (
          <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setRegistrar(true)}>
            <Plus size={14} aria-hidden="true" /> Registrar certificación
          </button>
        )
      }
    >
      {error && <Alerta>{error}</Alerta>}
      {!lista ? (
        <Cargando />
      ) : (
        <>
          <div className="certificacion__estado">
            <Insignia tipo={estado.tipo}>{estado.texto}</Insignia>
            <span>{plazoCertificacion(vigente?.dias_para_vencer)}</span>
          </div>
          {lista.length === 0 ? (
            <p className="texto-suave" style={{ margin: 0 }}>
              Registre la revisión general anual con el certificado del organismo de inspección. SIGMA avisará 60, 30 y 15 días antes del vencimiento.
            </p>
          ) : (
            <ul className="certificaciones">
              {lista.map((c) => (
                <li key={c.id}>
                  <div>
                    <b>{RESULTADOS_CERTIFICACION[c.resultado]}</b> · {c.organismo}
                    <div className="texto-pequeno texto-suave">
                      Inspección {fecha(c.fecha_inspeccion)} · vence {fecha(c.fecha_vencimiento)} · {c.norma}
                      {c.numero_certificado ? ` · N.º ${c.numero_certificado}` : ''}
                    </div>
                    {c.observaciones && <div className="texto-pequeno">{c.observaciones}</div>}
                  </div>
                  {c.tiene_archivo ? (
                    <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => servicio.descargarCertificado(c).catch((e) => notificar(e.message, 'error'))}>
                      <Download size={14} aria-hidden="true" /> Certificado
                    </button>
                  ) : (
                    tienePermiso('certificaciones.gestionar') && (
                      <label className="boton boton--secundario boton--pequeno">
                        <Paperclip size={14} aria-hidden="true" /> Adjuntar
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" hidden onChange={(e) => e.target.files[0] && adjuntar(c, e.target.files[0])} />
                      </label>
                    )
                  )}
                </li>
              ))}
            </ul>
          )}
        </>
      )}
      {registrar && (
        <ModalCertificacion
          equipo={equipo}
          alCerrar={(guardo) => {
            setRegistrar(false);
            if (guardo) cargar();
          }}
        />
      )}
    </Tarjeta>
  );
}

function ModalCertificacion({ equipo, alCerrar }) {
  const notificar = useNotificar();
  const hoy = fechaIso();
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    organismo: '',
    numero_certificado: '',
    norma: 'NTC 5926-1',
    fecha_inspeccion: hoy,
    fecha_vencimiento: '',
    resultado: 'conforme',
    observaciones: '',
    archivo: null,
  });

  // Por defecto vence un año después de la inspección.
  const alCambiarInspeccion = (valor) => {
    cambiar('fecha_inspeccion')(valor);
    if (valor && !datos.fecha_vencimiento) {
      const d = new Date(`${valor}T00:00:00`);
      d.setFullYear(d.getFullYear() + 1);
      cambiar('fecha_vencimiento')(fechaIso(d));
    }
  };

  async function alEnviar(evento) {
    evento.preventDefault();
    const respuesta = await enviar(() => servicio.registrarCertificacion(equipo.id, datos));
    if (respuesta) {
      notificar(respuesta.mensaje);
      alCerrar(true);
    }
  }

  return (
    <Modal
      titulo={`Registrar certificación · ${equipo.codigo}`}
      abierto
      alCerrar={() => alCerrar(false)}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={() => alCerrar(false)}>
            Cancelar
          </button>
          <button type="submit" form="formulario-certificacion" className="boton" disabled={enviando}>
            {enviando ? 'Registrando…' : 'Registrar certificación'}
          </button>
        </>
      }
    >
      <form id="formulario-certificacion" className="formulario" onSubmit={alEnviar} noValidate>
        <Alerta>{mensajeError}</Alerta>
        <CampoTexto etiqueta="Organismo de inspección" obligatorio valor={datos.organismo} alCambiar={cambiar('organismo')} error={error('organismo')} ayuda="Organismo acreditado por ONAC que hizo la revisión." />
        <div className="formulario__fila">
          <CampoTexto etiqueta="Número del certificado" valor={datos.numero_certificado} alCambiar={cambiar('numero_certificado')} error={error('numero_certificado')} />
          <CampoTexto etiqueta="Norma" valor={datos.norma} alCambiar={cambiar('norma')} error={error('norma')} ayuda="En Bogotá, NTC 5926-1. En otras ciudades, la norma local." />
        </div>
        <div className="formulario__fila">
          <CampoTexto etiqueta="Fecha de inspección" obligatorio tipo="date" max={hoy} valor={datos.fecha_inspeccion} alCambiar={alCambiarInspeccion} error={error('fecha_inspeccion')} />
          <CampoTexto etiqueta="Fecha de vencimiento" obligatorio tipo="date" valor={datos.fecha_vencimiento} alCambiar={cambiar('fecha_vencimiento')} error={error('fecha_vencimiento')} />
        </div>
        <CampoSelector
          etiqueta="Resultado"
          obligatorio
          vacio={null}
          valor={datos.resultado}
          alCambiar={cambiar('resultado')}
          error={error('resultado')}
          opciones={Object.entries(RESULTADOS_CERTIFICACION).map(([valor, texto]) => ({ valor, texto }))}
        />
        <CampoArea etiqueta="Observaciones" rows={2} valor={datos.observaciones} alCambiar={cambiar('observaciones')} error={error('observaciones')} placeholder="No conformidades o recomendaciones del organismo" />
        <label className="campo">
          <span className="campo__etiqueta">Certificado (PDF, JPG o PNG, máximo 10 MB)</span>
          <input type="file" className="campo__control" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => cambiar('archivo')(e.target.files[0] || null)} />
          {error('archivo') && <span className="campo__error">{error('archivo')}</span>}
        </label>
      </form>
    </Modal>
  );
}

/** QR para pegar en la cabina: abre el formulario de reporte sin iniciar sesión. */
export function CodigoQrAscensor({ equipo }) {
  const [qr, setQr] = useState(null);
  const [svg, setSvg] = useState('');

  useEffect(() => {
    servicio
      .qr(equipo.id)
      .then(async (r) => {
        setQr(r.datos);
        setSvg(await QRCode.toString(r.datos.url, { type: 'svg', margin: 1, errorCorrectionLevel: 'M', color: { dark: '#141218', light: '#ffffff' } }));
      })
      .catch(() => setQr(false));
  }, [equipo.id]);

  function imprimir() {
    const ventana = window.open('', '_blank', 'width=420,height=600');
    if (!ventana) return;
    ventana.document.write(`<!doctype html><title>QR ${equipo.codigo}</title>
      <style>@import url('https://fonts.googleapis.com/css2?family=Barlow:wght@600&family=Geist:wght@400&display=swap');body{font-family:'Geist',system-ui,sans-serif;text-align:center;padding:24px}svg{width:260px;height:260px}h1{font:600 22px/1.2 'Barlow',system-ui,sans-serif;letter-spacing:-0.01em;margin:12px 0 4px}p{margin:4px 0;color:#333}</style>
      ${svg}<h1>¿Falla en el ascensor ${equipo.codigo}?</h1><p>Escanee el código para reportarla las 24 horas.</p><p>${qr.url}</p>
      <script>window.onload=()=>window.print()</script>`);
    ventana.document.close();
  }

  if (qr === false) return null;
  return (
    <Tarjeta
      titulo="Código QR de la cabina"
      acciones={
        svg && (
          <button type="button" className="boton boton--secundario boton--pequeno" onClick={imprimir}>
            <Printer size={14} aria-hidden="true" /> Imprimir
          </button>
        )
      }
    >
      {!qr ? (
        <Cargando />
      ) : (
        <div className="qr-ascensor">
          {/* El SVG lo genera la librería qrcode a partir de la URL del propio sistema. */}
          <div className="qr-ascensor__codigo" dangerouslySetInnerHTML={{ __html: svg }} role="img" aria-label={`Código QR para reportar fallas del ascensor ${equipo.codigo}`} />
          <div>
            <p style={{ marginTop: 0 }}>Péguelo dentro de la cabina. Quien lo escanee puede reportar una falla o un atrapamiento sin iniciar sesión.</p>
            <a href={qr.url} target="_blank" rel="noreferrer" className="texto-pequeno">
              {qr.url}
            </a>
          </div>
        </div>
      )}
    </Tarjeta>
  );
}

/** Documentos del ascensor: certificados anuales e informes técnicos firmados. */
export function DocumentosAscensor({ equipo }) {
  const notificar = useNotificar();
  const [documentos, setDocumentos] = useState(null);

  useEffect(() => {
    servicio
      .documentos(equipo.id)
      .then((r) => setDocumentos(r.datos))
      .catch(() => setDocumentos([]));
  }, [equipo.id]);

  return (
    <Tarjeta titulo="Documentos">
      {!documentos ? (
        <Cargando />
      ) : documentos.length === 0 ? (
        <EstadoVacio titulo="Sin documentos todavía" descripcion="Aquí quedan los certificados de la revisión anual y los informes técnicos firmados de cada orden." />
      ) : (
        <ul className="documentos">
          {documentos.map((d) => (
            <li key={d.enlace}>
              <FileText size={18} aria-hidden="true" />
              <div>
                <b>{d.titulo}</b>
                <div className="texto-pequeno texto-suave">
                  {fecha(d.fecha)}
                  {d.detalle ? ` · ${d.detalle}` : ''}
                </div>
              </div>
              <button type="button" className="boton-icono" aria-label={`Descargar ${d.titulo}`} onClick={() => servicio.descargarDocumento(d).catch((e) => notificar(e.message, 'error'))}>
                <Download size={16} />
              </button>
            </li>
          ))}
        </ul>
      )}
    </Tarjeta>
  );
}

/** Últimas emergencias del ascensor con el botón para reportar una nueva. */
export function EmergenciasAscensor({ equipo }) {
  const { tienePermiso } = useAutenticacion();
  const [lista, setLista] = useState(null);
  const [reportar, setReportar] = useState(false);

  const cargar = useCallback(() => {
    servicio
      .emergencias({ equipo_id: equipo.id, por_pagina: 5 })
      .then((r) => setLista(r.datos))
      .catch(() => setLista([]));
  }, [equipo.id]);
  useEffect(cargar, [cargar]);

  if (!tienePermiso('emergencias.ver')) return null;
  return (
    <Tarjeta
      titulo="Emergencias"
      acciones={
        tienePermiso('emergencias.reportar') && (
          <button type="button" className="boton boton--peligro boton--pequeno" onClick={() => setReportar(true)}>
            <Siren size={14} aria-hidden="true" /> Reportar emergencia
          </button>
        )
      }
    >
      {!lista ? (
        <Cargando />
      ) : lista.length === 0 ? (
        <p className="texto-suave" style={{ margin: 0 }}>
          Sin emergencias registradas.
        </p>
      ) : (
        <ul className="documentos">
          {lista.map((e) => (
            <li key={e.id}>
              <Insignia tipo={ESTADOS_EMERGENCIA[e.estado]?.tipo}>{ESTADOS_EMERGENCIA[e.estado]?.texto}</Insignia>
              <div>
                <Link to={`/emergencias/${e.id}`}>
                  <b>{e.numero}</b>
                </Link>{' '}
                · {TIPOS_EMERGENCIA[e.tipo]}
                <div className="texto-pequeno texto-suave">{fechaHora(e.reportada_en)}</div>
              </div>
            </li>
          ))}
        </ul>
      )}
      {reportar && (
        <ModalReportarEmergencia
          equipo={equipo}
          alCerrar={(nueva) => {
            setReportar(false);
            if (nueva) cargar();
          }}
        />
      )}
    </Tarjeta>
  );
}

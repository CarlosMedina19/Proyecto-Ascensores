import { useRef, useState } from 'react';
import { FileDown, FilePlus2, PenLine } from 'lucide-react';
import { urlArchivo } from '../../api/cliente';
import { planilla as servicioPlanilla } from '../../api/servicios';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Modal, Tarjeta } from '../../componentes/Basicos';
import { CampoArea, CampoTexto } from '../../componentes/Campos';
import { ImagenProtegida } from '../../componentes/FotosEquipo';
import PanelFirma from '../../componentes/PanelFirma';
import { fechaHora } from '../../utilidades/formato';

const TIPOS = {
  tecnico: 'Firma del técnico',
  cliente: 'Firma de quien recibe',
};

function ModalFirma({ orden, tipo, alCerrar, alGuardar }) {
  const firma = useRef(null);
  const tecnico = orden.tecnicos?.[0]?.nombre ?? '';
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({
    nombre: tipo === 'tecnico' ? tecnico : '',
    documento: '',
    cargo: '',
  });
  const [sinFirma, setSinFirma] = useState(false);

  async function guardar() {
    const imagen = firma.current?.png();
    if (!imagen) {
      setSinFirma(true);
      return;
    }
    const respuesta = await enviar(() =>
      servicioPlanilla.firmar(orden.id, { tipo, nombre: datos.nombre, documento: datos.documento || null, cargo: datos.cargo || null, imagen }),
    );
    if (respuesta) alGuardar('Firma registrada.');
  }

  return (
    <Modal
      abierto
      ancho
      titulo={TIPOS[tipo]}
      alCerrar={alCerrar}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar}>
            Cancelar
          </button>
          <button type="button" className="boton" onClick={guardar} disabled={enviando}>
            {enviando ? 'Guardando…' : 'Guardar firma'}
          </button>
        </>
      }
    >
      <div className="formulario">
        <Alerta>{mensajeError || (sinFirma ? 'Dibuje la firma antes de guardar.' : '')}</Alerta>
        <div className="formulario__fila formulario__fila--3">
          <CampoTexto etiqueta="Nombre" obligatorio valor={datos.nombre} alCambiar={cambiar('nombre')} error={error('nombre')} />
          <CampoTexto etiqueta="Documento" valor={datos.documento} alCambiar={cambiar('documento')} error={error('documento')} />
          <CampoTexto etiqueta="Cargo" valor={datos.cargo} alCambiar={cambiar('cargo')} error={error('cargo')} placeholder={tipo === 'cliente' ? 'Administrador, portero…' : ''} />
        </div>
        <PanelFirma ref={firma} etiqueta={TIPOS[tipo]} />
      </div>
    </Modal>
  );
}

function ModalVersion({ orden, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ motivo: '' });

  async function guardar() {
    const respuesta = await enviar(() => servicioPlanilla.nuevaVersion(orden.id, datos.motivo));
    if (respuesta) alGuardar(`Informe versión ${respuesta.datos.version} generado.`);
  }

  return (
    <Modal
      abierto
      titulo="Nueva versión del informe"
      alCerrar={alCerrar}
      pie={
        <>
          <button type="button" className="boton boton--secundario" onClick={alCerrar}>
            Cancelar
          </button>
          <button type="button" className="boton" onClick={guardar} disabled={enviando}>
            {enviando ? 'Generando…' : 'Generar versión'}
          </button>
        </>
      }
    >
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <p style={{ margin: 0 }}>Las versiones anteriores no cambian. Indique qué se corrige.</p>
        <CampoArea etiqueta="Motivo" obligatorio valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} rows={3} />
      </div>
    </Modal>
  );
}

/** RF-025 y RF-039: firmas de la orden e informes PDF emitidos. */
export default function FirmasInformes({ orden, alCambiar, notificar }) {
  const [modal, setModal] = useState(null);
  const puedeFirmar = Boolean(orden.permisos?.ejecutar) && ['en_proceso', 'pendiente'].includes(orden.estado);
  const firmas = Object.fromEntries((orden.firmas || []).map((f) => [f.tipo, f]));
  const informes = orden.informes || [];

  async function abrirPdf(informe) {
    // El PDF se descarga con el token y se abre en una pestaña nueva.
    const ventana = window.open('', '_blank');
    try {
      const url = await urlArchivo(informe.ruta_archivo);
      if (ventana) ventana.location.href = url;
      else window.location.href = url;
    } catch (e) {
      ventana?.close();
      notificar(e.message, 'error');
    }
  }

  function alGuardar(mensaje) {
    setModal(null);
    notificar(mensaje);
    alCambiar();
  }

  return (
    <>
      <Tarjeta titulo="Firmas">
        <div className="firmas-orden">
          {Object.entries(TIPOS).map(([tipo, titulo]) => {
            const firma = firmas[tipo];
            return (
              <div key={tipo} className="firmas-orden__caja">
                <span className="etiqueta-superior">{titulo}</span>
                {firma ? (
                  <>
                    <div className="firmas-orden__imagen">
                      <ImagenProtegida ruta={firma.ruta_archivo} alt={`${titulo}: ${firma.nombre}`} />
                    </div>
                    <b>{firma.nombre}</b>
                    <small className="texto-suave">
                      {[firma.documento, firma.cargo].filter(Boolean).join(' · ')}
                      {firma.firmado_en && ` · ${fechaHora(firma.firmado_en)}`}
                    </small>
                  </>
                ) : (
                  <span className="texto-suave texto-pequeno">Sin firma.</span>
                )}
                {puedeFirmar && (
                  <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setModal({ tipo: 'firma', firma: tipo })}>
                    <PenLine size={14} /> {firma ? 'Volver a firmar' : 'Firmar'}
                  </button>
                )}
              </div>
            );
          })}
        </div>
        {orden.estado !== 'finalizada' && (
          <p className="texto-pequeno texto-suave" style={{ marginBottom: 0 }}>
            La orden se finaliza con las dos firmas registradas{orden.lleva_planilla ? ' y la planilla completa' : ''}.
          </p>
        )}
      </Tarjeta>

      {(informes.length > 0 || orden.estado === 'finalizada') && (
        <Tarjeta
          titulo="Informe PDF"
          acciones={
            orden.permisos?.editar &&
            orden.estado === 'finalizada' && (
              <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => setModal({ tipo: 'version' })}>
                <FilePlus2 size={15} /> Nueva versión
              </button>
            )
          }
        >
          {informes.length ? (
            <ul className="visitas">
              {informes.map((informe) => (
                <li key={informe.id}>
                  <button type="button" className="visitas__boton" onClick={() => abrirPdf(informe)}>
                    <span>
                      <b>Versión {informe.version}</b>
                      <span className="tabla__secundario">
                        {fechaHora(informe.created_at)}
                        {informe.generado_por && ` · ${informe.generado_por}`}
                        {informe.motivo && ` · ${informe.motivo}`}
                      </span>
                    </span>
                    <FileDown size={18} aria-label="Abrir PDF" />
                  </button>
                </li>
              ))}
            </ul>
          ) : (
            <p className="texto-suave texto-pequeno" style={{ margin: 0 }}>
              El informe se genera al finalizar la orden.
            </p>
          )}
        </Tarjeta>
      )}

      {modal?.tipo === 'firma' && <ModalFirma orden={orden} tipo={modal.firma} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
      {modal?.tipo === 'version' && <ModalVersion orden={orden} alCerrar={() => setModal(null)} alGuardar={alGuardar} />}
    </>
  );
}

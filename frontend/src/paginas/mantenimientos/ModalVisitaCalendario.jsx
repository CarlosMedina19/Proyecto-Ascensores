import { useEffect, useState } from 'react';
import { equipos, mantenimientos } from '../../api/servicios';
import { useFormulario } from '../../hooks/useFormulario';
import { Alerta, Modal } from '../../componentes/Basicos';
import { CampoArea, CampoBuscador, CampoTexto } from '../../componentes/Campos';
import { fecha as fechaLarga } from '../../utilidades/formato';

function Pie({ alCerrar, alGuardar, enviando, texto }) {
  return (
    <>
      <button type="button" className="boton boton--secundario" onClick={alCerrar}>
        Cancelar
      </button>
      <button type="button" className="boton" onClick={alGuardar} disabled={enviando}>
        {enviando ? 'Guardando…' : texto}
      </button>
    </>
  );
}

/** Agrega una visita de mantenimiento a un ascensor en el día elegido en el calendario. */
export function ModalProgramarVisita({ fecha, alCerrar, alGuardar }) {
  const [opciones, setOpciones] = useState([]);
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ equipo_id: '', fecha, hora: '08:00', motivo: '' });

  useEffect(() => {
    equipos
      .listar({ estado: 'activo', por_pagina: 100 })
      .then((r) =>
        setOpciones(
          r.datos.map((e) => ({
            valor: String(e.id),
            texto: `${e.codigo}${e.nombre ? ` · ${e.nombre}` : ''}`,
            detalle: [e.edificio?.nombre, e.edificio?.cliente?.nombre].filter(Boolean).join(' · '),
          })),
        ),
      )
      .catch(() => setOpciones([]));
  }, []);

  async function guardar() {
    const r = await enviar(() =>
      mantenimientos.crear({ equipo_id: datos.equipo_id ? Number(datos.equipo_id) : null, fecha: datos.fecha, hora: datos.hora || null, motivo: datos.motivo || null }),
    );
    if (r) alGuardar(`Visita programada para el ${fechaLarga(datos.fecha)}.`);
  }

  return (
    <Modal abierto titulo="Programar mantenimiento" alCerrar={alCerrar} pie={<Pie alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Programar visita" />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <CampoBuscador
          etiqueta="Ascensor"
          obligatorio
          valor={datos.equipo_id}
          alCambiar={cambiar('equipo_id')}
          error={error('equipo_id')}
          opciones={opciones}
          placeholder="Busque por código, edificio o cliente"
          ayuda="Se agrega como visita adicional de su programa de mantenimiento; las demás visitas no cambian."
        />
        <div className="formulario__fila">
          <CampoTexto etiqueta="Fecha" tipo="date" obligatorio valor={datos.fecha} alCambiar={cambiar('fecha')} error={error('fecha')} />
          <CampoTexto etiqueta="Hora" tipo="time" min="06:00" max="18:00" step="900" valor={datos.hora} alCambiar={cambiar('hora')} error={error('hora')} />
        </div>
        <CampoArea etiqueta="Motivo (opcional)" rows={2} valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} placeholder="Revisión pedida por la administración…" />
      </div>
    </Modal>
  );
}

/** Reagendar una visita arrastrada a otro día (u otra hora) del calendario. */
export function ModalReagendar({ visita, fecha, hora, alCerrar, alGuardar }) {
  const { datos, cambiar, error, mensajeError, enviando, enviar } = useFormulario({ fecha, hora: hora || visita.hora || '08:00', motivo: 'Reagendado desde el calendario' });

  async function guardar() {
    const r = await enviar(() => mantenimientos.reprogramar(visita.id, { fecha: datos.fecha, hora: datos.hora || null, motivo: datos.motivo }));
    if (r) alGuardar(`Visita de ${visita.equipo?.codigo ?? 'el ascensor'} reagendada para el ${fechaLarga(datos.fecha)}.`);
  }

  return (
    <Modal abierto titulo={`Reagendar visita de ${visita.equipo?.codigo ?? ''}`} alCerrar={alCerrar} pie={<Pie alCerrar={alCerrar} alGuardar={guardar} enviando={enviando} texto="Reagendar" />}>
      <div className="formulario">
        <Alerta>{mensajeError}</Alerta>
        <p className="texto-pequeno texto-suave" style={{ margin: 0 }}>
          Estaba para el {fechaLarga(visita.fecha_programada)}
          {visita.hora ? ` a las ${visita.hora}` : ''}. El cliente y el coordinador reciben el aviso del cambio.
        </p>
        <div className="formulario__fila">
          <CampoTexto etiqueta="Nueva fecha" tipo="date" obligatorio valor={datos.fecha} alCambiar={cambiar('fecha')} error={error('fecha')} />
          <CampoTexto etiqueta="Hora" tipo="time" min="06:00" max="18:00" step="900" valor={datos.hora} alCambiar={cambiar('hora')} error={error('hora')} />
        </div>
        <CampoArea etiqueta="Motivo" obligatorio rows={2} valor={datos.motivo} alCambiar={cambiar('motivo')} error={error('motivo')} />
      </div>
    </Modal>
  );
}

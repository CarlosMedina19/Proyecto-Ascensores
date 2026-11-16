import { useEffect, useState } from 'react';
import { FileDown } from 'lucide-react';
import { visitasTecnicas } from '../../api/servicios';
import { useNotificar } from '../../contexto/NotificacionesContexto';
import { Cargando, EstadoVacio, Insignia, Tarjeta } from '../../componentes/Basicos';
import { fechaHora } from '../../utilidades/formato';

/** Planillas de asistencia técnica que los técnicos diligenciaron en las visitas a este ascensor. */
export default function PlanillasDelEquipo({ equipoId }) {
  const notificar = useNotificar();
  const [lista, setLista] = useState(null);
  const [descargando, setDescargando] = useState(null);

  useEffect(() => {
    visitasTecnicas
      .listar(equipoId)
      .then((r) => setLista(r.datos || []))
      .catch(() => setLista([]));
  }, [equipoId]);

  async function descargar(visita) {
    setDescargando(visita.id);
    try {
      await visitasTecnicas.pdf(visita);
    } catch (e) {
      notificar(e.message, 'error');
    } finally {
      setDescargando(null);
    }
  }

  return (
    <Tarjeta titulo="Planillas de asistencia técnica">
      {lista === null ? (
        <Cargando />
      ) : lista.length ? (
        <ul className="lista-planillas">
          {lista.map((v) => (
            <li key={v.id}>
              <div>
                <b>N.º {v.numero}</b> · {v.tipo_nombre}
                <div className="texto-pequeno texto-suave">
                  {fechaHora(v.salida_en)} · {v.tecnico || 'Técnico'} · recibió {v.recibe_nombre}
                </div>
              </div>
              <span className="lista-planillas__conteo">
                <Insignia tipo="exito">B {v.conteo.B}</Insignia>
                {v.conteo.R > 0 && <Insignia tipo="alerta">R {v.conteo.R}</Insignia>}
                {v.conteo.M > 0 && <Insignia tipo="peligro">M {v.conteo.M}</Insignia>}
              </span>
              <button type="button" className="boton boton--secundario boton--pequeno" onClick={() => descargar(v)} disabled={descargando === v.id}>
                <FileDown size={15} /> {descargando === v.id ? 'Descargando…' : 'PDF'}
              </button>
            </li>
          ))}
        </ul>
      ) : (
        <EstadoVacio titulo="Sin planillas todavía" descripcion="El técnico la diligencia desde la app al registrar la visita." />
      )}
    </Tarjeta>
  );
}

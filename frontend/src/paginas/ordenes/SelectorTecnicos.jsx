import { useEffect, useState } from 'react';
import { tecnicos as servicioTecnicos } from '../../api/servicios';
import { Cargando } from '../../componentes/Basicos';

/**
 * RF-022 a RF-024: técnicos para una orden en un horario. Los no disponibles, inactivos o con
 * otra orden que se cruza aparecen deshabilitados con el motivo.
 */
export default function SelectorTecnicos({ fecha, hora, duracion, ordenId, seleccion, alCambiar }) {
  const [lista, setLista] = useState(null);

  useEffect(() => {
    if (!fecha || !hora) {
      setLista([]);
      return;
    }
    let vigente = true;
    setLista(null);
    servicioTecnicos
      .disponibilidad({ fecha, hora, duracion: duracion || 90, orden_id: ordenId })
      .then((r) => vigente && setLista(r.datos))
      .catch(() => vigente && setLista([]));
    return () => {
      vigente = false;
    };
  }, [fecha, hora, duracion, ordenId]);

  if (!fecha || !hora) return <p className="texto-suave texto-pequeno">Indique la fecha y la hora para ver qué técnicos están libres.</p>;
  if (lista === null) return <Cargando texto="Revisando disponibilidad…" />;
  if (!lista.length) return <p className="texto-suave texto-pequeno">No hay técnicos con perfil registrado.</p>;

  function alternar(id, marcado) {
    alCambiar(marcado ? [...seleccion, id] : seleccion.filter((t) => t !== id));
  }

  return (
    <div className="selector-tecnicos" role="group" aria-label="Técnicos">
      {lista.map((t) => {
        const elegido = seleccion.includes(t.id);
        // Un técnico ya asignado se puede quitar aunque ahora no esté disponible.
        const deshabilitado = !t.asignable && !elegido;
        return (
          <label key={t.id} className={`selector-tecnicos__item${deshabilitado ? ' deshabilitado' : ''}${elegido ? ' elegido' : ''}`}>
            <input type="checkbox" checked={elegido} disabled={deshabilitado} onChange={(e) => alternar(t.id, e.target.checked)} />
            <span>
              <b>{t.nombre}</b>
              <small>{t.especialidades.join(' · ') || 'Sin especialidades'}</small>
              {t.motivo && (
                <small className="selector-tecnicos__motivo">
                  {t.motivo}
                  {t.conflictos.length > 0 && `: ${t.conflictos.map((c) => `${c.numero} (${c.inicio}–${c.fin})`).join(', ')}`}
                </small>
              )}
            </span>
          </label>
        );
      })}
    </div>
  );
}

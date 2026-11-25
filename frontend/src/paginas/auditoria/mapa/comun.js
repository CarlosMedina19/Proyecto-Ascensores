import { fechaHora } from '../../../utilidades/formato';

// Bogotá como vista inicial cuando aún no hay posiciones.
export const CENTRO = { lat: 4.711, lng: -74.0721 };
export const ZOOM_INICIAL = 11;
// Al encuadrar o seleccionar un técnico no se acerca más que esto.
export const ZOOM_DETALLE = 15;

export function iniciales(nombre = '') {
  return (
    nombre
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map((p) => p[0])
      .join('')
      .toUpperCase() || '?'
  );
}

export function escapar(texto) {
  return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/** Clave de cada marcador: una orden y su técnico. */
export const claveTecnico = (t) => `${t.orden?.id}-${t.tecnico_id}`;

/** Solo los técnicos que ya enviaron su posición. */
export const conPosicion = (tecnicos) => tecnicos.filter((t) => t.latitud != null && t.longitud != null);

/** Contenido (HTML escapado) de la ventana de cada técnico. */
export function contenidoTecnico(t) {
  return `<div class="mapa-ventana"><b>${escapar(t.tecnico)}</b><br>${escapar(t.equipo || '')}${t.edificio ? ` · ${escapar(t.edificio)}` : ''}<br><small>Orden ${escapar(t.orden?.numero)} · ${escapar(fechaHora(t.registrada_en))}</small></div>`;
}

/** Marcador redondo con las iniciales del técnico. */
export const htmlMarcador = (nombre) => `<span class="mapa-tecnico__punto">${escapar(iniciales(nombre))}</span>`;

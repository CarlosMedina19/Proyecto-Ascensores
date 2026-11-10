import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { fechaHora } from '../../utilidades/formato';

// Bogotá como vista inicial cuando aún no hay posiciones.
const CENTRO = [4.711, -74.0721];

function iniciales(nombre = '') {
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

function icono(nombre) {
  return L.divIcon({
    className: 'mapa-tecnico',
    html: `<span class="mapa-tecnico__punto">${iniciales(nombre)}</span>`,
    iconSize: [34, 34],
    iconAnchor: [17, 17],
    popupAnchor: [0, -18],
  });
}

function escapar(texto) {
  return String(texto ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/** Mapa (OpenStreetMap) con la última posición de cada técnico que está haciendo un mantenimiento. */
export default function MapaTecnicos({ tecnicos, seleccionado }) {
  const nodo = useRef(null);
  const mapa = useRef(null);
  const marcadores = useRef(new Map());
  const yaEncuadro = useRef(false);

  useEffect(() => {
    mapa.current = L.map(nodo.current, { zoomControl: true, attributionControl: true }).setView(CENTRO, 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; colaboradores de <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }).addTo(mapa.current);
    const capa = marcadores.current;
    return () => {
      mapa.current?.remove();
      mapa.current = null;
      capa.clear();
    };
  }, []);

  useEffect(() => {
    if (!mapa.current) return;
    const vigentes = new Set();
    const puntos = [];
    tecnicos
      .filter((t) => t.latitud != null && t.longitud != null)
      .forEach((t) => {
        const clave = `${t.orden?.id}-${t.tecnico_id}`;
        vigentes.add(clave);
        const posicion = [t.latitud, t.longitud];
        puntos.push(posicion);
        const contenido = `<b>${escapar(t.tecnico)}</b><br>${escapar(t.equipo || '')}${t.edificio ? ` · ${escapar(t.edificio)}` : ''}<br><small>Orden ${escapar(t.orden?.numero)} · ${escapar(fechaHora(t.registrada_en))}</small>`;
        const existente = marcadores.current.get(clave);
        if (existente) {
          existente.setLatLng(posicion).setPopupContent(contenido);
        } else {
          marcadores.current.set(clave, L.marker(posicion, { icon: icono(t.tecnico), title: t.tecnico }).bindPopup(contenido).addTo(mapa.current));
        }
      });
    marcadores.current.forEach((marcador, clave) => {
      if (!vigentes.has(clave)) {
        marcador.remove();
        marcadores.current.delete(clave);
      }
    });
    // Se encuadra una sola vez al llegar las primeras posiciones; luego el usuario mueve el mapa a su gusto.
    if (puntos.length && !yaEncuadro.current) {
      yaEncuadro.current = true;
      mapa.current.fitBounds(L.latLngBounds(puntos).pad(0.3), { maxZoom: 15 });
    }
  }, [tecnicos]);

  useEffect(() => {
    if (!seleccionado || !mapa.current) return;
    const marcador = marcadores.current.get(seleccionado);
    if (marcador) {
      mapa.current.setView(marcador.getLatLng(), Math.max(mapa.current.getZoom(), 15));
      marcador.openPopup();
    }
  }, [seleccionado]);

  return <div ref={nodo} className="mapa-auditoria" role="region" aria-label="Mapa de técnicos en mantenimiento" />;
}

import { useEffect, useRef } from 'react';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { CENTRO, ZOOM_DETALLE, ZOOM_INICIAL, claveTecnico, conPosicion, contenidoTecnico, htmlMarcador } from './mapa/comun';

function icono(nombre) {
  return L.divIcon({ className: 'mapa-tecnico', html: htmlMarcador(nombre), iconSize: [34, 34], iconAnchor: [17, 17], popupAnchor: [0, -18] });
}

/**
 * Mapa de respaldo: OpenStreetMap (sin clave) oscurecido con un filtro CSS (.mapa-auditoria--oscuro en global.css).
 * Se usa mientras no haya clave de Google Maps o si Google Maps no carga.
 */
export default function MapaLeaflet({ tecnicos, seleccionado }) {
  const nodo = useRef(null);
  const mapa = useRef(null);
  const marcadores = useRef(new Map());
  const yaEncuadro = useRef(false);

  useEffect(() => {
    mapa.current = L.map(nodo.current, { zoomControl: true, attributionControl: true }).setView([CENTRO.lat, CENTRO.lng], ZOOM_INICIAL);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
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
    conPosicion(tecnicos).forEach((t) => {
      const clave = claveTecnico(t);
      vigentes.add(clave);
      const posicion = [t.latitud, t.longitud];
      puntos.push(posicion);
      const existente = marcadores.current.get(clave);
      if (existente) {
        existente.setLatLng(posicion).setPopupContent(contenidoTecnico(t));
      } else {
        marcadores.current.set(clave, L.marker(posicion, { icon: icono(t.tecnico), title: t.tecnico }).bindPopup(contenidoTecnico(t)).addTo(mapa.current));
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
      mapa.current.fitBounds(L.latLngBounds(puntos).pad(0.3), { maxZoom: ZOOM_DETALLE });
    }
  }, [tecnicos]);

  useEffect(() => {
    if (!seleccionado || !mapa.current) return;
    const marcador = marcadores.current.get(seleccionado);
    if (marcador) {
      mapa.current.setView(marcador.getLatLng(), Math.max(mapa.current.getZoom(), ZOOM_DETALLE));
      marcador.openPopup();
    }
  }, [seleccionado]);

  return <div ref={nodo} className="mapa-auditoria mapa-auditoria--oscuro" role="region" aria-label="Mapa de técnicos en mantenimiento" />;
}

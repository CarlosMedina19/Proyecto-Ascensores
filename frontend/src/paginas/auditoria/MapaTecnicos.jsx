import { useEffect, useRef, useState } from 'react';
import { LngLatBounds, Map as MapaLibre, Marker, NavigationControl, Popup, setWorkerUrl } from 'maplibre-gl';
import urlTrabajador from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import 'maplibre-gl/dist/maplibre-gl.css';
import { CENTRO, ZOOM_DETALLE, ZOOM_INICIAL, claveTecnico, conPosicion, contenidoTecnico, htmlMarcador } from './mapa/comun';
import { URL_ESTILO_BASE, estiloSegunTema } from './mapa/estiloMapa';

/** Tema del panel (atributo data-tema que pone useTema; si falta, el del sistema). */
function temaDelPanel() {
  const tema = document.documentElement.getAttribute('data-tema');
  if (tema === 'oscuro' || tema === 'claro') return tema;
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'oscuro' : 'claro';
}

/** El mapa sigue al tema del panel, también cuando el usuario lo cambia con el botón del encabezado. */
function useTemaDelPanel() {
  const [tema, setTema] = useState(temaDelPanel);
  useEffect(() => {
    const actualizar = () => setTema(temaDelPanel());
    const observador = new MutationObserver(actualizar);
    observador.observe(document.documentElement, { attributes: true, attributeFilter: ['data-tema'] });
    const sistema = window.matchMedia?.('(prefers-color-scheme: dark)');
    sistema?.addEventListener?.('change', actualizar);
    return () => {
      observador.disconnect();
      sistema?.removeEventListener?.('change', actualizar);
    };
  }, []);
  return tema;
}

// El procesador de teselas de MapLibre se sirve como archivo propio del panel.
setWorkerUrl(urlTrabajador);

function abrirVentana(estado, clave) {
  const marcador = estado.marcadores.get(clave);
  if (!marcador || !estado.mapa) return;
  estado.ventana.setLngLat(marcador.getLngLat()).setHTML(marcador.contenido).addTo(estado.mapa);
}

/**
 * Mapa de los técnicos en mantenimiento: OpenStreetMap (teselas vectoriales de OpenFreeMap, sin clave ni costo)
 * dibujado con MapLibre. En el tema oscuro del panel usa la paleta nocturna estilo Google Maps; en el claro, el
 * estilo claro original. Cambia en vivo al cambiar el tema.
 */
export default function MapaTecnicos({ tecnicos, seleccionado }) {
  const nodo = useRef(null);
  const estado = useRef({ mapa: null, ventana: null, marcadores: new Map(), yaEncuadro: false, base: null, temaPintado: null });
  const tema = useTemaDelPanel();
  const temaActual = useRef(tema);
  temaActual.current = tema;
  const [listo, setListo] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelado = false;
    const e = estado.current;
    fetch(URL_ESTILO_BASE)
      .then((r) => {
        if (!r.ok) throw new Error(`Estilo del mapa: ${r.status}`);
        return r.json();
      })
      .then((base) => {
        if (cancelado || !nodo.current) return;
        e.base = base;
        e.temaPintado = temaActual.current;
        e.mapa = new MapaLibre({
          container: nodo.current,
          style: estiloSegunTema(base, temaActual.current),
          center: [CENTRO.lng, CENTRO.lat],
          zoom: ZOOM_INICIAL,
          attributionControl: { compact: true },
          dragRotate: false,
          pitchWithRotate: false,
        });
        e.mapa.addControl(new NavigationControl({ showCompass: false }), 'top-left');
        e.ventana = new Popup({ offset: 22, maxWidth: '260px' });
        e.mapa.on('load', () => {
          if (!cancelado) setListo(true);
        });
        // Un fallo de teselas o de fuentes no tumba el mapa, pero queda en la consola para diagnosticarlo.
        e.mapa.on('error', (evento) => console.error('Mapa de técnicos:', evento.error?.message || evento));
      })
      // Sin conexión con OpenFreeMap o sin WebGL: la lista de técnicos sigue a la derecha.
      .catch(() => {
        if (!cancelado) setError('No se pudo cargar el mapa. La lista de técnicos sigue disponible.');
      });

    return () => {
      cancelado = true;
      e.marcadores.forEach((m) => m.remove());
      e.marcadores.clear();
      e.ventana?.remove();
      e.mapa?.remove();
      e.mapa = null;
      e.yaEncuadro = false;
    };
  }, []);

  // Cambio de tema: se vuelve a pintar el mapa (los marcadores se conservan).
  useEffect(() => {
    const e = estado.current;
    if (!listo || !e.mapa || !e.base || e.temaPintado === tema) return;
    e.temaPintado = tema;
    e.mapa.setStyle(estiloSegunTema(e.base, tema));
  }, [tema, listo]);

  useEffect(() => {
    const e = estado.current;
    if (!listo || !e.mapa) return;
    const vigentes = new Set();
    const limites = new LngLatBounds();
    const conUbicacion = conPosicion(tecnicos);
    conUbicacion.forEach((t) => {
      const llave = claveTecnico(t);
      vigentes.add(llave);
      const posicion = [Number(t.longitud), Number(t.latitud)];
      limites.extend(posicion);
      let marcador = e.marcadores.get(llave);
      if (marcador) {
        marcador.setLngLat(posicion);
      } else {
        const elemento = document.createElement('button');
        elemento.type = 'button';
        elemento.className = 'mapa-tecnico mapa-tecnico--libre';
        elemento.title = t.tecnico;
        elemento.setAttribute('aria-label', `Ver a ${t.tecnico}`);
        elemento.innerHTML = htmlMarcador(t.tecnico);
        elemento.addEventListener('click', (evento) => {
          evento.stopPropagation();
          abrirVentana(e, llave);
        });
        marcador = new Marker({ element: elemento }).setLngLat(posicion).addTo(e.mapa);
        e.marcadores.set(llave, marcador);
      }
      marcador.contenido = contenidoTecnico(t);
    });
    e.marcadores.forEach((marcador, llave) => {
      if (!vigentes.has(llave)) {
        marcador.remove();
        e.marcadores.delete(llave);
      }
    });
    // Se encuadra una sola vez al llegar las primeras posiciones; luego el usuario mueve el mapa a su gusto.
    if (conUbicacion.length && !e.yaEncuadro) {
      e.yaEncuadro = true;
      if (conUbicacion.length === 1) e.mapa.jumpTo({ center: limites.getCenter(), zoom: ZOOM_DETALLE });
      else e.mapa.fitBounds(limites, { padding: 60, maxZoom: ZOOM_DETALLE, duration: 0 });
    }
  }, [tecnicos, listo]);

  useEffect(() => {
    const e = estado.current;
    if (!listo || !seleccionado || !e.mapa) return;
    const marcador = e.marcadores.get(seleccionado);
    if (!marcador) return;
    e.mapa.easeTo({ center: marcador.getLngLat(), zoom: Math.max(e.mapa.getZoom(), ZOOM_DETALLE) });
    abrirVentana(e, seleccionado);
  }, [seleccionado, listo]);

  return (
    <div className={`mapa-auditoria${tema === 'oscuro' ? ' mapa-auditoria--oscuro' : ''}`} role="region" aria-label="Mapa de técnicos en mantenimiento">
      <div ref={nodo} className="mapa-auditoria__lienzo" />
      {!listo && <span className="mapa-auditoria__aviso">{error || 'Cargando mapa…'}</span>}
    </div>
  );
}

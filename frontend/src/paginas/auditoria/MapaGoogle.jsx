import { useEffect, useRef, useState } from 'react';
import { cargarGoogleMaps, EVENTO_ERROR } from './mapa/cargarGoogleMaps';
import { COLOR_FONDO, ESTILO_OSCURO } from './mapa/estiloOscuro';
import { CENTRO, ZOOM_DETALLE, ZOOM_INICIAL, claveTecnico, conPosicion, contenidoTecnico, htmlMarcador } from './mapa/comun';

/** Marcador HTML sobre Google Maps. Se hace con OverlayView para no depender de un Map ID de Google Cloud. */
function claseMarcador(OverlayView) {
  return class MarcadorTecnico extends OverlayView {
    constructor(posicion, titulo, alTocar) {
      super();
      this.posicion = posicion;
      this.contenido = '';
      this.elemento = document.createElement('button');
      this.elemento.type = 'button';
      this.elemento.className = 'mapa-tecnico mapa-tecnico--google';
      this.elemento.title = titulo;
      this.elemento.setAttribute('aria-label', `Ver a ${titulo}`);
      this.elemento.innerHTML = htmlMarcador(titulo);
      this.elemento.addEventListener('click', (evento) => {
        evento.stopPropagation();
        alTocar();
      });
    }

    onAdd() {
      this.getPanes().overlayMouseTarget.appendChild(this.elemento);
    }

    draw() {
      const punto = this.getProjection()?.fromLatLngToDivPixel(this.posicion);
      if (punto) {
        this.elemento.style.left = `${punto.x}px`;
        this.elemento.style.top = `${punto.y}px`;
      }
    }

    onRemove() {
      this.elemento.remove();
    }

    mover(posicion) {
      this.posicion = posicion;
      this.draw();
    }
  };
}

function abrirVentana(estado, clave) {
  const marcador = estado.marcadores.get(clave);
  if (!marcador || !estado.mapa) return;
  estado.ventana.setContent(marcador.contenido);
  estado.ventana.setPosition(marcador.posicion);
  estado.ventana.open({ map: estado.mapa });
}

/** Mapa de Google Maps en modo oscuro con la última posición de cada técnico en mantenimiento. */
export default function MapaGoogle({ clave, tecnicos, seleccionado, alFallar }) {
  const nodo = useRef(null);
  const estado = useRef({ mapa: null, maps: null, ventana: null, Marcador: null, LatLngBounds: null, marcadores: new Map(), yaEncuadro: false });
  const [listo, setListo] = useState(false);

  useEffect(() => {
    let cancelado = false;
    const fallo = () => {
      if (!cancelado) alFallar?.();
    };
    window.addEventListener(EVENTO_ERROR, fallo);
    cargarGoogleMaps(clave)
      .then(async (maps) => {
        const [{ Map, InfoWindow, OverlayView }, { LatLngBounds, Size }] = await Promise.all([maps.importLibrary('maps'), maps.importLibrary('core')]);
        if (cancelado || !nodo.current) return;
        const e = estado.current;
        e.maps = maps;
        e.LatLngBounds = LatLngBounds;
        e.Marcador = claseMarcador(OverlayView);
        e.mapa = new Map(nodo.current, {
          center: CENTRO,
          zoom: ZOOM_INICIAL,
          styles: ESTILO_OSCURO,
          backgroundColor: COLOR_FONDO,
          disableDefaultUI: true,
          zoomControl: true,
          fullscreenControl: true,
          clickableIcons: false,
          gestureHandling: 'cooperative',
        });
        e.ventana = new InfoWindow({ pixelOffset: new Size(0, -20) });
        setListo(true);
      })
      .catch(fallo);

    const e = estado.current;
    return () => {
      cancelado = true;
      window.removeEventListener(EVENTO_ERROR, fallo);
      e.marcadores.forEach((m) => m.setMap(null));
      e.marcadores.clear();
      e.ventana?.close();
      e.mapa = null;
      e.yaEncuadro = false;
    };
  }, [clave, alFallar]);

  useEffect(() => {
    const e = estado.current;
    if (!listo || !e.mapa) return;
    const vigentes = new Set();
    const limites = new e.LatLngBounds();
    const conUbicacion = conPosicion(tecnicos);
    conUbicacion.forEach((t) => {
      const llave = claveTecnico(t);
      vigentes.add(llave);
      const posicion = { lat: Number(t.latitud), lng: Number(t.longitud) };
      limites.extend(posicion);
      let marcador = e.marcadores.get(llave);
      if (marcador) {
        marcador.mover(posicion);
      } else {
        marcador = new e.Marcador(posicion, t.tecnico, () => abrirVentana(e, llave));
        marcador.setMap(e.mapa);
        e.marcadores.set(llave, marcador);
      }
      marcador.contenido = contenidoTecnico(t);
    });
    e.marcadores.forEach((marcador, llave) => {
      if (!vigentes.has(llave)) {
        marcador.setMap(null);
        e.marcadores.delete(llave);
      }
    });
    // Se encuadra una sola vez al llegar las primeras posiciones; luego el usuario mueve el mapa a su gusto.
    if (conUbicacion.length && !e.yaEncuadro) {
      e.yaEncuadro = true;
      if (conUbicacion.length === 1) {
        e.mapa.setCenter(limites.getCenter());
        e.mapa.setZoom(ZOOM_DETALLE);
      } else {
        e.mapa.fitBounds(limites, 60);
        e.maps.event.addListenerOnce(e.mapa, 'idle', () => {
          if (e.mapa?.getZoom() > ZOOM_DETALLE) e.mapa.setZoom(ZOOM_DETALLE);
        });
      }
    }
  }, [tecnicos, listo]);

  useEffect(() => {
    const e = estado.current;
    if (!listo || !seleccionado || !e.mapa) return;
    const marcador = e.marcadores.get(seleccionado);
    if (!marcador) return;
    e.mapa.panTo(marcador.posicion);
    if ((e.mapa.getZoom() ?? 0) < ZOOM_DETALLE) e.mapa.setZoom(ZOOM_DETALLE);
    abrirVentana(e, seleccionado);
  }, [seleccionado, listo]);

  return (
    <div className="mapa-auditoria mapa-auditoria--oscuro" role="region" aria-label="Mapa de técnicos en mantenimiento (Google Maps)">
      <div ref={nodo} className="mapa-auditoria__lienzo" data-testid="mapa-google" />
      {!listo && <span className="mapa-auditoria__aviso">Cargando Google Maps…</span>}
    </div>
  );
}

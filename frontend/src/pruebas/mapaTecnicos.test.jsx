import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import MapaTecnicos from '../paginas/auditoria/MapaTecnicos';
import { cargarGoogleMaps } from '../paginas/auditoria/mapa/cargarGoogleMaps';
import { ESTILO_OSCURO } from '../paginas/auditoria/mapa/estiloOscuro';
import { pintarOscuro } from '../paginas/auditoria/mapa/estiloRespaldo';
import { PALETA } from '../paginas/auditoria/mapa/paleta';

// MapLibre necesita WebGL: en las pruebas el respaldo se reemplaza por un marcador simple.
vi.mock('../paginas/auditoria/MapaRespaldo', () => ({
  default: ({ tecnicos }) => <div data-testid="mapa-respaldo">{tecnicos.length} técnicos</div>,
}));
vi.mock('../paginas/auditoria/mapa/cargarGoogleMaps', () => ({ cargarGoogleMaps: vi.fn(), EVENTO_ERROR: 'sigma:google-maps-error' }));

const TECNICOS = [
  { tecnico_id: 1, tecnico: 'Andrés Rojas', equipo: 'ASC-002', edificio: 'Torre A', orden: { id: 10, numero: 'OT-2026-00010' }, latitud: 4.70, longitud: -74.05, registrada_en: '2026-11-24T15:00:00-05:00' },
  { tecnico_id: 2, tecnico: 'Camilo Herrera', equipo: 'ASC-205', edificio: 'Torre 3', orden: { id: 11, numero: 'OT-2026-00011' }, latitud: 4.69, longitud: -74.04, registrada_en: '2026-11-24T15:05:00-05:00' },
  { tecnico_id: 3, tecnico: 'Sin señal', equipo: 'ASC-001', orden: { id: 12, numero: 'OT-2026-00012' }, latitud: null, longitud: null },
];

/** API de Google Maps simulada: lo justo para crear el mapa, los marcadores y la ventana. */
function simularGoogle() {
  const registro = { mapas: [], ventana: null };
  class Map {
    constructor(nodo, opciones) {
      this.opciones = opciones;
      this.zoom = opciones.zoom;
      registro.mapas.push(this);
    }
    setCenter(c) { this.centro = c; }
    setZoom(z) { this.zoom = z; }
    getZoom() { return this.zoom; }
    fitBounds(limites) { this.limites = limites; }
    panTo(p) { this.centro = p; }
  }
  class OverlayView {
    setMap(mapa) {
      if (mapa) {
        this.onAdd();
        this.draw();
      } else {
        this.onRemove();
      }
    }
    getPanes() { return { overlayMouseTarget: document.body }; }
    getProjection() { return { fromLatLngToDivPixel: () => ({ x: 10, y: 20 }) }; }
  }
  class InfoWindow {
    constructor() { registro.ventana = this; }
    setContent(c) { this.contenido = c; }
    setPosition(p) { this.posicion = p; }
    open() { this.abierta = true; }
    close() { this.abierta = false; }
  }
  class LatLngBounds {
    constructor() { this.puntos = []; }
    extend(p) { this.puntos.push(p); }
    getCenter() { return this.puntos[0]; }
  }
  const maps = {
    importLibrary: async (nombre) => (nombre === 'maps' ? { Map, InfoWindow, OverlayView } : { LatLngBounds, Size: class { constructor(x, y) { Object.assign(this, { x, y }); } } }),
    event: { addListenerOnce: (_m, _e, f) => f() },
  };
  return { maps, registro };
}

afterEach(() => {
  vi.unstubAllEnvs();
  vi.clearAllMocks();
  document.querySelectorAll('.mapa-tecnico--google').forEach((n) => n.remove());
});

describe('mapa de técnicos en Auditoría', () => {
  it('sin clave de Google Maps usa el mapa oscuro de respaldo', async () => {
    vi.stubEnv('VITE_GOOGLE_MAPS_KEY', '');
    render(<MapaTecnicos tecnicos={TECNICOS} />);
    expect(await screen.findByTestId('mapa-respaldo')).toHaveTextContent('3 técnicos');
    expect(cargarGoogleMaps).not.toHaveBeenCalled();
  });

  it('con clave usa Google Maps en modo oscuro y muestra cada técnico con posición', async () => {
    vi.stubEnv('VITE_GOOGLE_MAPS_KEY', 'clave-de-prueba');
    const { maps, registro } = simularGoogle();
    cargarGoogleMaps.mockResolvedValue(maps);
    render(<MapaTecnicos tecnicos={TECNICOS} />);

    expect(cargarGoogleMaps).toHaveBeenCalledWith('clave-de-prueba');
    await waitFor(() => expect(registro.mapas).toHaveLength(1));
    expect(registro.mapas[0].opciones.styles).toBe(ESTILO_OSCURO);
    expect(registro.mapas[0].opciones.backgroundColor).toMatch(/^#1/);

    const marcadores = await screen.findAllByRole('button', { name: /^Ver a / });
    expect(marcadores.map((m) => m.textContent)).toEqual(['AR', 'CH']);

    await userEvent.click(screen.getByRole('button', { name: 'Ver a Camilo Herrera' }));
    expect(registro.ventana.abierta).toBe(true);
    expect(registro.ventana.contenido).toContain('OT-2026-00011');
    expect(screen.queryByTestId('mapa-respaldo')).not.toBeInTheDocument();
  });

  it('si Google Maps no carga, vuelve al mapa de respaldo', async () => {
    vi.stubEnv('VITE_GOOGLE_MAPS_KEY', 'clave-invalida');
    cargarGoogleMaps.mockRejectedValue(new Error('No se pudo cargar Google Maps.'));
    render(<MapaTecnicos tecnicos={TECNICOS} />);
    expect(await screen.findByTestId('mapa-respaldo')).toBeInTheDocument();
  });

  it('si Google rechaza la clave, también vuelve al respaldo', async () => {
    vi.stubEnv('VITE_GOOGLE_MAPS_KEY', 'clave-sin-permiso');
    const { maps } = simularGoogle();
    cargarGoogleMaps.mockResolvedValue(maps);
    render(<MapaTecnicos tecnicos={TECNICOS} />);
    await screen.findAllByRole('button', { name: /^Ver a / });
    window.dispatchEvent(new Event('sigma:google-maps-error'));
    expect(await screen.findByTestId('mapa-respaldo')).toBeInTheDocument();
  });
});

describe('paleta oscura del mapa de respaldo', () => {
  it('vuelve a pintar el estilo claro con la paleta de SIGMA y oculta los escudos', () => {
    const base = {
      version: 8,
      layers: [
        { id: 'background', type: 'background', paint: { 'background-color': 'rgb(242,243,240)' } },
        { id: 'water', type: 'fill', paint: { 'fill-color': 'rgb(194, 200, 202)', 'fill-opacity': 1 } },
        { id: 'highway_motorway_inner', type: 'line', paint: { 'line-color': '#fff', 'line-width': 2 } },
        { id: 'label_city', type: 'symbol', layout: { 'text-field': ['coalesce', ['get', 'name_en'], ['get', 'name']] }, paint: { 'text-color': '#000', 'text-halo-color': '#fff' } },
        { id: 'highway-shield-non-us', type: 'symbol', layout: { 'icon-image': 'escudo' } },
        { id: 'capa_nueva', type: 'fill', paint: { 'fill-color': '#eee' } },
      ],
    };
    const [fondo, agua, autopista, ciudad, escudo, nueva] = pintarOscuro(base).layers;
    expect(fondo.paint['background-color']).toBe(PALETA.fondo);
    expect(agua.paint).toEqual({ 'fill-color': PALETA.agua, 'fill-opacity': 1 });
    expect(autopista.paint).toEqual({ 'line-color': PALETA.autopista, 'line-width': 2 });
    expect(ciudad.paint).toEqual({ 'text-color': PALETA.textoCiudad, 'text-halo-color': PALETA.halo });
    expect(ciudad.layout['text-field']).toEqual(['coalesce', ['get', 'name:es'], ['get', 'name'], ['get', 'name:latin']]);
    expect(escudo.layout['text-field']).toBeUndefined();
    expect(escudo.layout.visibility).toBe('none');
    expect(nueva.paint['fill-color']).toBe(PALETA.residencial);
    expect(base.layers[0].paint['background-color']).toBe('rgb(242,243,240)');
  });

  it('Google Maps usa la misma paleta', () => {
    expect(ESTILO_OSCURO[0].stylers[0].color).toBe(PALETA.fondo);
    expect(ESTILO_OSCURO.find((r) => r.featureType === 'water').stylers[0].color).toBe(PALETA.agua);
  });
});

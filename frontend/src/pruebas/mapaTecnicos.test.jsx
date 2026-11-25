import { describe, expect, it } from 'vitest';
import { estiloSegunTema, pintarClaro, pintarOscuro, NOMBRE_ES } from '../paginas/auditoria/mapa/estiloMapa';
import { PALETA } from '../paginas/auditoria/mapa/paleta';

// El mapa (MapLibre) necesita WebGL: aquí se prueba el estilo que se le entrega.
describe('paleta nocturna del mapa de técnicos', () => {
  const base = {
    version: 8,
    layers: [
      { id: 'background', type: 'background', paint: { 'background-color': 'rgb(242,243,240)' } },
      { id: 'water', type: 'fill', paint: { 'fill-color': 'rgb(194, 200, 202)', 'fill-opacity': 1 } },
      { id: 'park', type: 'fill', paint: { 'fill-color': 'rgb(230, 233, 229)' } },
      { id: 'highway_minor', type: 'line', paint: { 'line-color': 'hsl(0,0%,88%)' } },
      { id: 'highway_motorway_inner', type: 'line', paint: { 'line-color': '#fff', 'line-width': 2 } },
      { id: 'label_city', type: 'symbol', layout: { 'text-field': ['coalesce', ['get', 'name_en'], ['get', 'name']] }, paint: { 'text-color': '#000', 'text-halo-color': '#fff' } },
      { id: 'highway-shield-non-us', type: 'symbol', layout: { 'icon-image': 'escudo', 'text-field': ['get', 'ref'] } },
      { id: 'capa_nueva', type: 'fill', paint: { 'fill-color': '#eee' } },
    ],
  };

  it('usa los colores del modo noche de Google Maps', () => {
    const [fondo, agua, parque, via, autopista] = pintarOscuro(base).layers;
    expect(fondo.paint['background-color']).toBe('#242f3e');
    expect(agua.paint).toEqual({ 'fill-color': '#17263c', 'fill-opacity': 1 });
    expect(parque.paint['fill-color']).toBe('#263c3f');
    expect(via.paint['line-color']).toBe('#38414e');
    expect(autopista.paint).toEqual({ 'line-color': '#746855', 'line-width': 2 });
  });

  it('muestra los nombres en español, oculta los escudos y no cambia el estilo original', () => {
    const capas = pintarOscuro(base).layers;
    const ciudad = capas.find((c) => c.id === 'label_city');
    expect(ciudad.paint).toEqual({ 'text-color': PALETA.textoCiudad, 'text-halo-color': PALETA.halo });
    expect(ciudad.layout['text-field']).toEqual(NOMBRE_ES);
    expect(capas.find((c) => c.id === 'highway-shield-non-us').layout.visibility).toBe('none');
    expect(capas.find((c) => c.id === 'capa_nueva').paint['fill-color']).toBe(PALETA.residencial);
    expect(base.layers[0].paint['background-color']).toBe('rgb(242,243,240)');
  });
});

describe('estilo del mapa según el tema del panel', () => {
  const base = {
    version: 8,
    layers: [
      { id: 'background', type: 'background', paint: { 'background-color': 'rgb(242,243,240)' } },
      { id: 'label_city', type: 'symbol', layout: { 'text-field': ['get', 'name_en'] }, paint: { 'text-color': '#000' } },
      { id: 'highway-shield-non-us', type: 'symbol', layout: { 'text-field': ['get', 'ref'] } },
    ],
  };

  it('en el tema claro conserva los colores claros, con nombres en español y sin escudos', () => {
    const capas = pintarClaro(base).layers;
    expect(capas[0].paint['background-color']).toBe('rgb(242,243,240)');
    expect(capas[1].paint['text-color']).toBe('#000');
    expect(capas[1].layout['text-field']).toEqual(NOMBRE_ES);
    expect(capas[2].layout.visibility).toBe('none');
    expect(base.layers[1].layout['text-field']).toEqual(['get', 'name_en']);
  });

  it('elige la paleta nocturna solo en el tema oscuro', () => {
    expect(estiloSegunTema(base, 'oscuro').layers[0].paint['background-color']).toBe('#242f3e');
    expect(estiloSegunTema(base, 'claro').layers[0].paint['background-color']).toBe('rgb(242,243,240)');
  });
});

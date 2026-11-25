import { PALETA as P } from './paleta';

// Mapa de técnicos: teselas vectoriales de OpenFreeMap (datos de OpenStreetMap, sin clave ni costo).
// Se toma su estilo claro «positron» y se vuelve a pintar con la paleta nocturna (paleta.js).
export const URL_ESTILO_BASE = 'https://tiles.openfreemap.org/styles/positron';

const texto = (color, halo = P.halo) => ({ 'text-color': color, 'text-halo-color': halo });

// Colores por capa del estilo positron.
const COLORES = {
  background: { 'background-color': P.fondo },
  park: { 'fill-color': P.parque },
  water: { 'fill-color': P.agua },
  landcover_ice_shelf: { 'fill-color': P.hielo },
  landcover_glacier: { 'fill-color': P.hielo },
  landuse_residential: { 'fill-color': P.residencial },
  landcover_wood: { 'fill-color': P.parque },
  waterway: { 'line-color': P.rio },
  building: { 'fill-color': P.edificio, 'fill-outline-color': P.bordeEdificio },
  tunnel_motorway_casing: { 'line-color': P.bordeAutopista },
  tunnel_motorway_inner: { 'line-color': P.tunel },
  'aeroway-taxiway': { 'line-color': P.aeropuerto },
  'aeroway-runway-casing': { 'line-color': P.aeropuerto },
  'aeroway-area': { 'fill-color': P.aeropuerto },
  'aeroway-runway': { 'line-color': P.viaPrincipal },
  road_area_pier: { 'fill-color': P.fondo },
  road_pier: { 'line-color': P.fondo },
  highway_path: { 'line-color': P.sendero },
  highway_minor: { 'line-color': P.viaMenor },
  highway_major_casing: { 'line-color': P.bordeVia },
  highway_major_inner: { 'line-color': P.viaPrincipal },
  highway_major_subtle: { 'line-color': P.viaSutil },
  highway_motorway_casing: { 'line-color': P.bordeAutopista },
  highway_motorway_inner: { 'line-color': P.autopista },
  highway_motorway_subtle: { 'line-color': P.autopistaSutil },
  highway_motorway_bridge_casing: { 'line-color': P.bordeAutopista },
  highway_motorway_bridge_inner: { 'line-color': P.autopista },
  railway_transit: { 'line-color': P.ferrocarril },
  railway_transit_dashline: { 'line-color': P.sendero },
  railway_service: { 'line-color': P.ferrocarril },
  railway_service_dashline: { 'line-color': P.sendero },
  railway: { 'line-color': P.ferrocarril },
  railway_dashline: { 'line-color': P.sendero },
  boundary_3: { 'line-color': P.limite },
  boundary_2: { 'line-color': P.limite },
  boundary_disputed: { 'line-color': P.limite },
  waterway_line_label: texto(P.textoAgua, P.agua),
  water_name_point_label: texto(P.textoAgua, P.agua),
  water_name_line_label: texto(P.textoAgua, P.agua),
  'highway-name-path': texto(P.textoSendero),
  'highway-name-minor': texto(P.textoCalle),
  'highway-name-major': texto(P.textoCalle),
  airport: texto(P.texto),
  label_other: texto(P.textoCalle),
  label_village: texto(P.textoPueblo),
  label_town: texto(P.textoPueblo),
  label_state: texto(P.texto),
  label_city: texto(P.textoCiudad),
  label_city_capital: texto(P.textoCiudad),
  label_country_3: texto(P.textoPais),
  label_country_2: texto(P.textoPais),
  label_country_1: texto(P.textoPais),
};

// Los escudos de carreteras (íconos blancos pensados para Estados Unidos) sobran en un mapa oscuro de Colombia.
const OCULTAS = new Set(['highway-shield-non-us', 'highway-shield-us-interstate', 'road_shield_us']);

// Capas que no estén en la lista (si OpenFreeMap agrega alguna): un color oscuro según su tipo.
const POR_TIPO = {
  background: { 'background-color': P.fondo },
  fill: { 'fill-color': P.residencial },
  line: { 'line-color': P.viaSutil },
  symbol: texto(P.texto),
};

// Nombres en español: el nombre local (en Colombia ya es español) antes que la versión latina o inglesa.
export const NOMBRE_ES = ['coalesce', ['get', 'name:es'], ['get', 'name'], ['get', 'name:latin']];

/** Devuelve una copia del estilo con la paleta oscura de SIGMA y los nombres en español. */
export function pintarOscuro(estilo) {
  return {
    ...estilo,
    layers: estilo.layers.map((capa) => {
      if (OCULTAS.has(capa.id)) return { ...capa, layout: { ...capa.layout, visibility: 'none' } };
      const colores = COLORES[capa.id] ?? POR_TIPO[capa.type];
      let nueva = colores ? { ...capa, paint: { ...capa.paint, ...colores } } : capa;
      const campo = capa.layout?.['text-field'];
      if (campo && JSON.stringify(campo).includes('name')) nueva = { ...nueva, layout: { ...nueva.layout, 'text-field': NOMBRE_ES } };
      return nueva;
    }),
  };
}

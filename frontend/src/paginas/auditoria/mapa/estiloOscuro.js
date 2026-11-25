import { PALETA as P } from './paleta';

// Estilo oscuro de Google Maps con la paleta «SIGMA noche».
// Se aplica con la opción `styles`, que funciona sin un Map ID configurado en Google Cloud.
export const COLOR_FONDO = P.fondo;

export const ESTILO_OSCURO = [
  { elementType: 'geometry', stylers: [{ color: P.fondo }] },
  { elementType: 'labels.text.fill', stylers: [{ color: P.texto }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: P.halo }] },
  { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
  { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: P.limite }] },
  { featureType: 'administrative.country', elementType: 'labels.text.fill', stylers: [{ color: P.textoPais }] },
  { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: P.textoCiudad }] },
  { featureType: 'administrative.neighborhood', elementType: 'labels.text.fill', stylers: [{ color: P.textoCalle }] },
  { featureType: 'landscape.man_made', elementType: 'geometry', stylers: [{ color: P.residencial }] },
  { featureType: 'landscape.natural', elementType: 'geometry', stylers: [{ color: P.fondo }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'poi.park', stylers: [{ visibility: 'on' }] },
  { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: P.parque }] },
  { featureType: 'poi.park', elementType: 'labels', stylers: [{ visibility: 'off' }] },
  { featureType: 'road', elementType: 'geometry.fill', stylers: [{ color: P.viaMenor }] },
  { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: P.bordeVia }] },
  { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: P.textoCalle }] },
  { featureType: 'road.arterial', elementType: 'geometry.fill', stylers: [{ color: P.viaPrincipal }] },
  { featureType: 'road.highway', elementType: 'geometry.fill', stylers: [{ color: P.autopista }] },
  { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: P.bordeAutopista }] },
  { featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: P.textoPueblo }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: P.agua }] },
  { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: P.textoAgua }] },
];

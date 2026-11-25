// Estilo oscuro de Google Maps con la paleta del panel (negros con tinte violeta).
// Se aplica con la opción `styles`, que funciona sin un Map ID configurado en Google Cloud.
export const COLOR_FONDO = '#14121a';

export const ESTILO_OSCURO = [
  { elementType: 'geometry', stylers: [{ color: COLOR_FONDO }] },
  { elementType: 'labels.text.fill', stylers: [{ color: '#8f88a3' }] },
  { elementType: 'labels.text.stroke', stylers: [{ color: COLOR_FONDO }] },
  { elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
  { featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#2c2936' }] },
  { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#c9bff0' }] },
  { featureType: 'administrative.neighborhood', elementType: 'labels.text.fill', stylers: [{ color: '#6e687d' }] },
  { featureType: 'landscape.man_made', elementType: 'geometry', stylers: [{ color: '#18161f' }] },
  { featureType: 'poi', stylers: [{ visibility: 'off' }] },
  { featureType: 'poi.park', stylers: [{ visibility: 'on' }] },
  { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#15211b' }] },
  { featureType: 'poi.park', elementType: 'labels', stylers: [{ visibility: 'off' }] },
  { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#262330' }] },
  { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#1b1923' }] },
  { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#7d7790' }] },
  { featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: '#2e2a3b' }] },
  { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#3a3150' }] },
  { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#241f33' }] },
  { featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: '#bba6ff' }] },
  { featureType: 'transit', stylers: [{ visibility: 'off' }] },
  { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0d1626' }] },
  { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#3d4a66' }] },
];

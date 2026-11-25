/**
 * Carga la API de Google Maps una sola vez (carga asíncrona recomendada por Google) y devuelve `google.maps`.
 * La clave va en VITE_GOOGLE_MAPS_KEY y debe estar restringida por dominio en Google Cloud.
 */
let promesa = null;
const CALLBACK = '__sigmaGoogleMapsListo';
export const EVENTO_ERROR = 'sigma:google-maps-error';

export function cargarGoogleMaps(clave) {
  if (window.google?.maps?.importLibrary) return Promise.resolve(window.google.maps);
  if (promesa) return promesa;

  promesa = new Promise((resolver, rechazar) => {
    window[CALLBACK] = () => {
      delete window[CALLBACK];
      resolver(window.google.maps);
    };
    // Google llama a esta función si la clave no es válida o no está autorizada para este dominio.
    window.gm_authFailure = () => window.dispatchEvent(new Event(EVENTO_ERROR));

    const parametros = new URLSearchParams({ key: clave, v: 'weekly', language: 'es', region: 'CO', loading: 'async', callback: CALLBACK });
    const script = document.createElement('script');
    script.src = `https://maps.googleapis.com/maps/api/js?${parametros}`;
    script.async = true;
    script.onerror = () => {
      promesa = null;
      script.remove();
      rechazar(new Error('No se pudo cargar Google Maps.'));
    };
    document.head.appendChild(script);
  });
  return promesa;
}

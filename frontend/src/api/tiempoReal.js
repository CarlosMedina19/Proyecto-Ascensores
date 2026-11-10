// Conexión en tiempo real (WebSocket con Laravel Reverb). Solo la usan Auditoría y la campana de notificaciones.
// Si el panel se compiló sin VITE_REVERB_KEY, todo sigue funcionando con recargas normales.
import { URL_API, obtenerToken } from './cliente';

const CLAVE = import.meta.env.VITE_REVERB_KEY;
let echo = null;
let tokenUsado = null;

export function tiempoRealDisponible() {
  return Boolean(CLAVE) && typeof window !== 'undefined' && typeof window.WebSocket !== 'undefined';
}

async function conexion() {
  const token = obtenerToken();
  if (!tiempoRealDisponible() || !token) return null;
  if (echo && tokenUsado === token) return echo;
  if (echo) echo.disconnect();

  const [{ default: Echo }, { default: Pusher }] = await Promise.all([import('laravel-echo'), import('pusher-js')]);
  const seguro = window.location.protocol === 'https:';
  const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
  const puerto = Number(import.meta.env.VITE_REVERB_PORT || window.location.port || (seguro ? 443 : 80));
  // /api/v1 → /api/broadcasting/auth (misma API, mismo token).
  const base = new URL(URL_API, window.location.origin);
  const autorizacion = `${base.origin}${base.pathname.replace(/\/v1$/, '')}/broadcasting/auth`;

  window.Pusher = Pusher;
  echo = new Echo({
    broadcaster: 'reverb',
    key: CLAVE,
    wsHost: host,
    wsPort: puerto,
    wssPort: puerto,
    forceTLS: seguro,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: autorizacion,
    auth: { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
  });
  tokenUsado = token;
  return echo;
}

/**
 * Escucha eventos de un canal privado. Devuelve una función para dejar de escuchar.
 * @param {string} canal p. ej. 'auditoria' o 'usuarios.5'
 * @param {Record<string, (datos: any) => void>} eventos nombre (broadcastAs) → manejador
 */
export function escuchar(canal, eventos) {
  let activo = true;
  let suscripcion = null;
  conexion()
    .then((cliente) => {
      if (!cliente || !activo) return;
      suscripcion = cliente.private(canal);
      Object.entries(eventos).forEach(([nombre, manejador]) => suscripcion.listen(`.${nombre}`, manejador));
    })
    .catch(() => {});
  return () => {
    activo = false;
    if (suscripcion && echo) echo.leave(canal);
  };
}

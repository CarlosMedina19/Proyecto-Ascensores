/**
 * Utilidades para armar HTML a mano (ventanas de impresión) sin abrir la puerta a XSS.
 * En JSX no hace falta: React ya escapa el texto.
 */

const ENTIDADES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

/** Convierte un texto en HTML seguro: `<img onerror=…>` se muestra como texto y no se ejecuta. */
export function escaparHtml(valor) {
  return String(valor ?? '').replace(/[&<>"']/g, (c) => ENTIDADES[c]);
}

/**
 * Documento de la ventana para imprimir el QR de la cabina.
 * `svg` lo genera la librería qrcode y se inserta tal cual; el código del ascensor y la URL se escapan
 * porque los escribe un usuario (formulario o importación de Excel). No lleva <script>: la impresión
 * se lanza desde el panel.
 */
export function documentoImpresionQr({ codigo, url, svg }) {
  const codigoSeguro = escaparHtml(codigo);
  return `<!doctype html><title>QR ${codigoSeguro}</title>
      <style>@import url('https://fonts.googleapis.com/css2?family=Barlow:wght@600&family=Geist:wght@400&display=swap');body{font-family:'Geist',system-ui,sans-serif;text-align:center;padding:24px}svg{width:260px;height:260px}h1{font:600 22px/1.2 'Barlow',system-ui,sans-serif;letter-spacing:-0.01em;margin:12px 0 4px}p{margin:4px 0;color:#333}</style>
      ${svg}<h1>¿Falla en el ascensor ${codigoSeguro}?</h1><p>Escanee el código para reportarla las 24 horas.</p><p>${escaparHtml(url)}</p>`;
}

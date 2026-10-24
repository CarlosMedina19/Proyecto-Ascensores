// Cliente HTTP de la API de SIGMA (/api/v1). Usa el token Bearer de Sanctum.

export const URL_API = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api/v1').replace(/\/$/, '');

const CLAVE_TOKEN = 'sigma.token';

// El token se guarda en sessionStorage: al cerrar el navegador la sesión termina.
let token = sessionStorage.getItem(CLAVE_TOKEN);

export function obtenerToken() {
  return token;
}

export function guardarToken(nuevoToken) {
  token = nuevoToken;
  if (nuevoToken) {
    sessionStorage.setItem(CLAVE_TOKEN, nuevoToken);
  } else {
    sessionStorage.removeItem(CLAVE_TOKEN);
  }
}

/** Error de la API con el mensaje y los errores de validación en español. */
export class ErrorApi extends Error {
  constructor(mensaje, estado, errores = {}) {
    super(mensaje);
    this.name = 'ErrorApi';
    this.estado = estado;
    this.errores = errores;
  }
}

function construirUrl(ruta, consulta) {
  // En producción URL_API es relativa ("/api/v1", mismo dominio): sin la base, new URL() falla.
  const url = new URL(URL_API + ruta, window.location.origin);
  Object.entries(consulta || {}).forEach(([clave, valor]) => {
    if (valor !== undefined && valor !== null && valor !== '') {
      url.searchParams.set(clave, valor);
    }
  });
  return url.toString();
}

export async function solicitar(ruta, { metodo = 'GET', cuerpo, consulta } = {}) {
  const esFormulario = cuerpo instanceof FormData;
  const cabeceras = { Accept: 'application/json' };
  // Con FormData el navegador pone el Content-Type multipart con su separador.
  if (cuerpo !== undefined && !esFormulario) cabeceras['Content-Type'] = 'application/json';
  if (token) cabeceras.Authorization = `Bearer ${token}`;

  let respuesta;
  try {
    respuesta = await fetch(construirUrl(ruta, consulta), {
      method: metodo,
      headers: cabeceras,
      body: cuerpo === undefined ? undefined : esFormulario ? cuerpo : JSON.stringify(cuerpo),
    });
  } catch {
    throw new ErrorApi('No fue posible conectar con el servidor. Verifique su conexión.', 0);
  }

  const datos = respuesta.status === 204 ? null : await respuesta.json().catch(() => null);

  if (!respuesta.ok) {
    // Token vencido por inactividad, cerrado o usuario desactivado.
    if (respuesta.status === 401 && token) {
      guardarToken(null);
      window.dispatchEvent(new CustomEvent('sigma:sesion-expirada'));
    }

    throw new ErrorApi(
      datos?.mensaje || 'Ocurrió un error inesperado. Intente de nuevo.',
      respuesta.status,
      datos?.errores || {},
    );
  }

  return datos;
}

/**
 * Descarga un archivo protegido (p. ej. la foto de un ascensor) con el token y devuelve
 * una URL local (blob:) para mostrarlo en una etiqueta <img>.
 */
export async function urlArchivo(ruta) {
  const respuesta = await fetch(URL_API + ruta, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
  if (!respuesta.ok) throw new ErrorApi('No fue posible cargar el archivo.', respuesta.status);
  return URL.createObjectURL(await respuesta.blob());
}

/**
 * Descarga un archivo protegido (Excel, PDF) con el token y lo guarda con el nombre que envía el servidor.
 */
export async function descargarArchivo(ruta, nombrePorDefecto = 'archivo') {
  const respuesta = await fetch(URL_API + ruta, { headers: token ? { Authorization: `Bearer ${token}` } : {} });
  if (!respuesta.ok) {
    let mensaje = 'No fue posible descargar el archivo. Intente de nuevo.';
    try {
      mensaje = (await respuesta.json()).mensaje || mensaje;
    } catch {
      // Respuesta sin JSON: se usa el mensaje general.
    }
    throw new ErrorApi(mensaje, respuesta.status);
  }
  const disposicion = respuesta.headers.get('Content-Disposition') || '';
  const nombre = decodeURIComponent((disposicion.match(/filename\*=UTF-8''([^;]+)/i) || disposicion.match(/filename="?([^";]+)"?/i) || [])[1] || nombrePorDefecto);
  const url = URL.createObjectURL(await respuesta.blob());
  const enlace = document.createElement('a');
  enlace.href = url;
  enlace.download = nombre;
  document.body.appendChild(enlace);
  enlace.click();
  enlace.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

export const api = {
  obtener: (ruta, consulta) => solicitar(ruta, { consulta }),
  crear: (ruta, cuerpo) => solicitar(ruta, { metodo: 'POST', cuerpo }),
  actualizar: (ruta, cuerpo) => solicitar(ruta, { metodo: 'PATCH', cuerpo }),
  reemplazar: (ruta, cuerpo) => solicitar(ruta, { metodo: 'PUT', cuerpo }),
  eliminar: (ruta) => solicitar(ruta, { metodo: 'DELETE' }),
};

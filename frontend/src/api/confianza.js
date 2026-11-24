// Dispositivo de confianza de la verificación en dos pasos: después de verificar el código en este
// navegador, el servidor entrega una llave que se guarda aquí (por correo) y se envía en los próximos
// ingresos para no volver a pedir el código. Vive en localStorage: sobrevive al cierre del navegador.

const CLAVE = 'sigma.dispositivos-confianza';

function leerTodo() {
  try {
    return JSON.parse(localStorage.getItem(CLAVE) || '{}') || {};
  } catch {
    return {};
  }
}

function guardarTodo(mapa) {
  try {
    localStorage.setItem(CLAVE, JSON.stringify(mapa));
  } catch {
    // Navegación privada o almacenamiento bloqueado: se volverá a pedir el código, nada más.
  }
}

const normalizar = (correo) => String(correo || '').trim().toLowerCase();

export function leerConfianza(correo) {
  return leerTodo()[normalizar(correo)] || null;
}

export function guardarConfianza(correo, llave) {
  if (!correo || !llave) return;
  guardarTodo({ ...leerTodo(), [normalizar(correo)]: llave });
}

export function olvidarConfianza(correo) {
  const mapa = leerTodo();
  delete mapa[normalizar(correo)];
  guardarTodo(mapa);
}

// Etiquetas del servicio comercial: emergencias, cotizaciones y certificación anual.

export const TIPOS_EMERGENCIA = {
  atrapamiento: 'Personas atrapadas',
  falla: 'Ascensor detenido o con falla',
  ruido: 'Ruido o movimiento anormal',
  otro: 'Otro',
};

export const ESTADOS_EMERGENCIA = {
  reportada: { texto: 'Reportada', tipo: 'peligro' },
  asignada: { texto: 'Técnico en camino', tipo: 'alerta' },
  en_sitio: { texto: 'Técnico en sitio', tipo: 'info' },
  resuelta: { texto: 'Resuelta', tipo: 'exito' },
  cancelada: { texto: 'Cancelada', tipo: 'neutro' },
};

export const ORIGENES_EMERGENCIA = { panel: 'Panel', app: 'App', qr: 'Código QR' };

export const ESTADOS_COTIZACION = {
  enviada: { texto: 'Esperando respuesta', tipo: 'info' },
  aprobada: { texto: 'Aprobada', tipo: 'exito' },
  rechazada: { texto: 'Rechazada', tipo: 'peligro' },
  vencida: { texto: 'Vencida', tipo: 'neutro' },
};

export const RESULTADOS_CERTIFICACION = {
  conforme: 'Conforme',
  condicionado: 'Conforme con observaciones',
  no_conforme: 'No conforme',
};

export const ESTADOS_CERTIFICACION = {
  vigente: { texto: 'Vigente', tipo: 'exito' },
  por_vencer: { texto: 'Por vencer', tipo: 'alerta' },
  vencida: { texto: 'Vencida', tipo: 'peligro' },
  no_conforme: { texto: 'No conforme', tipo: 'peligro' },
  sin_certificar: { texto: 'Sin certificar', tipo: 'neutro' },
};

/** "25 min" o "1 h 05 min". */
export function duracion(minutos) {
  if (minutos === null || minutos === undefined) return '—';
  if (minutos < 60) return `${minutos} min`;
  const horas = Math.floor(minutos / 60);
  return `${horas} h ${String(minutos % 60).padStart(2, '0')} min`;
}

/** Texto del plazo de la certificación: "Vence en 25 días", "Venció hace 3 días". */
export function plazoCertificacion(dias) {
  if (dias === null || dias === undefined) return 'Sin certificado registrado';
  if (dias === 0) return 'Vence hoy';
  if (dias > 0) return `Vence en ${dias} ${dias === 1 ? 'día' : 'días'}`;
  return `Venció hace ${-dias} ${dias === -1 ? 'día' : 'días'}`;
}

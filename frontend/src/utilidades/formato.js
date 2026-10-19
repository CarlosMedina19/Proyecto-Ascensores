// Formatos y etiquetas en español para mostrar los datos de la API.

const formatoFechaHora = new Intl.DateTimeFormat('es-CO', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
});

const formatoFecha = new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: 'short', year: 'numeric' });

export function fechaHora(valor) {
  if (!valor) return '—';
  return formatoFechaHora.format(new Date(valor));
}

/** Fechas sin hora (AAAA-MM-DD) se interpretan en la zona local. */
export function fecha(valor) {
  if (!valor) return '—';
  const texto = String(valor);
  const fechaLocal = /^\d{4}-\d{2}-\d{2}$/.test(texto) ? new Date(`${texto}T00:00:00`) : new Date(texto);
  return formatoFecha.format(fechaLocal);
}

const formatoDia = new Intl.DateTimeFormat('es-CO', { day: '2-digit', month: 'short' });
const formatoHora = new Intl.DateTimeFormat('es-CO', { hour: '2-digit', minute: '2-digit', hour12: false });

/** "16 oct" para líneas de tiempo. */
export function fechaCorta(valor) {
  if (!valor) return '—';
  const partes = formatoDia.formatToParts(new Date(valor));
  const dia = partes.find((p) => p.type === 'day')?.value;
  const mes = partes.find((p) => p.type === 'month')?.value.replace('.', '');
  return `${dia} ${mes}`;
}

/** "08:12" para líneas de tiempo. */
export function horaCorta(valor) {
  return valor ? formatoHora.format(new Date(valor)) : '';
}

/** Color del punto en la línea de tiempo según el tipo de evento del historial. */
export function tipoEvento(evento = '') {
  if (evento.startsWith('alta') || evento === 'plan_asignado') return 'cian';
  if (evento === 'cambio_plan') return 'alerta';
  if (evento.includes('baja') || evento === 'incidente') return 'peligro';
  if (evento.includes('estado') || evento === 'reparacion' || evento === 'cambio_repuesto') return 'alerta';
  if (evento.includes('mantenimiento') || evento.includes('inspeccion')) return 'exito';
  return 'info';
}

export function numero(valor, sufijo = '') {
  if (valor === null || valor === undefined || valor === '') return '—';
  return `${Number(valor).toLocaleString('es-CO')}${sufijo}`;
}

export function iniciales(nombre = '') {
  return nombre
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((parte) => parte[0].toUpperCase())
    .join('');
}

/** "cambio_estado" → "Cambio estado" */
export function humanizar(texto = '') {
  const limpio = String(texto).replace(/_/g, ' ').trim();
  return limpio.charAt(0).toUpperCase() + limpio.slice(1);
}

export const ESTADOS_EQUIPO = {
  activo: { texto: 'Activo', tipo: 'exito' },
  mantenimiento: { texto: 'En mantenimiento', tipo: 'alerta' },
  inactivo: { texto: 'Inactivo', tipo: 'neutro' },
};

export const TIPOS_CLIENTE = {
  natural: 'Persona natural',
  juridica: 'Persona jurídica',
};

export const TIPOS_DOCUMENTO = [
  { valor: 'CC', texto: 'Cédula de ciudadanía (CC)' },
  { valor: 'CE', texto: 'Cédula de extranjería (CE)' },
  { valor: 'TI', texto: 'Tarjeta de identidad (TI)' },
  { valor: 'PA', texto: 'Pasaporte (PA)' },
  { valor: 'PEP', texto: 'Permiso especial de permanencia (PEP)' },
  { valor: 'NIT', texto: 'NIT' },
];

export const REGIMENES_TRIBUTARIOS = [
  'Responsable de IVA',
  'No responsable de IVA',
  'Régimen simple de tributación',
  'Gran contribuyente',
];

export const EVENTOS_HISTORIAL = [
  { valor: 'incidente', texto: 'Incidente / falla reportada' },
  { valor: 'reparacion', texto: 'Reparación' },
  { valor: 'mantenimiento', texto: 'Mantenimiento' },
  { valor: 'inspeccion', texto: 'Inspección' },
  { valor: 'cambio_repuesto', texto: 'Cambio de repuesto' },
  { valor: 'observacion', texto: 'Observación' },
];

export const ACCIONES_AUDITORIA = {
  creado: 'Creación',
  actualizado: 'Actualización',
  eliminado: 'Eliminación',
  activado: 'Activación',
  desactivado: 'Desactivación',
  permisos_actualizados: 'Permisos actualizados',
  inicio_sesion: 'Inicio de sesión',
  cierre_sesion: 'Cierre de sesión',
  cuenta_bloqueada: 'Cuenta bloqueada',
  contrasena_cambiada: 'Cambio de contraseña',
  contrasena_restablecida: 'Contraseña restablecida',
  correo_verificado: 'Correo verificado',
  precios_actualizados: 'Precios actualizados',
  precio_cambiado: 'Cambio de precio',
  plan_cambiado: 'Cambio de plan',
  vigencia_cambiada: 'Cambio de vigencia',
  vigencia_terminada: 'Fin de vigencia',
  finalizado: 'Finalización',
  reprogramado: 'Reprogramación',
  cancelado: 'Cancelación',
  ejecutado: 'Ejecución',
  seguimiento_renovacion: 'Seguimiento de renovación',
  renovado: 'Renovación',
  vencido: 'Vencimiento',
  prioridad_cambiada: 'Cambio de prioridad',
  fotos_agregadas: 'Fotos agregadas',
  foto_eliminada: 'Foto eliminada',
};

// ---------- Fase 2: planes, mantenimientos y contratos ----------

const formatoDinero = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

/** "$ 1.250.000" — los montos llegan de la API como texto con dos decimales. */
export function dinero(valor) {
  if (valor === null || valor === undefined || valor === '') return '—';
  return formatoDinero.format(Number(valor));
}

/** Nombres de las periodicidades de 1 a 6 meses (RF-011). */
export const NOMBRES_PERIODICIDAD = {
  1: 'Mensual',
  2: 'Bimestral',
  3: 'Trimestral',
  4: 'Cuatrimestral',
  5: 'Cada 5 meses',
  6: 'Semestral',
};

/** "Trimestral (3 meses)", "Mensual (1 mes)". */
export function periodicidad(meses) {
  if (!meses) return '—';
  const cantidad = Number(meses);
  const nombre = NOMBRES_PERIODICIDAD[cantidad];
  const duracion = cantidad === 1 ? '1 mes' : `${cantidad} meses`;
  return nombre && nombre !== `Cada ${duracion}` ? `${nombre} (${duracion})` : `Cada ${duracion}`;
}

/** Fecha local en formato AAAA-MM-DD (para los campos de fecha y la API). */
export function fechaIso(valor = new Date()) {
  const dia = valor instanceof Date ? valor : new Date(`${valor}T00:00:00`);
  const dosDigitos = (n) => String(n).padStart(2, '0');
  return `${dia.getFullYear()}-${dosDigitos(dia.getMonth() + 1)}-${dosDigitos(dia.getDate())}`;
}

/** Días que faltan desde hoy hasta una fecha AAAA-MM-DD (negativo si ya pasó). */
export function diasHasta(valor) {
  if (!valor) return null;
  const hoy = new Date(`${fechaIso()}T00:00:00`);
  return Math.round((new Date(`${valor}T00:00:00`) - hoy) / 86400000);
}

export const ESTADOS_ASIGNACION = {
  activa: { texto: 'Activa', tipo: 'exito' },
  finalizada: { texto: 'Finalizada', tipo: 'neutro' },
  cancelada: { texto: 'Cancelada', tipo: 'peligro' },
};

export const ESTADOS_MANTENIMIENTO = {
  programado: { texto: 'Programado', tipo: 'info' },
  reprogramado: { texto: 'Reprogramado', tipo: 'cian' },
  ejecutado: { texto: 'Ejecutado', tipo: 'exito' },
  cancelado: { texto: 'Cancelado', tipo: 'neutro' },
  atrasado: { texto: 'Atrasado', tipo: 'peligro' },
};

/** Un mantenimiento pendiente con fecha pasada se muestra como atrasado. */
export function estadoMantenimiento(mantenimiento) {
  const pendiente = mantenimiento.estado === 'programado' || mantenimiento.estado === 'reprogramado';
  if (pendiente && diasHasta(mantenimiento.fecha_programada) < 0) return ESTADOS_MANTENIMIENTO.atrasado;
  return ESTADOS_MANTENIMIENTO[mantenimiento.estado] || { texto: humanizar(mantenimiento.estado), tipo: 'neutro' };
}

export const ESTADOS_CONTRATO = {
  vigente: { texto: 'Vigente', tipo: 'exito' },
  vencido: { texto: 'Vencido', tipo: 'alerta' },
  renovado: { texto: 'Renovado', tipo: 'info' },
  cancelado: { texto: 'Cancelado', tipo: 'peligro' },
};

export const ESTADOS_RENOVACION = {
  pendiente: 'Pendiente de gestión',
  contactado: 'Cliente contactado',
  renovado: 'Renovado',
  no_renovado: 'No renovado',
};

// ---------- Fase 3: técnicos y órdenes de trabajo ----------

export const SERVICIOS_ORDEN = {
  preventivo: { texto: 'Preventivo', tipo: 'info' },
  correctivo: { texto: 'Correctivo', tipo: 'peligro' },
  inspeccion: { texto: 'Inspección', tipo: 'cian' },
};

export const PRIORIDADES_ORDEN = {
  baja: { texto: 'Baja', tipo: 'neutro' },
  media: { texto: 'Media', tipo: 'info' },
  alta: { texto: 'Alta', tipo: 'alerta' },
  critica: { texto: 'Crítica', tipo: 'peligro' },
};

export const ESTADOS_ORDEN = {
  programada: { texto: 'Programada', tipo: 'alerta' },
  asignada: { texto: 'Asignada', tipo: 'cian' },
  en_camino: { texto: 'En camino', tipo: 'info' },
  en_proceso: { texto: 'En proceso', tipo: 'info' },
  pendiente: { texto: 'Pendiente', tipo: 'alerta' },
  finalizada: { texto: 'Finalizada', tipo: 'exito' },
  cancelada: { texto: 'Cancelada', tipo: 'neutro' },
};

export const ACCIONES_ORDEN = {
  creada: 'Orden creada',
  tecnico_asignado: 'Técnico asignado',
  tecnico_retirado: 'Técnico retirado',
  estado_cambiado: 'Cambio de estado',
  prioridad_cambiada: 'Cambio de prioridad',
  reprogramada: 'Reprogramada',
  inicio_registrado: 'Inicio registrado',
  fin_registrado: 'Fin registrado',
  mantenimiento_ejecutado: 'Visita del plan ejecutada',
  informe_generado: 'Informe PDF emitido',
};

/** "1 h 45 min", "30 min". */
export function duracion(minutos) {
  if (minutos === null || minutos === undefined) return '—';
  const horas = Math.floor(minutos / 60);
  const resto = minutos % 60;
  if (!horas) return `${resto} min`;
  return resto ? `${horas} h ${resto} min` : `${horas} h`;
}

export const NOMBRES_ROL = {
  administrador: 'Administrador',
  coordinador: 'Coordinador',
  tecnico: 'Técnico',
  cliente: 'Cliente',
  contador: 'Contador',
  supervisor: 'Supervisor',
};

export function nombreRol(nombre) {
  return NOMBRES_ROL[nombre] || humanizar(nombre);
}

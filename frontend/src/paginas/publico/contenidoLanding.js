// Textos de la página de inicio pública (landing). Para cambiar lo que dice la página, edite aquí:
// la estructura y los estilos están en Landing.jsx y landing.css.

export const SEO = {
  titulo: 'SIGMA · Software de gestión y mantenimiento de ascensores',
  descripcion:
    'SIGMA organiza clientes, edificios y ascensores, programa los mantenimientos, guarda la documentación y da trazabilidad completa con códigos QR y una app para técnicos.',
};

/** Texto corto del pie de página: qué hace SIGMA. */
export const PIE = {
  resumen:
    'Programamos los mantenimientos, asignamos los técnicos y entregamos informes en PDF. Las empresas de ascensores, administraciones y conjuntos controlan sus equipos desde el panel web y la app móvil.',
};

export const CONTACTO = {
  correo: 'sigma3172409@gmail.com',
  // Número de WhatsApp con indicativo y sin espacios. Cámbielo por el real.
  whatsapp: '573000000000',
  mensajeWhatsapp: 'Hola, quiero una demostración de SIGMA.',
  redes: [
    { nombre: 'Instagram', url: '#' },
    { nombre: 'Facebook', url: '#' },
    { nombre: 'LinkedIn', url: '#' },
  ],
};

export const NAVEGACION = [
  { id: 'problema', texto: 'Solución' },
  { id: 'funciones', texto: 'Funciones' },
  { id: 'app', texto: 'App móvil' },
  { id: 'beneficios', texto: 'Beneficios' },
  { id: 'planes', texto: 'Planes' },
  { id: 'preguntas', texto: 'Preguntas' },
  { id: 'contacto', texto: 'Contacto' },
];

export const HERO = {
  etiqueta: 'Sistema Integral de Gestión y Mantenimiento de Ascensores',
  titulo: 'Cada ascensor, cada piso,',
  tituloResaltado: 'bajo control.',
  subtitulo:
    'SIGMA reúne en un solo lugar a sus clientes, edificios y ascensores: programa los mantenimientos, guarda la documentación y deja registro de cada visita, desde la web y desde la app de sus técnicos.',
  botonPrincipal: 'Solicitar demo',
  botonSecundario: 'Ver funciones',
  cifras: [
    { valor: '48', texto: 'ítems de checklist por visita' },
    { valor: '24/7', texto: 'reporte de emergencias' },
    { valor: '1 QR', texto: 'único por ascensor' },
  ],
};

export const PROBLEMAS = [
  {
    problema: 'Mantenimientos olvidados',
    detalle: 'Las visitas se programan en hojas de cálculo y alguien tiene que acordarse de cada fecha.',
    solucion: 'SIGMA programa las visitas en días hábiles según la frecuencia de cada ascensor y avisa antes de cada una.',
  },
  {
    problema: 'Documentación desordenada',
    detalle: 'Certificados, informes y fotos repartidos entre correos, carpetas y celulares.',
    solucion: 'Cada ascensor tiene su hoja de vida con informes en PDF, certificación anual, fotos y documentos.',
  },
  {
    problema: 'Sin trazabilidad',
    detalle: 'No se sabe quién hizo qué, cuándo llegó el técnico ni qué repuestos se usaron.',
    solucion: 'Cada orden registra técnicos, tiempos, checklist B · R · M, repuestos, firmas y su historial completo.',
  },
];

export const FUNCIONES = [
  { icono: 'clientes', titulo: 'Clientes por categoría', texto: 'Conjuntos, instituciones y residencias, cada uno con sus edificios y ascensores en un árbol que se despliega.' },
  { icono: 'edificios', titulo: 'Edificios y ascensores', texto: 'Ficha técnica, tipo de ascensor, puertas de piso, fotos y estado de cada equipo.' },
  { icono: 'historial', titulo: 'Historial de mantenimientos', texto: 'Órdenes preventivas, correctivas e inspecciones con su planilla, tiempos y firmas.' },
  { icono: 'documentos', titulo: 'Documentos', texto: 'Informes técnicos en PDF, certificación anual, contratos e indicadores descargables.' },
  { icono: 'qr', titulo: 'Código QR por ascensor', texto: 'Los usuarios ven el estado del ascensor; el técnico inicia el mantenimiento o reporta una anomalía.' },
  { icono: 'alertas', titulo: 'Alertas de mantenimiento', texto: 'Avisos de próximas visitas, contratos por vencer, emergencias y repuestos bajo el mínimo.' },
];

export const APP_MOVIL = {
  titulo: 'La app para sus técnicos en campo',
  texto: 'Sus técnicos llevan SIGMA en el bolsillo: reciben sus órdenes, escanean el QR del ascensor, llenan la planilla con fotos y recogen las firmas sin papel.',
  puntos: [
    'Órdenes del día y emergencias abiertas',
    'Escaneo del QR para iniciar el mantenimiento',
    'Checklist por secciones con fotos y observaciones',
    'Repuestos usados y firma de quien recibe',
  ],
  pantallas: [
    { imagen: '/landing/app-inicio.webp', texto: 'Inicio del técnico' },
    { imagen: '/landing/app-planilla.webp', texto: 'Planilla de mantenimiento' },
    { imagen: '/landing/app-orden-repuestos.webp', texto: 'Repuestos y firmas' },
  ],
  // Enlaces de las tiendas: déjelos en '#' mientras la app no esté publicada.
  tiendas: { googlePlay: '#', appStore: '#' },
  descargaDirecta: '/descargas/sigma.apk',
};

export const BENEFICIOS = [
  { titulo: 'Ahorro de tiempo', texto: 'La programación, los avisos y los informes se generan solos.' },
  { titulo: 'Prevención de fallas', texto: 'El checklist y el historial muestran a tiempo lo que está por fallar.' },
  { titulo: 'Información centralizada', texto: 'Clientes, contratos, órdenes, cartera e inventario en un mismo lugar.' },
  { titulo: 'Web y móvil', texto: 'La oficina trabaja en el panel web y los técnicos en la app.' },
];

export const AUDIENCIAS = [
  { titulo: 'Empresas de mantenimiento de ascensores', texto: 'Organice a sus técnicos, sus contratos y la cartera de todos sus clientes.' },
  { titulo: 'Administradores de conjuntos', texto: 'Consulte el estado de sus ascensores, apruebe cotizaciones y descargue los informes.' },
  { titulo: 'Instituciones', texto: 'Hospitales, universidades y edificios públicos con certificación al día.' },
  { titulo: 'Residencias', texto: 'Edificios residenciales con mantenimiento preventivo y atención de emergencias.' },
];

// Precios de ejemplo: cámbielos por los del plan real (o escriba 'Consultar').
export const PLANES = {
  nota: 'Un solo plan con todas las funciones: entre más tiempo alquila, menor es el precio por mes.',
  opciones: [
    { periodo: '1 mes', precio: 'Consultar', detalle: 'Ideal para empezar' },
    { periodo: '3 meses', precio: 'Consultar', detalle: 'El más elegido', destacado: true },
    { periodo: '6 meses', precio: 'Consultar', detalle: 'El mejor precio por mes' },
  ],
  incluye: ['Panel web y app para técnicos', 'Clientes, edificios y ascensores ilimitados', 'Informes en PDF y códigos QR', 'Soporte por correo'],
};

export const PREGUNTAS = [
  { pregunta: '¿Necesito instalar algo?', respuesta: 'No. El panel funciona en el navegador y los técnicos instalan la app en su Android.' },
  { pregunta: '¿Puedo cargar mis datos actuales?', respuesta: 'Sí. SIGMA importa clientes, edificios, ascensores, técnicos e inventario desde una plantilla de Excel con vista previa de errores.' },
  { pregunta: '¿Mis clientes pueden ver la información?', respuesta: 'Sí. Cada cliente tiene su portal para ver sus ascensores, órdenes, informes, cotizaciones y estado de cuenta.' },
  { pregunta: '¿Cómo funciona el código QR?', respuesta: 'Cada ascensor tiene un QR único. Cualquier persona ve su estado; un técnico con sesión inicia el mantenimiento o reporta una anomalía.' },
  { pregunta: '¿La información está segura?', respuesta: 'Cada rol ve solo lo que le corresponde, todo cambio queda en la auditoría y la base de datos se respalda todos los días.' },
];

// Recorrido por el producto: cada pestaña muestra una pantalla real del panel.
export const RECORRIDO = [
  { id: 'panel', titulo: 'Panel de operación', texto: 'Indicadores del día, emergencias abiertas, órdenes críticas y la cola de trabajo en una sola vista.', imagen: '/landing/panel.webp' },
  { id: 'clientes', titulo: 'Clientes', texto: 'Conjuntos, instituciones y residencias con sus edificios y ascensores en un árbol que se despliega.', imagen: '/landing/clientes-arbol.webp' },
  { id: 'calendario', titulo: 'Calendario', texto: 'Visitas por día, semana o mes; arrastre una visita para reagendarla y SIGMA avisa al cliente.', imagen: '/landing/calendario.webp' },
  { id: 'ascensor', titulo: 'Hoja de vida', texto: 'Ficha técnica, programa de mantenimiento, certificación anual, órdenes, historial y QR de cada ascensor.', imagen: '/landing/ascensor.webp' },
];

export const PASOS = [
  { titulo: 'Cargue su información', texto: 'Importe clientes, edificios, ascensores y técnicos desde Excel o regístrelos en el panel.' },
  { titulo: 'Programe y asigne', texto: 'SIGMA calcula las visitas en días hábiles y las asigna a técnicos según su horario y especialidad.' },
  { titulo: 'Ejecute y reporte', texto: 'El técnico escanea el QR, llena la planilla en la app y el cliente recibe el informe en PDF.' },
];

export const CTA = {
  titulo: '¿Listo para tener cada ascensor bajo control?',
  texto: 'Le mostramos SIGMA en una llamada de 30 minutos con sus propios datos.',
  boton: 'Solicitar demo',
};

// Insignias que flotan sobre la imagen principal.
export const INSIGNIAS_HERO = [
  { texto: 'Mantenimiento programado', detalle: 'ASC-101 · lunes 08:00' },
  { texto: 'Informe PDF enviado', detalle: 'OT-2026-00007 · firmado' },
];

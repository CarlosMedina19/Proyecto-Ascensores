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
    { valor: '45', texto: 'actividades en la planilla de cada visita' },
    { valor: '24/7', texto: 'reporte de emergencias' },
    { valor: '1 QR', texto: 'único por ascensor' },
  ],
};

// «ampliado»: lo que muestra la ventana al hacer clic en la tarjeta (texto, puntos y, si aplica, una pantalla real).
export const PROBLEMAS = [
  {
    problema: 'Mantenimientos olvidados',
    detalle: 'Las visitas se programan en hojas de cálculo y alguien tiene que acordarse de cada fecha.',
    solucion: 'SIGMA programa las visitas en días hábiles según la frecuencia de cada ascensor y avisa antes de cada una.',
    ampliado: {
      texto: 'Cada ascensor tiene su plan con la frecuencia pactada en el contrato (de 1 a 6 meses). SIGMA calcula las fechas en días hábiles, salta los festivos de Colombia y avisa antes de cada visita al coordinador, al técnico y al cliente.',
      puntos: ['Calendario por día, semana o mes: arrastre una visita para reagendarla', 'Avisos 15, 7 y 1 día antes, y el mismo día', 'Cumplimiento del plan: visitas ejecutadas frente a programadas', 'Las visitas atrasadas se resaltan en el panel de operación'],
      imagen: '/landing/calendario.webp',
    },
  },
  {
    problema: 'Documentación desordenada',
    detalle: 'Certificados, informes y fotos repartidos entre correos, carpetas y celulares.',
    solucion: 'Cada ascensor tiene su hoja de vida con informes en PDF, certificación anual, fotos y documentos.',
    ampliado: {
      texto: 'La hoja de vida de cada ascensor reúne su ficha técnica, las fotos por componente, las planillas firmadas, los informes en PDF, la certificación anual y el contrato vigente. Los archivos quedan en un almacenamiento aparte con copia de seguridad diaria.',
      puntos: ['Informe PDF de cada orden, con firmas y fotos', 'Certificación anual con alerta antes del vencimiento', 'Fotos de cada componente tomadas desde la app', 'Indicadores en PDF para el comité o la administración'],
      imagen: '/landing/ascensor.webp',
    },
  },
  {
    problema: 'Sin trazabilidad',
    detalle: 'No se sabe quién hizo qué, cuándo llegó el técnico ni qué repuestos se usaron.',
    solucion: 'Cada orden registra técnicos, tiempos, checklist B · R · M, repuestos, firmas y su historial completo.',
    ampliado: {
      texto: 'Cada orden de trabajo guarda quién la creó, qué técnicos la atendieron, a qué hora llegaron y terminaron, qué repuestos usaron y quién recibió el trabajo. La auditoría registra cada cambio del sistema con fecha, usuario y módulo.',
      puntos: ['Ubicación del técnico en el mapa mientras atiende la orden', 'Planilla con estado Bueno, Regular o Malo por actividad', 'Repuestos descontados del inventario al cerrar la orden', 'Firma del técnico y de quien recibe el trabajo'],
      imagen: '/landing/panel.webp',
    },
  },
];

export const FUNCIONES = [
  {
    icono: 'clientes',
    titulo: 'Clientes por categoría',
    texto: 'Conjuntos, instituciones y residencias, cada uno con sus edificios y ascensores en un árbol que se despliega.',
    ampliado: {
      texto: 'Organice a sus clientes como conjuntos, instituciones o residencias. Cada uno se despliega en un árbol con sus sitios, edificios y ascensores, y desde ahí llega a sus contactos, contratos y estado de cuenta.',
      puntos: ['Persona natural o jurídica, con NIT o cédula', 'Árbol cliente → conjunto → edificio → ascensor', 'Varios contactos por cliente', 'Portal para que cada cliente vea solo lo suyo'],
      imagen: '/landing/clientes-arbol.webp',
    },
  },
  {
    icono: 'edificios',
    titulo: 'Edificios y ascensores',
    texto: 'Ficha técnica, tipo de ascensor, puertas de piso, fotos y estado de cada equipo.',
    ampliado: {
      texto: 'Registre la ficha técnica completa de cada ascensor: marca, modelo, capacidad, velocidad, paradas, tracción, motor y puertas de cabina y de piso. Su estado (activo, en mantenimiento o inactivo) cambia con cada orden.',
      puntos: ['Pasajeros, carga o camillero', 'Puertas de piso con su estado individual', 'Galería de fotos por componente', 'Técnico habitual y plan asignado'],
      imagen: '/landing/ascensor.webp',
    },
  },
  {
    icono: 'historial',
    titulo: 'Historial de mantenimientos',
    texto: 'Órdenes preventivas, correctivas e inspecciones con su planilla, tiempos y firmas.',
    ampliado: {
      texto: 'Consulte todas las órdenes de un ascensor con su planilla, duración, técnicos, repuestos y firmas. Los indicadores calculan la disponibilidad, el tiempo medio entre fallas, el tiempo de respuesta a emergencias y el cumplimiento del plan.',
      puntos: ['Preventivos, correctivos e inspecciones', 'Planilla de 45 actividades en 6 secciones', 'Duración real frente a la estimada', 'Analítica con pronóstico y riesgo de falla'],
      imagen: '/landing/panel.webp',
    },
  },
  {
    icono: 'documentos',
    titulo: 'Documentos',
    texto: 'Informes técnicos en PDF, certificación anual, contratos e indicadores descargables.',
    ampliado: {
      texto: 'SIGMA genera los documentos que le piden sus clientes: el informe de cada orden, la planilla de asistencia técnica, el contrato y los indicadores del período, todos en PDF y listos para enviar.',
      puntos: ['Informe firmado al cerrar cada orden', 'Planilla de visita técnica en una página', 'Contrato con sus ascensores y condiciones', 'Exportación a Excel de los listados'],
    },
  },
  {
    icono: 'qr',
    titulo: 'Código QR por ascensor',
    texto: 'Los usuarios ven el estado del ascensor; el técnico inicia el mantenimiento o reporta una anomalía.',
    ampliado: {
      texto: 'Cada ascensor tiene un QR único para pegar en la cabina. Quien lo escanea ve si está en servicio y puede reportar una falla sin iniciar sesión; el técnico, con su sesión, inicia el mantenimiento desde ahí.',
      puntos: ['Estado del ascensor sin iniciar sesión', 'Reporte de emergencias desde el QR', 'Inicio del mantenimiento por el técnico', 'QR para descargar y pegar en la cabina'],
      imagen: '/landing/app-ascensor.webp',
    },
  },
  {
    icono: 'alertas',
    titulo: 'Alertas de mantenimiento',
    texto: 'Avisos de próximas visitas, contratos por vencer, emergencias y repuestos bajo el mínimo.',
    ampliado: {
      texto: 'SIGMA avisa en la campana del panel y por correo: próximas visitas, contratos por vencer, emergencias abiertas, repuestos bajo el mínimo y cuentas bloqueadas. Cada rol recibe solo lo que le corresponde.',
      puntos: ['Emergencias en tiempo real en el panel', 'Contratos por vencer con seguimiento de renovación', 'Repuestos bajo el stock mínimo', 'Alertas de seguridad de la cuenta'],
    },
  },
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
  {
    titulo: 'Ahorro de tiempo',
    texto: 'La programación, los avisos y los informes se generan solos.',
    ampliado: {
      texto: 'El calendario del contrato, los avisos, los informes en PDF y los indicadores se generan solos. El coordinador deja de armar agendas en hojas de cálculo y el técnico deja de llenar papel.',
      puntos: ['Calendario automático al asignar el plan', 'Informe PDF al cerrar la orden', 'Asistente que busca órdenes y descarga documentos', 'Importación inicial desde Excel'],
    },
  },
  {
    titulo: 'Prevención de fallas',
    texto: 'El checklist y el historial muestran a tiempo lo que está por fallar.',
    ampliado: {
      texto: 'La planilla Bueno · Regular · Malo marca a tiempo lo que está por fallar, y la analítica calcula el riesgo de falla de cada ascensor según su edad, sus fallas recientes y los días sin mantenimiento.',
      puntos: ['Una actividad crítica en estado Malo avisa de inmediato', 'Pronóstico de emergencias de los próximos meses', 'Ascensores ordenados por riesgo de falla', 'Cotización del arreglo desde el hallazgo'],
    },
  },
  {
    titulo: 'Información centralizada',
    texto: 'Clientes, contratos, órdenes, cartera e inventario en un mismo lugar.',
    ampliado: {
      texto: 'Clientes, contratos, órdenes, emergencias, cotizaciones, cartera e inventario viven en un mismo sistema, con un buscador general y permisos por rol.',
      puntos: ['Cartera con facturas, abonos y pagos por PSE', 'Inventario con movimientos y stock mínimo', 'Cotizaciones que el cliente aprueba en línea', 'Auditoría de todos los cambios'],
    },
  },
  {
    titulo: 'Web y móvil',
    texto: 'La oficina trabaja en el panel web y los técnicos en la app.',
    ampliado: {
      texto: 'La oficina trabaja en el panel web desde cualquier navegador y los técnicos en la app móvil, con sus órdenes del día, el escáner del QR, la planilla con fotos y la firma en pantalla.',
      puntos: ['Panel web con tema claro y oscuro', 'App para Android (descarga directa)', 'Versión para iPhone compilada en GitHub', 'Funciona también desde el navegador del celular'],
      imagen: '/landing/app-inicio.webp',
    },
  },
];

export const AUDIENCIAS = [
  {
    titulo: 'Empresas de mantenimiento de ascensores',
    texto: 'Organice a sus técnicos, sus contratos y la cartera de todos sus clientes.',
    ampliado: {
      texto: 'Programe a sus técnicos según su especialidad y horario, controle los contratos y su renovación, y lleve la cartera de todos sus clientes con los pagos en línea.',
      puntos: ['Roles: administrador, coordinador, supervisor, técnico, contador y cliente', 'Mapa de técnicos en vivo', 'Indicadores para cada cliente', 'Inventario de repuestos'],
    },
  },
  {
    titulo: 'Administradores de conjuntos',
    texto: 'Consulte el estado de sus ascensores, apruebe cotizaciones y descargue los informes.',
    ampliado: {
      texto: 'Desde su portal, la administración ve el estado de cada torre, las visitas programadas y los informes firmados; aprueba o rechaza las cotizaciones y paga sus facturas por PSE.',
      puntos: ['Estado de cuenta y pagos por PSE', 'Aprobación de cotizaciones en línea', 'Informes y planillas en PDF', 'Reporte de emergencias 24/7'],
    },
  },
  {
    titulo: 'Instituciones',
    texto: 'Hospitales, universidades y edificios públicos con certificación al día.',
    ampliado: {
      texto: 'En clínicas, universidades y edificios públicos un ascensor detenido afecta a cientos de personas. SIGMA prioriza las emergencias, mide el tiempo de respuesta y mantiene la certificación anual al día.',
      puntos: ['Ascensores camilleros y de carga', 'Tiempo objetivo para atrapamientos y fallas', 'Alerta de certificación por vencer', 'Disponibilidad medida mes a mes'],
    },
  },
  {
    titulo: 'Residencias',
    texto: 'Edificios residenciales con mantenimiento preventivo y atención de emergencias.',
    ampliado: {
      texto: 'Para edificios residenciales y propietarios particulares: mantenimiento preventivo en la fecha pactada, reporte de fallas con el QR de la cabina e informe de cada visita.',
      puntos: ['Plan cada 1 a 6 meses según el contrato', 'QR para que los residentes reporten una falla', 'Informe de cada visita', 'Atención de emergencias 24/7'],
    },
  },
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
  {
    titulo: 'Cargue su información',
    texto: 'Importe clientes, edificios, ascensores y técnicos desde Excel o regístrelos en el panel.',
    ampliado: {
      texto: 'Descargue la plantilla de Excel, llene sus clientes, edificios, ascensores, técnicos e inventario, y súbala. SIGMA muestra una vista previa con los errores fila por fila antes de guardar, para que nada quede a medias.',
      puntos: ['Plantillas de Excel descargables', 'Vista previa con los errores de cada fila', 'Ciudades y departamentos de Colombia normalizados', 'También puede registrar todo desde el panel'],
      imagen: '/landing/clientes-arbol.webp',
    },
  },
  {
    titulo: 'Programe y asigne',
    texto: 'SIGMA calcula las visitas en días hábiles y las asigna a técnicos según su horario y especialidad.',
    ampliado: {
      texto: 'Asigne un plan a cada ascensor y SIGMA genera el calendario de todo el contrato. Al crear la orden, muestra qué técnicos están disponibles según su especialidad, su horario y el trabajo que ya tienen ese día.',
      puntos: ['Frecuencias de 1 a 6 meses', 'Hora preferida por ascensor', 'Técnicos según especialidad y disponibilidad', 'Reprogramación con aviso al cliente'],
      imagen: '/landing/calendario.webp',
    },
  },
  {
    titulo: 'Ejecute y reporte',
    texto: 'El técnico escanea el QR, llena la planilla en la app y el cliente recibe el informe en PDF.',
    ampliado: {
      texto: 'El técnico recibe la orden en la app, escanea el QR del ascensor para iniciar, llena la planilla de 45 actividades con fotos y recoge la firma de quien recibe. Al terminar, el cliente recibe el informe en PDF.',
      puntos: ['Escaneo del QR para iniciar', 'Planilla por secciones con fotos', 'Firma en la pantalla del celular', 'Informe PDF para el cliente'],
      imagen: '/landing/app-planilla.webp',
    },
  },
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

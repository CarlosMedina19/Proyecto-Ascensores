import { api, descargarArchivo } from './cliente';

// RF-001 Autenticación
export const autenticacion = {
  iniciarSesion: (correo, password) =>
    api.crear('/iniciar-sesion', { correo, password, dispositivo: 'web' }),
  cerrarSesion: () => api.crear('/cerrar-sesion'),
  perfil: () => api.obtener('/perfil'),
  cambiarContrasena: (datos) => api.reemplazar('/perfil/contrasena', datos),
  recuperarContrasena: (correo) => api.crear('/recuperar-contrasena', { correo }),
  restablecerContrasena: (datos) => api.crear('/restablecer-contrasena', datos),
};

export const resumen = {
  obtener: () => api.obtener('/resumen'),
};

// RF-002 Roles y permisos
export const roles = {
  listar: () => api.obtener('/roles'),
  obtener: (id) => api.obtener(`/roles/${id}`),
  crear: (datos) => api.crear('/roles', datos),
  actualizar: (id, datos) => api.actualizar(`/roles/${id}`, datos),
  sincronizarPermisos: (id, permisos) => api.reemplazar(`/roles/${id}/permisos`, { permisos }),
  permisos: () => api.obtener('/permisos'),
};

// RF-003 Usuarios
export const usuarios = {
  listar: (filtros) => api.obtener('/usuarios', filtros),
  obtener: (id) => api.obtener(`/usuarios/${id}`),
  crear: (datos) => api.crear('/usuarios', datos),
  actualizar: (id, datos) => api.actualizar(`/usuarios/${id}`, datos),
  alternarEstado: (id) => api.actualizar(`/usuarios/${id}/alternar-estado`),
  eliminar: (id) => api.eliminar(`/usuarios/${id}`),
};

// RF-004 Auditoría
export const auditoria = {
  listar: (filtros) => api.obtener('/registros-auditoria', filtros),
  modulos: () => api.obtener('/registros-auditoria/modulos'),
};

// RF-005 Clientes
export const clientes = {
  listar: (filtros) => api.obtener('/clientes', filtros),
  obtener: (id) => api.obtener(`/clientes/${id}`),
  crear: (datos) => api.crear('/clientes', datos),
  actualizar: (id, datos) => api.actualizar(`/clientes/${id}`, datos),
  eliminar: (id) => api.eliminar(`/clientes/${id}`),
  alternarEstado: (id) => api.actualizar(`/clientes/${id}/alternar-estado`),
  agregarContacto: (id, datos) => api.crear(`/clientes/${id}/contactos`, datos),
  // Tipos de edificio que tiene el cliente, con sus edificios y ascensores.
  estructura: (id) => api.obtener(`/clientes/${id}/estructura`),
};

// RF-006 Edificios
export const edificios = {
  listar: (filtros) => api.obtener('/edificios', filtros),
  obtener: (id) => api.obtener(`/edificios/${id}`),
  crear: (datos) => api.crear('/edificios', datos),
  actualizar: (id, datos) => api.actualizar(`/edificios/${id}`, datos),
  eliminar: (id) => api.eliminar(`/edificios/${id}`),
  alternarEstado: (id) => api.actualizar(`/edificios/${id}/alternar-estado`),
};

// RF-007, RF-008 y RF-009 Equipos, ascensores e historial
export const equipos = {
  listar: (filtros) => api.obtener('/equipos', filtros),
  obtener: (id) => api.obtener(`/equipos/${id}`),
  crear: (datos) => api.crear('/equipos', datos),
  actualizar: (id, datos) => api.actualizar(`/equipos/${id}`, datos),
  eliminar: (id) => api.eliminar(`/equipos/${id}`),
  alternarEstado: (id) => api.actualizar(`/equipos/${id}/alternar-estado`),
  historial: (id) => api.obtener(`/equipos/${id}/historial`),
  agregarHistorial: (id, datos) => api.crear(`/equipos/${id}/historial`, datos),
};

// Notificaciones del usuario (avisos de mantenimiento y alertas de contratos)
export const notificaciones = {
  listar: (filtros) => api.obtener('/notificaciones', filtros),
  marcarLeida: (id) => api.actualizar(`/notificaciones/${id}/leer`),
  marcarTodas: () => api.actualizar('/notificaciones/leer-todas'),
};

// Configuración del sistema y periodicidades (RF-011)
export const configuracion = {
  obtener: () => api.obtener('/configuracion'),
  actualizar: (datos) => api.reemplazar('/configuracion', datos),
  periodicidades: () => api.obtener('/periodicidades'),
};

// RF-010, RF-012 y RF-015 Planes, precios por periodicidad e historial de precios
export const planes = {
  listar: (filtros) => api.obtener('/planes', filtros),
  obtener: (id) => api.obtener(`/planes/${id}`),
  crear: (datos) => api.crear('/planes', datos),
  actualizar: (id, datos) => api.actualizar(`/planes/${id}`, datos),
  eliminar: (id) => api.eliminar(`/planes/${id}`),
  alternarEstado: (id) => api.actualizar(`/planes/${id}/alternar-estado`),
  actualizarPrecios: (id, datos) => api.reemplazar(`/planes/${id}/precios`, datos),
  historialPrecios: (id, filtros) => api.obtener(`/planes/${id}/historial-precios`, filtros),
};

// RF-013 a RF-016 Asignación de planes a los ascensores
export const asignaciones = {
  listar: (filtros) => api.obtener('/asignaciones', filtros),
  obtener: (id) => api.obtener(`/asignaciones/${id}`),
  crear: (datos) => api.crear('/asignaciones', datos),
  cambiarPlan: (id, datos) => api.actualizar(`/asignaciones/${id}/plan`, datos),
  cambiarPrecio: (id, datos) => api.actualizar(`/asignaciones/${id}/precio`, datos),
  cambiarVigencia: (id, datos) => api.actualizar(`/asignaciones/${id}/vigencia`, datos),
  finalizar: (id, motivo) => api.actualizar(`/asignaciones/${id}/finalizar`, { motivo }),
};

// RF-017 y RF-018 Calendario de mantenimientos preventivos
export const mantenimientos = {
  listar: (filtros) => api.obtener('/mantenimientos', filtros),
  obtener: (id) => api.obtener(`/mantenimientos/${id}`),
  reprogramar: (id, datos) => api.actualizar(`/mantenimientos/${id}/reprogramar`, datos),
  cancelar: (id, motivo) => api.actualizar(`/mantenimientos/${id}/cancelar`, { motivo }),
  ejecutar: (id, datos) => api.actualizar(`/mantenimientos/${id}/ejecutar`, datos),
};

// RF-019 a RF-021 Contratos y renovaciones
export const contratos = {
  listar: (filtros) => api.obtener('/contratos', filtros),
  obtener: (id) => api.obtener(`/contratos/${id}`),
  crear: (datos) => api.crear('/contratos', datos),
  actualizar: (id, datos) => api.actualizar(`/contratos/${id}`, datos),
  registrarSeguimiento: (id, datos) => api.crear(`/contratos/${id}/seguimientos`, datos),
  renovar: (id, datos) => api.crear(`/contratos/${id}/renovar`, datos),
  cancelar: (id, motivo) => api.actualizar(`/contratos/${id}/cancelar`, { motivo }),
};

// Panel de operación y búsqueda global (Ctrl+K)
export const panel = {
  obtener: () => api.obtener('/panel'),
};

export const busqueda = {
  buscar: (texto) => api.obtener('/buscar', { q: texto }),
};

// Fotos del ascensor (máximo 3)
export const fotosEquipo = {
  listar: (equipoId) => api.obtener(`/equipos/${equipoId}/fotos`),
  subir: (equipoId, archivos) => {
    const formulario = new FormData();
    archivos.forEach((archivo) => formulario.append('fotos[]', archivo));
    return api.crear(`/equipos/${equipoId}/fotos`, formulario);
  },
  eliminar: (equipoId, fotoId) => api.eliminar(`/equipos/${equipoId}/fotos/${fotoId}`),
};

// Festivos de Colombia (para marcar el calendario)
export const festivos = {
  listar: (anio) => api.obtener('/festivos', { anio }),
};

// RF-022 Técnicos, especialidades y disponibilidad
export const tecnicos = {
  listar: (filtros) => api.obtener('/tecnicos', filtros),
  obtener: (usuarioId) => api.obtener(`/tecnicos/${usuarioId}`),
  crear: (datos) => api.crear('/tecnicos', datos),
  guardarPerfil: (usuarioId, datos) => api.reemplazar(`/tecnicos/${usuarioId}/perfil`, datos),
  cambiarEstado: (usuarioId, activo) => api.actualizar(`/tecnicos/${usuarioId}/estado`, { activo }),
  disponibilidad: (consulta) => api.obtener('/tecnicos/disponibilidad', consulta),
  especialidades: () => api.obtener('/especialidades'),
};

// RF-023 a RF-029 Agenda y órdenes de trabajo
export const ordenes = {
  listar: (filtros) => api.obtener('/ordenes', filtros),
  agenda: (filtros) => api.obtener('/ordenes/agenda', filtros),
  obtener: (id) => api.obtener(`/ordenes/${id}`),
  crear: (datos) => api.crear('/ordenes', datos),
  asignar: (id, tecnicosIds) => api.reemplazar(`/ordenes/${id}/tecnicos`, { tecnicos: tecnicosIds }),
  reprogramar: (id, datos) => api.actualizar(`/ordenes/${id}/reprogramar`, datos),
  cambiarPrioridad: (id, datos) => api.actualizar(`/ordenes/${id}/prioridad`, datos),
  cambiarEstado: (id, datos) => api.actualizar(`/ordenes/${id}/estado`, datos),
  cancelar: (id, motivo) => api.actualizar(`/ordenes/${id}/cancelar`, { motivo }),
};

// Fase 4 (RF-030 a RF-039): planilla, fotos, firmas, correctivo derivado e informe PDF
export const planilla = {
  catalogo: () => api.obtener('/checklist/items'),
  obtener: (ordenId) => api.obtener(`/ordenes/${ordenId}/planilla`),
  guardar: (ordenId, datos) => api.reemplazar(`/ordenes/${ordenId}/planilla`, datos),
  subirFoto: (ordenId, archivo, itemId, descripcion) => {
    const formulario = new FormData();
    formulario.append('foto', archivo);
    if (itemId) formulario.append('item_id', itemId);
    if (descripcion) formulario.append('descripcion', descripcion);
    return api.crear(`/ordenes/${ordenId}/planilla/fotos`, formulario);
  },
  eliminarFoto: (ordenId, fotoId) => api.eliminar(`/ordenes/${ordenId}/planilla/fotos/${fotoId}`),
  crearCorrectivo: (ordenId, resultadoId, datos = {}) => api.crear(`/ordenes/${ordenId}/planilla/resultados/${resultadoId}/correctivo`, datos),
  firmar: (ordenId, datos) => api.crear(`/ordenes/${ordenId}/firmas`, datos),
  informes: (ordenId) => api.obtener(`/ordenes/${ordenId}/informes`),
  nuevaVersion: (ordenId, motivo) => api.crear(`/ordenes/${ordenId}/informes`, { motivo }),
};

// Plantilla, exportación e importación en Excel (vista previa y confirmación)
export const excel = {
  plantilla: () => descargarArchivo('/excel/plantilla', 'SIGMA_Plantilla_Datos.xlsx'),
  // Exporta solo lo que muestra la tabla: se envían sus filtros (tipo de edificio, estado, búsqueda…).
  exportar: (modulo, filtros = {}) => {
    const consulta = new URLSearchParams(Object.entries(filtros).filter(([, v]) => v !== '' && v !== null && v !== undefined)).toString();
    return descargarArchivo(`/excel/exportar/${modulo}${consulta ? `?${consulta}` : ''}`, `SIGMA_${modulo}.xlsx`);
  },
  previsualizar: (archivo) => {
    const formulario = new FormData();
    formulario.append('archivo', archivo);
    return api.crear('/excel/importaciones', formulario);
  },
  confirmar: (id) => api.crear(`/excel/importaciones/${id}/confirmar`),
  errores: (id) => descargarArchivo(`/excel/importaciones/${id}/errores`, 'errores.xlsx'),
};

// Cartera mínima: facturas, pagos y saldo por antigüedad
// RF-040 a RF-043: inventario de repuestos y repuestos usados en las órdenes.
export const inventario = {
  listar: (filtros) => api.obtener('/repuestos', filtros),
  ver: (id) => api.obtener(`/repuestos/${id}`),
  crear: (datos) => api.crear('/repuestos', datos),
  actualizar: (id, datos) => api.reemplazar(`/repuestos/${id}`, datos),
  alternarEstado: (id) => api.actualizar(`/repuestos/${id}/alternar-estado`),
  eliminar: (id) => api.eliminar(`/repuestos/${id}`),
  movimientos: (id, filtros) => api.obtener(`/repuestos/${id}/movimientos`, filtros),
  registrarMovimiento: (id, datos) => api.crear(`/repuestos/${id}/movimientos`, datos),
  deOrden: (ordenId) => api.obtener(`/ordenes/${ordenId}/repuestos`),
  agregarAOrden: (ordenId, datos) => api.crear(`/ordenes/${ordenId}/repuestos`, datos),
  quitarDeOrden: (ordenId, registroId) => api.eliminar(`/ordenes/${ordenId}/repuestos/${registroId}`),
};

export const cartera = {
  resumen: () => api.obtener('/cartera/resumen'),
  facturas: (filtros) => api.obtener('/facturas', filtros),
  factura: (id) => api.obtener(`/facturas/${id}`),
  crearFactura: (datos) => api.crear('/facturas', datos),
  registrarPago: (datos) => api.crear('/pagos', datos),
};

// Servicio comercial: certificación anual, emergencias 24/7, cotizaciones, indicadores y documentos.
function conArchivo(datos) {
  const formulario = new FormData();
  Object.entries(datos).forEach(([clave, valor]) => {
    if (valor !== null && valor !== undefined && valor !== '') formulario.append(clave, valor);
  });
  return formulario;
}

export const servicio = {
  certificaciones: (filtros) => api.obtener('/certificaciones', filtros),
  certificacionesEquipo: (equipoId) => api.obtener(`/equipos/${equipoId}/certificaciones`),
  registrarCertificacion: (equipoId, datos) => api.crear(`/equipos/${equipoId}/certificaciones`, conArchivo(datos)),
  adjuntarCertificado: (id, archivo) => api.crear(`/certificaciones/${id}/archivo`, conArchivo({ archivo })),
  descargarCertificado: (c) => descargarArchivo(`/certificaciones/${c.id}/archivo`, c.archivo_nombre || 'certificado.pdf'),
  descargarDocumento: (documento) => descargarArchivo(documento.enlace, `${documento.titulo}.pdf`),
  qr: (equipoId) => api.obtener(`/equipos/${equipoId}/qr`),
  documentos: (equipoId) => api.obtener(`/equipos/${equipoId}/documentos`),
  emergencias: (filtros) => api.obtener('/emergencias', filtros),
  emergencia: (id) => api.obtener(`/emergencias/${id}`),
  reportarEmergencia: (datos) => api.crear('/emergencias', datos),
  cambiarEstadoEmergencia: (id, datos) => api.crear(`/emergencias/${id}/estado`, datos),
  ordenDesdeEmergencia: (id, datos = {}) => api.crear(`/emergencias/${id}/orden`, datos),
  cotizaciones: (filtros) => api.obtener('/cotizaciones', filtros),
  cotizacion: (id) => api.obtener(`/cotizaciones/${id}`),
  crearCotizacion: (datos) => api.crear('/cotizaciones', datos),
  responderCotizacion: (id, datos) => api.crear(`/cotizaciones/${id}/respuesta`, datos),
  ordenDesdeCotizacion: (id, datos = {}) => api.crear(`/cotizaciones/${id}/orden`, datos),
  facturarCotizacion: (id, datos) => api.crear(`/cotizaciones/${id}/factura`, datos),
  indicadores: (filtros) => api.obtener('/indicadores/servicio', filtros),
  indicadoresPdf: (filtros = {}) => {
    const consulta = new URLSearchParams(Object.entries(filtros).filter(([, v]) => v !== '' && v != null)).toString();
    return descargarArchivo(`/indicadores/servicio/pdf${consulta ? `?${consulta}` : ''}`, 'SIGMA_Indicadores.pdf');
  },
};

// Reporte de fallas por el QR de la cabina (sin sesión).
export const publico = {
  ascensor: (codigo) => api.obtener(`/publico/ascensores/${codigo}`),
  reportar: (codigo, datos) => api.crear(`/publico/ascensores/${codigo}/reportes`, datos),
};

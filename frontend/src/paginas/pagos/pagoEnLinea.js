import { cartera } from '../../api/servicios';

/** Estado de cada pago en línea, para las insignias del historial. */
export const ESTADOS_PAGO = {
  pendiente: { texto: 'Pendiente', tipo: 'alerta' },
  aprobado: { texto: 'Aprobado', tipo: 'exito' },
  rechazado: { texto: 'Rechazado', tipo: 'peligro' },
  anulado: { texto: 'Anulado', tipo: 'peligro' },
  error: { texto: 'Error', tipo: 'peligro' },
};

/**
 * Inicia el pago de una factura. La pasarela de prueba es una página del panel; el checkout de Wompi, un
 * sitio externo que al terminar devuelve a /pagos/resultado.
 */
export async function iniciarPago(factura, monto, navegar) {
  const respuesta = await cartera.pagarConPse(factura.id, monto);
  if (respuesta.datos.simulado) navegar(respuesta.datos.url);
  else window.location.assign(respuesta.datos.url);
}

# SIGMA · Pendientes y decisiones abiertas

Estado al cierre de la fase 6 (21/10/2026).

## Decisiones que le corresponden

| # | Decisión | Por qué importa | Propuesta |
| --- | --- | --- | --- |
| D1 | **Norma de la certificación anual fuera de Bogotá.** SIGMA registra la norma por certificación (por defecto NTC 5926-1). | En Bogotá aplica el Acuerdo 470 de 2011 (Decreto 663 de 2011, vigilancia del IDIGER, Resolución 092 de 2014 que adopta la NTC 5926-1). En otras ciudades depende del acuerdo municipal. | Confirmar con cada cliente la norma de su ciudad. No se citan artículos en el producto. |
| D2 | **Tiempos objetivo de llegada** (hoy 30 min con personas atrapadas y 60 min para otras fallas). | Son el compromiso comercial que mide el indicador de respuesta. | Ajustarlos en Configuración según el contrato; si varían por cliente, se puede pasar al contrato. |
| D3 | **Quién aprueba cotizaciones.** Hoy el cliente y el administrador (que puede registrar una aprobación telefónica). | El administrador ve los botones de aprobar en cualquier cotización. | Dejar así o quitar `cotizaciones.responder` al administrador en Roles y permisos. |
| D4 | **Dominio público del QR.** El enlace usa `FRONTEND_URL` del backend (hoy `http://localhost:5173`). | Los QR impresos con el dominio de pruebas dejan de servir. | Imprimir los QR solo cuando el dominio definitivo esté configurado. |
| D5 | **Significado de INS y EST en la planilla** (RF-037). | Sigue pendiente de la fase 4; en venta solo se muestra "Tipo de revisión". | Confirmar con el área técnica. |

## Pendientes técnicos

| # | Pendiente | Detalle |
| --- | --- | --- |
| P1 | App móvil: emergencias y QR | La app de técnicos aún no muestra las emergencias asignadas ni permite marcar llegada y solución; hoy se hace desde el panel web (también en el teléfono). |
| P2 | Cotización desde un hallazgo | Desde un ítem en R o M del checklist se crea el correctivo; falta el botón "Cotizar" que precargue la cotización. |
| P3 | Orden de trabajo desde una emergencia o una cotización aprobada | Hoy se crea la orden aparte; la relación `orden_id` ya existe en las tablas. |
| P4 | Indicadores por cliente en el portal | El cliente ya ve sus indicadores en Indicadores; falta exportarlos a PDF para el comité del edificio. |
| P5 | Facturación electrónica | La cartera es mínima (facturas y pagos manuales). La factura electrónica DIAN queda fuera de este alcance. |
| P6 | IoT y mantenimiento predictivo | Solo la sección "Próximamente"; requiere elegir el sensor o la pasarela del controlador. |
| P7 | Captura del velo del recorrido guiado | En el Edge sin interfaz que se usa para las capturas, el velo oscuro translúcido no aparece en la imagen; en el navegador sí se ve. Verificar en Chrome y Edge normales. |

## Cómo probar lo nuevo de la fase 6

1. Restaurar datos de demostración: `docker exec ascensores_app php artisan db:seed --class=DatosDemoSeeder`.
2. Panel → **Servicio**: Emergencias, Certificación anual, Cotizaciones, Indicadores y Próximamente.
3. Ficha de un ascensor (por ejemplo ASC-002): certificación anual, emergencias, documentos y código QR.
4. Abrir el enlace del QR en otra ventana privada (sin sesión) y reportar una falla.
5. Con la cuenta del cliente, abrir la cotización CT-AAAA-00001 y aprobarla o rechazarla.
6. Botón **?** del encabezado: recorrido guiado de 5 pasos (en modo demostración se abre solo la primera vez).

# API de SIGMA — Fase 4 (Checklist de ascensores e informe PDF)

Base: `http://localhost:8000/api/v1`. Mismo formato de respuestas, paginación y errores que la
[fase 1](FASE-1-API.md). Cubre RF-030 a RF-039: planilla digital del mantenimiento preventivo por
secciones del ascensor, estado Bueno/Regular/Malo, observaciones, fotos, firmas, orden correctiva
derivada e informe PDF versionado.

## Modelo de datos

| Tabla | Contenido |
| --- | --- |
| `checklist_items` | Catálogo de 48 ítems en 6 secciones: cuarto de máquinas (12), cabina (11), contrapeso (5), puertas de cabina y de piso (9), hueco y recorrido (7) y foso (4). 11 son **críticos** (seguridad). |
| `planillas` | Una por orden preventiva (`orden_id` único): recomendaciones y quién la completó. |
| `planilla_resultados` | Por ítem: resultado (`INS`, `EST`, `NA`), estado (`B`, `R`, `M`), observación y cuándo se avisó. |
| `planilla_fotos` | Hasta **20** fotos por planilla, opcionalmente asociadas a un ítem. |
| `firmas_orden` | Firma del `tecnico` y del `cliente` (quien recibe): nombre, documento, cargo e imagen PNG. Una de cada tipo por orden. |
| `informes_orden` | Versiones del informe PDF: número de versión, motivo, ruta, hash SHA-256 y quién lo generó. **No se editan ni se borran.** |
| `ordenes_trabajo` | Agrega `orden_origen_id` y `resultado_origen_id` para el correctivo derivado. |

Restricciones en PostgreSQL: valores válidos de resultado y estado; observación obligatoria en
R o M; un solo correctivo abierto por ítem de una planilla.

> El significado exacto de `INS` y `EST` está pendiente de confirmar con el cliente (RF-037); el
> sistema solo los registra.

## Reglas

| Requerimiento | Regla |
| --- | --- |
| RF-030 | Toda orden **preventiva** lleva planilla. Se diligencia con la orden `en_proceso` y solo la edita un técnico asignado. |
| RF-031 a RF-036 | Los ítems vienen del catálogo por sección; el avance se muestra por sección. |
| RF-037 | Cada ítem necesita resultado y estado; **R o M exigen observación**. Un ítem crítico en M avisa **de inmediato** a los coordinadores (una sola vez). Los demás M se avisan al finalizar. |
| RF-038 | Desde un ítem guardado en R o M se crea una orden **correctiva** ligada a la preventiva: siguiente día hábil 08:00, prioridad alta (M) o media (R). No se duplica mientras haya una abierta para ese ítem. |
| RF-025 | **Finalizar** exige la planilla completa (si es preventiva), la firma del técnico y la de quien recibe. El error dice qué falta. |
| RF-039 | Al finalizar se genera el informe PDF (versión 1) con datos de la orden, resumen B/R/M, ítems por sección, observaciones, recomendaciones, fotos y firmas. Se pueden generar versiones nuevas con motivo; las anteriores se conservan. |

La última revisión de cada ascensor (peor estado por sección) se expone en
`GET /equipos/{id}` como `ultima_revision` y en el panel de estado de ascensores.

## Endpoints

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/checklist/items` | Token | Catálogo activo, ordenado por sección. |
| GET | `/ordenes/{orden}/planilla` | Ver la orden | Planilla con resultados, fotos y `faltantes`. |
| PUT | `/ordenes/{orden}/planilla` | Técnico asignado | `resultados[]` (`item_id`, `resultado`, `estado`, `observacion`) y `recomendaciones`. Guarda solo lo enviado. |
| POST | `/ordenes/{orden}/planilla/fotos` | Técnico asignado | Multipart: `foto` (JPG, PNG o WEBP, máx. 5 MB), `item_id`, `descripcion`. |
| GET | `/ordenes/{orden}/planilla/fotos/{foto}/archivo` | Ver la orden | Imagen. |
| DELETE | `/ordenes/{orden}/planilla/fotos/{foto}` | Técnico asignado | Solo con la orden en proceso. |
| POST | `/ordenes/{orden}/planilla/resultados/{resultado}/correctivo` | `ordenes.crear` o técnico asignado | Opcional: `prioridad`, `fecha`, `hora`, `duracion_estimada`, `descripcion`, `tecnicos[]` (asignar técnicos exige `ordenes.asignar`). |
| POST | `/ordenes/{orden}/firmas` | Técnico asignado | `tipo` (`tecnico`/`cliente`), `nombre`, `documento`, `cargo`, `imagen` (PNG en base64, máx. 500 KB). En proceso o pendiente; reemplaza la anterior. |
| GET | `/ordenes/{orden}/firmas/{firma}/archivo` | Ver la orden | PNG. |
| GET | `/ordenes/{orden}/informes` | Ver la orden | Versiones del informe. |
| POST | `/ordenes/{orden}/informes` | Editar la orden | Nueva versión: `motivo`. Solo en órdenes finalizadas. |
| GET | `/ordenes/{orden}/informes/{informe}/pdf` | Ver la orden | Descarga del PDF. |

`GET /ordenes/{orden}` agrega `lleva_planilla`, `planilla` (registrados, total, B/R/M), `firmas`,
`informes`, `orden_origen` y `correctivos_derivados`. La trazabilidad registra `informe_generado`.

## Panel web y app móvil

- **Panel:** el detalle de la orden muestra la planilla por secciones (con avance y B/R/M), fotos,
  creación del correctivo, firmas en pantalla y las versiones del informe PDF. El detalle del ascensor
  y el panel muestran la última revisión por sección.
- **App:** desde el detalle de la orden, el técnico abre la planilla (pestañas por sección, INS/EST/N.A.,
  B/R/M, observación, correctivo, recomendaciones y fotos con cámara o galería) y las firmas del técnico y
  de quien recibe.

## Pruebas

`tests/Feature/PruebasFaseCuatroTest.php` cubre: planilla solo en proceso y por técnico asignado,
observación obligatoria en R/M, aviso inmediato de ítem crítico, fotos (límite y formato), firmas,
bloqueo de finalización con lo que falta, informe PDF y versiones, correctivo derivado sin duplicados
y la última revisión del ascensor.

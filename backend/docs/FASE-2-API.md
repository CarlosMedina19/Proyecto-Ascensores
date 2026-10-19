# API de SIGMA — Fase 2 (Planes, periodicidad, precios y contratos)

Base: `http://localhost:8000/api/v1`. Mismo formato de respuestas, paginación y errores que la
[fase 1](FASE-1-API.md). Cubre RF-010 a RF-021: catálogo de planes, periodicidades, precios por
periodicidad e historial de precios, asignación de planes a los ascensores, cálculo del próximo
mantenimiento, programación automática, contratos, renovaciones y avisos.

## Modelo de datos

| Tabla | Contenido |
| --- | --- |
| `periodicidades` | 1 a 6 meses (`meses`, `nombre`, `activo`). Configurable sin tocar el código. |
| `planes_mantenimiento` | Nombre único, descripción, `servicios_incluidos` (lista), `activo`. Borrado lógico. |
| `precios_plan` | Precio por visita de cada plan según la periodicidad (un precio por pareja plan–periodicidad). |
| `historial_precios` | Cada cambio de precio con el anterior, el nuevo, el motivo y el usuario. **No se modifica ni se borra.** |
| `asignaciones_plan` | Plan de un ascensor: periodicidad, precio pactado (copia del catálogo), IVA, vigencia, contrato, último y próximo mantenimiento. |
| `cambios_asignacion` | Cambios de plan, periodicidad, vigencia o estado, con el motivo. Inmutable. |
| `mantenimientos_programados` | Cada visita: fecha teórica, fecha programada (día hábil), precio, estado y motivo. |
| `contratos`, `contrato_equipos` | Contrato con cliente, vigencia, valor, servicios, condiciones y ascensores incluidos. |
| `seguimientos_renovacion` | Gestión de la renovación (contactado, renovado, no renovado). Inmutable. |
| `configuraciones` | Parámetros editables por el administrador. |
| `envios_notificacion` | Registro de cada aviso por usuario y canal (pendiente, enviado, fallido) para no repetirlo. |
| `notifications`, `jobs`, `failed_jobs` | Tablas estándar de Laravel para la bandeja de avisos y la cola. |

Restricciones en PostgreSQL: un ascensor tiene **un solo plan activo** (índice único parcial) y
una asignación no repite la misma fecha teórica entre sus visitas pendientes.

## Reglas de programación (RF-017 y RF-018)

- **Próxima fecha** = fecha base + periodicidad. La base es el último mantenimiento ejecutado o,
  si aún no hay ninguno, la fecha de inicio del plan.
- **Fin de mes:** 31 de enero + 1 mes = 28 (o 29) de febrero. Cada visita se cuenta desde la base
  (31-ene → 28-feb → **31-mar**), así el recorte de febrero no se arrastra.
- **Días hábiles:** si la fecha cae en domingo o festivo de Colombia (Ley 51 de 1983, con Semana
  Santa calculada desde el domingo de Pascua) pasa al siguiente día hábil. El sábado es hábil.
  Se guardan las dos fechas: `fecha_teorica` (la del plan) y `fecha_programada` (la real).
- **Vigencia:** no se programa después de la fecha final del plan ni de la del contrato asociado.
- **Ejecutar** una visita recalcula las siguientes desde la fecha real de ejecución; si se ejecuta
  una visita atrasada, las pendientes anteriores se cancelan porque quedan cubiertas.
- **Cambiar** plan, periodicidad o vigencia regenera las visitas pendientes. Las ejecutadas no
  cambian nunca (ni su fecha ni su precio).

## Periodicidades y configuración

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/periodicidades` | Token | Lista de 1 a 6 meses. |
| GET | `/configuracion` | Token | `[{ clave, valor, descripcion }]` |
| PUT | `/configuracion` | `configuracion.editar` (exclusivo del administrador) | Campos opcionales: `notificaciones_canales` (`database`, `mail`), `notificaciones_dias_aviso` (p. ej. `[15, 7, 1, 0]`), `contratos_dias_alerta_vencimiento` (30), `iva_porcentaje` (19). Cada cambio queda en auditoría. |

## Planes y precios (RF-010, RF-012 y RF-015)

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/planes` | `planes.ver` | Filtros `busqueda`, `activo`. Incluye `precios` y `asignaciones_activas_count`. |
| GET | `/planes/{plan}` | `planes.ver` | |
| POST | `/planes` | `planes.crear` | `nombre`, `descripcion`, `servicios_incluidos[]`, `activo`, `precios: [{ periodicidad_id, precio }]` (al menos uno, mayor que cero). |
| PATCH | `/planes/{plan}` | `planes.editar` | Sin `precios` (responde 422 si se envían). |
| PUT | `/planes/{plan}/precios` | `precios.editar` | `precios[]` y `motivo` obligatorio. Solo afecta las asignaciones nuevas. |
| GET | `/planes/{plan}/historial-precios` | `planes.ver` | Cambios del catálogo, del más reciente al más antiguo. |
| DELETE | `/planes/{plan}` | `planes.eliminar` | 422 si algún ascensor tiene el plan activo (desactívelo en su lugar). |

## Planes de los ascensores (RF-013 a RF-016)

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/asignaciones` | `asignaciones.ver` | Filtros `equipo_id`, `plan_id`, `contrato_id`, `cliente_id`, `estado`. El cliente solo ve las suyas. |
| GET | `/asignaciones/{asignacion}` | `asignaciones.ver` | Con `mantenimientos`, `cambios` e `historial_precios`. |
| POST | `/asignaciones` | `asignaciones.crear` | `equipo_id`, `plan_id`, `periodicidad_id`, `fecha_inicio`; opcionales `fecha_fin` (por defecto la del contrato o un año), `contrato_id`, `aplica_iva`, `porcentaje_iva`, `precio`. |
| PATCH | `/asignaciones/{asignacion}/plan` | `asignaciones.editar` | `plan_id` y/o `periodicidad_id`, `motivo`. Toma el precio del catálogo para la nueva combinación. |
| PATCH | `/asignaciones/{asignacion}/precio` | `precios.editar` | `precio`, `motivo`. Cambia el precio de las visitas pendientes. |
| PATCH | `/asignaciones/{asignacion}/vigencia` | `asignaciones.editar` | `fecha_fin`, `motivo`. |
| PATCH | `/asignaciones/{asignacion}/finalizar` | `asignaciones.editar` | `motivo`. Cancela las visitas pendientes. |

Validaciones al asignar: el ascensor no está inactivo, su cliente está activo, no tiene otro plan
activo, el plan y la periodicidad están activos y, si se indica contrato, está vigente e incluye
el ascensor. Un `precio` distinto al del catálogo solo lo fija quien tiene `precios.editar`
(contador o administrador). Asignar, cambiar y finalizar quedan en el historial del ascensor.

`GET /equipos/{equipo}` ahora incluye `asignacion_activa` (plan, periodicidad, contrato) y
`proximo_mantenimiento`. Un ascensor con plan activo no se puede dar de baja.

## Calendario de mantenimientos (RF-017 y RF-018)

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/mantenimientos` | `mantenimientos.ver` | `desde` y `hasta` obligatorios (máximo 100 días). Filtros `equipo_id`, `cliente_id`, `estado`. Por defecto excluye los cancelados. Sin paginar. |
| GET | `/mantenimientos/{mantenimiento}` | `mantenimientos.ver` | |
| PATCH | `/mantenimientos/{mantenimiento}/reprogramar` | `mantenimientos.gestionar` | `fecha` (día hábil, no pasada, dentro de la vigencia), `motivo`. Avisa al cliente y al coordinador. |
| PATCH | `/mantenimientos/{mantenimiento}/cancelar` | `mantenimientos.gestionar` | `motivo`. |
| PATCH | `/mantenimientos/{mantenimiento}/ejecutar` | `mantenimientos.gestionar` | `fecha_ejecucion` (no futura), `observacion`. En la fase 3 lo hará el cierre de la orden de trabajo. |

Estados: `programado`, `reprogramado`, `ejecutado`, `cancelado`. Los ejecutados y cancelados no
se pueden modificar (422).

## Contratos y renovaciones (RF-019 y RF-020)

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/contratos` | `contratos.ver` | Filtros `busqueda`, `cliente_id`, `estado`, `estado_renovacion`, `por_vencer` (días). El cliente solo ve los suyos. |
| GET | `/contratos/{contrato}` | `contratos.ver` | Con ascensores, planes, seguimientos y contrato anterior. `dias_para_vencer` en los vigentes. |
| POST | `/contratos` | `contratos.crear` | `cliente_id`, `fecha_inicio`, `fecha_fin`, `valor`, `equipos[]` (al menos uno), `servicios`, `condiciones`. Número automático `CT-AAAA-0001`. |
| PATCH | `/contratos/{contrato}` | `contratos.editar` | Solo vigentes. El cliente no se cambia. Si cambia la fecha final, se reprograman sus planes. |
| POST | `/contratos/{contrato}/seguimientos` | `contratos.renovacion` | `resultado` (`contactado` o `no_renovado`), `observacion`. |
| POST | `/contratos/{contrato}/renovar` | `contratos.renovacion` | `fecha_fin`; opcionales `fecha_inicio` (por defecto el día siguiente al fin), `valor`, `servicios`, `condiciones`, `observacion`. |
| PATCH | `/contratos/{contrato}/cancelar` | `contratos.editar` | `motivo`. Cancela las visitas pendientes de sus planes. |

Reglas: los ascensores deben ser del cliente y no estar en otro contrato vigente con fechas que
se crucen. Al renovar, el contrato anterior queda `renovado`, se crea uno nuevo con los mismos
ascensores y los planes vinculados se extienden hasta la nueva fecha final. Estados del contrato:
`vigente`, `vencido`, `renovado`, `cancelado`; de la renovación: `pendiente`, `contactado`,
`renovado`, `no_renovado`.

## Avisos y tareas programadas (RF-020 y RF-021)

| Método | Ruta | Acceso | Notas |
| --- | --- | --- | --- |
| GET | `/notificaciones` | Token | Del usuario autenticado. `solo_no_leidas=1`. Incluye `no_leidas`. |
| PATCH | `/notificaciones/{id}/leer` | Token | |
| PATCH | `/notificaciones/leer-todas` | Token | |

| Tarea | Horario | Qué hace |
| --- | --- | --- |
| `sigma:procesar-vigencias` | 00:15 | Finaliza los planes vencidos y marca `vencido` los contratos sin renovar (cancela sus visitas posteriores). |
| `sigma:enviar-avisos` | 07:00 | Avisa los mantenimientos que faltan 15, 7, 1 y 0 días (configurable) y alerta los contratos que vencen en 30 días. |
| `queue:prune-failed` | Domingos | Limpia los envíos fallidos de más de 30 días. |

- Los avisos de mantenimiento llegan al coordinador y a los usuarios del cliente dueño del
  ascensor; las alertas de contratos, al administrador, el coordinador y el contador.
- Cada aviso se registra en `envios_notificacion` por usuario, canal y fecha: **no se repite**
  aunque la tarea corra dos veces. Si una visita se reprograma, se vuelve a avisar con la nueva fecha.
- La alerta de vencimiento se envía una sola vez por contrato (y otra vez si cambia la fecha final).
- Los envíos se procesan en la cola (3 intentos); un envío fallido queda con el error registrado.

Con Docker, los servicios `queue` (`php artisan queue:work`) y `scheduler`
(`php artisan schedule:work`) se inician junto con la API. Sin Docker:

```sh
php artisan queue:work
php artisan schedule:work
```

## Hora de las visitas

Cada plan tiene una `hora_preferida` (por defecto 08:00, entre 06:00 y 18:00) que se envía al
asignar el plan y se copia a cada visita (`hora` en `/mantenimientos`). Al reprogramar se puede
enviar `hora` junto con la `fecha`. El calendario del panel web muestra las visitas por hora en
las vistas de día y semana (bloques de 90 minutos).

| Método | Ruta | Acceso | Notas |
| --- | --- | --- | --- |
| GET | `/festivos?anio=2026` | Token | Festivos de Colombia del año: `[{ fecha, nombre }]`. |

## Fotos del ascensor

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/equipos/{equipo}/fotos` | Ver el ascensor | También llegan en `GET /equipos/{equipo}` (`fotos`). |
| POST | `/equipos/{equipo}/fotos` | `equipos.crear` o `equipos.editar` | `multipart/form-data` con `fotos[]`. JPG, PNG o WEBP de máximo 5 MB; **máximo 3 fotos por ascensor** (422 si se supera). |
| GET | `/equipos/{equipo}/fotos/{foto}/archivo` | Ver el ascensor | Devuelve la imagen. Se guarda en el disco privado: el cliente solo ve las de sus ascensores. |
| DELETE | `/equipos/{equipo}/fotos/{foto}` | `equipos.crear` o `equipos.editar` | Borra el registro y el archivo. |

El panel web permite elegirlas al registrar el ascensor (se suben al guardarlo) y agregarlas o
eliminarlas al editarlo o desde su detalle. Subir y eliminar fotos queda en el historial y en la auditoría.

## Panel de operación y búsqueda global

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/panel` | `resumen.ver` | Indicadores (mantenimientos al día, ascensores activos, visitas atrasadas, contratos vigentes), programados frente a ejecutados de las últimas 9 semanas, alertas, cola de hoy, estado por ascensor (primero los que requieren atención), técnicos y próximo festivo. |
| GET | `/buscar?q=texto` | Token | Mínimo 2 caracteres. Ascensores, edificios, clientes y contratos según los permisos del rol; el cliente solo encuentra lo suyo. Hasta 5 resultados por grupo. |

*Mantenimientos al día* es el porcentaje de visitas con fecha vencida (últimos 90 días) que se
ejecutaron. La ocupación de los técnicos y las órdenes correctivas se calculan desde la fase 3
(órdenes de trabajo); la cartera, desde la fase 5.

## Permisos de la fase 2

| Permiso | administrador | coordinador | supervisor | técnico | contador | cliente |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| `planes.ver` | ✔ | ✔ | ✔ | | ✔ | |
| `planes.crear`, `planes.editar`, `planes.eliminar` | ✔ | ✔ | | | | |
| `precios.editar` | ✔ | | | | ✔ | |
| `asignaciones.ver` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ (los suyos) |
| `asignaciones.crear`, `asignaciones.editar` | ✔ | ✔ | | | | |
| `mantenimientos.ver` | ✔ | ✔ | ✔ | ✔ | ✔ | ✔ (los suyos) |
| `mantenimientos.gestionar` | ✔ | ✔ | | | | |
| `contratos.ver` | ✔ | ✔ | ✔ | | ✔ | ✔ (los suyos) |
| `contratos.crear`, `contratos.editar`, `contratos.renovacion` | ✔ | ✔ | | | ✔ | |
| `configuracion.editar` | ✔ (exclusivo) | | | | | |

## Datos de demostración

`DatosDemoSeeder` crea los planes Básico, Integral y Premium con precio para las seis
periodicidades, el contrato `CT-AAAA-0001` del primer cliente y un plan asignado a cada uno de
sus ascensores activos.

## Pruebas

- `tests/Unit/CalendarioProgramacionTest.php`: festivos de Colombia 2026, Semana Santa, días
  hábiles, fin de mes sin desbordamiento y próxima fecha para las seis periodicidades.
- `tests/Feature/PruebasFaseDosTest.php`: planes y precios con motivo y permisos, asignación con
  programación en días hábiles, un plan activo por ascensor, cambio de periodicidad conservando
  lo ejecutado, cambio de precio solo en pendientes, ejecución anticipada, reprogramación,
  calendario por cliente, contratos (traslapes, numeración, renovación), alertas sin repetición,
  vigencias y configuración exclusiva del administrador.

Las pruebas solo se ejecutan sobre una base `*_test`: `tests/TestCase.php` se detiene si la
conexión apunta a otra base (por ejemplo, si el contenedor define `DB_DATABASE`).

# API de SIGMA — Fase 3 (Técnicos, agenda y órdenes de trabajo)

Base: `http://localhost:8000/api/v1`. Mismo formato de respuestas, paginación y errores que la
[fase 1](FASE-1-API.md). Cubre RF-022 a RF-029: técnicos, agenda, asignación, órdenes de trabajo,
estados, prioridad, tiempos y trazabilidad.

## Modelo de datos

| Tabla | Contenido |
| --- | --- |
| `especialidades` | Catálogo configurable (mantenimiento preventivo, mecánica, electricidad y control, electrónica, puertas, hidráulica, inspección). |
| `tecnicos` | Perfil 1:1 del usuario de rol técnico: tipo y número de documento (**único**), teléfono, disponibilidad (`disponible` / `no_disponible` con motivo y fecha de regreso) y observaciones. El estado (activo/inactivo) es el de su cuenta. |
| `especialidad_tecnico` | Especialidades de cada técnico (**al menos una**). |
| `ordenes_trabajo` | Número `OT-AAAA-00001` único y consecutivo, servicio (`preventivo`, `correctivo`, `inspeccion`), cliente, edificio, ascensor, visita del plan (opcional), fecha, hora, duración estimada, prioridad, estado, descripción, observaciones, informe, motivo de cancelación, hora de inicio y de fin, y duración real. |
| `orden_tecnico` | Técnicos de cada orden (uno o varios), con quién y cuándo los asignó. |
| `trazabilidad_ordenes` | Cada cambio: usuario, fecha, acción, campo, valor anterior y nuevo. **No se edita ni se borra.** |

Restricciones en PostgreSQL: valores válidos de servicio, prioridad, estado y disponibilidad;
fin posterior al inicio; duración estimada de 15 a 720 minutos; una visita del plan tiene como
máximo una orden que no esté cancelada.

## Reglas

| Requerimiento | Regla |
| --- | --- |
| RF-022 | Solo los técnicos **activos, disponibles y con perfil completo** reciben órdenes. Documento único y al menos una especialidad. |
| RF-023 | El técnico solo ve sus órdenes; el cliente solo las de su empresa; el coordinador ve todas. Un técnico **no puede tener dos órdenes que se crucen** (fecha + hora + duración estimada). |
| RF-024 | Una orden puede tener varios técnicos y debe tener al menos uno para quedar `asignada`. Al asignar se notifica al técnico. |
| RF-025 | Toda orden requiere cliente, edificio, ascensor y servicio, coherentes entre sí. No se crea en fechas pasadas. |
| RF-026 | Flujo: `programada → asignada → en_camino → en_proceso → finalizada`; desde `en_proceso` puede pasar a `pendiente` (con motivo) y retomarse. Solo se cancela en `programada`, `asignada` o `pendiente`, con motivo. `finalizada` y `cancelada` son finales. Los estados de trabajo los avanza **un técnico asignado**. |
| RF-027 | Prioridad `baja`, `media` (por defecto), `alta` o `critica`. Solo el coordinador y el administrador la cambian. Una orden crítica avisa de inmediato a los coordinadores. |
| RF-028 | La hora de inicio se registra al pasar a `en_proceso`; la de fin, al finalizar; la duración se calcula sola (minutos). |
| RF-029 | Todo cambio queda en la trazabilidad de la orden. |

**Finalizar** exige el informe del trabajo realizado. El checklist completo por zonas y las firmas
(también exigidos por RF-025) llegan en la fase 4. Al finalizar, el trabajo queda en el historial del
ascensor y, si la orden es preventiva y viene de una visita del plan, **la visita queda ejecutada y
se recalcula la siguiente** (fase 2).

Una visita del plan que ya tiene una orden en curso no se reprograma, cancela ni ejecuta desde el
calendario: se gestiona desde la orden. Al regenerar la programación (cambio de periodicidad o de
vigencia), esas visitas se conservan.

## Técnicos (RF-022)

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/especialidades` | Token | Catálogo activo. |
| GET | `/tecnicos` | `tecnicos.ver` | Usuarios de rol técnico con su perfil (`perfil_completo`, `puede_recibir_ordenes`). Filtros `busqueda`, `disponibilidad`, `especialidad_id`, `activo`. |
| POST | `/tecnicos` | `tecnicos.crear` (exclusivo del administrador) | Crea la cuenta y el perfil: `nombre`, `correo`, `password`, `tipo_documento`, `numero_documento`, `telefono`, `especialidades[]`, `disponibilidad`. |
| GET | `/tecnicos/{usuario}` | `tecnicos.ver` o el propio técnico | Incluye `ordenes_activas`. |
| PUT | `/tecnicos/{usuario}/perfil` | `tecnicos.editar` | Crea o actualiza el perfil (sirve para completar el de un técnico creado desde Usuarios). `no_disponible` exige `motivo_no_disponible`. |
| PATCH | `/tecnicos/{usuario}/estado` | `tecnicos.editar` | `activo`. Desactivar cierra sus sesiones. |
| GET | `/tecnicos/disponibilidad` | `tecnicos.ver` | `fecha`, `hora`, `duracion`, `orden_id` (opcional). Cada técnico con `asignable`, `motivo` y las órdenes que se cruzan. |

## Órdenes de trabajo (RF-023 a RF-029)

| Método | Ruta | Permiso | Notas |
| --- | --- | --- | --- |
| GET | `/ordenes` | `ordenes.ver` | Filtros `busqueda`, `estado`, `activas=1`, `servicio`, `prioridad`, `tecnico_id`, `equipo_id`, `cliente_id`, `desde`, `hasta`. |
| GET | `/ordenes/agenda` | `ordenes.ver` | `desde`, `hasta` (máx. 100 días), `tecnico_id`. Sin paginar, por fecha y hora. |
| POST | `/ordenes` | `ordenes.crear` | `servicio`, `cliente_id`, `edificio_id`, `equipo_id`, `fecha`, `hora`, `duracion_estimada`, `prioridad`, `descripcion`, `observaciones`, `tecnicos[]` (requiere `ordenes.asignar`). Con `mantenimiento_id` se toman el ascensor, la fecha y la hora de la visita del plan. |
| GET | `/ordenes/{orden}` | Ver la orden | Incluye técnicos, trazabilidad y `permisos` del usuario (`editar`, `asignar`, `cancelar`, `ejecutar`). |
| PUT | `/ordenes/{orden}/tecnicos` | `ordenes.asignar` | `tecnicos[]` (al menos uno). Reemplaza la lista; avisa a los nuevos. |
| PATCH | `/ordenes/{orden}/reprogramar` | `ordenes.editar` | `fecha`, `hora`, `duracion_estimada`, `motivo`. Revisa cruces de los técnicos. |
| PATCH | `/ordenes/{orden}/prioridad` | `ordenes.editar` | `prioridad`, `motivo` opcional. |
| PATCH | `/ordenes/{orden}/estado` | `ordenes.ejecutar` + técnico asignado | `estado` (`en_camino`, `en_proceso`, `pendiente`, `finalizada`), `motivo` (pendiente), `informe` y `observaciones` (finalizada). |
| PATCH | `/ordenes/{orden}/cancelar` | `ordenes.cancelar` | `motivo`. |

Avisos (RF-024 y RF-027): `asignada` al técnico, `critica` a los coordinadores, `reprogramada` y
`cancelada` a los técnicos asignados. Llegan a la campana y al correo según la configuración.

## Permisos de la fase 3

| Permiso | administrador | coordinador | supervisor | técnico | contador | cliente |
| --- | :-: | :-: | :-: | :-: | :-: | :-: |
| `tecnicos.ver` | ✔ | ✔ | ✔ | | | |
| `tecnicos.crear` | ✔ (exclusivo) | | | | | |
| `tecnicos.editar` | ✔ | ✔ | | | | |
| `ordenes.ver` | ✔ | ✔ | ✔ | ✔ (las suyas) | ✔ | ✔ (las suyas) |
| `ordenes.crear`, `ordenes.editar`, `ordenes.asignar`, `ordenes.cancelar` | ✔ | ✔ | | | | |
| `ordenes.ejecutar` | ✔ | | | ✔ | | |

## Integración con el panel

- `GET /panel` ahora incluye técnicos en campo, correctivos abiertos, órdenes críticas en las
  alertas, la cola de hoy con órdenes y visitas sin orden, y la ocupación de cada técnico (horas de
  órdenes del día sobre una jornada de 8 horas).
- `GET /buscar` también encuentra órdenes por número o código del ascensor.
- `GET /mantenimientos` acepta `sin_orden=1` y devuelve `orden_activa` de cada visita.

## Datos de demostración

`DatosDemoSeeder` crea el catálogo de especialidades, el perfil del técnico demo y tres técnicos más
(`andres.rojas@gmail.com`, `diana.mora@gmail.com`, `felipe.gomez@gmail.com`, contraseña
`SIGMA_DEMO_PASSWORD`) y cuatro órdenes: un correctivo crítico en proceso, una inspección asignada, un
correctivo sin técnico y un preventivo creado desde la próxima visita del plan.

## Pruebas

`tests/Feature/PruebasFaseTresTest.php`: registro de técnicos (documento único, especialidades,
exclusivo del administrador), disponibilidad, número consecutivo y datos coherentes, prioridad crítica
con aviso, asignación con aviso, cruces de horario, visibilidad por técnico y cliente, flujo completo de
estados con tiempos y duración, cancelación, trazabilidad inmutable y la orden preventiva que ejecuta la
visita del plan.

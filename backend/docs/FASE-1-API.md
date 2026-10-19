# API de SIGMA — Fase 1 (Fundación y datos maestros)

Base: `http://localhost:8000/api/v1`. Solicitudes y respuestas en JSON y en español.
Cubre RF-001 a RF-009 del documento de requerimientos de SIGMA: autenticación, roles y
permisos, usuarios, auditoría, clientes, edificios, equipos, ascensores (con sus puertas de
cabina y de piso) e historial. No incluye puertas eléctricas independientes.

## Formato de las respuestas

| Caso | Forma |
| --- | --- |
| Un registro | `{ "datos": { ... } }` |
| Listado paginado | `{ "datos": [...], "enlaces": { "primera", "ultima", "anterior", "siguiente" }, "metadatos": { "pagina_actual", "ultima_pagina", "por_pagina", "desde", "hasta", "total" } }` |
| Error de validación (422) | `{ "mensaje": "Los datos enviados no son válidos.", "errores": { "campo": ["mensaje"] } }` |
| Otros errores | `{ "mensaje": "..." }` con 401, 403, 404, 405, 422 (regla de negocio), 423 (cuenta bloqueada) o 429 |

Paginación: `?pagina=2&por_pagina=20` (máximo 100 por página). Los listados aceptan
`busqueda` y los filtros indicados en cada recurso.

## Autenticación (RF-001)

| Método | Ruta | Acceso | Notas |
| --- | --- | --- | --- |
| POST | `/iniciar-sesion` | Público | `correo`, `password`, `dispositivo` (opcional). Devuelve `usuario`, `token_acceso`, `minutos_inactividad`. Máx. 5 intentos/minuto por correo e IP. |
| POST | `/recuperar-contrasena` | Público | `correo`. Siempre responde igual, exista o no la cuenta. |
| POST | `/restablecer-contrasena` | Público | `correo`, `token`, `password`, `password_confirmation`. Enlace de un solo uso, vence a los 60 min. |
| GET | `/verificar-correo/{usuario}/{hash}` | Enlace firmado | Llega por correo al crear el usuario; vence en 24 h. |
| POST | `/cerrar-sesion` | Token | Invalida el token actual. |
| GET | `/perfil` | Token | Usuario con `rol.permisos` y `cliente`. |
| PUT | `/perfil/contrasena` | Token | `contrasena_actual`, `password`, `password_confirmation`. Cierra las demás sesiones. |

Envíe el token en `Authorization: Bearer <token>`.

Reglas implementadas:

- **Inactividad:** un token sin uso durante `SIGMA_MINUTOS_INACTIVIDAD` (10) deja de ser válido (401).
  Los clientes web y móvil avisan un minuto antes y permiten mantener la sesión.
- **Bloqueo:** 5 intentos fallidos consecutivos bloquean la cuenta `SIGMA_MINUTOS_BLOQUEO` (15) minutos (423).
- **Usuario desactivado:** pierde el acceso de inmediato; sus tokens se eliminan.
- **Contraseñas:** mínimo 8 caracteres con letras y números; se guardan con hash.

## Roles y permisos (RF-002)

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/roles`, `/roles/{rol}` | `roles.ver` |
| POST | `/roles` | `roles.crear` |
| PATCH | `/roles/{rol}` | `roles.editar` |
| PUT | `/roles/{rol}/permisos` | `roles.permisos.editar` — `{ "permisos": ["clientes.ver", ...] }` reemplaza la lista |
| GET | `/permisos` | `permisos.ver` |

El rol `administrador` no se puede renombrar ni cambiar de permisos. Los permisos
`roles.crear`, `roles.editar`, `roles.permisos.editar`, `permisos.ver`, `usuarios.crear`,
`usuarios.editar` y `registros_auditoria.ver` son **exclusivos del administrador**
(`config/sigma.php`); asignarlos a otro rol responde 422.

Permisos iniciales por rol (`PermisoSeeder`):

| Rol | Permisos |
| --- | --- |
| administrador | Todos |
| coordinador | Resumen, ver roles y usuarios, y gestión completa de clientes, edificios, equipos e historial |
| supervisor | Solo consulta: resumen, clientes, edificios, equipos e historial |
| técnico | Ver edificios y equipos; ver y registrar eventos del historial |
| contador | Resumen y consulta de clientes, edificios y equipos |
| cliente | Consulta de **sus propios** clientes, edificios, equipos e historial |

## Usuarios (RF-003)

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/usuarios` | `usuarios.ver` — filtros `busqueda`, `rol_id`, `activo` |
| GET | `/usuarios/{usuario}` | `usuarios.ver` |
| POST | `/usuarios` | `usuarios.crear` — `nombre`, `correo`, `password`, `rol_id`, `cliente_id` (obligatorio si el rol es cliente), `activo` |
| PATCH | `/usuarios/{usuario}` | `usuarios.editar` |
| PATCH | `/usuarios/{usuario}/alternar-estado` | `usuarios.editar` |

No hay borrado: los usuarios se desactivan y conservan su historial. Nadie puede
desactivarse ni cambiar su propio rol. Al crear un usuario se le envía el correo de verificación.

## Auditoría (RF-004)

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/registros-auditoria` | `registros_auditoria.ver` — filtros `modulo`, `accion`, `usuario_id`, `fecha_desde`, `fecha_hasta` |
| GET | `/registros-auditoria/modulos` | `registros_auditoria.ver` |

Cada registro guarda usuario, acción, módulo, modelo e id afectado, cambios, IP y fecha
(`TIMESTAMPTZ`). Se registran altas, cambios, bajas, inicios y cierres de sesión, bloqueos
y cambios de contraseña. Los registros no se pueden modificar ni eliminar (el modelo lo impide).

## Clientes (RF-005)

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/clientes` | `clientes.ver` — filtros `busqueda`, `tipo`, `estado` |
| GET | `/clientes/{cliente}` | `clientes.ver` (incluye `contactos` y `edificios`) |
| POST | `/clientes` | `clientes.crear` |
| PATCH | `/clientes/{cliente}` | `clientes.editar` |
| DELETE | `/clientes/{cliente}` | `clientes.eliminar` — solo si no tiene edificios; si los tiene, se desactiva (`estado: false`) |
| POST | `/clientes/{cliente}/contactos` | `clientes.contactos.crear` — `nombre` y teléfono o correo |

`tipo` es `juridica` (exige `nit`) o `natural` (exige `tipo_documento` y `numero_documento`).
NIT y número de documento son únicos.

## Edificios (RF-006)

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/edificios` | `edificios.ver` — filtros `busqueda`, `cliente_id`, `ciudad`, `activo` |
| GET | `/edificios/{edificio}` | `edificios.ver` (incluye `cliente` y `equipos`) |
| POST | `/edificios` | `edificios.crear` — `cliente_id`, `nombre`, `direccion`, `ciudad`, `nombre_contacto` y teléfono o correo de contacto |
| PATCH | `/edificios/{edificio}` | `edificios.editar` (incluye `activo`) |
| DELETE | `/edificios/{edificio}` | `edificios.eliminar` — un edificio con equipos activos no se elimina, se desactiva |

No se registran equipos en un edificio inactivo.

## Equipos, ascensores e historial (RF-007, RF-008, RF-009)

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/equipos` | `equipos.ver` — filtros `busqueda`, `estado`, `edificio_id`, `cliente_id` |
| GET | `/equipos/{equipo}` | `equipos.ver` (incluye `edificio.cliente` y `ascensor`) |
| POST | `/equipos` | `equipos.crear` |
| PATCH | `/equipos/{equipo}` | `equipos.editar` |
| DELETE | `/equipos/{equipo}` | `equipos.eliminar` — baja lógica; queda el evento `baja_equipo` |
| GET | `/equipos/{equipo}/historial` | `equipos.historial.ver` |
| POST | `/equipos/{equipo}/historial` | `equipos.historial.crear` — `evento` (ej. `incidente`, `reparacion`, `mantenimiento`) y `descripcion` |

Ejemplo de creación:

```json
{
  "edificio_id": 3,
  "codigo": "ASC-010",
  "marca": "Otis",
  "modelo": "Gen2",
  "estado": "activo",
  "ascensor": {
    "capacidad_kg": 630,
    "velocidad_mpm": 60,
    "paradas": 7,
    "tipo_traccion": "Eléctrico de tracción",
    "tipo_puerta": "Automática apertura central",
    "puertas_cabina": 1,
    "puertas_piso": 7
  }
}
```

- El código es único. Capacidad, velocidad y paradas son obligatorias y mayores que cero.
- Las puertas de cabina y de piso se registran dentro del ascensor (no son equipos aparte).
- Estados: `activo`, `mantenimiento`, `inactivo`. Cada cambio de estado o de ficha técnica
  queda en el historial automáticamente, igual que el alta (`alta_equipo`).
- El historial no se modifica ni se elimina; las correcciones se registran como eventos nuevos.

## Resumen

`GET /resumen` (`resumen.ver`): totales de usuarios, clientes, edificios, equipos por estado
y los últimos eventos del historial.

## Modelo de datos

| Tabla | Relación |
| --- | --- |
| `usuarios` | pertenece a `roles` y, si es cliente, a `clientes` |
| `roles` ⟷ `permisos` | muchos a muchos por `rol_permisos` |
| `clientes` | tiene muchos `contactos_cliente` y `edificios` |
| `edificios` | pertenece a `clientes`, tiene muchos `equipos` |
| `equipos` | pertenece a `edificios`, tiene un `ascensores` y muchos `historial_equipos` |
| `registros_auditoria` | pertenece a `usuarios` |

Convenciones PostgreSQL aplicadas en la migración `2026_10_16_000000_reforzar_fase_uno_sigma`:
UUID públicos (`identificador_uuid`), `TIMESTAMPTZ` en auditoría e historial, restricciones
`UNIQUE` y `CHECK`, índices en todas las llaves foráneas y en `estado`/`created_at`, y
borrado lógico (`deleted_at`) en clientes, edificios y equipos. La conexión usa la zona
horaria `America/Bogota` (`DB_TIMEZONE`), igual que la aplicación.

## Administrador inicial y datos de demostración

`php artisan db:seed` crea los roles, los permisos y el administrador definido en
`ADMIN_EMAIL` / `ADMIN_PASSWORD` (mínimo 12 caracteres). Para desarrollo,
`php artisan db:seed --class=DatosDemoSeeder` crea clientes, edificios, ascensores y un
usuario por rol (`coordinador@gmail.com`, `tecnico@gmail.com`, `supervisor@gmail.com`,
`contador@gmail.com`, `cliente@gmail.com`) con la contraseña de `SIGMA_DEMO_PASSWORD`.
Nunca se ejecuta en producción.

## Pendiente

- Documentación OpenAPI/Swagger (RNF-11): este documento describe la API mientras se genera la especificación.

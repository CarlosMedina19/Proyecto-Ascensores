# API — fase 1

Base local: `http://localhost:8000/api/v1`. Solicitudes y respuestas usan JSON.
La fase cubre autenticación, RBAC, usuarios, auditoría, clientes/contactos,
edificios, equipos tipo ascensor e historial. No incluye puertas eléctricas,
planes, contratos, órdenes ni inventario.

## Autenticación

| Método | Ruta | Acceso |
| --- | --- | --- |
| POST | `/login` | Público; máximo 5 intentos por minuto y correo/IP |
| POST | `/logout` | Token Sanctum |
| GET | `/me` | Token Sanctum |
| GET | `/dashboard` | `dashboard.view` |

Envía `email` y `password` a `/login`; la respuesta incluye `token` y `user`.
En rutas protegidas envía el encabezado HTTP `Authorization` con el esquema
`Bearer` y el token recibido. Los usuarios inactivos no pueden iniciar sesión;
las solicitudes sin permiso responden 403.

## Roles, usuarios y auditoría

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET | `/roles`, `/roles/{role}` | `roles.view` |
| GET | `/users`, `/users/{user}` | `users.view` |
| POST | `/users` | `users.create` |
| PUT/PATCH | `/users/{user}` | `users.update` |
| PATCH | `/users/{user}/toggle-status` | `users.update` |
| GET | `/audit-logs` | `audit-logs.view` |

Los listados admiten paginación y filtros; para usuarios se admiten `search`,
`role_id`, `is_active`. La desactivación reemplaza el borrado permanente de
usuarios. La auditoría admite `action`, `model`, `user_id` y limita la página a
100 registros.

## Clientes y edificios

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET/POST | `/clients` | `clients.view` / `clients.create` |
| GET/PUT/PATCH/DELETE | `/clients/{client}` | `clients.view`, `clients.update`, `clients.delete` |
| POST | `/clients/{client}/contacts` | `clients.contacts.create` |
| GET/POST | `/buildings` | `buildings.view` / `buildings.create` |
| GET/PUT/PATCH/DELETE | `/buildings/{building}` | `buildings.view`, `buildings.update`, `buildings.delete` |

La eliminación de clientes y edificios es lógica. Clientes admiten filtros
`search`, `status`, `type`; edificios admiten `client_id`, `city`, `search`.
Contactos requieren `name`; edificios requieren `client_id`, `name`, `address`.

## Equipos, ascensores e historial

| Método | Ruta | Permiso |
| --- | --- | --- |
| GET/POST | `/equipment` | `equipment.view` / `equipment.create` |
| GET/PUT/PATCH/DELETE | `/equipment/{equipment}` | `equipment.view`, `equipment.update`, `equipment.delete` |
| GET/POST | `/equipment/{equipment}/history` | `equipment.history.view` / `equipment.history.create` |

El tipo admitido es `elevator`. El registro crea automáticamente el evento
`alta_equipo`; los cambios de estado y las bajas también quedan en el historial.
Los filtros son `building_id`, `type`, `status`, `search`.

## Permisos sembrados

Capacidades: `dashboard.view`, `roles.view`, `users.view`, `users.create`,
`users.update`, `audit-logs.view`, `clients.view`, `clients.create`,
`clients.update`, `clients.delete`, `clients.contacts.create`,
`buildings.view`, `buildings.create`, `buildings.update`, `buildings.delete`,
`equipment.view`, `equipment.create`, `equipment.update`, `equipment.delete`,
`equipment.history.view`, `equipment.history.create`.

El seeder asigna todos al rol `admin`; `coordinador` recibe permisos operativos
y `supervisor` acceso de lectura. Roles distintos comienzan sin permisos de
fase 1. La siembra es idempotente y conserva permisos adicionales.

## Relaciones

- `Role` tiene muchos `Permission` por `role_permissions` y muchos `User`.
- `Client` tiene muchos `ClientContact` y `Building`.
- `Building` pertenece a un `Client` y tiene muchos `Equipment`.
- `Equipment` pertenece a un `Building`, tiene un `Elevator` y muchos
  `EquipmentHistory`.
- El historial y la auditoría guardan el usuario actor cuando está disponible.

## Convención de base de datos

Los campos JSON de API conservan los nombres ingleses documentados arriba. Las
columnas persistidas de negocio en fase 1 son:

| Tabla | Columnas |
| --- | --- |
| `usuarios` | `id`, `nombre`, `correo`, `correo_verificado_en`, `password`, `remember_token`, `rol_id`, `activo`, `created_at`, `updated_at` |
| `roles` | `id`, `nombre`, `descripcion`, `created_at`, `updated_at` |
| `permisos` | `id`, `nombre`, `descripcion`, `created_at`, `updated_at` |
| `rol_permisos` | `id`, `rol_id`, `permiso_id`, `created_at`, `updated_at` |
| `registros_auditoria` | `id`, `usuario_id`, `accion`, `modelo`, `modelo_id`, `cambios`, `created_at` |
| `puertas_electricas` | `id`, `equipo_id`, `marca`, `modelo`, `tipo_puerta`, `fecha_instalacion`, `tipo_apertura`, `tipo_acceso`, `numero_serie`, `especificaciones_tecnicas`, `created_at`, `updated_at` |
| `clientes` | `id`, `identificador_uuid`, `tipo`, `nombre`, `tipo_documento`, `numero_documento`, `nit`, `direccion`, `telefono`, `correo`, `regimen_tributario`, `actividad_economica`, `estado`, `observaciones`, `created_at`, `updated_at`, `deleted_at` |
| `contactos_cliente` | `id`, `cliente_id`, `nombre`, `cargo`, `telefono`, `correo`, `created_at`, `updated_at` |
| `edificios` | `id`, `identificador_uuid`, `cliente_id`, `nombre`, `direccion`, `ciudad`, `departamento`, `codigo_postal`, `pisos`, `observaciones`, `nombre_contacto`, `telefono_contacto`, `correo_contacto`, `created_at`, `updated_at`, `deleted_at` |
| `equipos` | `id`, `identificador_uuid`, `edificio_id`, `codigo`, `tipo`, `marca`, `modelo`, `numero_serie`, `ubicacion`, `estado`, `fecha_instalacion`, `observaciones`, `created_at`, `updated_at`, `deleted_at` |
| `ascensores` | `id`, `equipo_id`, `marca`, `modelo`, `capacidad_kg`, `paradas`, `velocidad_mpm`, `tipo_traccion`, `motor`, `controlador`, `tipo_puerta`, `especificaciones_tecnicas`, `fecha_instalacion`, `created_at`, `updated_at` |
| `historial_equipos` | `id`, `equipo_id`, `usuario_id`, `evento`, `descripcion`, `created_at` |

Se conservan los nombres técnicos que requiere Laravel/Sanctum (`id`,
`password`, `remember_token`, timestamps y `deleted_at`); la fecha de
verificación se almacena como `correo_verificado_en`.
Las llaves foráneas de dominio usan `rol_id`, `permiso_id`, `cliente_id`,
`edificio_id`, `equipo_id` y `usuario_id`. Los campos de `puertas_electricas`
se traducen para mantener consistente el esquema, pero la funcionalidad de
puertas eléctricas queda fuera de la fase 1.

Se conservan sin renombrar las tablas técnicas Laravel/Sanctum: `migrations`,
`cache`, `cache_locks`, `sessions`, `personal_access_tokens`, `jobs`,
`failed_jobs`, `job_batches` y `password_reset_tokens`. `identificador_uuid` se
expone como `uuid` en JSON. Los nombres de secuencias, índices y restricciones
de las tablas de dominio también usan los nuevos nombres españoles.

Las migraciones `2026_10_08_000000_translate_phase_one_columns_to_spanish` y
`2026_10_09_020000_rename_phase_one_tables_to_spanish` renombraron columnas y
tablas en sitio y conservaron los datos. Antes de aplicarlas a una base
existente, genera un respaldo; luego ejecuta `php artisan migrate`.

## Administrador inicial

Configura `ADMIN_EMAIL` válido y `ADMIN_PASSWORD` de al menos 12 caracteres en
el entorno antes de ejecutar `php artisan db:seed`. No se usa contraseña
predeterminada en el repositorio.

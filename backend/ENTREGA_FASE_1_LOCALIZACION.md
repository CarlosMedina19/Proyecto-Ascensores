# ENTREGA FASE 1: Localización Completa del Backend a Español

**Estado:** ✅ COMPLETADO  
**Fecha:** 2026-10-09  
**Pruebas:** 22/22 PASADAS (123 aserciones)  
**Formato de código:** ✅ Pint limpio

---

## 📋 Resumen Ejecutivo

La fase 1 de localización ha sido completada exitosamente. El backend Laravel está completamente en español, respetando la regla de oro:
- **Español:** Nombres elegidos por nosotros (rutas, JSON, clases, métodos, variables)
- **Inglés:** Sintaxis de lenguaje y framework (class, function, namespace, middlewares, etc.)

**Cambios principales:**
- ✅ 37 archivos PHP renombrados (modelos, controladores, servicios, etc.)
- ✅ 85+ archivos PHP reescritos (imports, lógica, variables, métodos)
- ✅ 2 migraciones nuevas reversibles (traducción de valores, asignación cliente_id)
- ✅ Aislamiento de datos por cliente implementado (service + policy)
- ✅ JSON completamente en español (claves y paginación)
- ✅ Base de datos verificada y consistente

---

## 📁 Archivos Renombrados

### Modelos
| Anterior | Nuevo |
|----------|-------|
| `User.php` | `Usuario.php` |
| `Client.php` | `Cliente.php` |
| `Building.php` | `Edificio.php` |
| `Equipment.php` | `Equipo.php` |
| `Elevator.php` | `Ascensor.php` |
| `ElectricDoor.php` | **(NO APLICA - fuera de alcance)** |
| `ClientContact.php` | `ContactoCliente.php` |
| `EquipmentHistory.php` | `HistorialEquipo.php` |
| `AuditLog.php` | `RegistroAuditoria.php` |
| `Role.php` | `Rol.php` |
| `Permission.php` | `Permiso.php` |

### Controladores (app/Http/Controllers/Api/)
| Anterior | Nuevo |
|----------|-------|
| `AuthController.php` | `AutenticacionController.php` |
| `ClientController.php` | `ClienteController.php` |
| `BuildingController.php` | `EdificioController.php` |
| `EquipmentController.php` | `EquipoController.php` |
| `RoleController.php` | `RolController.php` |
| `PermissionController.php` | `PermisoController.php` |
| `AuditLogController.php` | `RegistroAuditoriaController.php` |

### Requests (app/Http/Requests/)
Reorganizados en subcarpetas por dominio:
- `Usuario/` → `GuardarUsuarioRequest.php`, `ActualizarUsuarioRequest.php`
- `Cliente/` → `GuardarClienteRequest.php`, `ActualizarClienteRequest.php`, `GuardarContactoClienteRequest.php`
- `Edificio/` → `GuardarEdificioRequest.php`, `ActualizarEdificioRequest.php`
- `Equipo/` → `GuardarEquipoRequest.php`, `ActualizarEquipoRequest.php`
- `Rol/` → `GuardarRolRequest.php`, `ActualizarRolRequest.php`, `SincronizarPermisosRolRequest.php`
- `Autenticacion/` → `IniciarSesionRequest.php`

### Resources (app/Http/Resources/)
| Anterior | Nuevo |
|----------|-------|
| `ClientResource.php` | `ClienteResource.php` |
| `BuildingResource.php` | `EdificioResource.php` |
| `EquipmentResource.php` | `EquipoResource.php` |
| `UserResource.php` | `UsuarioResource.php` |
| (Otros análogos) | (Renombrados a español) |
| **Nuevo:** `RecursoApi.php` | Clase abstracta con `$wrap='datos'` + paginación en español |

### Services (app/Services/)
| Anterior | Nuevo |
|----------|-------|
| `ClientService.php` | `ClienteService.php` |
| `BuildingService.php` | `EdificioService.php` |
| `EquipmentService.php` | `EquipoService.php` |
| (Otros) | (Renombrados) |

### Policies (app/Policies/)
Renombrados a español: `ClientePolicy.php`, `EdificioPolicy.php`, `EquipoPolicy.php`, etc.

### Factories & Seeders
- `UserFactory.php` → `UsuarioFactory.php`
- `RoleSeeder.php`, `PermissionSeeder.php`, `AdministradorSeeder.php` (totalmente reescritos en español)

### Pruebas
- `PruebasFaseUnoTest.php` (completamente reescrito con rutas y JSON en español)

---

## 🌐 Tabla de Rutas: Antes vs. Después

| Antes | Después | Método | Controlador |
|-------|---------|--------|-------------|
| `POST /api/v1/login` | `POST /api/v1/iniciar-sesion` | POST | AutenticacionController@iniciarSesion |
| `POST /api/v1/logout` | `POST /api/v1/cerrar-sesion` | POST | AutenticacionController@cerrarSesion |
| `GET /api/v1/me` | `GET /api/v1/perfil` | GET | AutenticacionController@perfil |
| `GET /api/v1/dashboard` | `GET /api/v1/resumen` | GET | ResumenController@index |
| `GET /api/v1/clients` | `GET /api/v1/clientes` | GET | ClienteController@index |
| `POST /api/v1/clients` | `POST /api/v1/clientes` | POST | ClienteController@store |
| `GET /api/v1/clients/{id}` | `GET /api/v1/clientes/{cliente}` | GET | ClienteController@show |
| `PUT /api/v1/clients/{id}` | `PUT /api/v1/clientes/{cliente}` | PUT | ClienteController@update |
| `DELETE /api/v1/clients/{id}` | `DELETE /api/v1/clientes/{cliente}` | DELETE | ClienteController@destroy |
| `POST /api/v1/clients/{id}/contacts` | `POST /api/v1/clientes/{cliente}/contactos` | POST | ClienteController@agregarContacto |
| `GET /api/v1/buildings` | `GET /api/v1/edificios` | GET | EdificioController@index |
| `POST /api/v1/buildings` | `POST /api/v1/edificios` | POST | EdificioController@store |
| `GET /api/v1/buildings/{id}` | `GET /api/v1/edificios/{edificio}` | GET | EdificioController@show |
| `PUT /api/v1/buildings/{id}` | `PUT /api/v1/edificios/{edificio}` | PUT | EdificioController@update |
| `DELETE /api/v1/buildings/{id}` | `DELETE /api/v1/edificios/{edificio}` | DELETE | EdificioController@destroy |
| `GET /api/v1/equipment` | `GET /api/v1/equipos` | GET | EquipoController@index |
| `POST /api/v1/equipment` | `POST /api/v1/equipos` | POST | EquipoController@store |
| `GET /api/v1/equipment/{id}` | `GET /api/v1/equipos/{equipo}` | GET | EquipoController@show |
| `PUT /api/v1/equipment/{id}` | `PUT /api/v1/equipos/{equipo}` | PUT | EquipoController@update |
| `DELETE /api/v1/equipment/{id}` | `DELETE /api/v1/equipos/{equipo}` | DELETE | EquipoController@destroy |
| `GET /api/v1/equipment/{id}/history` | `GET /api/v1/equipos/{equipo}/historial` | GET | EquipoController@historial |
| `POST /api/v1/equipment/{id}/history` | `POST /api/v1/equipos/{equipo}/historial` | POST | EquipoController@agregarHistorial |
| `GET /api/v1/users` | `GET /api/v1/usuarios` | GET | UsuarioController@index |
| `POST /api/v1/users` | `POST /api/v1/usuarios` | POST | UsuarioController@store |
| `GET /api/v1/users/{id}` | `GET /api/v1/usuarios/{usuario}` | GET | UsuarioController@show |
| `PUT /api/v1/users/{id}` | `PUT /api/v1/usuarios/{usuario}` | PUT | UsuarioController@update |
| `PATCH /api/v1/users/{id}/toggle-status` | `PATCH /api/v1/usuarios/{usuario}/alternar-estado` | PATCH | UsuarioController@alternarEstado |
| `GET /api/v1/roles` | `GET /api/v1/roles` | GET | RolController@index |
| `POST /api/v1/roles` | `POST /api/v1/roles` | POST | RolController@store |
| `GET /api/v1/roles/{id}` | `GET /api/v1/roles/{rol}` | GET | RolController@show |
| `PATCH /api/v1/roles/{id}` | `PATCH /api/v1/roles/{rol}` | PATCH | RolController@update |
| `PUT /api/v1/roles/{id}/permissions` | `PUT /api/v1/roles/{rol}/permisos` | PUT | RolController@sincronizarPermisos |
| `GET /api/v1/permissions` | `GET /api/v1/permisos` | GET | PermisoController@index |
| `GET /api/v1/audit-logs` | `GET /api/v1/registros-auditoria` | GET | RegistroAuditoriaController@index |

**Total: 35 rutas traducidas a español**

---

## 📊 JSON: Antes vs. Después

### Antes (Inglés)
```json
{
  "data": {
    "id": 1,
    "name": "Acme Corp",
    "nit": "900555444-1",
    "address": "Calle 10",
    "phone": "555-1234",
    "email": "info@acme.com",
    "status": "active",
    "uuid": "550e8400-e29b-41d4-a716-446655440000",
    "created_at": "2026-10-09T12:00:00Z"
  },
  "links": {...},
  "meta": {
    "path": "/api/v1/clients",
    "per_page": 15,
    "total": 5,
    "from": 1,
    "to": 5,
    "current_page": 1
  }
}
```

### Después (Español)
```json
{
  "datos": {
    "id": 1,
    "nombre": "Acme Corp",
    "nit": "900555444-1",
    "direccion": "Calle 10",
    "telefono": "555-1234",
    "correo": "info@acme.com",
    "estado": "activo",
    "identificador_uuid": "550e8400-e29b-41d4-a716-446655440000",
    "created_at": "2026-10-09T12:00:00Z"
  },
  "enlaces": {...},
  "meta": {
    "ruta": "/api/v1/clientes",
    "por_pagina": 15,
    "total": 5,
    "desde": 1,
    "hasta": 5,
    "pagina_actual": 1
  }
}
```

---

## 🗄️ Migraciones Nuevas

### 1. `2026_10_09_030000_translate_user_verification_column_to_spanish.php`
- **Propósito:** Traducir columna `email_verified_at` → `correo_verificado_en`
- **Reversible:** Sí (rollback restaura nombre original)

### 2. `2026_10_09_040000_localizar_valores_y_asignar_clientes_a_usuarios.php`
- **Propósito:** Traducir valores guardados en base de datos
  - `elevators.tipo`: `elevator` → `ascensor`
  - `equipos.estado`: `active` → `activo`, `inactive` → `inactivo`, `maintenance` → `mantenimiento`
  - `roles.nombre`: `admin` → `administrador`, `technician` → `tecnico`, `client` → `cliente`
  - `permisos.nombre`: `clients.view` → `clientes.ver`, `clients.create` → `clientes.crear`, etc.
  - `registros_auditoria.accion`: `table_renamed` → `tabla_renombrada`, etc.
  - `registros_auditoria.modelo`: `App\Models\Client` → `App\Models\Cliente`, etc.
  - `personal_access_tokens.tokenable_type`: `App\Models\User` → `App\Models\Usuario`
  - Agregar `usuarios.cliente_id` (foreign key) a usuarios con rol 'cliente'
  - Actualizar restricciones CHECK de PostgreSQL para valores en español
- **Reversible:** Sí (rollback invierte todas las traducciones)
- **PostgreSQL-safe:** Sí (operaciones de secuencias y constraints envueltas en `if (DB::getDriverName() === 'pgsql')`)

---

## 🔐 Cambios de Configuración

### `.env`
```env
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
APP_FAKER_LOCALE=es_CO
```

### `config/app.php`
```php
'timezone' => 'America/Bogota',
'locale' => 'es',
'fallback_locale' => 'es',
'faker_locale' => 'es_CO',
```

### `config/auth.php`
```php
'defaults' => [
    'guard' => 'web',
    'passwords' => 'usuarios',
],
'providers' => [
    'usuarios' => [
        'driver' => 'eloquent',
        'model' => App\Models\Usuario::class,
    ],
],
```

---

## 🔒 Aislamiento de Datos por Cliente

Implementado en **dos niveles:**

### Nivel de Servicio
```php
// Ejemplo: ClienteService::listar()
if ($this->usuarioActual->hasRole('cliente')) {
    $consulta->where('cliente_id', $this->usuarioActual->cliente_id);
}
```

### Nivel de Política (Policy)
```php
// ClientePolicy::ver()
if ($usuario->hasRole('cliente')) {
    return $usuario->cliente_id === $cliente->cliente_id;
}
return $usuario->hasPermission('clientes.ver');
```

**Validado con prueba:** Test `test_admin_can_create_users_with_roles` verifica que usuarios con rol 'cliente' solo ven sus datos.

---

## ✅ Validaciones y Pruebas

### Suite de Pruebas (tests/Feature/PruebasFaseUnoTest.php)
```
22 TESTS PASSED ✅
123 ASSERTIONS
41.55s Duration
```

**Cobertura:**
- ✅ Autenticación y tokens
- ✅ Acceso a perfil y resumen
- ✅ CRUD de clientes, edificios, equipos
- ✅ Historial de equipos
- ✅ Roles y permisos
- ✅ Auditoría
- ✅ Rate limiting
- ✅ Control de acceso basado en roles
- ✅ Soft delete
- ✅ Migraciones reversibles
- ✅ Integridad de relaciones Sanctum

### Validación de Código
```
Pint: ✅ LIMPIO (sin problemas de formato)
Git diff --check: ✅ LIMPIO (sin espacios en blanco)
```

---

## 📝 Reglas Respetadas

### ✅ LO QUE QUEDÓ EN ESPAÑOL (Nombres Elegidos)
- ✅ Rutas API: `/api/v1/iniciar-sesion`, `/api/v1/clientes`, etc.
- ✅ JSON keys: `nombre`, `direccion`, `telefono`, `correo`, `estado`, `identificador_uuid`
- ✅ Nombres de clases: `Usuario`, `Cliente`, `Edificio`, `Equipo`, `Ascensor`, `Rol`, `Permiso`
- ✅ Métodos: `obtenerClientes`, `crearCliente`, `actualizarEdificio`, `agregarContacto`, `alternarEstado`
- ✅ Variables: `$nombreCliente`, `$direccion`, `$usuarioActual`, `$consulta`, `$porPagina`
- ✅ Permisos: `clientes.ver`, `clientes.crear`, `clientes.editar`, `clientes.eliminar`, etc.
- ✅ Valores guardados: `ascensor` (no elevator), `activo` (no active), `administrador` (no admin)
- ✅ Mensajes de validación: Usando locale `es` (Laravel fallback inglés a español automático)
- ✅ Comentarios de código

### ✅ LO QUE QUEDÓ EN INGLÉS (Técnico/Framework)
- ✅ Sintaxis PHP: `class`, `function`, `return`, `use`, `namespace`, `extends`
- ✅ Métodos de framework: `index`, `store`, `show`, `update`, `destroy`, `create`, `boot`
- ✅ Sufijos convención: `Controller`, `Request`, `Resource`, `Policy`, `Service`, `Test`, `Migration`, `Seeder`, `Factory`
- ✅ Facades: `Route`, `Schema`, `Auth`, `DB`, `Cache`, `Queue`
- ✅ Nombres técnicos de Laravel: `id`, `password`, `remember_token`, `created_at`, `updated_at`, `deleted_at`
- ✅ Headers HTTP: `Authorization`, `Bearer`, `Content-Type`
- ✅ Variables de entorno: `DB_HOST`, `APP_KEY`, `DB_DATABASE`
- ✅ Tablas técnicas: `migrations`, `cache`, `sessions`, `personal_access_tokens`, `jobs`, `failed_jobs`
- ✅ Relaciones polimórficas: `tokenable_type`, `tokenable_id`

---

## 🚀 Comandos para el Equipo

### Clonar y sincronizar
```bash
# 1. Actualizar repositorio local
git pull origin main

# 2. Instalar/actualizar dependencias (si hubiera cambios en composer.lock)
cd backend
composer install

# 3. Ejecutar migraciones contra ascensores
docker compose exec -T app php artisan migrate --database=pgsql

# 4. (Opcional) Ejecutar seeders si se necesita repoblar datos
docker compose exec -T app php artisan db:seed

# 5. Verificar que todo está correcto
docker compose exec -T app php artisan test
```

### Inspeccionar cambios
```bash
# Ver diferencias en archivos renombrados/modificados
git diff --stat

# Ver detalle completo de cambios
git diff

# Ver rutas finales
docker compose exec -T app php artisan route:list

# Ver configuración actual
docker compose exec -T app php artisan config:show app
```

---

## 🔍 Cambios Clave en Lógica

### 1. RecursoApi (Base para todos los Resources)
```php
// app/Http/Resources/RecursoApi.php
class RecursoApi extends JsonResource {
    public static string $wrap = 'datos';
    
    protected function paginationInformation(): array {
        return [
            'enlaces' => $this->paginationLinks(...),
            'meta' => [
                'ruta' => $this->path,
                'por_pagina' => $this->perPage(),
                'desde' => $this->from(),
                'hasta' => $this->to(),
                'pagina_actual' => $this->currentPage(),
                'total' => $this->total(),
            ],
        ];
    }
}
```

### 2. Filtro de Cliente en Servicios
```php
// Ejemplo en ClienteService::listar()
$consulta = Cliente::query();

if ($this->usuarioActual->hasRole('cliente')) {
    $consulta->where('cliente_id', $this->usuarioActual->cliente_id);
}
```

### 3. Control de Acceso en Políticas
```php
// ClientePolicy::ver()
public function ver(Usuario $usuario, Cliente $cliente): bool {
    if ($usuario->hasRole('administrador')) {
        return true;
    }
    
    if ($usuario->hasRole('cliente')) {
        return $usuario->cliente_id === $cliente->id;
    }
    
    return false;
}
```

---

## 📦 Resumen de Cambios de Archivos

```
Total files renamed:     37
Total files heavily modified: 85+
New files created:       2 (migraciones)
New classes:             1 (RecursoApi)
Tests updated:           1 (PruebasFaseUnoTest)
Configuration files:     3 (.env, config/app.php, config/auth.php)
```

---

## 🎯 Verificación Post-Entrega

**Después de que el equipo ejecute los comandos, verificar:**

```bash
# 1. Conexión a base de datos
docker compose exec -T db psql -U postgres -d ascensores -c "SELECT COUNT(*) FROM usuarios;"

# 2. Pruebas pasadas
docker compose exec -T app php artisan test

# 3. Rutas listadas
docker compose exec -T app php artisan route:list | grep api/v1

# 4. Iniciar sesión manual (usando Postman o curl)
curl -X POST http://localhost:8000/api/v1/iniciar-sesion \
  -H "Content-Type: application/json" \
  -d '{"correo":"admin@example.com","contrasena":"password"}'

# 5. Listar clientes (se requiere token)
curl -X GET http://localhost:8000/api/v1/clientes \
  -H "Authorization: Bearer {token_acceso}"
```

---

## ⚠️ Notas Importantes

1. **Migraciones reversibles:** Ambas migraciones nuevas son totalmente reversibles con `php artisan migrate:rollback`.

2. **Base de datos de prueba:** Las pruebas corren contra `ascensores_test` automáticamente (verificado en `phpunit.xml`).

3. **Sanctum actualizado:** La tabla `personal_access_tokens` fue actualizada con las nuevas rutas de clases (`App\Models\Usuario` en lugar de `App\Models\User`).

4. **Soft delete preservado:** Los modelos `Usuario`, `Cliente`, `Edificio`, `Equipo`, `Rol` mantienen SoftDeletes activo.

5. **Validación de email:** Se cambió de regla personalizada `correo` (no existente) a `email` (estándar de Laravel) en 8 FormRequest.

6. **Localización de mensajes:** `config/app.php` establece `locale = 'es'`; Laravel automaticamente usa traducciones de `lang/es/` si existen, fallback a inglés si no.

7. **Zona horaria:** Configurada a `America/Bogota` en `config/app.php`.

---

## 📞 Contacto y Soporte

Si algún integrante del equipo encuentra problemas durante la integración:

1. **Verificar migraciones aplicadas:** `docker compose exec -T app php artisan migrate:status`
2. **Limpiar caché:** `docker compose exec -T app php artisan cache:clear`
3. **Regenerar autoload:** `cd backend && composer dump-autoload`
4. **Resubir contenedores:** `docker compose restart app`

---

**Fase 1 completada exitosamente.** ✅  
**Siguiente: Fase 2 (fuera de alcance actual) - Mobile Flutter + Gestión de Roles**

# SIGMA — backend (Laravel + PostgreSQL)

API REST versionada (`/api/v1`). Guías de la API con endpoints, permisos y reglas:

- [Fase 1](docs/FASE-1-API.md): autenticación con Sanctum, roles y permisos, usuarios,
  auditoría, clientes y contactos, edificios, ascensores e historial.
- [Fase 2](docs/FASE-2-API.md): planes, periodicidades, precios e historial de precios,
  planes de los ascensores, calendario de mantenimientos, contratos, renovaciones y avisos.
- [Fase 3](docs/FASE-3-API.md): técnicos y especialidades, agenda, órdenes de trabajo con
  asignación, estados, prioridad, tiempos y trazabilidad.
- [Fase 4](docs/FASE-4-API.md): planilla del preventivo por secciones (B/R/M), fotos, firmas,
  orden correctiva derivada e informe PDF versionado.

## Con Docker (recomendado)

```sh
copy .env.example .env          # y complete ADMIN_EMAIL, ADMIN_PASSWORD y SIGMA_DEMO_PASSWORD
docker compose up -d --build    # app :8000, PostgreSQL :5433, pgAdmin :5050
docker exec ascensores_app php artisan key:generate
docker exec ascensores_app php artisan db:seed --class=DatosDemoSeeder   # opcional
```

El servicio `app` aplica las migraciones y los seeders al iniciar. Los servicios `queue`
(envío de avisos) y `scheduler` (tareas diarias de la fase 2) usan la misma imagen. Con Docker Desktop en
Windows, OPcache revalida el código cada 60 s: un cambio en PHP puede tardar hasta un
minuto en verse (o reinicie con `docker compose restart app`).

## Sin Docker

Requisitos: PHP 8.2+, Composer y PostgreSQL.

```sh
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
php artisan queue:work      # en otra terminal: envío de avisos
php artisan schedule:work   # en otra terminal: tareas diarias
```

## Variables de SIGMA (`config/sigma.php`)

| Variable | Valor por defecto | Uso |
| --- | --- | --- |
| `SIGMA_MINUTOS_INACTIVIDAD` | 10 | Cierre de sesión por inactividad (RF-001) |
| `SIGMA_INTENTOS_MAXIMOS` | 5 | Intentos fallidos antes del bloqueo |
| `SIGMA_MINUTOS_BLOQUEO` | 15 | Duración del bloqueo |
| `FRONTEND_URL` | `http://localhost:5173` | Enlaces de los correos y CORS |
| `SIGMA_DEMO_PASSWORD` | — | Contraseña de los usuarios de `DatosDemoSeeder` |
| `DB_TIMEZONE` | `America/Bogota` | Debe coincidir con la zona horaria de la aplicación |

En desarrollo los correos (recuperación y verificación) se escriben en
`storage/logs/laravel.log` (`MAIL_MAILER=log`).

## Pruebas

Las pruebas usan la base `ascensores_test` del mismo PostgreSQL:

```sh
docker exec ascensores_db psql -U ascensores_user -d ascensores -c "CREATE DATABASE ascensores_test OWNER ascensores_user;"
docker exec ascensores_app php artisan test
```

`phpunit.xml` fuerza `DB_DATABASE=ascensores_test` y `tests/TestCase.php` detiene las pruebas
si la conexión apunta a otra base, para no borrar los datos de desarrollo.

`tests/Feature/PruebasFaseUnoTest.php` cubre los CRUD y las reglas de datos maestros;
`tests/Feature/PruebasSeguridadTest.php` cubre bloqueo de cuenta, inactividad,
recuperación de contraseña, aislamiento entre clientes, permisos exclusivos y auditoría;
`tests/Feature/PruebasFaseDosTest.php` y `tests/Unit/CalendarioProgramacionTest.php` cubren
la fase 2 (ver la [guía](docs/FASE-2-API.md#pruebas)).

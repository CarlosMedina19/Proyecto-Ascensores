# Proyecto Ascensores — backend

API REST versionada para la fase 1: autenticación con Sanctum, roles y permisos,
usuarios, auditoría, clientes y contactos, edificios, ascensores e historial
técnico. La gestión de puertas eléctricas queda fuera del alcance.

## Requisitos

- PHP 8.2 o superior
- Composer
- PostgreSQL para ejecución local/producción

## Instalación

```sh
composer install
copy .env.example .env
php artisan key:generate
```

Configura en `.env` una conexión PostgreSQL válida y define `ADMIN_EMAIL` y
`ADMIN_PASSWORD`. La contraseña debe tener mínimo 12 caracteres; el seeder no
crea una cuenta con contraseñas conocidas o predeterminadas.

```sh
php artisan migrate --seed
php artisan serve
```

Para ejecutar las pruebas con PostgreSQL, inicia el servicio `db` y crea una
base aislada. En PowerShell:

```sh
docker compose up -d db
docker exec ascensores_db psql -U ascensores_user -d ascensores -c "CREATE DATABASE ascensores_test OWNER ascensores_user;"
```

Crea `backend/.env.testing` (no se sube a Git) con `APP_ENV=testing`,
`DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_PORT=5433`,
`DB_DATABASE=ascensores_test` y las credenciales del servicio `db`. Añade una
clave de aplicación independiente con `php artisan key:generate --env=testing`.
PHPUnit ya está configurado para utilizar PostgreSQL:

```sh
php artisan test --compact
```

El puerto `5433` es el puerto del host publicado por `docker-compose.yml`; desde
el contenedor `app`, PostgreSQL se alcanza como `db:5432`.

## Autenticación y permisos

Envía `POST /api/v1/login` con correo y contraseña. Envía el token Sanctum en
el encabezado HTTP `Authorization` con el esquema `Bearer`. El login limita a
cinco intentos por minuto y combinación de correo/IP. Los endpoints privados requieren token y
validan la capacidad del rol; el administrador recibe todos los permisos del
catálogo sembrado.

Consulta [la guía de la API de fase 1](docs/FASE-1-API.md) para endpoints,
permisos, relaciones y ejemplos.

Las tablas y columnas propias de negocio están en español, mientras la API
conserva sus claves JSON existentes. Los nombres de tablas técnicas de
Laravel/Sanctum no se cambian. Antes de desplegar, respalda la base y ejecuta
`php artisan migrate` para aplicar los renombrados sin recrear ni borrar datos.

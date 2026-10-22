# SIGMA — Sistema Integral de Gestión y Mantenimiento de Ascensores

Proyecto formativo SENA. Plataforma para administrar clientes, edificios y ascensores (con sus
puertas de cabina y de piso) y su mantenimiento preventivo y correctivo.

| Carpeta | Tecnología | Contenido |
| --- | --- | --- |
| `backend/` | Laravel 12, PHP 8.2, PostgreSQL 16, Sanctum | API REST `/api/v1` |
| [`frontend/`](../frontend/README.md) | React 18 + Vite | Panel web administrativo y portal del cliente |
| [`app/flutter_application_1/`](../app/flutter_application_1/README.md) | Flutter / Dart | App para técnicos y clientes |

## Puesta en marcha

```sh
# 1. API y base de datos
cd backend
copy .env.example .env        # complete ADMIN_EMAIL, ADMIN_PASSWORD y SIGMA_DEMO_PASSWORD
docker compose up -d --build
docker exec ascensores_app php artisan key:generate
docker exec ascensores_app php artisan db:seed --class=DatosDemoSeeder

# 2. Panel web
cd ../frontend
npm install
copy .env.example .env
npm run dev                   # http://localhost:5173

# 3. App móvil
cd ../app/flutter_application_1
flutter pub get
flutter run
```

Usuarios de demostración (contraseña = `SIGMA_DEMO_PASSWORD`): `coordinador@gmail.com`,
`tecnico@gmail.com`, `supervisor@gmail.com`, `contador@gmail.com` y `cliente@gmail.com`, y los técnicos `andres.rojas@gmail.com`, `diana.mora@gmail.com` y `felipe.gomez@gmail.com`.
El administrador es el definido en `ADMIN_EMAIL`.

`docker compose up` también inicia la cola de avisos (`ascensores_queue`) y
las tareas diarias (`ascensores_scheduler`): vigencias de planes y contratos, avisos de
mantenimiento y alertas de vencimiento.

## Contenedores y base de datos

En Docker Desktop el proyecto se llama **proyecto_ascensores** (`backend/docker-compose.yml`). Todos sus
contenedores se usan:

| Contenedor | Para qué sirve |
| --- | --- |
| `ascensores_app` | API de Laravel en http://localhost:8000 |
| `ascensores_db` | PostgreSQL 16 (puerto 5433 en el equipo) |
| `ascensores_queue` | Envía los correos y avisos encolados |
| `ascensores_scheduler` | Tareas diarias: vigencias, avisos de mantenimiento, contratos y certificación anual |
| `ascensores_pgadmin` | Visor de la base de datos en http://localhost:5050 |

**Ver las tablas y el diagrama:** abra http://localhost:5050 (usuario `admin@ascensores.com`, contraseña
`admin123`). El servidor **SIGMA** ya está registrado; al abrirlo pide la contraseña de la base
(`ascensores_pass`). Luego: *SIGMA → Databases → ascensores → clic derecho → ERD For Database* para el
diagrama de tablas, o *Schemas → public → Tables* para ver cada tabla (*View/Edit Data*).

Desde otro programa (DBeaver, DataGrip): host `localhost`, puerto `5433`, base `ascensores`, usuario
`ascensores_user`, contraseña `ascensores_pass`.

## Carga y descarga en Excel

La plantilla [`resources/plantillas/SIGMA_Plantilla_Datos.xlsx`](resources/plantillas/SIGMA_Plantilla_Datos.xlsx) (también se descarga desde el panel con **Plantilla**) tiene una hoja
por formulario (Clientes, Edificios, Ascensores, Puertas de piso, Certificaciones, Órdenes de trabajo, Checklist,
Emergencias, Cotizaciones, Facturas, Pagos y Usuarios) con listas desplegables, rangos validados y una fila de ejemplo.

- **Exportar a Excel** en Ascensores, Clientes, Órdenes de trabajo, Cartera, Usuarios, Emergencias y
  Certificación anual descarga los datos
  actuales con la misma estructura.
- **Importar desde Excel** (.xlsx o .csv, con arrastrar y soltar) muestra primero una vista previa con las
  filas válidas, los errores (hoja, fila, columna y motivo) y los duplicados; luego importa solo las filas
  válidas. **Descargar errores** devuelve el archivo con una columna de observaciones.
- Un código que ya existe actualiza el registro: exportar → editar → importar no pierde datos.
- Regenerar la plantilla: `cd backend/scripts/plantilla && npm install && npm run plantilla`.

## Servicio comercial

- **Certificación anual** de cada ascensor con su certificado, estado (vigente, por vencer, vencida) y avisos
  60, 30 y 15 días antes. Referencia: en Bogotá, Acuerdo 470 de 2011 con la NTC 5926-1 (organismos acreditados
  por ONAC); en otras ciudades se registra la norma local.
- **Emergencias 24/7**: reporte desde el panel, la app o el **QR de la cabina sin iniciar sesión**
  (`/reportar/{código}`), tiempo objetivo de llegada configurable y seguimiento por estados.
- **Cotizaciones** de correctivos que el cliente aprueba o rechaza desde su portal.
- **Indicadores**: disponibilidad, MTBF, tiempo medio de respuesta y cumplimiento del plan.
- Historial del ascensor con línea de tiempo y documentos; **recorrido guiado** de 5 pasos.

## Producción (VPS)

`docker-compose.prod.yml` levanta `sigma_backend` (Apache + PHP), `sigma_queue`, `sigma_scheduler`,
`sigma_frontend` (nginx con el panel compilado) y `sigma_db` (PostgreSQL privada, sin puertos
publicados). Caddy, en la red externa `proxy`, envía `/api/*` y `/up` al backend y el resto al panel.

```sh
cd /opt/apps/sigma/backend
cp .env.produccion.example .env && chmod 600 .env    # completar claves
docker compose -f docker-compose.prod.yml up -d --build
```

Bloque de Caddy:

```
sigma.proyectoscampus.top {
	encode zstd gzip
	@sigma_api path /api/* /up
	handle @sigma_api {
		reverse_proxy sigma_backend:80
	}
	handle {
		reverse_proxy sigma_frontend:80
	}
}
```

Nunca ejecutar `docker compose down -v` en producción (borra la base de datos y los archivos).

## Pruebas

```sh
docker exec ascensores_app php artisan test     # backend: 113 pruebas (base ascensores_test)
cd frontend && npm test                          # 39 pruebas
cd app/flutter_application_1 && flutter test     # 28 pruebas
```

## Detalles del backend

### Con Docker (recomendado)

```sh
copy .env.example .env          # y complete ADMIN_EMAIL, ADMIN_PASSWORD y SIGMA_DEMO_PASSWORD
docker compose up -d --build    # app :8000, PostgreSQL :5433, pgAdmin :5050
docker exec ascensores_app php artisan key:generate
docker exec ascensores_app php artisan db:seed --class=DatosDemoSeeder   # opcional
```

El servicio `app` aplica las migraciones y los seeders al iniciar. Los servicios `queue`
(envío de avisos) y `scheduler` (tareas diarias) usan la misma imagen. Con Docker Desktop en
Windows, OPcache revalida el código cada 60 s: un cambio en PHP puede tardar hasta un
minuto en verse (o reinicie con `docker compose restart app`).

### Sin Docker

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

### Variables de SIGMA (`config/sigma.php`)

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

### Pruebas

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
los planes, la programación y los contratos.

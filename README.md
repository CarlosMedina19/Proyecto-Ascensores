# SIGMA — Sistema Integral de Gestión y Mantenimiento de Ascensores

Proyecto formativo SENA. Plataforma para administrar clientes, edificios y ascensores (con sus
puertas de cabina y de piso) y su mantenimiento preventivo y correctivo.

| Carpeta | Tecnología | Contenido |
| --- | --- | --- |
| [`backend/`](backend/README.md) | Laravel 12, PHP 8.2, PostgreSQL 16, Sanctum | API REST `/api/v1` |
| [`frontend/`](frontend/README.md) | React 18 + Vite | Panel web administrativo y portal del cliente |
| [`app/flutter_application_1/`](app/flutter_application_1/README.md) | Flutter / Dart | App para técnicos y clientes |

## Uso diario (Windows)

Con el proyecto ya instalado (ver *Puesta en marcha*):

- **Iniciar:** doble clic en `iniciar-sigma.bat`. Abre Docker si hace falta, levanta la API, la base
  de datos, la cola y las tareas programadas, el panel web (http://localhost:5173) y la app móvil
  en versión web (http://127.0.0.1:5180), y abre ambas en el navegador.
- **Detener:** doble clic en `detener-sigma.bat`. Los datos quedan guardados.

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
`tecnico@gmail.com`, `supervisor@gmail.com`, `contador@gmail.com` y `cliente@gmail.com`, y desde la
fase 3 los técnicos `andres.rojas@gmail.com`, `diana.mora@gmail.com` y `felipe.gomez@gmail.com`.
El administrador es el definido en `ADMIN_EMAIL`.

Desde la fase 2, `docker compose up` también inicia la cola de avisos (`ascensores_queue`) y
las tareas diarias (`ascensores_scheduler`): vigencias de planes y contratos, avisos de
mantenimiento y alertas de vencimiento.

## Estado por fases

| Fase | Alcance | Estado |
| --- | --- | --- |
| 1 | Fundación y datos maestros (RF-001 a RF-009) | API, panel web y app móvil implementados y probados |
| 2 | Planes, periodicidad, precios y contratos (RF-010 a RF-021) | API, panel web y app móvil implementados y probados |
| 3 | Técnicos, agenda y órdenes de trabajo (RF-022 a RF-029) | API, panel web y app móvil implementados y probados |
| 4 | Checklist de ascensores, firmas e informe PDF (RF-030 a RF-039) | API, panel web y app móvil implementados y probados |
| 5 | Inventario, cotizaciones, facturación y cartera | Pendiente |
| 6 | App móvil completa y portal del cliente (solicitudes, sin conexión con sincronización) | Pendiente |
| 7–9 | IoT, analítica y Machine Learning, inteligencia artificial | Pendiente |

La numeración sigue el documento *Requerimientos Proyecto SIGMA* (9 fases), que reemplaza la
fase de puertas eléctricas de `FASES.pdf`.

Las puertas eléctricas independientes quedaron fuera del alcance: solo se gestionan las
puertas de cabina y de piso de cada ascensor.

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

La plantilla [`backend/resources/plantillas/SIGMA_Plantilla_Datos.xlsx`](backend/resources/plantillas/SIGMA_Plantilla_Datos.xlsx) (también se descarga desde el panel con **Plantilla**) tiene una hoja
por formulario (Clientes, Edificios, Ascensores, Puertas de piso, Certificaciones, Órdenes de trabajo, Checklist,
Emergencias, Cotizaciones, Facturas, Pagos y Usuarios) con listas desplegables, rangos validados y una fila de ejemplo.

- **Exportar a Excel** en Ascensores, Clientes, Órdenes de trabajo, Cartera, Usuarios, Emergencias y
  Certificación anual descarga los datos
  actuales con la misma estructura.
- **Importar desde Excel** (.xlsx o .csv, con arrastrar y soltar) muestra primero una vista previa con las
  filas válidas, los errores (hoja, fila, columna y motivo) y los duplicados; luego importa solo las filas
  válidas. **Descargar errores** devuelve el archivo con una columna de observaciones.
- Un código que ya existe actualiza el registro: exportar → editar → importar no pierde datos.
- Regenerar la plantilla: `cd scripts && npm install && npm run plantilla`.


## Servicio comercial

- **Certificación anual** de cada ascensor con su certificado, estado (vigente, por vencer, vencida) y avisos
  60, 30 y 15 días antes. Referencia: en Bogotá, Acuerdo 470 de 2011 con la NTC 5926-1 (organismos acreditados
  por ONAC); en otras ciudades se registra la norma local.
- **Emergencias 24/7**: reporte desde el panel, la app o el **QR de la cabina sin iniciar sesión**
  (`/reportar/{código}`), tiempo objetivo de llegada configurable y seguimiento por estados.
- **Cotizaciones** de correctivos que el cliente aprueba o rechaza desde su portal.
- **Indicadores**: disponibilidad, MTBF, tiempo medio de respuesta y cumplimiento del plan.
- Historial del ascensor con línea de tiempo y documentos; **recorrido guiado** de 5 pasos.

Pendientes y decisiones abiertas en [docs/PENDIENTES.md](docs/PENDIENTES.md).

## Pruebas

```sh
docker exec ascensores_app php artisan test     # backend: 113 pruebas (base ascensores_test)
cd frontend && npm test                          # 39 pruebas
cd app/flutter_application_1 && flutter test     # 28 pruebas
```

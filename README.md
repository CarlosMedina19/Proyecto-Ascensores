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
| 2 | Planes, periodicidad, precios y contratos (RF-010 a RF-021) | API, panel web y app móvil implementados y probados ([guía de la API](backend/docs/FASE-2-API.md)) |
| 3 | Técnicos, agenda y órdenes de trabajo (RF-022 a RF-029) | API, panel web y app móvil implementados y probados ([guía de la API](backend/docs/FASE-3-API.md)) |
| 4 | Checklist de ascensores, firmas e informe PDF (RF-030 a RF-039) | API, panel web y app móvil implementados y probados ([guía de la API](backend/docs/FASE-4-API.md)) |
| 5 | Inventario, cotizaciones, facturación y cartera | Pendiente |
| 6 | App móvil completa y portal del cliente (solicitudes, sin conexión con sincronización) | Pendiente |
| 7–9 | IoT, analítica y Machine Learning, inteligencia artificial | Pendiente |

La numeración sigue el documento *Requerimientos Proyecto SIGMA* (9 fases), que reemplaza la
fase de puertas eléctricas de `FASES.pdf`.

Las puertas eléctricas independientes quedaron fuera del alcance: solo se gestionan las
puertas de cabina y de piso de cada ascensor.

## Pruebas

```sh
docker exec ascensores_app php artisan test     # backend: 90 pruebas (base ascensores_test)
cd frontend && npm test                          # 27 pruebas
cd app/flutter_application_1 && flutter test     # 28 pruebas
```

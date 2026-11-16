# SIGMA — Sistema Integral de Gestión y Mantenimiento de Ascensores

Proyecto formativo del **SENA**. SIGMA es una plataforma para empresas de mantenimiento de ascensores:
administra clientes, edificios y ascensores, programa el mantenimiento preventivo y correctivo, coordina a los
técnicos en campo y lleva la parte comercial (contratos, cotizaciones, facturación y certificación anual).

## Qué hace

- **Clientes, edificios y ascensores**: organizados por tipo (conjuntos, institucionales y residenciales), con
  fotos, historial y un código QR en cada cabina.
- **Mantenimiento**: planes con periodicidad, calendario de visitas, órdenes de trabajo con asignación de
  técnicos, planilla del preventivo, firmas e informes en PDF.
- **Emergencias 24/7**: reporte desde el panel, la app o el QR de la cabina sin iniciar sesión.
- **Comercial**: contratos, cotizaciones que el cliente aprueba desde su portal, facturas, pagos e inventario
  de repuestos.
- **Indicadores y analítica**: disponibilidad, MTBF, tiempos de respuesta, cumplimiento del plan y predicción
  de riesgo de falla.
- **Tiempo real**: auditoría en vivo con el mapa de los técnicos y notificaciones por WebSocket.
- **Seguridad**: roles y permisos, bloqueo por intentos fallidos, cierre por inactividad y auditoría de acciones.
- **Excel**: plantilla de carga, importación con vista previa de errores y exportación de los listados.

## Arquitectura

```
                ┌──────────────────────┐        ┌──────────────────────┐
                │  Panel web (React)   │        │  App móvil (Flutter) │
                │  frontend/           │        │  app/                │
                └──────────┬───────────┘        └──────────┬───────────┘
                           │        API REST /api/v1       │
                           └───────────────┬───────────────┘
                                           ▼
                           ┌───────────────────────────────┐
                           │  Backend (Laravel 12)         │──── WebSocket (Reverb)
                           │  backend/                     │──── Cola y tareas diarias
                           └───────┬───────────────┬───────┘
                                   │               │ red interna + X-Api-Key
                                   ▼               ▼
                           ┌──────────────┐ ┌──────────────────────┐
                           │ PostgreSQL 16│ │ Analítica (FastAPI)  │
                           └──────────────┘ │ analitica/           │
                                            └──────────────────────┘
```

| Carpeta | Tecnología | Contenido |
| --- | --- | --- |
| [`backend/`](backend/README.md) | Laravel 12, PHP 8.2, PostgreSQL 16, Sanctum, Reverb | API REST, colas, tareas programadas y WebSocket |
| [`frontend/`](frontend/README.md) | React 18, Vite, Leaflet | Panel administrativo y portal del cliente |
| [`app/flutter_application_1/`](app/flutter_application_1/README.md) | Flutter / Dart | App para técnicos y clientes |
| [`analitica/`](analitica/README.md) | Python, FastAPI, Pandas, scikit-learn | Indicadores y predicciones |

## Puesta en marcha (desarrollo)

Requisitos: Docker Desktop, Node.js 20+ y, para la app móvil, Flutter.

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

| Servicio | Dirección |
| --- | --- |
| Panel web | http://localhost:5173 |
| API | http://localhost:8000/api/v1 |
| Documentación de la API (desarrollo) | http://127.0.0.1:8000/docs/api |
| pgAdmin (visor de la base de datos) | http://localhost:5050 |

Los usuarios de demostración, los contenedores, la carga desde Excel y el despliegue en producción están
explicados en [`backend/README.md`](backend/README.md).

## Pruebas

Cada parte tiene sus pruebas, y GitHub Actions las ejecuta en cada cambio
([`.github/workflows/pruebas.yml`](.github/workflows/pruebas.yml)).

```sh
docker exec ascensores_app php artisan test     # backend (Laravel)
cd frontend && npm test                          # panel web (Vitest)
cd app/flutter_application_1 && flutter test     # app móvil
cd analitica && python -m pytest pruebas         # analítica (pytest)
```

## Cómo contribuir

1. Actualice `main`: `git switch main && git pull`.
2. Cree una rama para su cambio: `git switch -c tipo/descripcion-corta` (por ejemplo `docs/readme-principal`
   o `feat/filtro-ordenes`).
3. Haga commits pequeños con mensajes que expliquen el cambio.
4. Suba la rama y abra un **Pull Request** hacia `main` para que el equipo lo revise.

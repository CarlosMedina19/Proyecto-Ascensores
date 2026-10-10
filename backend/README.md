<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Ascensores API

La API está versionada en `/api/v1`; los recursos operativos requieren autenticación
Sanctum. En las colecciones se aceptan `elementos_por_pagina` (1–100), `pagina`,
`buscar` y los filtros indicados por cada recurso. Las respuestas de colección
incluyen `datos` y metadatos de paginación en español.

### Esquema de base de datos

Los nombres físicos de las tablas y columnas se traducen al español mediante la
migración `2026_10_08_200007_translate_database_identifiers_to_spanish`. Eloquent
adapta las consultas al esquema elegido. La migración también contempla las tablas de
autenticación y notificaciones de Laravel. Al aplicarla en cada entorno, renombra los
objetos existentes sin recrearlos ni borrar sus datos.

La migración `2026_10_08_200008_translate_electric_doors_table_to_spanish` también
renombra la tabla heredada `electric_doors` a `puertas_electricas` y traduce sus
columnas si existe en una instalación previa. No agrega rutas CRUD para ese módulo.
La tabla interna `migrations` y la clave primaria convencional `id` conservan sus
nombres técnicos de Laravel.

La API dispone de rutas y campos JSON en español. Por ejemplo, se puede crear un plan
con `POST /api/v1/planes-mantenimiento` y enviar `nombre`, `tipo_servicio` y
`esta_activo`; las respuestas usan `datos` y los errores `errores`. Se conserva la
superficie anterior en inglés para no romper integraciones existentes. Para listar
recursos, los filtros de búsqueda y paginación en español son `buscar`, `pagina` y
`elementos_por_pagina`.

| Módulo | Rutas en español |
| --- | --- |
| Mantenimiento | `/planes-mantenimiento`, `/asignaciones-planes`, `/historial-precios-planes`, `/programaciones-mantenimiento` |
| Operaciones | `/tecnicos`, `/contratos`, `/equipos-contratos`, `/ordenes-trabajo`, `/tecnicos-ordenes-trabajo`, `/eventos-ordenes-trabajo` |
| Verificaciones | `/plantillas-verificacion`, `/secciones-verificacion`, `/elementos-verificacion`, `/verificaciones-ordenes-trabajo` |
| Inventario | `/repuestos`, `/movimientos-inventario`, `/repuestos-ordenes-trabajo` |
| Finanzas | `/cotizaciones`, `/conceptos-cotizaciones`, `/facturas`, `/conceptos-facturas`, `/pagos`, `/movimientos-cuenta` |
| Archivos | `POST /adjuntos/cargar`; `GET /adjuntos/{attachment}/descargar` |
| IoT | `/iot/dispositivos`, `/iot/sensores`, `/iot/lecturas`, `/iot/eventos`, `/iot/reglas-alerta`, `/iot/alertas` |
| IA | `/ia/documentos`, `/ia/fragmentos-documentos`, `/ia/incrustaciones`, `/ia/conversaciones`, `/ia/mensajes`, `/ia/comentarios`, `/ia/predicciones` |
| Analítica | `GET /analitica/indicadores`, `/analitica/anomalias`, `/analitica/predicciones` |

Los recursos CRUD usan `GET` para listar/detallar, `POST` para crear y `PATCH`/`PUT`
para actualizar cuando el recurso lo permite. El consumo de repuestos y los
movimientos de inventario se registran transaccionalmente y no permiten dejar el
stock negativo. Los pagos contabilizan el crédito en cartera, impiden exceder el
saldo y actualizan el estado de la factura. Los importes de cotizaciones y facturas
se calculan a partir de sus conceptos; una cotización aprobada se puede convertir
en borrador de factura. Historiales financieros, lecturas IoT y eventos se conservan
como registros inmutables. No se incluyen recursos de puertas eléctricas.

Las tablas y endpoints de IA almacenan documentos, conversaciones y predicciones;
la generación de respuestas con un proveedor IA, el transporte MQTT, la entrega
real de notificaciones y las pantallas Flutter requieren servicios/configuración
adicionales y no se simulan en esta API.

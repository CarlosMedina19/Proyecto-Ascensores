# SIGMA — app móvil (Flutter)

Una sola app con interfaces según el rol, conectada a la API de SIGMA (`/api/v1`).

| Rol | Pestañas |
| --- | --- |
| Técnico | Inicio, Ascensores, Edificios, Visitas, Perfil |
| Cliente | Inicio (portal), Mis ascensores, Edificios, Perfil |
| Administrativos | Inicio con resumen, Ascensores, Edificios, Perfil (y Visitas si pueden registrar historial) |

## Ejecutar

```sh
flutter pub get
flutter run                                   # emulador Android: usa http://10.0.2.2:8000/api/v1
flutter run --dart-define=API_URL=http://192.168.1.10:8000/api/v1   # teléfono físico en la misma red
```

En web y escritorio la URL por defecto es `http://localhost:8000/api/v1`. Para compilar
plugins en Windows active el Modo de desarrollador (`start ms-settings:developers`).

## Fase 3

- **Órdenes** (pestaña nueva): el técnico ve sus órdenes activas y finalizadas. En el detalle avanza el
  estado (voy en camino, iniciar trabajo, dejar pendiente con motivo, finalizar con informe), y ve los
  tiempos, los técnicos y la trazabilidad.
- **Inicio del técnico:** "Mis próximas órdenes".

## Fase 4

- **Planilla** (orden preventiva en proceso): pestañas por sección, INS/EST/N.A., B/R/M, observación
  obligatoria en R o M, orden correctiva desde un ítem, recomendaciones y fotos (cámara o galería).
- **Firmas** del técnico y de quien recibe. Sin la planilla completa y las dos firmas, la orden no se finaliza.

## Diseño (prototipo SIGMA)

Mismo sistema visual del panel web: morado #4E29AA, verde lima para datos en vivo y verde/ámbar/rojo
para Bueno/Regular/Malo (en modo oscuro pasan a sus variantes claras). Tarjetas con borde luminoso
degradado, encabezados con brillo, marca SIGMA en Michroma, títulos en Bricolage Grotesque, texto en
Geist y etiquetas en JetBrains Mono. Las fuentes van incluidas en `assets/fuentes` (licencia OFL)
para que la app funcione sin conexión. La planilla usa los botones B / R / M cuadrados y el botón
punteado INS → EST → N.A.; la orden muestra su avance por etapas. Al ingresar, unas puertas de
ascensor se cierran y se abren ya dentro de la app (se omite si el sistema desactiva las animaciones).

### APK para Android (liviano)

```sh
flutter build apk --release --split-per-abi --obfuscate --split-debug-info=build/simbolos \n  --dart-define=API_URL=https://sigma.proyectoscampus.top/api/v1
```

Sin `API_URL` el APK busca la API en `10.0.2.2:8000` (el computador visto desde el emulador) y en un
celular real no conecta. Desde el inicio de sesión, «Servidor» permite cambiar la dirección sin recompilar.

Genera un APK por tipo de procesador en `build/app/outputs/flutter-apk/` (unos 8–9 MB cada uno):
`app-arm64-v8a-release.apk` sirve para casi todos los teléfonos actuales y `app-armeabi-v7a-release.apk`
para los más antiguos. El APK de depuración (`flutter build apk --debug`) pesa más de 150 MB y no es para
instalar en usuarios. El tamaño se logra con R8 (código y recursos sin uso eliminados), librerías nativas
comprimidas y los símbolos de depuración guardados aparte en `build/simbolos`, que sirven para leer los
errores con `flutter symbolize`.

### Versión web

```sh
flutter build web --release --pwa-strategy=none --no-tree-shake-icons
cd build/web && python -m http.server 5180
```

Se compila **sin service worker** y con la fuente de íconos completa. Con el service worker, el
navegador mezclaba archivos de compilaciones anteriores y los íconos se veían como cuadros
vacíos; `web/index.html` además elimina el service worker que haya quedado de antes.

## Pruebas

```sh
flutter analyze
flutter test
```

## Qué hace (fase 1)

- **Inicio de sesión** real contra la API, recuperación de contraseña, cambio de contraseña.
  El token se guarda cifrado (`flutter_secure_storage`).
- **Inactividad (RF-001):** aviso al minuto 9 y cierre a los 10; cualquier toque cuenta como actividad.
- **Ascensores:** búsqueda, filtro por estado, ficha técnica (capacidad, velocidad, paradas,
  puertas de cabina y de piso), historial y registro de eventos.
- **Edificios:** listado y detalle con sus ascensores.
- **Visita de mantenimiento (técnico):** revisión de las 6 zonas del ascensor con B/R/M
  (Regular o Malo exigen observación), actividades, diagnóstico, observaciones, repuestos,
  fotos y firma. Se guarda en el dispositivo sin conexión y no se pierde al cerrar sesión;
  al enviarla, el resumen queda en el historial del ascensor.
- **Modo oscuro** y colores del sistema visual de SIGMA.

## Fase 2

- **Plan de mantenimiento** en el detalle del ascensor: plan, periodicidad, precio con IVA,
  vigencia, contrato, último y próximo mantenimiento, y las próximas visitas.
- **Próximos mantenimientos** en el inicio: visitas pendientes de los próximos 30 días
  (las atrasadas se resaltan en rojo). Solo consulta; la gestión se hace en el panel web.

## Estructura

```
lib/
  configuracion.dart   URL de la API y parámetros
  tema.dart            colores y tema claro/oscuro
  datos/               cliente HTTP, modelos y repositorio
  estado/              sesión, preferencias y borradores de visitas
  pantallas/           inicio de sesión, principal por rol, inicio, ascensores, edificios, visitas, visita, perfil
  widgets/             componentes comunes (logo, insignias, línea de tiempo)
test/                  pruebas de datos, sesión y pantallas
```

## Próximas fases

Checklist completo con informe PDF y carga de fotos y firmas al servidor (fase 4; también será
requisito para finalizar una orden), solicitudes del cliente y sincronización con control de
conflictos (fase 6).

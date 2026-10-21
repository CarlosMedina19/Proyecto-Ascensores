# SIGMA · Diagnóstico para la versión comercial

Revisión del producto real (panel web React + API Laravel + app Flutter) al cerrar la fase 1 (Excel).
Cada hallazgo indica el problema, dónde está, la solución propuesta y en qué fase del plan se resuelve.
Medido el 20 de octubre de 2026 con capturas en 1280×720, 1366×768, 1440×900, 1920×1080, 1280×650 y
390×844, y con el cálculo de contraste WCAG de los colores actuales.

## Prioridad alta

| # | Problema | Dónde | Solución | Fase |
| --- | --- | --- | --- | --- |
| A1 | **El ingreso en celular se rompe**: en 390×844 conserva dos columnas; el formulario queda aplastado (campos de 110 px, título en cuatro líneas) y hay 21 px de scroll. | `frontend/src/estilos/global.css`: la regla `.acceso` del pulido anula la de pantallas pequeñas. | Una sola columna por debajo de 1000 px, ilustración oculta en celular, formulario a todo el ancho. | 5 |
| A2 | **El ingreso no cabe en un visor de 650 px de alto**: 218 px de scroll en 1280×650. | Ingreso web (`DisenoAcceso.jsx`, estilos `.acceso*`). | Alturas fluidas (`dvh`, `clamp`) y una ilustración que se reduce o se oculta en pantallas bajas. | 5 |
| A3 | **Referencias internas visibles para el cliente**: «Conectado · Fase 4» en el menú; «SENA · Proyecto formativo SIGMA» en el ingreso web y en la app; texto de ayuda «Significado de INS y EST por definir (RF-037)» en la planilla; en la app, «Disponible en la fase 6», «se habilita en la fase 5» y «Se habilitan en la fase 3». | `Estructura.jsx`, `DisenoAcceso.jsx`, `PlanillaOrden.jsx`; app: `inicio.dart`, `inicio_sesion.dart`, `visita.dart`, `visitas.dart`. | Modo demostración que se activa y desactiva; en modo de venta, textos comerciales. | 4 |
| A4 | **Contraste insuficiente (WCAG AA)**: texto tenue `#9A8DBE` sobre la página `#F2EFFA` = 2,67:1 y sobre blanco = 3,03:1; ámbar de «Regular» `#B86E06` en insignias = 3,53:1; etiquetas de grupo del menú `#8161D1` = 3,83:1 con 10,5 px. | Tokens `--color-texto-tenue`, `--color-alerta` y menú lateral. | Nueva paleta con todos los pares verificados (texto normal ≥ 4,5:1, texto grande e íconos ≥ 3:1). | 3 |
| A5 | **Trato mezclado (tú y usted)**: «Ingresa a SIGMA», «Usa el correo y la contraseña de tu cuenta» (web y app) y «Revisa tipo y peso» junto a textos en usted. | `IniciarSesion.jsx`, `FotosEquipo.jsx`, `inicio_sesion.dart`. | Usted en toda la aplicación, con glosario único. | 4 |
| A6 | **Faltan funciones que el comprador espera**: certificado anual de inspección con alertas, reporte de emergencias y atrapamientos 24/7, QR por ascensor, cotizaciones aprobadas por el cliente e indicadores de servicio (disponibilidad, MTBF, tiempo de respuesta). | Producto. | Módulos nuevos, con su hoja en la plantilla de Excel. | 6 |
| A7 | **Un ascensor tiene datos fuera de los rangos nuevos**: F001 registra 10.000 kg y 2 m/min (0,03 m/s); no se puede editar ni reimportar hasta corregirlo. | Datos de desarrollo (`equipos` F001). | Corregir la ficha con los valores reales. **Necesita su confirmación de los valores.** | — |

## Prioridad media

| # | Problema | Dónde | Solución | Fase |
| --- | --- | --- | --- | --- |
| M1 | El encabezado de bienvenida del panel mide unos 230 px; en 1366×768 los indicadores quedan al borde de la primera pantalla y la gráfica no se ve. | `PanelOperacion.jsx` (bloque de bienvenida y franja de estado). | Encabezado compacto en una línea con las acciones a la derecha; franja de estado integrada. | 5 |
| M2 | Concordancia y textos sin contexto en el panel: «1 críticos», «Cumplimiento semanal 0/0», «0 técnicos en campo de 4 activos». | `PanelOperacion.jsx`. | Singular y plural correctos y textos para cero («Sin visitas programadas esta semana»). | 4 |
| M3 | Monoespaciada usada como adorno en textos largos (subtítulos de indicadores, migas, encabezados de tabla, pie del menú). | `global.css` (`.estadistica__detalle`, `.migas`, `.tabla th`, `.lateral__version`). | Reservar la monoespaciada para datos (códigos, horas, cifras). | 5 |
| M4 | Unidades mezcladas: el formulario y la ficha usan m/min; la plantilla y el sector, m/s. | `FormularioEquipo.jsx`, `DetalleEquipo.jsx`. | Mostrar y capturar en m/s («1,0 m/s»); guardar internamente igual que hoy. | 4 |
| M5 | Carga con texto «Cargando…» y un círculo en lugar de esqueletos; varias listas quedan en blanco mientras cargan. | `Cargando` en `Basicos.jsx`, listas y fichas. | Esqueletos de tabla, tarjeta y ficha. | 5 |
| M6 | Estados vacíos sin acción en varias listas («No se encontraron clientes»). | Listas de clientes, edificios, ascensores, órdenes y técnicos. | Mensaje con la acción siguiente (registrar o importar desde Excel). | 4 |
| M7 | El logo detallado no se lee a 24 px (cabina, letras y rieles se vuelven una mancha). | `Marca.jsx`, `sigma.svg`, logo de la app. | Versión reducida para 16–32 px (Σ con la cabina, sin letras). | 5 |
| M8 | Áreas táctiles menores a 44 px: flechas del menú (26–30 px) y botón de desplegar filas (28 px). | `global.css` (`.lateral__desplegar`, `.boton-desplegar`). | Zona táctil de 44 px sin cambiar el tamaño visual. | 5 |
| M9 | Formatos de fecha distintos según la pantalla («17 oct 2026», «17 de oct de 2026, 06:00 p. m.», «2026-10-17» en algunos campos). | `utilidades/formato.js`, fichas y tablas. | Formato colombiano único: 17/10/2026 en tablas y «17 de octubre de 2026» en textos. | 4 |
| M10 | El saludo usa el primer nombre tal cual: el superusuario aparece como «Buenas noches, Super.». | Datos del usuario semilla (`Super Administrador`). | Renombrar el usuario semilla («Administrador SIGMA») o saludar sin nombre cuando no es una persona. | 4 |

## Prioridad baja

| # | Problema | Dónde | Solución | Fase |
| --- | --- | --- | --- | --- |
| B1 | La barra de Excel ocupa una fila propia sobre la tabla. | Listas con `HerramientasExcel`. | Integrarla al encabezado como «Exportar» y un menú «Más acciones». | 5 |
| B2 | La gráfica semanal y los indicadores usan tonos morados que se distinguen mal con daltonismo (programados y ejecutados). | `PanelOperacion.jsx`, tokens `--chP`/`--chE`. | Escala derivada de la paleta, con contraste de luminosidad y patrón. | 3 |
| B3 | Recorridos de demostración y sección «Próximamente» inexistentes. | Producto. | Recorrido guiado de 3–5 pasos y página Próximamente (IoT, IA). | 6 |
| B4 | El pie del ingreso y la app muestran textos de proyecto en lugar de soporte o versión. | Ingreso web y app. | Versión del producto y contacto de soporte (en modo demostración, el texto del proyecto). | 4 |

## Lo que está bien y se conserva

- Ninguna vista tiene scroll horizontal en los tamaños medidos; las tablas se desplazan en su caja.
- El ingreso cabe completo en 1280×720, 1366×768, 1440×900 y 1920×1080.
- Foco visible en controles, «reducir movimiento» respetado, botones con verbo y sin exclamaciones.
- Moneda en COP con separador de miles y validaciones en español en formularios y en la importación.

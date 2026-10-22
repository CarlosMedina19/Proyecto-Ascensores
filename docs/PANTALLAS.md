# SIGMA · Ajuste a la pantalla (Fase 5)

Medido con Edge sin interfaz, escala 1, en modo claro y oscuro (las capturas se generan con el script de abajo; no se guardan en el repositorio). "Desplazamiento" es lo
que sobra del documento por debajo de la ventana; "fin de KPI" es el borde inferior de la última tarjeta de
indicadores del panel.

## Inicio de sesión (panel web)

| Tamaño | Antes | Después | Claro | Oscuro |
| --- | --- | --- | --- | --- |
| 1280 × 720 | sin desplazamiento | sin desplazamiento | ✔ | ✔ |
| 1366 × 768 | 103 px de desplazamiento | sin desplazamiento | ✔ | ✔ |
| 1440 × 900 | sin desplazamiento | sin desplazamiento | ✔ | ✔ |
| 1920 × 1080 | sin desplazamiento | sin desplazamiento | ✔ | ✔ |
| 1280 × 650 (ventana baja) | 218 px de desplazamiento | sin desplazamiento | ✔ | ✔ |
| 390 × 844 (teléfono) | dos columnas apretadas, 35 px | una columna, sin desplazamiento | ✔ | ✔ |

En teléfono se ocultan la ilustración del edificio y el subtítulo de la marca; quedan el logo, el lema y el
formulario completo. En ventanas de 720 px de alto o menos se oculta el párrafo de la presentación.

**App (Flutter) 390 × 844:** inicio de sesión completo sin desplazamiento.

## Panel de inicio

| Tamaño | Fin de KPI | ¿KPI en la primera pantalla? | Desplazamiento horizontal |
| --- | --- | --- | --- |
| 1280 × 720 | 600 px | Sí | No |
| 1366 × 768 | 578 px | Sí | No |
| 1440 × 900 | 578 px | Sí | No |
| 1920 × 1080 | 540 px | Sí | No |
| 1280 × 650 | 600 px | Sí | No |
| 390 × 844 | 1027 px | No (en teléfono van debajo del saludo, en una columna) | No |

Encabezado fijo compacto: 61 px en escritorio y 59 px en teléfono.

## Reglas aplicadas

- Alturas con `100dvh` y tamaños con `clamp()` según el alto visible; el formulario se desplaza por dentro
  solo si la ventana mide menos de 560 px de alto.
- Texto base de 14,5 px con interlineado 1,5; escala de radios 8 / 12 / 16 px.
- Transiciones de 150 a 250 ms. Con *reducir movimiento* del sistema se apagan las animaciones (también el
  recorrido del ascensor y las puertas).
- Foco visible en todos los controles (`:focus-visible`) y controles de al menos 44 px en pantallas táctiles.
- Estados: esqueleto de carga en lugar del círculo giratorio, estados vacíos con siguiente paso, errores con
  qué pasó y cómo resolverlo, avisos de éxito.
- Íconos: un solo estilo (Lucide, trazo de 2 px) en el panel y Material *outlined* en la app.
- Logo: versión reducida a 32 px o menos (solo la Σ y la cabina) para que se lea a 24 px; también es el
  ícono de la pestaña del navegador.
- Las descripciones de los indicadores usan la fuente de texto; la monoespaciada queda para datos.

## Contraste

Los pares de color de las dos paletas cumplen WCAG AA (4,5:1 texto, 3:1 bordes de controles, íconos y
foco). Detalle por par en [`PALETA.md`](PALETA.md).

## Cómo repetir la medición

```bash
# Con el panel en http://localhost:5173 y la API en http://localhost:8000
SIGMA_CORREO=... SIGMA_CLAVE=... python scripts/medir_pantallas.py docs/capturas claro
python scripts/medir_pantallas.py docs/capturas oscuro
```

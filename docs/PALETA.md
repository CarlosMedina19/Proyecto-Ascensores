# SIGMA · Paleta comercial

Elegida a partir de la propuesta [`paletas.html`](paletas.html): base **A · Acero y violeta** en modo claro y,
a pedido, un **modo oscuro en negros con tinte violeta** donde el morado queda solo como acento (antes las
superficies grandes eran moradas y cansaban la vista).

Todo el color del panel web sale de variables CSS (`frontend/src/estilos/global.css`, bloque `:root`) y en
la app de `lib/tema.dart`. En el CSS no queda ningún color hexadecimal fuera de los tokens; el logo
conserva sus colores de marca.

## Tokens

| Token | Claro | Oscuro | Uso |
| --- | --- | --- | --- |
| `--color-pagina` (fondo) | `#F4F3F7` | `#0F0E13` | Fondo de la página |
| `--color-superficie` | `#FFFFFF` | `#17151C` | Tarjetas, tablas, formularios |
| `--color-superficie-suave` (elevada) | `#F8F7FA` | `#1C1A23` | Campos, encabezados de tabla |
| `--color-borde` | `#E1DEE8` | `#2C2936` | Bordes decorativos |
| `--color-borde-fuerte` | `#8A8597` | `#6E687D` | Bordes de controles (≥ 3:1) |
| `--color-texto` | `#1A1822` | `#ECEAF2` | Texto principal |
| `--color-texto-suave` | `#534F63` | `#AFA9BE` | Texto secundario |
| `--color-texto-tenue` | `#6B6680` | `#968FA8` | Ayudas y metadatos (≥ 4,5:1) |
| `--color-primario` (acento) | `#5B34C4` | `#A58BFF` | Acciones, selección y foco |
| `--color-primario-suave` | `#EFEAFB` | `#2A2342` | Fondo de la opción activa |
| `--color-foco` | `#5B34C4` | `#BBA6FF` | Anillo de foco |
| `--menu-fondo` | `#141218` | `#141218` | Menú lateral (casi negro en los dos modos) |

Estados, independientes del acento:

| Estado | Claro (texto · fondo) | Oscuro (texto · fondo) |
| --- | --- | --- |
| Bueno | `#1E7A3E` · `#E5F3E9` | `#6FD08F` · `#173A26` |
| Regular | `#8A5300` · `#FCEFD6` | `#F2B84B` · `#3F2F12` |
| Malo | `#B42318` · `#FCE6E3` | `#FF8F80` · `#45201C` |
| Pendiente | `#556070` · `#EDF0F4` | `#AAB4C3` · `#262C36` |
| Información | `#0B62B8` · `#E2EEFB` | `#78B6FF` · `#14304F` |

Gráficas: `--grafica-1` (acento intenso), `--grafica-2` (acento claro) y `--grafica-3` (información). Se
distinguen por luminosidad, así funcionan con cualquier tipo de daltonismo. Sombras: dos niveles
(`--sombra-tarjeta` y `--sombra-elevada`).

## Contraste verificado (WCAG AA)

| Par | Claro | Oscuro |
| --- | --- | --- |
| Texto principal / superficie | 17,53:1 | 15,18:1 |
| Texto secundario / superficie | 7,88:1 | 7,96:1 |
| Texto secundario / fondo | 7,13:1 | 8,46:1 |
| Texto tenue / fondo | 4,95:1 | 6,22:1 |
| Acento como texto / superficie | 7,68:1 | 6,67:1 |
| Texto sobre el acento (botón) | 7,68:1 | 6,86:1 |
| Acento / acento suave | 6,52:1 | 5,46:1 |
| Foco / superficie (mín. 3:1) | 7,68:1 | 8,63:1 |
| Borde de controles / superficie (mín. 3:1) | 3,57:1 | 3,39:1 |
| Texto del menú / menú | 15,60:1 | 16,56:1 |
| Texto secundario del menú / menú | 7,63:1 | 8,10:1 |
| Bueno / su fondo | 4,69:1 | 6,65:1 |
| Regular / su fondo | 5,56:1 | 7,21:1 |
| Malo / su fondo | 5,50:1 | 6,43:1 |
| Información / su fondo | 5,17:1 | 6,35:1 |

Todos los pares cumplen: 4,5:1 en texto normal y 3:1 en íconos, bordes de controles y foco.

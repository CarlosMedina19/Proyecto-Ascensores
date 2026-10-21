# Carga y descarga de datos en Excel

SIGMA importa y exporta su información con una sola plantilla:
[`plantillas/SIGMA_Plantilla_Datos.xlsx`](../../plantillas/SIGMA_Plantilla_Datos.xlsx).

## La plantilla

Tiene una hoja por formulario, en el orden de carga:

**Clientes → Edificios → Ascensores → Puertas de piso → Certificaciones → Órdenes de trabajo → Checklist → Emergencias → Cotizaciones → Facturas → Pagos → Usuarios**

más una hoja de **Instrucciones** y una hoja oculta de **Listas** (valores de las listas desplegables).

- Encabezados con el color de la marca, fila congelada y filtros. Los obligatorios llevan `*` y cada
  encabezado tiene un comentario que explica el campo.
- Validación de datos en Excel: listas desplegables, rangos (año 1960–2026, paradas 2–60, capacidad
  100–5.000 kg, velocidad 0,1–10 m/s, periodicidad 1–6), fechas válidas y formato en pesos.
- La fila 2 de cada hoja es un ejemplo: su primer valor empieza por `EJEMPLO` y se ignora al importar.

Edificios y Facturas no estaban en la propuesta inicial: SIGMA exige el edificio (dirección y contacto)
antes de registrar un ascensor, y un pago siempre se aplica a una factura.

### Regenerarla

La estructura vive en `backend/resources/plantillas/estructura.json` (hojas, columnas, reglas, listas y
ejemplos). El mismo archivo lo usa Laravel para exportar e importar, así que nunca se desalinean.

```sh
cd scripts
npm install
npm run plantilla   # escribe plantillas/ y backend/resources/plantillas/
```

## Exportar

Botón **Exportar a Excel** en Ascensores, Clientes, Órdenes de trabajo, Cartera, Usuarios, Emergencias y Certificación anual. Descarga los
datos actuales con la estructura de la plantilla (mismas validaciones y listas; técnicos y planes reales):

| Módulo | Hojas con datos |
| --- | --- |
| Clientes | Clientes, Edificios |
| Ascensores | Ascensores, Puertas de piso, Certificaciones |
| Órdenes de trabajo | Órdenes de trabajo, Checklist |
| Cartera | Facturas, Pagos |
| Usuarios | Usuarios |
| Servicio (Emergencias, Certificación anual) | Certificaciones, Emergencias, Cotizaciones |

Cada rol exporta solo lo que puede ver (el cliente, solo lo de su empresa; el técnico, solo sus órdenes).
**Descargar plantilla vacía** baja la plantilla con los ejemplos.

API: `GET /api/v1/excel/exportar/{clientes|ascensores|ordenes|cartera|usuarios|servicio|todo}` y
`GET /api/v1/excel/plantilla`.

## Importar

Botón **Importar desde Excel** (permiso `datos.importar`): acepta `.xlsx` y `.csv` (con `;` o `,`, en
UTF-8 o Windows-1252), con arrastrar y soltar.

1. **Vista previa** (`POST /api/v1/excel/importaciones`): procesa todo el archivo con las mismas
   validaciones y reglas de los formularios, dentro de una transacción que se deshace al final. No guarda
   nada ni envía correos. Muestra por hoja las filas nuevas, las que actualizan un registro existente, las
   que ya existen sin cambios, las filas con error (hoja, fila, columna y motivo) y los duplicados del
   archivo (mismo código, NIT, número o referencia).
2. **Importar solo las filas válidas** (`POST /api/v1/excel/importaciones/{id}/confirmar`) o **Corregir y
   volver a cargar**. Resumen final, por ejemplo: «Se importaron 42 ascensores y 318 puertas de piso.
   3 filas no se cargaron.»
3. **Descargar errores** (`GET /api/v1/excel/importaciones/{id}/errores`): el mismo archivo con la columna
   «Observaciones de SIGMA» en cada fila que no se cargó.

Reglas de la carga:

- Un código que ya existe **actualiza** el registro (clientes por código, edificios por cliente y nombre,
  ascensores por código, puertas por ascensor y piso, usuarios por correo). Así funciona la ida y vuelta:
  exportar → editar en Excel → importar, sin perder datos.
- Órdenes de trabajo, facturas y pagos que ya existen no se modifican desde Excel; se informan como
  existentes. Una orden nueva se crea con el número vacío (lo asigna SIGMA).
- Al crear un ascensor se crean sus puertas de piso según las paradas; la hoja Puertas de piso indica su
  tipo y estado.
- Con periodicidad y fecha del primer mantenimiento se asigna el plan (el indicado o el primer plan activo
  con precio para esa periodicidad) y se programan las visitas, si el ascensor aún no tiene plan.
- En Clientes, con vigencia y valor (y sin número de contrato) se crea el contrato con los ascensores del
  cliente, después de cargar la hoja Ascensores.
- Los usuarios nuevos reciben un enlace para crear su contraseña.
- Cada hoja exige el permiso de su formulario (por ejemplo, el contador carga facturas y pagos, pero no
  clientes). El checklist solo lo carga un técnico asignado a la orden en proceso.

Código: `app/Support/Excel` (estructura, lector y opciones), `app/Services/Excel` (importación y
exportación) y `app/Http/Controllers/Api/ExcelController.php`. Pruebas: `tests/Feature/PruebasExcelTest.php`.

## Hojas del servicio comercial (fase 6)

| Hoja | Clave para no duplicar | Notas |
| --- | --- | --- |
| Certificaciones | Ascensor + fecha de inspección | Resultado: Conforme, Conforme con observaciones o No conforme. Norma vacía = NTC 5926-1. El certificado (PDF) se adjunta desde la ficha del ascensor. |
| Emergencias | Número, o ascensor + fecha y hora del reporte | Carga emergencias pasadas con sus horas de llegada y solución; no envía avisos. Si trae fecha de solución, exige la solución. |
| Cotizaciones | Número | Cada fila crea una cotización de un renglón (valor antes de IVA) y la envía al cliente. |


// Genera plantillas/SIGMA_Plantilla_Datos.xlsx a partir de backend/resources/plantillas/estructura.json.
// Uso: cd scripts && npm install && npm run plantilla
// La misma estructura la usa Laravel para exportar e importar, así que plantilla y sistema no se desalinean.
import { readFileSync, mkdirSync, copyFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import ExcelJS from 'exceljs';

const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const estructura = JSON.parse(readFileSync(join(raiz, 'backend/resources/plantillas/estructura.json'), 'utf8'));

// Colores de la marca (prototipo SIGMA).
const MORADO = 'FF4E29AA';
const MORADO_OSCURO = 'FF22124A';
const LAVANDA = 'FFF2EFFA';
const TEXTO_SUAVE = 'FF6B5B95';
const BORDE = 'FFCDC0ED';
const FILAS = 2000; // filas con validación en cada hoja
const FUENTE = 'Segoe UI';

const letra = (n) => {
  let s = '';
  for (let i = n; i > 0; i = Math.floor((i - 1) / 26)) s = String.fromCharCode(65 + ((i - 1) % 26)) + s;
  return s;
};

// Columnas de la hoja Listas: una por lista, en el orden del JSON.
const nombresListas = Object.keys(estructura.listas);
const columnaLista = (nombre) => letra(nombresListas.indexOf(nombre) + 1);
const rangoLista = (nombre) => {
  const valores = estructura.listas[nombre];
  const hasta = Math.max(valores.length + 1, 500); // listas dinámicas (técnicos, planes) se llenan al exportar
  return `Listas!$${columnaLista(nombre)}$2:$${columnaLista(nombre)}$${hasta}`;
};

const fechaExcel = (texto) => {
  const [a, m, d] = texto.split('-').map(Number);
  return new Date(Date.UTC(a, m - 1, d));
};
const horaExcel = (texto) => {
  const [h, m] = texto.split(':').map(Number);
  return (h * 60 + m) / 1440;
};

const formatos = { fecha: 'dd/mm/yyyy', hora: 'hh:mm', moneda: '"$" #,##0', decimal: '0.0#', entero: '0' };

function validacion(columna) {
  const titulo = columna.titulo;
  const base = { allowBlank: true, showErrorMessage: true, errorTitle: titulo, showInputMessage: false };
  switch (columna.tipo) {
    case 'lista':
      return {
        ...base,
        type: 'list',
        formulae: [rangoLista(columna.lista)],
        errorStyle: columna.libre ? 'information' : 'stop',
        error: columna.libre ? 'El valor no está en la lista. Puede conservarlo si es correcto.' : 'Elija un valor de la lista desplegable.',
      };
    case 'entero':
      return { ...base, type: 'whole', operator: 'between', formulae: [columna.min ?? 0, columna.max ?? 999999999], error: `Escriba un número entero entre ${columna.min} y ${columna.max}.` };
    case 'decimal':
      return { ...base, type: 'decimal', operator: 'between', formulae: [columna.min ?? 0, columna.max ?? 999999999], error: `Escriba un número entre ${String(columna.min).replace('.', ',')} y ${columna.max}.` };
    case 'moneda':
      return { ...base, type: 'decimal', operator: 'greaterThanOrEqual', formulae: [columna.min ?? 0], error: 'Escriba el valor en pesos, sin decimales.' };
    case 'fecha':
      return { ...base, type: 'date', operator: 'between', formulae: [new Date(Date.UTC(1960, 0, 1)), new Date(Date.UTC(2100, 11, 31))], error: 'Escriba una fecha válida (día/mes/año).' };
    case 'hora':
      return { ...base, type: 'time', operator: 'between', formulae: [0, 0.99999], error: 'Escriba una hora en formato 24 horas (07:30).' };
    default:
      return null;
  }
}

const libro = new ExcelJS.Workbook();
libro.creator = 'SIGMA';
libro.title = 'Plantilla de carga de datos';
libro.created = new Date();

// Instrucciones
const instrucciones = libro.addWorksheet('Instrucciones', { properties: { tabColor: { argb: MORADO } } });
instrucciones.getColumn(1).width = 120;
estructura.instrucciones.forEach((texto, i) => {
  const celda = instrucciones.getCell(i + 1, 1);
  celda.value = texto;
  celda.alignment = { wrapText: true, vertical: 'top' };
  const esTitulo = i === 0;
  const esSeccion = ['Cómo llenarla', 'Orden de carga', 'Formatos', 'Actualizar datos'].includes(texto);
  celda.font = { name: FUENTE, size: esTitulo ? 16 : 11, bold: esTitulo || esSeccion, color: { argb: esTitulo || esSeccion ? MORADO : 'FF22124A' } };
});

// Hojas de datos
for (const hoja of estructura.hojas) {
  const ws = libro.addWorksheet(hoja.nombre, { views: [{ state: 'frozen', ySplit: 1 }], properties: { tabColor: { argb: MORADO_OSCURO } } });
  hoja.columnas.forEach((columna, i) => {
    const col = ws.getColumn(i + 1);
    col.width = columna.ancho ?? 16;
    if (formatos[columna.tipo]) col.numFmt = formatos[columna.tipo];

    const celda = ws.getCell(1, i + 1);
    celda.value = columna.obligatorio ? `${columna.titulo} *` : columna.titulo;
    celda.font = { name: FUENTE, size: 10, bold: true, color: { argb: 'FFFFFFFF' } };
    celda.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: columna.soloLectura ? TEXTO_SUAVE : MORADO } };
    celda.alignment = { vertical: 'middle', wrapText: true };
    celda.border = { bottom: { style: 'thin', color: { argb: MORADO_OSCURO } } };
    celda.note = {
      texts: [{ text: (columna.obligatorio ? 'Obligatorio. ' : '') + columna.ayuda }],
      margins: { insetmode: 'auto' },
    };

    const regla = validacion(columna);
    const rango = `${letra(i + 1)}2:${letra(i + 1)}${FILAS}`;
    if (regla) ws.dataValidations.add(rango, regla);
    if (columna.tipo === 'correo') {
      ws.dataValidations.add(rango, { type: 'custom', allowBlank: true, showErrorMessage: true, errorTitle: columna.titulo, error: 'Escriba un correo válido (nombre@empresa.co).', formulae: [`ISNUMBER(SEARCH("@",${letra(i + 1)}2))`] });
    }
  });
  ws.getRow(1).height = 32;
  ws.autoFilter = { from: { row: 1, column: 1 }, to: { row: 1, column: hoja.columnas.length } };

  // Fila de ejemplo: empieza por EJEMPLO y se ignora al importar.
  const fila = ws.getRow(2);
  hoja.columnas.forEach((columna, i) => {
    let valor = hoja.ejemplo[columna.clave];
    if (valor === undefined) return;
    if (columna.tipo === 'fecha') valor = fechaExcel(valor);
    if (columna.tipo === 'hora') valor = horaExcel(valor);
    fila.getCell(i + 1).value = valor;
  });
  fila.eachCell({ includeEmpty: true }, (celda, numero) => {
    if (numero > hoja.columnas.length) return;
    celda.font = { name: FUENTE, size: 10, italic: true, color: { argb: TEXTO_SUAVE } };
    celda.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: LAVANDA } };
    celda.border = { bottom: { style: 'hair', color: { argb: BORDE } } };
  });

}

// Listas (oculta): valores de las listas desplegables.
const listas = libro.addWorksheet('Listas', { state: 'hidden' });
nombresListas.forEach((nombre, i) => {
  listas.getCell(1, i + 1).value = nombre;
  listas.getCell(1, i + 1).font = { bold: true };
  estructura.listas[nombre].forEach((valor, j) => (listas.getCell(j + 2, i + 1).value = valor));
  listas.getColumn(i + 1).width = 28;
});

const destino = join(raiz, 'plantillas', estructura.nombre);
mkdirSync(dirname(destino), { recursive: true });
await libro.xlsx.writeFile(destino);
copyFileSync(destino, join(raiz, 'backend/resources/plantillas', estructura.nombre));
console.log(`Plantilla generada: ${destino}`);

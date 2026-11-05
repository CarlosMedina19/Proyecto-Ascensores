// Genera backend/resources/plantillas/SIGMA_Plantilla_Datos.xlsx a partir de estructura.json (misma carpeta).
// Uso: cd scripts && npm install && npm run plantilla
// La misma estructura la usa Laravel para exportar e importar, así que plantilla y sistema no se desalinean.
import { readFileSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import ExcelJS from 'exceljs';

// Raíz del backend (este script está en backend/scripts/plantilla).
const raiz = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const estructura = JSON.parse(readFileSync(join(raiz, 'resources/plantillas/estructura.json'), 'utf8'));

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
libro.description = 'Plantilla de SIGMA: una hoja por tipo de información; la hoja Diccionario describe cada columna.';
libro.keywords = 'SIGMA, ascensores, mantenimiento, plantilla, importar';
libro.created = new Date();

// Instrucciones
const instrucciones = libro.addWorksheet('Instrucciones', { properties: { tabColor: { argb: MORADO } } });
instrucciones.getColumn(1).width = 120;
estructura.instrucciones.forEach((texto, i) => {
  const celda = instrucciones.getCell(i + 1, 1);
  celda.value = texto;
  celda.alignment = { wrapText: true, vertical: 'top' };
  const esTitulo = i === 0;
  const esSeccion = (estructura.secciones ?? []).includes(texto);
  celda.font = { name: FUENTE, size: esTitulo ? 16 : 11, bold: esTitulo || esSeccion, color: { argb: esTitulo || esSeccion ? MORADO : 'FF22124A' } };
});

// Diccionario: una fila por columna de cada hoja (encabezado exacto, tipo, valores permitidos y ejemplo).
// Es la referencia para quien llena la plantilla a mano y para un asistente de IA.
const TIPOS = {
  texto: 'Texto',
  entero: 'Número entero',
  decimal: 'Número decimal (coma decimal)',
  moneda: 'Pesos colombianos sin decimales',
  fecha: 'Fecha (dd/mm/aaaa)',
  hora: 'Hora 24 h (hh:mm)',
  lista: 'Lista de valores',
  correo: 'Correo electrónico',
};
const LISTAS_DINAMICAS = {
  tecnicos: 'Nombre o correo de un técnico registrado en SIGMA',
  planes: 'Nombre de un plan activo en SIGMA',
  tiposAscensor: `Los tipos activos de Configuración (por defecto: ${estructura.listas.tiposAscensor.join(' | ')})`,
};
const textoEjemplo = (columna, valor) => {
  if (valor === undefined || valor === null) return '';
  if (columna.tipo === 'fecha') return valor.split('-').reverse().join('/');
  return String(valor);
};
const diccionario = libro.addWorksheet('Diccionario', { views: [{ state: 'frozen', ySplit: 1 }], properties: { tabColor: { argb: MORADO } } });
const encabezadosDiccionario = [
  ['Hoja', 18],
  ['Encabezado', 26],
  ['Clave', 20],
  ['Obligatorio', 12],
  ['Tipo de dato', 26],
  ['Valores permitidos o rango', 48],
  ['Descripción', 70],
  ['Ejemplo', 24],
];
encabezadosDiccionario.forEach(([titulo, ancho], i) => {
  const celda = diccionario.getCell(1, i + 1);
  celda.value = titulo;
  celda.font = { name: FUENTE, size: 10, bold: true, color: { argb: 'FFFFFFFF' } };
  celda.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: MORADO } };
  celda.alignment = { vertical: 'middle' };
  diccionario.getColumn(i + 1).width = ancho;
});
diccionario.getRow(1).height = 24;
let filaDiccionario = 2;
for (const hoja of estructura.hojas) {
  for (const columna of hoja.columnas) {
    let valores = '';
    if (columna.soloLectura) valores = 'Lo calcula SIGMA; al importar se ignora.';
    else if (columna.tipo === 'lista') valores = LISTAS_DINAMICAS[columna.lista] ?? estructura.listas[columna.lista].join(' | ');
    else if (['entero', 'decimal'].includes(columna.tipo) && columna.min !== undefined) valores = `De ${String(columna.min).replace('.', ',')} a ${columna.max}`;
    else if (columna.tipo === 'moneda') valores = `Mayor o igual a ${columna.min ?? 0}`;
    if (columna.tipo === 'lista' && columna.libre) valores += ' (acepta otro valor)';
    const fila = diccionario.getRow(filaDiccionario++);
    fila.values = [
      hoja.nombre,
      columna.titulo,
      columna.clave,
      columna.soloLectura ? 'No (calculado)' : columna.obligatorio ? 'Sí' : 'No',
      TIPOS[columna.tipo] ?? columna.tipo,
      valores,
      columna.ayuda,
      textoEjemplo(columna, hoja.ejemplo[columna.clave]),
    ];
    fila.eachCell((celda) => {
      celda.font = { name: FUENTE, size: 10, color: { argb: 'FF22124A' } };
      celda.alignment = { vertical: 'top', wrapText: true };
      celda.border = { bottom: { style: 'hair', color: { argb: BORDE } } };
    });
    if (columna.obligatorio && !columna.soloLectura) fila.getCell(4).font = { name: FUENTE, size: 10, bold: true, color: { argb: MORADO } };
  }
}
diccionario.autoFilter = { from: { row: 1, column: 1 }, to: { row: 1, column: encabezadosDiccionario.length } };

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

// La API la entrega con "Descargar plantilla vacía"; no hay otra copia en el repositorio.
const destino = join(raiz, 'resources/plantillas', estructura.nombre);
mkdirSync(dirname(destino), { recursive: true });
await libro.xlsx.writeFile(destino);
console.log(`Plantilla generada: ${destino}`);

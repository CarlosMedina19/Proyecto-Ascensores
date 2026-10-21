<?php

namespace App\Support\Excel;

use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;

/**
 * Estructura de la plantilla de Excel (resources/plantillas/estructura.json): hojas, columnas y listas.
 * La misma definición genera el archivo (scripts/generar-plantilla.mjs), y aquí se usa para exportar e importar.
 */
class Plantilla
{
    private static ?array $estructura = null;

    public static function estructura(): array
    {
        return self::$estructura ??= json_decode(file_get_contents(self::rutaEstructura()), true, flags: JSON_THROW_ON_ERROR);
    }

    public static function rutaEstructura(): string
    {
        return resource_path('plantillas/estructura.json');
    }

    public static function rutaArchivo(): string
    {
        return resource_path('plantillas/'.self::estructura()['nombre']);
    }

    /** Hojas en el orden de carga, indexadas por su clave (clientes, edificios…). */
    public static function hojas(): array
    {
        $hojas = [];
        foreach (self::estructura()['hojas'] as $hoja) {
            $hojas[$hoja['clave']] = $hoja;
        }

        return $hojas;
    }

    public static function hoja(string $clave): array
    {
        return self::hojas()[$clave];
    }

    /** Clave de la hoja según el nombre de la pestaña (ignora mayúsculas y tildes). */
    public static function claveDeHoja(string $nombre): ?string
    {
        foreach (self::hojas() as $clave => $hoja) {
            if (self::normalizar($hoja['nombre']) === self::normalizar($nombre)) {
                return $clave;
            }
        }

        return null;
    }

    /** Para un CSV (una sola hoja): la hoja cuyos encabezados coinciden mejor. */
    public static function claveSegunEncabezados(array $encabezados): ?string
    {
        $normalizados = array_map(fn ($e) => self::normalizar(self::sinAsterisco((string) $e)), $encabezados);
        $mejor = null;
        $puntaje = 0;
        foreach (self::hojas() as $clave => $hoja) {
            $titulos = array_map(fn ($c) => self::normalizar($c['titulo']), $hoja['columnas']);
            $coincidencias = count(array_intersect($titulos, $normalizados));
            $obligatorios = array_map(fn ($c) => self::normalizar($c['titulo']), array_filter($hoja['columnas'], fn ($c) => $c['obligatorio'] ?? false));
            if (array_diff($obligatorios, $normalizados) === [] && $coincidencias > $puntaje) {
                $mejor = $clave;
                $puntaje = $coincidencias;
            }
        }

        return $mejor;
    }

    /** Clave de cada columna según su título de encabezado (con o sin asterisco). */
    public static function mapaEncabezados(string $hoja, array $encabezados): array
    {
        $porTitulo = [];
        foreach (self::hoja($hoja)['columnas'] as $columna) {
            $porTitulo[self::normalizar($columna['titulo'])] = $columna['clave'];
        }
        $mapa = [];
        foreach ($encabezados as $indice => $encabezado) {
            $clave = $porTitulo[self::normalizar(self::sinAsterisco((string) $encabezado))] ?? null;
            if ($clave) {
                $mapa[$indice] = $clave;
            }
        }

        return $mapa;
    }

    public static function columna(string $hoja, string $clave): ?array
    {
        foreach (self::hoja($hoja)['columnas'] as $columna) {
            if ($columna['clave'] === $clave) {
                return $columna;
            }
        }

        return null;
    }

    public static function lista(string $nombre): array
    {
        return self::estructura()['listas'][$nombre] ?? [];
    }

    /** Letra de la columna de una lista en la hoja Listas (A, B, …) según el orden del JSON. */
    public static function columnaLista(string $nombre): string
    {
        $indice = array_search($nombre, array_keys(self::estructura()['listas']), true);

        return self::letra($indice + 1);
    }

    public static function letra(int $numero): string
    {
        $letra = '';
        for ($n = $numero; $n > 0; $n = intdiv($n - 1, 26)) {
            $letra = chr(65 + (($n - 1) % 26)).$letra;
        }

        return $letra;
    }

    /** Una fila es de ejemplo si su primer valor empieza por EJEMPLO. */
    public static function esEjemplo(array $valores): bool
    {
        foreach ($valores as $valor) {
            if ($valor !== null && trim((string) $valor) !== '') {
                return str_starts_with(mb_strtoupper(trim((string) $valor)), self::estructura()['marcadorEjemplo']);
            }
        }

        return false;
    }

    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto));
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return preg_replace('/\s+/', ' ', $texto);
    }

    private static function sinAsterisco(string $texto): string
    {
        return rtrim(trim($texto), ' *');
    }

    // ---------- Conversión de valores leídos de Excel o CSV ----------

    public static function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    /** Número con coma o punto decimal, con o sin símbolo de pesos y separador de miles. */
    public static function numero(mixed $valor): float|int|string|null
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_int($valor)) {
            return $valor;
        }
        if (is_float($valor)) {
            return floor($valor) === $valor && abs($valor) < PHP_INT_MAX ? (int) $valor : $valor;
        }
        $texto = str_replace(['$', 'COP', ' ', "\u{00A0}"], '', trim((string) $valor));
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d+)?$/', $texto)) {
            $texto = str_replace(['.', ','], ['', '.'], $texto); // 1.250.000,50
        } elseif (preg_match('/^-?\d+,\d+$/', $texto)) {
            $texto = str_replace(',', '.', $texto); // 1,5
        } elseif (preg_match('/^-?\d{1,3}(,\d{3})+$/', $texto)) {
            $texto = str_replace(',', '', $texto); // 1,250,000
        }

        if (! is_numeric($texto)) {
            return (string) $valor;
        }
        $numero = $texto + 0;

        return is_float($numero) && floor($numero) === $numero ? (int) $numero : $numero;
    }

    /** Fecha en formato Y-m-d: número de serie de Excel, 15/10/2026 o 2026-10-15. Si no se reconoce, devuelve el texto. */
    public static function fecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            return CarbonImmutable::instance(FechaExcel::excelToDateTimeObject((float) $valor))->toDateString();
        }
        $texto = trim((string) $valor);
        foreach (['d/m/Y', 'j/n/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'] as $formato) {
            $fecha = CarbonImmutable::createFromFormat('!'.$formato, $texto);
            if ($fecha && $fecha->format($formato) === $texto) {
                return $fecha->toDateString();
            }
        }

        return $texto;
    }

    /** Hora H:i: fracción del día de Excel, 7:30 o 07:30. */
    public static function hora(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }
        if (is_numeric($valor)) {
            $minutos = (int) round(fmod((float) $valor, 1) * 1440);

            return sprintf('%02d:%02d', intdiv($minutos, 60) % 24, $minutos % 60);
        }
        $texto = trim((string) $valor);
        if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $texto, $m)) {
            return sprintf('%02d:%02d', $m[1], $m[2]);
        }

        return $texto;
    }

    /** Valor interno de una etiqueta de lista; si no coincide, devuelve el texto para que la validación lo rechace. */
    public static function opcion(mixed $valor, array $opciones): ?string
    {
        $texto = self::texto($valor);
        if ($texto === null) {
            return null;
        }
        foreach ($opciones as $interno => $etiqueta) {
            if (self::normalizar($etiqueta) === self::normalizar($texto) || self::normalizar((string) $interno) === self::normalizar($texto)) {
                return (string) $interno;
            }
        }

        return $texto;
    }

    /** Valor de Excel para una fecha Y-m-d. */
    public static function aFechaExcel(?string $fecha): ?float
    {
        return $fecha ? FechaExcel::PHPToExcel(CarbonImmutable::parse($fecha)->startOfDay()) : null;
    }

    public static function aHoraExcel(?string $hora): ?float
    {
        if (! $hora) {
            return null;
        }
        [$h, $m] = array_map('intval', explode(':', $hora));

        return ($h * 60 + $m) / 1440;
    }
}

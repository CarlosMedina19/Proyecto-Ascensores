<?php

namespace App\Support\Excel;

use Maatwebsite\Excel\Concerns\SkipsUnknownSheets;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Excel as TipoExcel;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Lee un archivo .xlsx (todas las hojas de la plantilla, por su nombre) o un .csv (una hoja, reconocida por sus
 * encabezados) y devuelve filas con número de fila y valores por clave de columna.
 */
class LectorSigma implements SkipsUnknownSheets, WithMultipleSheets
{
    /** @var array<string, HojaLeida> */
    private array $hojas = [];

    public function sheets(): array
    {
        foreach (Plantilla::hojas() as $clave => $hoja) {
            $this->hojas[$clave] = new HojaLeida;
        }

        return collect(Plantilla::hojas())->mapWithKeys(fn ($hoja, $clave) => [$hoja['nombre'] => $this->hojas[$clave]])->all();
    }

    public function onUnknownSheet($nombreHoja): void
    {
        // Instrucciones, Listas u hojas propias del usuario: no se importan.
    }

    /**
     * @return array{hojas: array<string, array<int, array{fila: int, valores: array<string, mixed>}>>, avisos: array<int, string>}
     */
    public static function leer(string $ruta, string $extension): array
    {
        $avisos = [];
        if (in_array($extension, ['csv', 'txt'], true)) {
            $hoja = new HojaLeida(true, self::delimitador($ruta), self::codificacion($ruta));
            Excel::import($hoja, $ruta, null, TipoExcel::CSV);
            $clave = Plantilla::claveSegunEncabezados($hoja->filas[0] ?? []);
            if (! $clave) {
                return ['hojas' => [], 'avisos' => ['No se reconoció la hoja del archivo CSV. Use los encabezados de una hoja de la plantilla, con todos sus campos obligatorios.']];
            }

            return ['hojas' => [$clave => self::filas($clave, $hoja->filas)], 'avisos' => $avisos];
        }

        $lector = new self;
        Excel::import($lector, $ruta, null, TipoExcel::XLSX);
        $resultado = [];
        foreach ($lector->hojas as $clave => $hoja) {
            if ($hoja->filas !== []) {
                $resultado[$clave] = self::filas($clave, $hoja->filas);
            }
        }
        if ($resultado === []) {
            $avisos[] = 'El archivo no tiene datos en las hojas de la plantilla. Revise que las pestañas conserven sus nombres (Clientes, Ascensores…).';
        }

        return ['hojas' => $resultado, 'avisos' => $avisos];
    }

    /** Convierte las filas crudas en filas con clave de columna; ignora vacías y de ejemplo. */
    private static function filas(string $clave, array $crudas): array
    {
        $mapa = Plantilla::mapaEncabezados($clave, $crudas[0] ?? []);
        $filas = [];
        foreach (array_slice($crudas, 1, null, true) as $indice => $valores) {
            $valores = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $valores);
            if (count(array_filter($valores, fn ($v) => $v !== null && $v !== '')) === 0 || Plantilla::esEjemplo($valores)) {
                continue;
            }
            $porClave = [];
            foreach ($mapa as $posicion => $columna) {
                $porClave[$columna] = $valores[$posicion] ?? null;
            }
            $filas[] = ['fila' => $indice + 1, 'valores' => $porClave];
        }

        return $filas;
    }

    private static function delimitador(string $ruta): string
    {
        $linea = (string) fgets(fopen($ruta, 'r'));

        return substr_count($linea, ';') > substr_count($linea, ',') ? ';' : ',';
    }

    /** Excel en español guarda los CSV en Windows-1252; los demás, en UTF-8. */
    private static function codificacion(string $ruta): string
    {
        return mb_check_encoding((string) file_get_contents($ruta, false, null, 0, 65536), 'UTF-8') ? 'UTF-8' : 'Windows-1252';
    }
}

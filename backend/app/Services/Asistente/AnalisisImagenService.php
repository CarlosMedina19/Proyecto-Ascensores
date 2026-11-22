<?php

namespace App\Services\Asistente;

use App\Models\ChecklistItem;
use App\Models\ChecklistSeccion;
use Illuminate\Support\Str;

/**
 * Análisis local de una foto del ascensor (sin IA): mide luz, nitidez y colores de la imagen para señalar
 * posibles signos de óxido, grasa o aceite, y sugiere qué revisar según la zona (con las actividades de la
 * planilla). Es una ayuda para el técnico: con la IA de n8n activa, la foto la analiza el modelo con visión.
 */
class AnalisisImagenService
{
    /** Palabras del mensaje que indican la zona fotografiada (clave de la sección de la planilla). */
    private const ZONAS = [
        'puertas' => ['puerta', 'cerradura', 'quicio', 'operador', 'hall', 'electroiman', 'zapata de nylon'],
        'cabina' => ['cabina', 'botonera', 'boton', 'espejo', 'techo', 'piso de cabina', 'cortina', 'ventilador', 'nivelacion'],
        'cuarto_maquinas' => ['maquina', 'motor', 'cuarto', 'freno', 'tablero', 'control', 'polea', 'limitador', 'bomba', 'aceite', 'hidraulic', 'breaker'],
        'hueco' => ['cable', 'guaya', 'riel', 'guia', 'hueco', 'bracket', 'braket', 'final de carrera'],
        'foso' => ['foso', 'buffer', 'amortiguador', 'tensora', 'stop'],
        'contrapeso' => ['contrapeso'],
    ];

    /**
     * @return array{texto: string, hallazgos: list<string>, estado: string, zona: ?string, metricas: array}
     */
    public function analizar(string $ruta, string $mensaje = ''): array
    {
        $original = @imagecreatefromstring((string) file_get_contents($ruta));
        if ($original === false) {
            return [
                'texto' => 'No pude leer la imagen. Envíela en JPG, PNG o WEBP (desde la cámara o la galería).',
                'hallazgos' => [], 'estado' => 'ilegible', 'zona' => null, 'metricas' => [],
            ];
        }

        $metricas = $this->metricas($original);
        imagedestroy($original);
        $zona = $this->zona($mensaje);

        [$estado, $hallazgos] = $this->interpretar($metricas);
        $resumen = match ($estado) {
            'revisar' => 'Requiere revisión: la foto muestra señales que conviene verificar en sitio.',
            'repetir' => 'No es concluyente: la foto no deja ver bien el componente.',
            default => 'Sin señales visibles de deterioro en la foto.',
        };

        $lineas = ["Análisis de la foto · {$resumen}"];
        foreach ($hallazgos as $hallazgo) {
            $lineas[] = "• {$hallazgo}";
        }

        $revisar = $this->actividades($zona);
        if ($revisar) {
            $lineas[] = '';
            $lineas[] = 'Según la planilla ('.ChecklistSeccion::nombre($zona).'), revise en sitio:';
            foreach ($revisar as $actividad) {
                $lineas[] = "• {$actividad}";
            }
        } elseif ($zona === null) {
            $lineas[] = '';
            $lineas[] = 'Si me dice qué zona es (puertas, cabina, cuarto de máquinas, cables, foso…), le indico qué revisar según la planilla.';
        }

        $lineas[] = '';
        $lineas[] = 'Es un análisis automático de luz, nitidez y color (sin IA): orienta, pero no reemplaza la inspección del técnico.';

        return ['texto' => implode("\n", $lineas), 'hallazgos' => $hallazgos, 'estado' => $estado, 'zona' => $zona, 'metricas' => $metricas];
    }

    /** Mide la imagen reducida a 200 px: brillo, contraste, nitidez (varianza del laplaciano) y proporción de colores. */
    private function metricas(\GdImage $original): array
    {
        $ancho = imagesx($original);
        $alto = imagesy($original);
        $escala = min(1, 200 / max($ancho, $alto));
        $w = max(1, (int) round($ancho * $escala));
        $h = max(1, (int) round($alto * $escala));
        $imagen = imagecreatetruecolor($w, $h);
        imagecopyresampled($imagen, $original, 0, 0, 0, 0, $w, $h, $ancho, $alto);

        $gris = [];
        $suma = $sumaCuadrados = 0.0;
        $oxido = $oscuros = $quemados = 0;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgb = imagecolorat($imagen, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $luma = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $gris[$y][$x] = $luma;
                $suma += $luma;
                $sumaCuadrados += $luma * $luma;

                if ($luma < 35) {
                    $oscuros++;
                } elseif ($luma > 245) {
                    $quemados++;
                }

                [$tono, $saturacion, $valor] = $this->hsv($r, $g, $b);
                // Óxido: naranja a marrón rojizo, bastante saturado y ni muy oscuro ni muy claro.
                if ($tono >= 8 && $tono <= 40 && $saturacion >= 0.45 && $valor >= 0.25 && $valor <= 0.85) {
                    $oxido++;
                }
            }
        }
        imagedestroy($imagen);

        $total = $w * $h;
        $brillo = $suma / $total;
        $contraste = sqrt(max(0, $sumaCuadrados / $total - $brillo * $brillo));

        $laplacianos = [];
        for ($y = 1; $y < $h - 1; $y++) {
            for ($x = 1; $x < $w - 1; $x++) {
                $laplacianos[] = $gris[$y - 1][$x] + $gris[$y + 1][$x] + $gris[$y][$x - 1] + $gris[$y][$x + 1] - 4 * $gris[$y][$x];
            }
        }
        $nitidez = 0.0;
        if ($laplacianos) {
            $media = array_sum($laplacianos) / count($laplacianos);
            $nitidez = array_sum(array_map(fn ($v) => ($v - $media) ** 2, $laplacianos)) / count($laplacianos);
        }

        return [
            'ancho' => $ancho,
            'alto' => $alto,
            'brillo' => round($brillo, 1),
            'contraste' => round($contraste, 1),
            'nitidez' => round($nitidez, 1),
            'oxido' => round(100 * $oxido / $total, 1),
            'oscuro' => round(100 * $oscuros / $total, 1),
            'quemado' => round(100 * $quemados / $total, 1),
        ];
    }

    /** @return array{0: string, 1: list<string>} estado (bien, revisar o repetir) y hallazgos. */
    private function interpretar(array $m): array
    {
        $hallazgos = [];
        $calidad = false;

        if ($m['brillo'] < 55) {
            $hallazgos[] = 'La foto está muy oscura: use la linterna del celular o encienda la luz del cuarto o del foso.';
            $calidad = true;
        } elseif ($m['brillo'] > 210 || $m['quemado'] > 35) {
            $hallazgos[] = 'La foto está sobreexpuesta (mucho brillo): evite el reflejo directo del flash sobre superficies metálicas.';
            $calidad = true;
        }
        if ($m['nitidez'] < 30 || $m['contraste'] < 18) {
            $hallazgos[] = 'La foto se ve borrosa o sin detalle: acérquese, enfoque tocando el componente en la pantalla y mantenga el celular quieto.';
            $calidad = true;
        }

        $alerta = false;
        if ($m['oxido'] >= 6) {
            $hallazgos[] = "Hay tonos de óxido en el {$m['oxido']} % de la imagen: revise corrosión en cables, guías, soportes, bisagras o la lámina de las puertas.";
            $alerta = true;
        } elseif ($m['oxido'] >= 2) {
            $hallazgos[] = "Se ven algunos tonos de óxido ({$m['oxido']} % de la imagen): confirme si es corrosión superficial o el color propio de la pieza.";
            $alerta = true;
        }
        if ($m['oscuro'] >= 30 && $m['brillo'] >= 55) {
            $hallazgos[] = "Hay zonas muy oscuras ({$m['oscuro']} % de la imagen): pueden ser grasa o aceite acumulado, o una parte sin luz. Verifique fugas y limpieza.";
            $alerta = true;
        }

        if (! $hallazgos) {
            $hallazgos[] = 'La luz y el enfoque son adecuados y no se ven tonos de óxido ni manchas oscuras grandes.';
        }

        return [$alerta ? 'revisar' : ($calidad ? 'repetir' : 'bien'), $hallazgos];
    }

    private function zona(string $mensaje): ?string
    {
        $texto = mb_strtolower(Str::ascii($mensaje));
        foreach (self::ZONAS as $zona => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($texto, $palabra)) {
                    return $zona;
                }
            }
        }

        return null;
    }

    /** Hasta 5 actividades activas de la sección, primero las críticas. */
    private function actividades(?string $zona): array
    {
        if ($zona === null) {
            return [];
        }

        return ChecklistItem::where('seccion', $zona)->where('activo', true)
            ->orderByDesc('critico')->orderBy('orden')->limit(5)->get()
            ->map(fn ($item) => trim("{$item->codigo} {$item->nombre}").($item->critico ? ' (crítica)' : ''))
            ->all();
    }

    /** @return array{0: float, 1: float, 2: float} tono (0-360), saturación y valor (0-1). */
    private function hsv(int $r, int $g, int $b): array
    {
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $delta = $max - $min;
        $valor = $max / 255;
        $saturacion = $max === 0 ? 0.0 : $delta / $max;
        if ($delta === 0) {
            return [0.0, $saturacion, $valor];
        }
        $tono = match ($max) {
            $r => 60 * fmod(($g - $b) / $delta, 6),
            $g => 60 * (($b - $r) / $delta + 2),
            default => 60 * (($r - $g) / $delta + 4),
        };

        return [$tono < 0 ? $tono + 360 : $tono, $saturacion, $valor];
    }
}

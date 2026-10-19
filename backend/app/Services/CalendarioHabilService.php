<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Días hábiles en Colombia (RF-017: si la fecha cae en domingo o festivo,
 * se programa el siguiente día hábil). El sábado se considera hábil.
 *
 * Festivos según la Ley 51 de 1983 ("Ley Emiliani"):
 * - Fijos: 1 ene, 1 may, 20 jul, 7 ago, 8 dic y 25 dic.
 * - Trasladables al lunes siguiente: 6 ene, 19 mar, 29 jun, 15 ago, 12 oct, 1 nov y 11 nov.
 * - Según la Pascua: Jueves y Viernes Santo, y (trasladados a lunes) Ascensión,
 *   Corpus Christi y Sagrado Corazón.
 */
class CalendarioHabilService
{
    /** @var array<int, array<string, string>> */
    private array $cache = [];

    /**
     * @return array<string, string> fecha (Y-m-d) => nombre del festivo
     */
    public function festivos(int $anio): array
    {
        if (isset($this->cache[$anio])) {
            return $this->cache[$anio];
        }

        $fecha = fn (int $mes, int $dia) => CarbonImmutable::create($anio, $mes, $dia);
        $alLunes = fn (CarbonImmutable $dia) => $dia->isMonday() ? $dia : $dia->next(CarbonInterface::MONDAY);
        $pascua = $this->domingoDePascua($anio);

        $lista = [
            [$fecha(1, 1), 'Año Nuevo'],
            [$alLunes($fecha(1, 6)), 'Día de los Reyes Magos'],
            [$alLunes($fecha(3, 19)), 'Día de San José'],
            [$pascua->subDays(3), 'Jueves Santo'],
            [$pascua->subDays(2), 'Viernes Santo'],
            [$fecha(5, 1), 'Día del Trabajo'],
            [$pascua->addDays(43), 'Ascensión del Señor'],
            [$pascua->addDays(64), 'Corpus Christi'],
            [$pascua->addDays(71), 'Sagrado Corazón'],
            [$alLunes($fecha(6, 29)), 'San Pedro y San Pablo'],
            [$fecha(7, 20), 'Día de la Independencia'],
            [$fecha(8, 7), 'Batalla de Boyacá'],
            [$alLunes($fecha(8, 15)), 'La Asunción de la Virgen'],
            [$alLunes($fecha(10, 12)), 'Día de la Raza'],
            [$alLunes($fecha(11, 1)), 'Todos los Santos'],
            [$alLunes($fecha(11, 11)), 'Independencia de Cartagena'],
            [$fecha(12, 8), 'Inmaculada Concepción'],
            [$fecha(12, 25), 'Navidad'],
        ];

        $festivos = [];
        foreach ($lista as [$dia, $nombre]) {
            $festivos[$dia->toDateString()] = $nombre;
        }
        ksort($festivos);

        return $this->cache[$anio] = $festivos;
    }

    public function esFestivo(CarbonInterface $dia): bool
    {
        return array_key_exists($dia->toDateString(), $this->festivos($dia->year));
    }

    public function esHabil(CarbonInterface $dia): bool
    {
        return ! $dia->isSunday() && ! $this->esFestivo($dia);
    }

    /** La misma fecha si es hábil; si no, el siguiente día hábil. */
    public function siguienteHabil(CarbonInterface $dia): CarbonImmutable
    {
        $candidato = CarbonImmutable::parse($dia->toDateString());
        while (! $this->esHabil($candidato)) {
            $candidato = $candidato->addDay();
        }

        return $candidato;
    }

    /**
     * Domingo de Pascua (algoritmo anónimo gregoriano de Meeus/Jones/Butcher).
     */
    public function domingoDePascua(int $anio): CarbonImmutable
    {
        $a = $anio % 19;
        $b = intdiv($anio, 100);
        $c = $anio % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $mes = intdiv($h + $l - 7 * $m + 114, 31);
        $dia = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($anio, $mes, $dia);
    }
}

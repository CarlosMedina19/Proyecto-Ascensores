<?php

namespace Tests\Unit;

use App\Services\CalendarioHabilService;
use App\Services\ProgramacionService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * RF-017: cálculo de la próxima fecha con días hábiles y festivos de Colombia.
 */
class CalendarioProgramacionTest extends TestCase
{
    private CalendarioHabilService $calendario;

    private ProgramacionService $programacion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calendario = new CalendarioHabilService;
        $this->programacion = new ProgramacionService($this->calendario);
    }

    private function dia(string $fecha): CarbonImmutable
    {
        return CarbonImmutable::parse($fecha);
    }

    public function test_domingo_de_pascua(): void
    {
        $this->assertSame('2026-04-05', $this->calendario->domingoDePascua(2026)->toDateString());
        $this->assertSame('2027-03-28', $this->calendario->domingoDePascua(2027)->toDateString());
    }

    public function test_festivos_de_colombia_2026(): void
    {
        $festivos = $this->calendario->festivos(2026);

        foreach ([
            '2026-01-01', // Año nuevo
            '2026-01-12', // Reyes (6 de enero, trasladado al lunes)
            '2026-03-23', // San José
            '2026-04-02', // Jueves santo
            '2026-04-03', // Viernes santo
            '2026-05-01', // Día del trabajo
            '2026-05-18', // Ascensión
            '2026-06-08', // Corpus Christi
            '2026-06-15', // Sagrado Corazón
            '2026-06-29', // San Pedro y San Pablo
            '2026-07-20', // Independencia
            '2026-08-07', // Batalla de Boyacá
            '2026-08-17', // Asunción
            '2026-10-12', // Día de la Raza
            '2026-11-02', // Todos los Santos
            '2026-11-16', // Independencia de Cartagena
            '2026-12-08', // Inmaculada Concepción
            '2026-12-25', // Navidad
        ] as $fecha) {
            $this->assertArrayHasKey($fecha, $festivos, "Falta el festivo {$fecha}");
        }

        $this->assertCount(18, $festivos);
    }

    public function test_sabado_es_habil_domingo_y_festivo_no(): void
    {
        $this->assertTrue($this->calendario->esHabil($this->dia('2026-01-10')));
        $this->assertFalse($this->calendario->esHabil($this->dia('2026-01-11')));
        $this->assertFalse($this->calendario->esHabil($this->dia('2026-01-12')));
    }

    public function test_domingo_seguido_de_festivo_pasa_al_martes(): void
    {
        $this->assertSame('2026-01-13', $this->calendario->siguienteHabil($this->dia('2026-01-11'))->toDateString());
        $this->assertSame('2026-11-03', $this->calendario->siguienteHabil($this->dia('2026-11-01'))->toDateString());
    }

    public function test_semana_santa_salta_jueves_viernes_y_domingo(): void
    {
        $this->assertSame('2026-04-04', $this->calendario->siguienteHabil($this->dia('2026-04-02'))->toDateString());
        $this->assertSame('2026-04-06', $this->calendario->siguienteHabil($this->dia('2026-04-05'))->toDateString());
    }

    public function test_fin_de_mes_no_desborda(): void
    {
        $base = $this->dia('2026-01-31');

        $this->assertSame('2026-02-28', $this->programacion->fechaTeorica($base, 1)->toDateString());
        // Cada visita se calcula desde la base: el recorte de febrero no se arrastra.
        $this->assertSame('2026-03-31', $this->programacion->fechaTeorica($base, 1, 2)->toDateString());
        $this->assertSame('2028-02-29', $this->programacion->fechaTeorica($this->dia('2027-11-30'), 3)->toDateString());
    }

    public function test_proxima_fecha_segun_periodicidad(): void
    {
        $base = $this->dia('2026-01-15');

        foreach ([1 => '2026-02-16', 2 => '2026-03-16', 3 => '2026-04-15', 4 => '2026-05-15', 5 => '2026-06-16', 6 => '2026-07-15'] as $meses => $esperada) {
            // 15-feb, 15-mar y 15-jun de 2026 son domingo: pasan al lunes 16.
            $this->assertSame($esperada, $this->programacion->proximaFecha($base, $meses)->toDateString(), "Periodicidad de {$meses} mes(es)");
        }
    }
}

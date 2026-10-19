<?php

namespace App\Services;

use App\Models\AsignacionPlan;
use App\Models\MantenimientoProgramado;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;

/**
 * RF-017 y RF-018: cálculo del próximo mantenimiento y programación automática.
 *
 * - La próxima fecha es la fecha base (último mantenimiento ejecutado o inicio del plan)
 *   más la periodicidad. Si cae en domingo o festivo pasa al siguiente día hábil.
 * - Cada fecha se calcula desde la base y no desde la fecha ya corrida al día hábil,
 *   para que un festivo no desplace todas las visitas siguientes.
 * - No se programa nada fuera de la vigencia del plan ni del contrato asociado.
 * - Los mantenimientos ejecutados nunca se modifican.
 */
class ProgramacionService
{
    private const MAXIMO_VISITAS = 240;

    public function __construct(private readonly CalendarioHabilService $calendario) {}

    /**
     * Fecha teórica del mantenimiento número $numero contado desde la base.
     * Usa "sin desbordamiento": 31 de enero + 1 mes = 28 (o 29) de febrero.
     */
    public function fechaTeorica(CarbonInterface $base, int $meses, int $numero = 1): CarbonImmutable
    {
        return CarbonImmutable::parse($base->toDateString())->addMonthsNoOverflow($meses * $numero);
    }

    /** RF-017: próxima fecha hábil a partir de la base. */
    public function proximaFecha(CarbonInterface $base, int $meses): CarbonImmutable
    {
        return $this->calendario->siguienteHabil($this->fechaTeorica($base, $meses));
    }

    /** Última fecha en que se puede programar: fin del plan o del contrato, la que llegue primero. */
    public function fechaLimite(AsignacionPlan $asignacion): CarbonImmutable
    {
        $limite = CarbonImmutable::parse($asignacion->fecha_fin->toDateString());
        $contrato = $asignacion->contrato;

        if ($contrato && $contrato->fecha_fin->lt($limite)) {
            $limite = CarbonImmutable::parse($contrato->fecha_fin->toDateString());
        }

        return $limite;
    }

    /**
     * Fechas (teórica, programada) que corresponden a una asignación desde una base.
     *
     * @return list<array{teorica: CarbonImmutable, programada: CarbonImmutable}>
     */
    public function calcularFechas(AsignacionPlan $asignacion, ?CarbonInterface $base = null): array
    {
        $base ??= $asignacion->ultimo_mantenimiento ?? $asignacion->fecha_inicio;
        $meses = $asignacion->periodicidad->meses;
        $limite = $this->fechaLimite($asignacion);
        $fechas = [];

        for ($numero = 1; $numero <= self::MAXIMO_VISITAS; $numero++) {
            $teorica = $this->fechaTeorica($base, $meses, $numero);
            if ($teorica->gt($limite)) {
                break;
            }

            $programada = $this->calendario->siguienteHabil($teorica);
            if ($programada->gt($limite)) {
                break;
            }

            $fechas[] = ['teorica' => $teorica, 'programada' => $programada];
        }

        return $fechas;
    }

    /**
     * RF-018: (re)genera los mantenimientos pendientes de la asignación.
     * Cancela todos los pendientes y crea los nuevos desde la base. Los pendientes
     * anteriores a la base también se cancelan: un mantenimiento atrasado que se
     * ejecuta cubre las visitas que estaban vencidas.
     *
     * @return int cantidad de mantenimientos programados
     */
    public function programar(AsignacionPlan $asignacion, string $motivoCancelacion = 'Reprogramación automática', ?CarbonInterface $base = null): int
    {
        $asignacion->loadMissing(['periodicidad', 'contrato']);
        $base ??= $asignacion->ultimo_mantenimiento ?? $asignacion->fecha_inicio;

        // Las visitas que ya tienen una orden de trabajo en curso se conservan (fase 3).
        $asignacion->mantenimientos()
            ->pendientes()
            ->whereDoesntHave('ordenActiva')
            ->update([
                'estado' => 'cancelado',
                'motivo' => $motivoCancelacion,
                'actualizado_por' => Auth::id(),
                'updated_at' => now(),
            ]);
        $conservadas = $asignacion->mantenimientos()->pendientes()->pluck('fecha_teorica')
            ->map(fn ($fecha) => substr((string) $fecha, 0, 10))
            ->flip();

        $creados = 0;
        if ($asignacion->estado === 'activa') {
            foreach ($this->calcularFechas($asignacion, $base) as $fecha) {
                if (isset($conservadas[$fecha['teorica']->toDateString()])) {
                    continue;
                }
                MantenimientoProgramado::create([
                    'asignacion_id' => $asignacion->id,
                    'equipo_id' => $asignacion->equipo_id,
                    'fecha_teorica' => $fecha['teorica']->toDateString(),
                    'fecha_programada' => $fecha['programada']->toDateString(),
                    'precio' => $asignacion->precio,
                    'hora' => $asignacion->hora_preferida ?? '08:00',
                    'estado' => 'programado',
                    'actualizado_por' => Auth::id(),
                ]);
                $creados++;
            }
        }

        $this->actualizarProximo($asignacion);

        return $creados;
    }

    /** Cancela todos los mantenimientos pendientes (plan finalizado o cancelado). */
    public function cancelarPendientes(AsignacionPlan $asignacion, string $motivo): void
    {
        $asignacion->mantenimientos()->pendientes()->update([
            'estado' => 'cancelado',
            'motivo' => $motivo,
            'actualizado_por' => Auth::id(),
            'updated_at' => now(),
        ]);
        $this->actualizarProximo($asignacion);
    }

    /** Guarda en la asignación la fecha del próximo mantenimiento pendiente. */
    public function actualizarProximo(AsignacionPlan $asignacion): void
    {
        $proximo = $asignacion->mantenimientos()->pendientes()->orderBy('fecha_programada')->value('fecha_programada');
        $asignacion->forceFill(['proximo_mantenimiento' => $proximo])->saveQuietly();
    }
}

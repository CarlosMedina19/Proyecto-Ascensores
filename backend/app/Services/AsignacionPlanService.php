<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\AsignacionPlan;
use App\Models\CambioAsignacion;
use App\Models\Configuracion;
use App\Models\Contrato;
use App\Models\Equipo;
use App\Models\HistorialPrecio;
use App\Models\Periodicidad;
use App\Models\PlanMantenimiento;
use App\Models\Usuario;
use App\Support\Montos;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * RF-013 (asignación), RF-014 (vigencia), RF-015 (precios), RF-016 (cambio de plan).
 */
class AsignacionPlanService
{
    public function __construct(private readonly ProgramacionService $programacion) {}

    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = AsignacionPlan::with(['equipo.edificio.cliente', 'plan', 'periodicidad', 'contrato']);
        $usuario = Auth::user();

        if ($usuario instanceof Usuario && $usuario->esCliente()) {
            $consulta->whereHas('equipo.edificio', fn ($c) => $c->where('cliente_id', $usuario->cliente_id));
        }

        foreach (['equipo_id', 'plan_id', 'contrato_id', 'estado'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }

        if (! empty($filtros['cliente_id'])) {
            $consulta->whereHas('equipo.edificio', fn ($c) => $c->where('cliente_id', $filtros['cliente_id']));
        }

        return $consulta->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate($porPagina, ['*'], 'pagina');
    }

    /**
     * RF-013: asigna un plan a un ascensor activo y programa sus mantenimientos.
     */
    public function asignar(array $datos): AsignacionPlan
    {
        return DB::transaction(function () use ($datos) {
            $equipo = Equipo::with('edificio.cliente')->lockForUpdate()->findOrFail($datos['equipo_id']);
            $plan = PlanMantenimiento::findOrFail($datos['plan_id']);
            $periodicidad = Periodicidad::findOrFail($datos['periodicidad_id']);
            $contrato = isset($datos['contrato_id']) ? Contrato::with('equipos')->findOrFail($datos['contrato_id']) : null;

            $this->validarAsignacion($equipo, $plan, $periodicidad, $contrato);

            $precio = $datos['precio'] ?? $plan->precioPara($periodicidad->id);
            if ($precio === null) {
                throw new ReglaNegocioException("El plan «{$plan->nombre}» no tiene precio para la periodicidad «{$periodicidad->nombre}». Indique el precio.");
            }

            $fechaInicio = $datos['fecha_inicio'];
            $fechaFin = $datos['fecha_fin'] ?? $contrato?->fecha_fin?->toDateString()
                ?? CarbonImmutable::parse($fechaInicio)->addYear()->toDateString();

            $asignacion = AsignacionPlan::create([
                'equipo_id' => $equipo->id,
                'plan_id' => $plan->id,
                'periodicidad_id' => $periodicidad->id,
                'contrato_id' => $contrato?->id,
                'precio' => $precio,
                'aplica_iva' => $datos['aplica_iva'] ?? true,
                'porcentaje_iva' => ($datos['aplica_iva'] ?? true) ? ($datos['porcentaje_iva'] ?? Configuracion::valor('iva.porcentaje')) : 0,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'estado' => 'activa',
                'creado_por' => Auth::id(),
                'hora_preferida' => $datos['hora_preferida'] ?? '08:00',
            ]);

            HistorialPrecio::create([
                'asignacion_id' => $asignacion->id,
                'plan_id' => $plan->id,
                'periodicidad_id' => $periodicidad->id,
                'precio_anterior' => null,
                'precio_nuevo' => $precio,
                'motivo' => 'Precio pactado al asignar el plan',
                'usuario_id' => Auth::id(),
            ]);

            // RF-013: la asignación queda en el historial del ascensor.
            $equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'plan_asignado',
                'descripcion' => "Plan «{$plan->nombre}» cada {$periodicidad->meses} mes(es), vigente del {$fechaInicio} al {$fechaFin}.",
            ]);

            $this->programacion->programar($asignacion);
            RegistroAuditoriaService::registrar('creado', $asignacion, $asignacion->toArray());

            return $asignacion->fresh(['plan', 'periodicidad', 'contrato', 'equipo.edificio.cliente']);
        });
    }

    /**
     * RF-016: cambio de plan y/o periodicidad, conservando el historial. Aplica desde el
     * siguiente mantenimiento no ejecutado; los realizados no cambian.
     */
    public function cambiarPlan(AsignacionPlan $asignacion, array $datos): AsignacionPlan
    {
        $this->exigirActiva($asignacion);

        return DB::transaction(function () use ($asignacion, $datos) {
            $asignacion->load(['plan', 'periodicidad']);
            $plan = isset($datos['plan_id']) ? PlanMantenimiento::findOrFail($datos['plan_id']) : $asignacion->plan;
            $periodicidad = isset($datos['periodicidad_id']) ? Periodicidad::findOrFail($datos['periodicidad_id']) : $asignacion->periodicidad;

            if ($plan->id === $asignacion->plan_id && $periodicidad->id === $asignacion->periodicidad_id) {
                throw new ReglaNegocioException('Seleccione un plan o una periodicidad diferente a la actual.');
            }
            if (! $plan->activo) {
                throw new ReglaNegocioException('El plan seleccionado está inactivo.');
            }
            if (! $periodicidad->activo) {
                throw new ReglaNegocioException('La periodicidad seleccionada está inactiva.');
            }

            $motivo = $datos['motivo'];
            $cambios = [
                'plan' => [$asignacion->plan->nombre, $plan->nombre, $plan->id !== $asignacion->plan_id],
                'periodicidad' => ["{$asignacion->periodicidad->meses} meses", "{$periodicidad->meses} meses", $periodicidad->id !== $asignacion->periodicidad_id],
            ];
            foreach ($cambios as $campo => [$anterior, $nuevo, $cambio]) {
                if ($cambio) {
                    $this->registrarCambio($asignacion, $campo, $anterior, $nuevo, $motivo);
                }
            }

            // Cada periodicidad tiene su propio precio (RF-012): se toma el del catálogo.
            $precioNuevo = $plan->precioPara($periodicidad->id);
            if ($precioNuevo === null) {
                throw new ReglaNegocioException("El plan «{$plan->nombre}» no tiene precio para la periodicidad «{$periodicidad->nombre}».");
            }
            if (! Montos::iguales($precioNuevo, $asignacion->precio)) {
                HistorialPrecio::create([
                    'asignacion_id' => $asignacion->id,
                    'plan_id' => $plan->id,
                    'periodicidad_id' => $periodicidad->id,
                    'precio_anterior' => $asignacion->precio,
                    'precio_nuevo' => $precioNuevo,
                    'motivo' => "Cambio de plan o periodicidad: {$motivo}",
                    'usuario_id' => Auth::id(),
                ]);
            }

            $asignacion->update(['plan_id' => $plan->id, 'periodicidad_id' => $periodicidad->id, 'precio' => $precioNuevo]);
            $asignacion->unsetRelation('periodicidad');

            $asignacion->equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'cambio_plan',
                'descripcion' => "Plan «{$plan->nombre}» cada {$periodicidad->meses} mes(es). Motivo: {$motivo}",
            ]);

            $this->programacion->programar($asignacion, "Cambio de plan o periodicidad: {$motivo}");
            RegistroAuditoriaService::registrar('plan_cambiado', $asignacion, $asignacion->getChanges() + ['motivo' => $motivo]);

            return $asignacion->fresh(['plan', 'periodicidad', 'contrato', 'equipo.edificio.cliente']);
        });
    }

    /**
     * RF-015: cambio del precio pactado. Solo afecta los mantenimientos pendientes;
     * los ejecutados conservan su precio.
     */
    public function cambiarPrecio(AsignacionPlan $asignacion, string $precio, string $motivo): AsignacionPlan
    {
        $this->exigirActiva($asignacion);

        if (Montos::iguales($precio, $asignacion->precio)) {
            throw new ReglaNegocioException('El precio nuevo es igual al actual.');
        }

        return DB::transaction(function () use ($asignacion, $precio, $motivo) {
            HistorialPrecio::create([
                'asignacion_id' => $asignacion->id,
                'plan_id' => $asignacion->plan_id,
                'periodicidad_id' => $asignacion->periodicidad_id,
                'precio_anterior' => $asignacion->precio,
                'precio_nuevo' => $precio,
                'motivo' => $motivo,
                'usuario_id' => Auth::id(),
            ]);

            $anterior = $asignacion->precio;
            $asignacion->update(['precio' => $precio]);
            $asignacion->mantenimientos()->pendientes()->update(['precio' => $precio, 'updated_at' => now()]);

            RegistroAuditoriaService::registrar('precio_cambiado', $asignacion, [
                'precio_anterior' => $anterior,
                'precio_nuevo' => $precio,
                'motivo' => $motivo,
            ]);

            return $asignacion->fresh(['plan', 'periodicidad', 'contrato', 'equipo.edificio.cliente']);
        });
    }

    /** RF-014: cambia la fecha final de la vigencia y reprograma. */
    public function cambiarVigencia(AsignacionPlan $asignacion, string $fechaFin, string $motivo): AsignacionPlan
    {
        $this->exigirActiva($asignacion);

        if (CarbonImmutable::parse($fechaFin)->lte($asignacion->fecha_inicio)) {
            throw new ReglaNegocioException('La fecha final debe ser posterior a la fecha inicial.');
        }

        return DB::transaction(function () use ($asignacion, $fechaFin, $motivo) {
            $this->registrarCambio($asignacion, 'fecha_fin', $asignacion->fecha_fin->toDateString(), $fechaFin, $motivo);
            $asignacion->update(['fecha_fin' => $fechaFin]);
            $this->programacion->programar($asignacion, "Cambio de vigencia: {$motivo}");
            RegistroAuditoriaService::registrar('vigencia_cambiada', $asignacion, ['fecha_fin' => $fechaFin, 'motivo' => $motivo]);

            return $asignacion->fresh(['plan', 'periodicidad', 'contrato', 'equipo.edificio.cliente']);
        });
    }

    /** Termina la asignación y cancela los mantenimientos pendientes. */
    public function finalizar(AsignacionPlan $asignacion, string $motivo, string $estado = 'finalizada'): AsignacionPlan
    {
        $this->exigirActiva($asignacion);

        return DB::transaction(function () use ($asignacion, $motivo, $estado) {
            $this->registrarCambio($asignacion, 'estado', 'activa', $estado, $motivo);
            $asignacion->update(['estado' => $estado]);
            $this->programacion->cancelarPendientes($asignacion, "Plan {$estado}: {$motivo}");

            $asignacion->equipo->historial()->create([
                'usuario_id' => Auth::id(),
                'evento' => 'plan_finalizado',
                'descripcion' => "Plan «{$asignacion->plan->nombre}» {$estado}. Motivo: {$motivo}",
            ]);

            RegistroAuditoriaService::registrar('finalizado', $asignacion, ['estado' => $estado, 'motivo' => $motivo]);

            return $asignacion->fresh(['plan', 'periodicidad', 'contrato', 'equipo.edificio.cliente']);
        });
    }

    private function validarAsignacion(Equipo $equipo, PlanMantenimiento $plan, Periodicidad $periodicidad, ?Contrato $contrato): void
    {
        if ($equipo->estado === 'inactivo') {
            throw new ReglaNegocioException('El plan solo se asigna a ascensores activos.');
        }
        if (! $equipo->edificio?->cliente?->estado) {
            throw new ReglaNegocioException('El cliente del ascensor está inactivo.');
        }
        if ($equipo->asignacionActiva()->exists()) {
            throw new ReglaNegocioException('El ascensor ya tiene un plan activo. Use el cambio de plan.');
        }
        if (! $plan->activo) {
            throw new ReglaNegocioException('El plan seleccionado está inactivo.');
        }
        if (! $periodicidad->activo) {
            throw new ReglaNegocioException('La periodicidad seleccionada está inactiva.');
        }
        if ($contrato) {
            if ($contrato->estado !== 'vigente') {
                throw new ReglaNegocioException('El contrato seleccionado no está vigente.');
            }
            if (! $contrato->equipos->contains('id', $equipo->id)) {
                throw new ReglaNegocioException('El ascensor no pertenece al contrato seleccionado.');
            }
        }
    }

    private function registrarCambio(AsignacionPlan $asignacion, string $campo, ?string $anterior, ?string $nuevo, string $motivo): void
    {
        CambioAsignacion::create([
            'asignacion_id' => $asignacion->id,
            'usuario_id' => Auth::id(),
            'campo' => $campo,
            'valor_anterior' => $anterior,
            'valor_nuevo' => $nuevo,
            'motivo' => $motivo,
        ]);
    }

    private function exigirActiva(AsignacionPlan $asignacion): void
    {
        if ($asignacion->estado !== 'activa') {
            throw new ReglaNegocioException('La asignación no está activa.');
        }
    }
}

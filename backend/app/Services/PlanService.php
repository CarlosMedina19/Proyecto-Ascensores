<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\HistorialPrecio;
use App\Models\PlanMantenimiento;
use App\Models\PrecioPlan;
use App\Support\Montos;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * RF-010 (catálogo de planes), RF-012 (precios por periodicidad) y RF-015 (historial de precios).
 */
class PlanService
{
    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = PlanMantenimiento::with(['precios.periodicidad'])
            ->withCount(['asignaciones as asignaciones_activas_count' => fn ($c) => $c->where('estado', 'activa')]);

        if (! empty($filtros['busqueda'])) {
            $coincidencia = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $consulta->where('nombre', $coincidencia, "%{$filtros['busqueda']}%");
        }

        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $consulta->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }

        return $consulta->orderBy('nombre')->paginate($porPagina, ['*'], 'pagina');
    }

    /**
     * Crea el plan con sus precios iniciales (uno por periodicidad).
     *
     * @param  array{precios?: array<int, array{periodicidad_id: int, precio: numeric}>}  $datos
     */
    public function crear(array $datos): PlanMantenimiento
    {
        return DB::transaction(function () use ($datos) {
            $plan = PlanMantenimiento::create(collect($datos)->except('precios')->all());

            foreach ($datos['precios'] ?? [] as $precio) {
                $plan->precios()->create($precio);
                HistorialPrecio::create([
                    'plan_id' => $plan->id,
                    'periodicidad_id' => $precio['periodicidad_id'],
                    'precio_anterior' => null,
                    'precio_nuevo' => $precio['precio'],
                    'motivo' => 'Precio inicial del plan',
                    'usuario_id' => Auth::id(),
                ]);
            }

            RegistroAuditoriaService::registrar('creado', $plan, $plan->toArray() + ['precios' => $datos['precios'] ?? []]);

            return $plan->load('precios.periodicidad');
        });
    }

    public function actualizar(PlanMantenimiento $plan, array $datos): PlanMantenimiento
    {
        return DB::transaction(function () use ($plan, $datos) {
            $plan->update($datos);
            RegistroAuditoriaService::registrar('actualizado', $plan, $plan->getChanges());

            return $plan->fresh(['precios.periodicidad']);
        });
    }

    /**
     * RF-012 y RF-015: cambia los precios del catálogo. Cada cambio queda en el historial
     * con el precio anterior, el nuevo, el usuario y el motivo. Las asignaciones existentes
     * conservan el precio pactado (RF-014).
     *
     * @param  array<int, array{periodicidad_id: int, precio: numeric}>  $precios
     * @return int cantidad de precios que cambiaron
     */
    public function actualizarPrecios(PlanMantenimiento $plan, array $precios, string $motivo): int
    {
        return DB::transaction(function () use ($plan, $precios, $motivo) {
            $cambios = 0;

            foreach ($precios as $dato) {
                $actual = PrecioPlan::where('plan_id', $plan->id)
                    ->where('periodicidad_id', $dato['periodicidad_id'])
                    ->lockForUpdate()
                    ->first();

                $anterior = $actual?->precio;
                if ($anterior !== null && Montos::iguales($anterior, $dato['precio'])) {
                    continue;
                }

                PrecioPlan::updateOrCreate(
                    ['plan_id' => $plan->id, 'periodicidad_id' => $dato['periodicidad_id']],
                    ['precio' => $dato['precio']]
                );

                HistorialPrecio::create([
                    'plan_id' => $plan->id,
                    'periodicidad_id' => $dato['periodicidad_id'],
                    'precio_anterior' => $anterior,
                    'precio_nuevo' => $dato['precio'],
                    'motivo' => $motivo,
                    'usuario_id' => Auth::id(),
                ]);
                $cambios++;
            }

            if ($cambios > 0) {
                RegistroAuditoriaService::registrar('precios_actualizados', $plan, ['precios' => $precios, 'motivo' => $motivo]);
            }

            return $cambios;
        });
    }

    /** Un plan con asignaciones activas no se elimina; se desactiva. */
    public function eliminar(PlanMantenimiento $plan): void
    {
        if ($plan->asignaciones()->where('estado', 'activa')->exists()) {
            throw new ReglaNegocioException('El plan tiene ascensores asignados. Desactívelo en lugar de eliminarlo.');
        }

        DB::transaction(function () use ($plan) {
            $plan->delete();
            RegistroAuditoriaService::registrar('eliminado', $plan, []);
        });
    }
}

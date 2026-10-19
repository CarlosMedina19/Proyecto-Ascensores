<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Equipo;
use App\Models\SeguimientoRenovacion;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * RF-019 (contratos) y RF-020 (renovación).
 */
class ContratoService
{
    public function __construct(private readonly ProgramacionService $programacion) {}

    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = Contrato::with('cliente')->withCount('equipos');
        $usuario = Auth::user();

        if ($usuario instanceof Usuario && $usuario->esCliente()) {
            $consulta->where('cliente_id', $usuario->cliente_id);
        }

        foreach (['cliente_id', 'estado', 'estado_renovacion'] as $campo) {
            if (! empty($filtros[$campo])) {
                $consulta->where($campo, $filtros[$campo]);
            }
        }

        if (! empty($filtros['por_vencer'])) {
            $consulta->where('estado', 'vigente')
                ->whereDate('fecha_fin', '<=', now()->addDays((int) $filtros['por_vencer'])->toDateString());
        }

        if (! empty($filtros['busqueda'])) {
            $coincidencia = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $consulta->where(fn ($c) => $c->where('numero', $coincidencia, "%{$filtros['busqueda']}%")
                ->orWhereHas('cliente', fn ($cl) => $cl->where('nombre', $coincidencia, "%{$filtros['busqueda']}%")));
        }

        return $consulta->orderBy('fecha_fin')->orderBy('numero')->paginate($porPagina, ['*'], 'pagina');
    }

    public function crear(array $datos): Contrato
    {
        return DB::transaction(function () use ($datos) {
            $cliente = Cliente::findOrFail($datos['cliente_id']);
            if (! $cliente->estado) {
                throw new ReglaNegocioException('Un cliente inactivo no puede tener contratos nuevos.');
            }

            $this->validarEquipos($cliente, $datos['equipos'], $datos['fecha_inicio'], $datos['fecha_fin']);

            $contrato = Contrato::create([
                ...collect($datos)->except('equipos')->all(),
                'numero' => $this->siguienteNumero(),
                'estado' => 'vigente',
                'estado_renovacion' => 'pendiente',
                'creado_por' => Auth::id(),
            ]);
            $contrato->equipos()->sync($datos['equipos']);

            RegistroAuditoriaService::registrar('creado', $contrato, $contrato->toArray() + ['equipos' => $datos['equipos']]);

            return $contrato->load(['cliente', 'equipos.edificio']);
        });
    }

    public function actualizar(Contrato $contrato, array $datos): Contrato
    {
        if ($contrato->estado !== 'vigente') {
            throw new ReglaNegocioException('Solo se pueden editar contratos vigentes.');
        }

        return DB::transaction(function () use ($contrato, $datos) {
            $inicio = $datos['fecha_inicio'] ?? $contrato->fecha_inicio->toDateString();
            $fin = $datos['fecha_fin'] ?? $contrato->fecha_fin->toDateString();
            $equipos = $datos['equipos'] ?? $contrato->equipos()->pluck('equipos.id')->all();

            $this->validarEquipos($contrato->cliente, $equipos, $inicio, $fin, $contrato->id);

            $contrato->update(collect($datos)->except('equipos')->all());
            if (isset($datos['equipos'])) {
                $contrato->equipos()->sync($datos['equipos']);
            }

            // La vigencia del contrato limita la programación de sus planes.
            if ($contrato->wasChanged('fecha_fin')) {
                $contrato->update(['alerta_vencimiento_en' => null]);
                foreach ($contrato->asignaciones()->activas()->get() as $asignacion) {
                    $this->programacion->programar($asignacion, 'Cambio en la vigencia del contrato');
                }
            }

            RegistroAuditoriaService::registrar('actualizado', $contrato, $contrato->getChanges() + (isset($datos['equipos']) ? ['equipos' => $datos['equipos']] : []));

            return $contrato->fresh(['cliente', 'equipos.edificio']);
        });
    }

    /**
     * RF-020: seguimiento de la renovación (contactado o no renovado).
     */
    public function registrarSeguimiento(Contrato $contrato, string $resultado, ?string $observacion): SeguimientoRenovacion
    {
        if (in_array($contrato->estado, ['renovado', 'cancelado'], true)) {
            throw new ReglaNegocioException('El contrato ya fue renovado o cancelado.');
        }

        return DB::transaction(function () use ($contrato, $resultado, $observacion) {
            $seguimiento = $contrato->seguimientos()->create([
                'usuario_id' => Auth::id(),
                'resultado' => $resultado,
                'observacion' => $observacion,
            ]);
            $contrato->update(['estado_renovacion' => $resultado]);
            RegistroAuditoriaService::registrar('seguimiento_renovacion', $contrato, ['resultado' => $resultado, 'observacion' => $observacion]);

            return $seguimiento->load('usuario');
        });
    }

    /**
     * RF-020: renueva el contrato. Crea el contrato nuevo con los mismos equipos y
     * extiende los planes activos vinculados hasta la nueva fecha final.
     */
    public function renovar(Contrato $contrato, array $datos): Contrato
    {
        if (! in_array($contrato->estado, ['vigente', 'vencido'], true)) {
            throw new ReglaNegocioException('Solo se renuevan contratos vigentes o vencidos.');
        }

        return DB::transaction(function () use ($contrato, $datos) {
            $inicio = $datos['fecha_inicio'] ?? $contrato->fecha_fin->copy()->addDay()->toDateString();
            if (CarbonImmutable::parse($inicio)->lte($contrato->fecha_fin) && $contrato->estado === 'vigente') {
                // El contrato anterior termina el día antes de que empiece el nuevo.
                $contrato->update(['fecha_fin' => CarbonImmutable::parse($inicio)->subDay()->toDateString()]);
            }

            $contrato->update(['estado' => 'renovado', 'estado_renovacion' => 'renovado']);
            $contrato->seguimientos()->create([
                'usuario_id' => Auth::id(),
                'resultado' => 'renovado',
                'observacion' => $datos['observacion'] ?? null,
            ]);

            $equipos = $contrato->equipos()->pluck('equipos.id')->all();
            $nuevo = $this->crear([
                'cliente_id' => $contrato->cliente_id,
                'contrato_anterior_id' => $contrato->id,
                'fecha_inicio' => $inicio,
                'fecha_fin' => $datos['fecha_fin'],
                'valor' => $datos['valor'] ?? $contrato->valor,
                'servicios' => $datos['servicios'] ?? $contrato->servicios,
                'condiciones' => $datos['condiciones'] ?? $contrato->condiciones,
                'equipos' => $equipos,
            ]);

            foreach ($contrato->asignaciones()->activas()->get() as $asignacion) {
                $asignacion->update([
                    'contrato_id' => $nuevo->id,
                    'fecha_fin' => max($asignacion->fecha_fin->toDateString(), $nuevo->fecha_fin->toDateString()),
                ]);
                $this->programacion->programar($asignacion->fresh(), "Renovación del contrato {$contrato->numero}");
            }

            RegistroAuditoriaService::registrar('renovado', $contrato, ['contrato_nuevo' => $nuevo->numero]);

            return $nuevo->fresh(['cliente', 'equipos.edificio', 'contratoAnterior']);
        });
    }

    public function cancelar(Contrato $contrato, string $motivo): Contrato
    {
        if ($contrato->estado !== 'vigente') {
            throw new ReglaNegocioException('Solo se cancelan contratos vigentes.');
        }

        return DB::transaction(function () use ($contrato, $motivo) {
            $contrato->update(['estado' => 'cancelado']);
            foreach ($contrato->asignaciones()->activas()->get() as $asignacion) {
                $this->programacion->cancelarPendientes($asignacion, "Contrato {$contrato->numero} cancelado: {$motivo}");
            }
            RegistroAuditoriaService::registrar('cancelado', $contrato, ['motivo' => $motivo]);

            return $contrato->fresh(['cliente', 'equipos.edificio']);
        });
    }

    /** RF-019: los equipos son del cliente y no están en otro contrato vigente en las mismas fechas. */
    private function validarEquipos(Cliente $cliente, array $equipoIds, string $inicio, string $fin, ?int $excluirContrato = null): void
    {
        $equipos = Equipo::with('edificio')->whereIn('id', $equipoIds)->get();

        $ajenos = $equipos->filter(fn (Equipo $e) => $e->edificio?->cliente_id !== $cliente->id);
        if ($ajenos->isNotEmpty()) {
            throw new ReglaNegocioException('Estos ascensores no pertenecen al cliente: '.$ajenos->pluck('codigo')->implode(', ').'.');
        }

        $ocupados = DB::table('contrato_equipos')
            ->join('contratos', 'contratos.id', '=', 'contrato_equipos.contrato_id')
            ->join('equipos', 'equipos.id', '=', 'contrato_equipos.equipo_id')
            ->whereIn('contrato_equipos.equipo_id', $equipoIds)
            ->where('contratos.estado', 'vigente')
            ->when($excluirContrato, fn ($c) => $c->where('contratos.id', '!=', $excluirContrato))
            ->whereDate('contratos.fecha_inicio', '<=', $fin)
            ->whereDate('contratos.fecha_fin', '>=', $inicio)
            ->select('equipos.codigo', 'contratos.numero')
            ->get();

        if ($ocupados->isNotEmpty()) {
            $detalle = $ocupados->map(fn ($o) => "{$o->codigo} ({$o->numero})")->implode(', ');
            throw new ReglaNegocioException("Estos ascensores ya están en otro contrato vigente en esas fechas: {$detalle}.");
        }
    }

    /** Número único y consecutivo por año: CT-2026-0001. */
    private function siguienteNumero(): string
    {
        $anio = now()->year;
        if (DB::getDriverName() === 'pgsql') {
            DB::select('SELECT pg_advisory_xact_lock(?)', [crc32("contratos-{$anio}")]);
        }

        $ultimo = Contrato::where('numero', 'like', "CT-{$anio}-%")->orderByDesc('numero')->value('numero');
        $consecutivo = $ultimo ? ((int) substr($ultimo, -4)) + 1 : 1;

        return sprintf('CT-%d-%04d', $anio, $consecutivo);
    }
}

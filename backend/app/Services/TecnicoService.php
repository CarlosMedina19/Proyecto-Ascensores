<?php

namespace App\Services;

use App\Exceptions\ReglaNegocioException;
use App\Models\OrdenTrabajo;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RF-022: técnicos con datos personales, especialidades, disponibilidad y estado.
 * Un técnico es un usuario de rol "tecnico" con su perfil; sin perfil completo no recibe órdenes.
 */
class TecnicoService
{
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        $consulta = Usuario::with(['tecnico.especialidades'])
            ->whereHas('rol', fn ($rol) => $rol->where('nombre', 'tecnico'));

        if (! empty($filtros['busqueda'])) {
            $como = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $texto = "%{$filtros['busqueda']}%";
            $consulta->where(fn ($c) => $c->where('nombre', $como, $texto)
                ->orWhere('correo', $como, $texto)
                ->orWhereHas('tecnico', fn ($t) => $t->where('numero_documento', $como, $texto)));
        }
        if (isset($filtros['activo']) && $filtros['activo'] !== '') {
            $consulta->where('activo', filter_var($filtros['activo'], FILTER_VALIDATE_BOOLEAN));
        }
        if (! empty($filtros['disponibilidad'])) {
            $consulta->whereHas('tecnico', fn ($t) => $t->where('disponibilidad', $filtros['disponibilidad']));
        }
        if (! empty($filtros['especialidad_id'])) {
            $consulta->whereHas('tecnico.especialidades', fn ($e) => $e->whereKey($filtros['especialidad_id']));
        }

        return $consulta->orderBy('nombre')->paginate($porPagina, ['*'], 'pagina');
    }

    /** Registra la cuenta del técnico y su perfil en una sola operación. */
    public function crear(array $datos): Usuario
    {
        return DB::transaction(function () use ($datos) {
            $usuario = $this->usuarios->crear([
                'nombre' => $datos['nombre'],
                'correo' => $datos['correo'],
                'password' => $datos['password'],
                'rol_id' => Rol::where('nombre', 'tecnico')->value('id'),
                'activo' => $datos['activo'] ?? true,
            ]);

            return $this->guardarPerfil($usuario, $datos);
        });
    }

    /** Crea o actualiza el perfil (documento, teléfono, especialidades y disponibilidad). */
    public function guardarPerfil(Usuario $usuario, array $datos): Usuario
    {
        if (! $usuario->esTecnico()) {
            throw new ReglaNegocioException('Solo los usuarios con rol técnico tienen perfil de técnico.');
        }

        return DB::transaction(function () use ($usuario, $datos) {
            $tecnico = $usuario->tecnico ?? new Tecnico(['usuario_id' => $usuario->id]);
            $anterior = $tecnico->exists ? $tecnico->only(['numero_documento', 'telefono', 'disponibilidad']) : [];

            $tecnico->fill(collect($datos)->only([
                'tipo_documento', 'numero_documento', 'telefono', 'disponibilidad',
                'motivo_no_disponible', 'no_disponible_hasta', 'observaciones',
            ])->all());

            if (($tecnico->disponibilidad ?? 'disponible') === 'disponible') {
                $tecnico->motivo_no_disponible = null;
                $tecnico->no_disponible_hasta = null;
            }
            $tecnico->save();

            if (array_key_exists('especialidades', $datos)) {
                $tecnico->especialidades()->sync($datos['especialidades']);
            }
            if ($tecnico->especialidades()->doesntExist()) {
                throw new ReglaNegocioException('El técnico debe tener al menos una especialidad.');
            }

            RegistroAuditoriaService::registrar(
                $anterior ? 'actualizado' : 'creado',
                $tecnico,
                ['anterior' => $anterior, 'nuevo' => $tecnico->only(['numero_documento', 'telefono', 'disponibilidad']),
                    'especialidades' => $tecnico->especialidades()->pluck('nombre')->all()],
                null,
                'tecnicos'
            );

            return $usuario->fresh(['tecnico.especialidades']);
        });
    }

    /** Activa o desactiva la cuenta del técnico; desactivado deja de recibir órdenes y pierde sus sesiones. */
    public function cambiarEstado(Usuario $usuario, bool $activo): Usuario
    {
        if (! $usuario->esTecnico()) {
            throw new ReglaNegocioException('El usuario no es un técnico.');
        }

        $this->usuarios->actualizar($usuario, ['activo' => $activo]);

        return $usuario->fresh(['tecnico.especialidades']);
    }

    /** Órdenes activas de un técnico (para advertir antes de marcarlo no disponible). */
    public function ordenesActivas(Tecnico $tecnico): int
    {
        return $tecnico->ordenes()->whereNotIn('estado', OrdenTrabajo::FINALES)->count();
    }

    /**
     * RF-022 y RF-023: técnicos que pueden recibir una orden en un horario, con sus cruces.
     *
     * @return Collection<int, array{tecnico: Tecnico, asignable: bool, motivo: ?string, conflictos: array}>
     */
    public function disponibilidad(CarbonImmutable $inicio, int $minutos, ?int $excluirOrden = null): Collection
    {
        $fin = $inicio->addMinutes($minutos);

        return Tecnico::with(['usuario', 'especialidades'])
            ->whereHas('usuario', fn ($u) => $u->whereHas('rol', fn ($r) => $r->where('nombre', 'tecnico')))
            ->get()
            ->sortBy(fn (Tecnico $t) => $t->usuario?->nombre)
            ->map(function (Tecnico $tecnico) use ($inicio, $fin, $excluirOrden) {
                $conflictos = $this->conflictos($tecnico, $inicio, $fin, $excluirOrden);
                $motivo = match (true) {
                    ! $tecnico->usuario?->activo => 'Técnico inactivo',
                    $tecnico->disponibilidad !== 'disponible' => 'No disponible'.($tecnico->motivo_no_disponible ? ": {$tecnico->motivo_no_disponible}" : ''),
                    $conflictos->isNotEmpty() => 'Tiene otra orden en ese horario',
                    default => null,
                };

                return [
                    'tecnico' => $tecnico,
                    'asignable' => $motivo === null,
                    'motivo' => $motivo,
                    'conflictos' => $conflictos->map(fn (OrdenTrabajo $o) => [
                        'id' => $o->id,
                        'numero' => $o->numero,
                        'inicio' => $o->inicioProgramado()->format('H:i'),
                        'fin' => $o->finProgramado()->format('H:i'),
                    ])->values()->all(),
                ];
            })
            ->values();
    }

    /**
     * RF-023: órdenes activas del técnico que se cruzan con el horario indicado.
     *
     * @return Collection<int, OrdenTrabajo>
     */
    public function conflictos(Tecnico $tecnico, CarbonImmutable $inicio, CarbonImmutable $fin, ?int $excluirOrden = null): Collection
    {
        return $tecnico->ordenes()
            ->whereNotIn('estado', OrdenTrabajo::FINALES)
            ->whereBetween('fecha', [$inicio->subDay()->toDateString(), $fin->toDateString()])
            ->when($excluirOrden, fn ($c) => $c->where('ordenes_trabajo.id', '!=', $excluirOrden))
            ->get()
            ->filter(fn (OrdenTrabajo $o) => $o->inicioProgramado()->lt($fin) && $inicio->lt($o->finProgramado()))
            ->values();
    }
}

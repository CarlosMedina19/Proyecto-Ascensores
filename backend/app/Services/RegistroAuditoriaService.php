<?php

namespace App\Services;

use App\Models\Ascensor;
use App\Models\AsignacionPlan;
use App\Models\Cliente;
use App\Models\Configuracion;
use App\Models\ContactoCliente;
use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\HistorialEquipo;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Periodicidad;
use App\Models\PlanMantenimiento;
use App\Models\RegistroAuditoria;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * RF-004: registra usuario, acción, módulo, fecha, IP, registro afectado y cambios.
 */
class RegistroAuditoriaService
{
    private const MODULOS = [
        Usuario::class => 'usuarios',
        Rol::class => 'roles',
        Cliente::class => 'clientes',
        ContactoCliente::class => 'clientes',
        Edificio::class => 'edificios',
        Equipo::class => 'equipos',
        Ascensor::class => 'equipos',
        HistorialEquipo::class => 'equipos',
        PlanMantenimiento::class => 'planes',
        AsignacionPlan::class => 'planes',
        MantenimientoProgramado::class => 'mantenimientos',
        Contrato::class => 'contratos',
        Configuracion::class => 'configuracion',
        Periodicidad::class => 'configuracion',
        Tecnico::class => 'tecnicos',
        OrdenTrabajo::class => 'ordenes',
    ];

    private const CAMPOS_OCULTOS = ['password', 'remember_token'];

    public static function registrar(
        string $accion,
        Model $modelo,
        ?array $cambios = null,
        ?int $usuarioId = null,
        ?string $modulo = null
    ): RegistroAuditoria {
        $cambios = collect($cambios ?? $modelo->getChanges())
            ->except(self::CAMPOS_OCULTOS)
            ->all();

        return RegistroAuditoria::create([
            'usuario_id' => $usuarioId ?? Auth::id(),
            'accion' => $accion,
            'modulo' => $modulo ?? self::MODULOS[$modelo::class] ?? 'sistema',
            'modelo' => $modelo::class,
            'modelo_id' => $modelo->getKey(),
            'cambios' => $cambios ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    public static function modulos(): array
    {
        return array_values(array_unique([...array_values(self::MODULOS), 'autenticacion']));
    }
}

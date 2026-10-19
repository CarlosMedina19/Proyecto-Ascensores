<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\MantenimientoProgramado;
use App\Models\OrdenTrabajo;
use App\Models\Periodicidad;
use App\Models\PlanMantenimiento;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\Usuario;
use App\Services\AsignacionPlanService;
use App\Services\ContratoService;
use App\Services\OrdenTrabajoService;
use App\Services\PlanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Datos de demostración para desarrollo local y pruebas de las interfaces.
 *
 *   php artisan db:seed --class=DatosDemoSeeder
 *
 * Crea un usuario por rol con la contraseña definida en SIGMA_DEMO_PASSWORD.
 * Nunca se ejecuta en producción.
 */
class DatosDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DatosDemoSeeder no se ejecuta en producción.');
        }

        $password = env('SIGMA_DEMO_PASSWORD');

        if (! is_string($password) || strlen($password) < 8) {
            throw new RuntimeException('Configura SIGMA_DEMO_PASSWORD (mínimo 8 caracteres) para crear los usuarios de demostración.');
        }

        $this->call([RolSeeder::class, PermisoSeeder::class, PeriodicidadSeeder::class, ConfiguracionSeeder::class, EspecialidadSeeder::class]);

        $clientes = [
            [
                'datos' => ['tipo' => 'juridica', 'nombre' => 'Conjunto Residencial Mirador del Parque', 'tipo_documento' => 'NIT', 'nit' => '900123456-1', 'direccion' => 'Cra 15 # 100-20', 'telefono' => '6017001122', 'correo' => 'administracion@miradorparque.co', 'regimen_tributario' => 'Responsable de IVA'],
                'edificios' => [
                    ['nombre' => 'Torre A', 'direccion' => 'Cra 15 # 100-20', 'ciudad' => 'Bogotá', 'departamento' => 'Cundinamarca', 'pisos' => 18, 'nombre_contacto' => 'Laura Gómez', 'telefono_contacto' => '3101234567'],
                    ['nombre' => 'Torre B', 'direccion' => 'Cra 15 # 100-40', 'ciudad' => 'Bogotá', 'departamento' => 'Cundinamarca', 'pisos' => 18, 'nombre_contacto' => 'Laura Gómez', 'telefono_contacto' => '3101234567'],
                ],
            ],
            [
                'datos' => ['tipo' => 'juridica', 'nombre' => 'Centro Empresarial Andino', 'tipo_documento' => 'NIT', 'nit' => '901555222-3', 'direccion' => 'Calle 72 # 10-07', 'telefono' => '6014445566', 'correo' => 'mantenimiento@andino.co', 'regimen_tributario' => 'Gran contribuyente'],
                'edificios' => [
                    ['nombre' => 'Edificio Andino', 'direccion' => 'Calle 72 # 10-07', 'ciudad' => 'Bogotá', 'departamento' => 'Cundinamarca', 'pisos' => 24, 'nombre_contacto' => 'Carlos Ruiz', 'correo_contacto' => 'cruiz@andino.co'],
                ],
            ],
            [
                'datos' => ['tipo' => 'natural', 'nombre' => 'María Fernanda López', 'tipo_documento' => 'CC', 'numero_documento' => '52123456', 'direccion' => 'Calle 10 # 43-12', 'telefono' => '3157778899', 'correo' => 'mflopez@correo.co'],
                'edificios' => [
                    ['nombre' => 'Edificio Poblado Real', 'direccion' => 'Calle 10 # 43-12', 'ciudad' => 'Medellín', 'departamento' => 'Antioquia', 'pisos' => 8, 'nombre_contacto' => 'María Fernanda López', 'telefono_contacto' => '3157778899'],
                ],
            ],
        ];

        $marcas = ['Otis', 'Schindler', 'Mitsubishi', 'Kone', 'ThyssenKrupp'];
        $estados = ['activo', 'activo', 'activo', 'mantenimiento', 'inactivo'];
        $consecutivo = 1;
        $primerCliente = null;

        foreach ($clientes as $definicion) {
            $cliente = Cliente::firstOrCreate(
                ['nombre' => $definicion['datos']['nombre']],
                $definicion['datos'] + ['estado' => true]
            );
            $primerCliente ??= $cliente;

            if ($cliente->contactos()->doesntExist()) {
                $cliente->contactos()->create([
                    'nombre' => $definicion['edificios'][0]['nombre_contacto'],
                    'cargo' => 'Administrador(a)',
                    'telefono' => $definicion['edificios'][0]['telefono_contacto'] ?? null,
                    'correo' => $definicion['edificios'][0]['correo_contacto'] ?? null,
                ]);
            }

            foreach ($definicion['edificios'] as $datosEdificio) {
                $edificio = Edificio::firstOrCreate(
                    ['cliente_id' => $cliente->id, 'nombre' => $datosEdificio['nombre']],
                    $datosEdificio
                );

                foreach (range(1, 2) as $numero) {
                    $codigo = sprintf('ASC-%03d', $consecutivo++);
                    $marca = $marcas[$consecutivo % count($marcas)];

                    $equipo = Equipo::firstOrCreate(['codigo' => $codigo], [
                        'edificio_id' => $edificio->id,
                        'tipo' => 'ascensor',
                        'marca' => $marca,
                        'modelo' => 'Serie '.(1000 + $consecutivo * 7),
                        'numero_serie' => 'SN-'.strtoupper(substr(md5($codigo), 0, 8)),
                        'ubicacion' => "{$edificio->nombre} — Ascensor {$numero}",
                        'estado' => $estados[$consecutivo % count($estados)],
                        'fecha_instalacion' => now()->subYears(2 + $consecutivo % 8)->toDateString(),
                    ]);

                    if ($equipo->wasRecentlyCreated) {
                        $equipo->ascensor()->create([
                            'capacidad_kg' => [450, 630, 800, 1000][$consecutivo % 4],
                            'velocidad_mpm' => [60, 90, 105, 150][$consecutivo % 4],
                            'paradas' => $edificio->pisos,
                            'tipo_traccion' => $consecutivo % 3 === 0 ? 'Hidráulico' : 'Eléctrico de tracción',
                            'motor' => 'Motor sin engranajes (gearless)',
                            'controlador' => 'Control de maniobra colectiva selectiva',
                            'tipo_puerta' => 'Automática de apertura central',
                            'puertas_cabina' => 1,
                            'puertas_piso' => $edificio->pisos,
                            'operador_puertas' => 'Operador VVVF',
                        ]);

                        $equipo->historial()->create([
                            'evento' => 'alta_equipo',
                            'descripcion' => "Ascensor registrado con código {$equipo->codigo}.",
                        ]);
                    }
                }
            }
        }

        $usuarios = [
            'coordinador' => ['nombre' => 'Coordinador Demo', 'correo' => 'coordinador@gmail.com'],
            'tecnico' => ['nombre' => 'Técnico Demo', 'correo' => 'tecnico@gmail.com'],
            'supervisor' => ['nombre' => 'Supervisor Demo', 'correo' => 'supervisor@gmail.com'],
            'contador' => ['nombre' => 'Contador Demo', 'correo' => 'contador@gmail.com'],
            'cliente' => ['nombre' => 'Cliente Demo', 'correo' => 'cliente@gmail.com'],
        ];

        foreach ($usuarios as $rol => $datos) {
            Usuario::firstOrCreate(['correo' => $datos['correo']], [
                'nombre' => $datos['nombre'],
                'password' => $password,
                'rol_id' => Rol::where('nombre', $rol)->value('id'),
                'cliente_id' => $rol === 'cliente' ? $primerCliente?->id : null,
                'activo' => true,
            ]);
        }

        $this->crearPlanesYContratos($primerCliente);
        $this->crearTecnicosYOrdenes($password);
    }

    /**
     * Fase 2: catálogo de planes, un contrato vigente y planes asignados a los ascensores del primer cliente.
     */
    private function crearPlanesYContratos(?Cliente $cliente): void
    {
        if ($cliente === null || PlanMantenimiento::exists()) {
            return;
        }

        $periodicidades = Periodicidad::orderBy('meses')->pluck('id', 'meses');
        $planes = app(PlanService::class);

        $catalogo = [
            ['nombre' => 'Plan Básico', 'descripcion' => 'Revisión preventiva y lubricación.', 'servicios_incluidos' => ['Inspección general', 'Lubricación de guías', 'Prueba de puertas de cabina y de piso'], 'base' => 380000],
            ['nombre' => 'Plan Integral', 'descripcion' => 'Preventivo completo con ajustes y repuestos menores.', 'servicios_incluidos' => ['Inspección general', 'Lubricación de guías', 'Ajuste de frenos', 'Prueba de seguridades', 'Repuestos menores'], 'base' => 620000],
            ['nombre' => 'Plan Premium', 'descripcion' => 'Cobertura total con atención prioritaria.', 'servicios_incluidos' => ['Todo el Plan Integral', 'Atención prioritaria 24/7', 'Informe técnico mensual'], 'base' => 950000],
        ];

        $creados = [];
        foreach ($catalogo as $definicion) {
            // Entre más meses entre visitas, mayor el valor de cada visita.
            $precios = $periodicidades->map(fn ($id, $meses) => [
                'periodicidad_id' => $id,
                'precio' => $definicion['base'] + ($meses - 1) * 40000,
            ])->values()->all();

            $creados[] = $planes->crear([
                'nombre' => $definicion['nombre'],
                'descripcion' => $definicion['descripcion'],
                'servicios_incluidos' => $definicion['servicios_incluidos'],
                'activo' => true,
                'precios' => $precios,
            ]);
        }

        $equipos = Equipo::whereHas('edificio', fn ($c) => $c->where('cliente_id', $cliente->id))
            ->where('estado', '!=', 'inactivo')
            ->orderBy('codigo')
            ->get();

        if ($equipos->isEmpty() || Contrato::exists()) {
            return;
        }

        $inicio = now()->startOfMonth()->subMonths(2);
        $contrato = app(ContratoService::class)->crear([
            'cliente_id' => $cliente->id,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addYear()->subDay()->toDateString(),
            'valor' => 18000000,
            'servicios' => 'Mantenimiento preventivo de ascensores, puertas de cabina y puertas de piso.',
            'condiciones' => 'Pago mensual a 30 días. Visitas según la periodicidad del plan asignado.',
            'equipos' => $equipos->pluck('id')->all(),
        ]);

        $asignaciones = app(AsignacionPlanService::class);
        foreach ($equipos as $indice => $equipo) {
            $asignaciones->asignar([
                'equipo_id' => $equipo->id,
                'plan_id' => $creados[$indice % count($creados)]->id,
                'periodicidad_id' => $periodicidades[[1, 2, 3][$indice % 3]],
                'contrato_id' => $contrato->id,
                'aplica_iva' => true,
                'fecha_inicio' => $inicio->toDateString(),
                'hora_preferida' => ['08:00', '10:30', '14:00'][$indice % 3],
            ]);
        }
    }

    /**
     * Fase 3: perfiles de técnicos y algunas órdenes de trabajo de ejemplo.
     */
    private function crearTecnicosYOrdenes(string $password): void
    {
        $rolTecnico = Rol::where('nombre', 'tecnico')->value('id');
        $especialidades = Especialidad::pluck('id', 'nombre');

        $tecnicos = [
            ['correo' => 'tecnico@gmail.com', 'nombre' => 'Técnico Demo', 'documento' => '1110450001', 'telefono' => '3001234567',
                'especialidades' => ['Mantenimiento preventivo', 'Puertas de cabina y de piso']],
            ['correo' => 'andres.rojas@gmail.com', 'nombre' => 'Andrés Rojas', 'documento' => '1110450002', 'telefono' => '3002345678',
                'especialidades' => ['Mecánica de ascensores', 'Mantenimiento preventivo']],
            ['correo' => 'diana.mora@gmail.com', 'nombre' => 'Diana Mora', 'documento' => '1110450003', 'telefono' => '3003456789',
                'especialidades' => ['Electricidad y control', 'Electrónica y variadores']],
            ['correo' => 'felipe.gomez@gmail.com', 'nombre' => 'Felipe Gómez', 'documento' => '1110450004', 'telefono' => '3004567890',
                'especialidades' => ['Inspección y seguridad', 'Sistemas hidráulicos']],
        ];

        $perfiles = [];
        foreach ($tecnicos as $datos) {
            $usuario = Usuario::firstOrCreate(['correo' => $datos['correo']], [
                'nombre' => $datos['nombre'],
                'password' => $password,
                'rol_id' => $rolTecnico,
                'activo' => true,
            ]);
            $tecnico = Tecnico::firstOrCreate(['usuario_id' => $usuario->id], [
                'tipo_documento' => 'CC',
                'numero_documento' => $datos['documento'],
                'telefono' => $datos['telefono'],
                'disponibilidad' => 'disponible',
            ]);
            $tecnico->especialidades()->syncWithoutDetaching(
                collect($datos['especialidades'])->map(fn ($nombre) => $especialidades[$nombre])->all()
            );
            $perfiles[$datos['correo']] = $tecnico;
        }

        if (OrdenTrabajo::exists()) {
            return;
        }

        $ordenes = app(OrdenTrabajoService::class);
        $equipos = Equipo::orderBy('codigo')->get()->keyBy('codigo');
        $hoy = now()->toDateString();
        $manana = now()->addDay()->toDateString();

        // Correctivo crítico de hoy, en proceso.
        if ($equipo = $equipos->get('ASC-002')) {
            $orden = $ordenes->crear([
                'servicio' => 'correctivo', 'equipo_id' => $equipo->id, 'cliente_id' => $equipo->edificio->cliente_id,
                'edificio_id' => $equipo->edificio_id, 'fecha' => $hoy, 'hora' => '07:30', 'duracion_estimada' => 120,
                'prioridad' => 'critica', 'descripcion' => 'Amortiguadores del foso en mal estado; ruido al llegar al primer piso.',
                'tecnicos' => [$perfiles['andres.rojas@gmail.com']->id],
            ]);
            Auth::setUser($perfiles['andres.rojas@gmail.com']->usuario);
            $ordenes->avanzar($orden, 'en_camino');
            $ordenes->avanzar($orden->fresh(), 'en_proceso');
            Auth::forgetUser();
        }

        // Inspección de hoy, asignada.
        if ($equipo = $equipos->get('ASC-005')) {
            $ordenes->crear([
                'servicio' => 'inspeccion', 'equipo_id' => $equipo->id, 'cliente_id' => $equipo->edificio->cliente_id,
                'edificio_id' => $equipo->edificio_id, 'fecha' => $hoy, 'hora' => '14:00', 'duracion_estimada' => 60,
                'prioridad' => 'media', 'descripcion' => 'Inspección solicitada por el cliente: vibración en la cabina.',
                'tecnicos' => [$perfiles['felipe.gomez@gmail.com']->id],
            ]);
        }

        // Correctivo de mañana, sin técnico (queda programada).
        if ($equipo = $equipos->get('ASC-006')) {
            $ordenes->crear([
                'servicio' => 'correctivo', 'equipo_id' => $equipo->id, 'cliente_id' => $equipo->edificio->cliente_id,
                'edificio_id' => $equipo->edificio_id, 'fecha' => $manana, 'hora' => '10:00', 'duracion_estimada' => 90,
                'prioridad' => 'alta', 'descripcion' => 'La puerta de piso 3 no cierra por completo.',
            ]);
        }

        // Preventivo a partir de la próxima visita programada del plan.
        $visita = MantenimientoProgramado::pendientes()->whereDate('fecha_programada', '>=', $hoy)->orderBy('fecha_programada')->first();
        if ($visita) {
            $ordenes->crear([
                'mantenimiento_id' => $visita->id,
                'duracion_estimada' => 90,
                'descripcion' => 'Mantenimiento preventivo según el plan.',
                'tecnicos' => [$perfiles['tecnico@gmail.com']->id, $perfiles['diana.mora@gmail.com']->id],
            ]);
        }
    }
}

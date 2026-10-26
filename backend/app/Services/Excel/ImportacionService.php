<?php

namespace App\Services\Excel;

use App\Exceptions\ReglaNegocioException;
use App\Http\Requests\Asignacion\GuardarAsignacionRequest;
use App\Http\Requests\Cartera\GuardarFacturaRequest;
use App\Http\Requests\Cartera\RegistrarPagoRequest;
use App\Http\Requests\Cliente\ActualizarClienteRequest;
use App\Http\Requests\Cliente\GuardarClienteRequest;
use App\Http\Requests\Contrato\GuardarContratoRequest;
use App\Http\Requests\Edificio\ActualizarEdificioRequest;
use App\Http\Requests\Edificio\GuardarEdificioRequest;
use App\Http\Requests\Equipo\ActualizarEquipoRequest;
use App\Http\Requests\Equipo\GuardarEquipoRequest;
use App\Http\Requests\Orden\GuardarOrdenRequest;
use App\Http\Requests\Servicio\GuardarCertificacionRequest;
use App\Http\Requests\Servicio\GuardarCotizacionRequest;
use App\Http\Requests\Servicio\ReportarEmergenciaRequest;
use App\Http\Requests\Usuario\ActualizarUsuarioRequest;
use App\Http\Requests\Usuario\GuardarUsuarioRequest;
use App\Models\Certificacion;
use App\Models\ChecklistItem;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Models\Edificio;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\Factura;
use App\Models\OrdenTrabajo;
use App\Models\Pago;
use App\Models\Periodicidad;
use App\Models\PlanMantenimiento;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\Usuario;
use App\Services\AsignacionPlanService;
use App\Services\AutenticacionService;
use App\Services\CarteraService;
use App\Services\CertificacionService;
use App\Services\ClienteService;
use App\Services\ContratoService;
use App\Services\CotizacionService;
use App\Services\EdificioService;
use App\Services\EmergenciaService;
use App\Services\EquipoService;
use App\Services\OrdenTrabajoService;
use App\Services\PlanillaService;
use App\Services\UsuarioService;
use App\Support\Excel\Opciones;
use App\Support\Excel\Plantilla;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Importa la plantilla de Excel aplicando las mismas validaciones y reglas de negocio que los formularios.
 *
 * Vista previa: procesa todo dentro de una transacción que se deshace al final (no guarda nada ni envía avisos).
 * Confirmación: repite el proceso y guarda solo las filas válidas. Un código que ya existe actualiza el registro.
 */
class ImportacionService
{
    /** Permiso para crear y para actualizar cada hoja. */
    private const PERMISOS = [
        'clientes' => ['clientes.crear', 'clientes.editar'],
        'edificios' => ['edificios.crear', 'edificios.editar'],
        'ascensores' => ['equipos.crear', 'equipos.editar'],
        'puertas' => ['equipos.editar', 'equipos.editar'],
        'certificaciones' => ['certificaciones.gestionar', 'certificaciones.gestionar'],
        'emergencias' => ['emergencias.gestionar', 'emergencias.gestionar'],
        'cotizaciones' => ['cotizaciones.crear', 'cotizaciones.crear'],
        'ordenes' => ['ordenes.crear', 'ordenes.crear'],
        'checklist' => ['ordenes.ejecutar', 'ordenes.ejecutar'],
        'facturas' => ['facturas.crear', 'facturas.crear'],
        'pagos' => ['pagos.registrar', 'pagos.registrar'],
        'usuarios' => ['usuarios.crear', 'usuarios.editar'],
    ];

    /** Campo del formulario → columna de la plantilla, para ubicar cada error. */
    private const COLUMNAS = [
        'clientes' => ['nit' => 'documento', 'numero_documento' => 'documento'],
        'edificios' => ['cliente_id' => 'cliente', 'nombre_contacto' => 'contacto', 'telefono_contacto' => 'telefono', 'correo_contacto' => 'correo', 'activo' => 'estado'],
        'ascensores' => [
            'edificio_id' => 'edificio', 'fecha_instalacion' => 'anio_instalacion', 'tecnico_id' => 'tecnico',
            'ascensor.capacidad_kg' => 'capacidad_kg', 'ascensor.capacidad_personas' => 'personas', 'ascensor.velocidad_mpm' => 'velocidad_ms',
            'ascensor.paradas' => 'paradas', 'ascensor.tipo_traccion' => 'accionamiento', 'ascensor.tipo_ascensor' => 'tipo_ascensor', 'ascensor.controlador' => 'controlador',
            'ascensor.tipo_puerta' => 'tipo_puerta', 'plan_id' => 'plan', 'periodicidad_id' => 'periodicidad',
            'fecha_inicio' => 'fecha_primer_mantenimiento', 'precio' => 'valor_visita',
        ],
        'ordenes' => ['equipo_id' => 'ascensor', 'servicio' => 'tipo', 'hora' => 'hora_inicio', 'duracion_estimada' => 'hora_fin', 'tecnicos' => 'tecnico', 'tecnicos.0' => 'tecnico'],
        'facturas' => ['cliente_id' => 'cliente'],
        'certificaciones' => [],
        'emergencias' => ['equipo_id' => 'ascensor', 'reportante_nombre' => 'reportante', 'reportante_telefono' => 'telefono', 'tecnico_id' => 'tecnico'],
        'cotizaciones' => ['equipo_id' => 'ascensor', 'iva_porcentaje' => 'iva', 'items' => 'valor', 'items.0.valor_unitario' => 'valor', 'items.0.descripcion' => 'titulo'],
        'pagos' => ['factura_id' => 'factura'],
        'usuarios' => ['rol_id' => 'rol', 'activo' => 'estado', 'cliente_id' => 'cliente'],
    ];

    private Usuario $usuario;

    private array $resultado;

    /** Contratos pedidos en la hoja Clientes; se crean después de los ascensores. */
    private array $contratosPendientes = [];

    /** Claves ya vistas por hoja, para detectar duplicados dentro del archivo. */
    private array $vistos = [];

    public function __construct(
        private readonly ClienteService $clientes,
        private readonly EdificioService $edificios,
        private readonly EquipoService $equipos,
        private readonly AsignacionPlanService $asignaciones,
        private readonly ContratoService $contratos,
        private readonly OrdenTrabajoService $ordenes,
        private readonly PlanillaService $planillas,
        private readonly CarteraService $cartera,
        private readonly UsuarioService $usuarios,
        private readonly AutenticacionService $autenticacion,
        private readonly CertificacionService $certificaciones,
        private readonly EmergenciaService $emergencias,
        private readonly CotizacionService $cotizaciones,
    ) {}

    /**
     * @param  array{hojas: array, avisos: array}  $lectura  resultado de LectorSigma::leer
     */
    public function procesar(array $lectura, Usuario $usuario, bool $guardar): array
    {
        $this->usuario = $usuario;
        $this->contratosPendientes = [];
        $this->vistos = [];
        $this->resultado = ['hojas' => [], 'errores' => [], 'duplicados' => [], 'avisos' => $lectura['avisos'], 'derivados' => ['puertas_creadas' => 0, 'contratos_creados' => 0, 'planes_asignados' => 0, 'invitaciones' => 0]];

        $notificaciones = Notification::getFacadeRoot();
        $correo = Mail::getFacadeRoot();
        if (! $guardar) {
            // La vista previa no envía avisos ni correos.
            Notification::fake();
            Mail::fake();
        }

        DB::beginTransaction();
        try {
            foreach (Plantilla::hojas() as $clave => $hoja) {
                foreach ($lectura['hojas'][$clave] ?? [] as $fila) {
                    $this->fila($clave, $fila['fila'], $fila['valores']);
                }
                if ($clave === 'ascensores') {
                    $this->crearContratosPendientes();
                }
            }
            if (! isset($lectura['hojas']['ascensores'])) {
                $this->crearContratosPendientes();
            }
            $guardar ? DB::commit() : DB::rollBack();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        } finally {
            if (! $guardar) {
                Notification::swap($notificaciones);
                Mail::swap($correo);
            }
        }

        return $this->resultado;
    }

    // ---------- Proceso de una fila ----------

    private function fila(string $hoja, int $numero, array $valores): void
    {
        $this->resultado['hojas'][$hoja] ??= ['nombre' => Plantilla::hoja($hoja)['nombre'], 'total' => 0, 'creados' => 0, 'actualizados' => 0, 'existentes' => 0, 'errores' => 0, 'duplicados' => 0, 'filas' => []];
        $resumen = &$this->resultado['hojas'][$hoja];
        $resumen['total']++;

        try {
            $faltantes = $this->faltantes($hoja, $valores);
            if ($faltantes) {
                throw new FilaInvalida($faltantes);
            }
            $estado = DB::transaction(fn () => $this->{'importar'.Str::studly($hoja)}($valores, $numero));
            $resumen[$estado.'s']++;
            $resumen['filas'][$numero] = ['estado' => $estado];
        } catch (FilaDuplicada $e) {
            $resumen['duplicados']++;
            $resumen['filas'][$numero] = ['estado' => 'duplicado', 'motivo' => $e->getMessage()];
            $this->resultado['duplicados'][] = ['hoja' => $resumen['nombre'], 'fila' => $numero, 'columna' => $this->titulo($hoja, $e->columna), 'motivo' => $e->getMessage()];
        } catch (Throwable $e) {
            $errores = $this->errores($hoja, $e);
            $resumen['errores']++;
            $resumen['filas'][$numero] = ['estado' => 'error', 'motivo' => implode(' ', array_column($errores, 'motivo'))];
            foreach ($errores as $error) {
                $this->resultado['errores'][] = ['hoja' => $resumen['nombre'], 'fila' => $numero] + $error;
            }
        }
    }

    /**
     * Reglas de la plantilla (las mismas validaciones de datos del Excel): obligatorios, rangos, fechas, horas,
     * correos y listas cerradas. Así cada fila informa todos sus errores de una vez.
     */
    private function faltantes(string $hoja, array $valores): array
    {
        $errores = [];
        foreach (Plantilla::hoja($hoja)['columnas'] as $columna) {
            $crudo = $valores[$columna['clave']] ?? null;
            $titulo = $columna['titulo'];
            if (Plantilla::texto($crudo) === null) {
                if ($columna['obligatorio'] ?? false) {
                    $errores[] = ['columna' => $columna['clave'], 'motivo' => "Falta {$titulo}. Es obligatorio."];
                }

                continue;
            }
            if ($columna['soloLectura'] ?? false) {
                continue;
            }
            $motivo = match ($columna['tipo']) {
                'entero', 'decimal', 'moneda' => $this->revisarNumero($columna, Plantilla::numero($crudo)),
                'fecha' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) Plantilla::fecha($crudo)) ? null : "{$titulo}: escriba una fecha válida (día/mes/año).",
                'hora' => preg_match('/^\d{2}:\d{2}$/', (string) Plantilla::hora($crudo)) ? null : "{$titulo}: escriba una hora en formato 24 horas (07:30).",
                'correo' => filter_var(Plantilla::texto($crudo), FILTER_VALIDATE_EMAIL) ? null : "{$titulo}: escriba un correo válido (nombre@empresa.co).",
                'lista' => $this->revisarLista($columna, $crudo),
                default => null,
            };
            if ($motivo) {
                $errores[] = ['columna' => $columna['clave'], 'motivo' => $motivo];
            }
        }

        return $errores;
    }

    private function revisarNumero(array $columna, mixed $numero): ?string
    {
        if (! is_int($numero) && ! is_float($numero)) {
            return "{$columna['titulo']}: escriba un número.";
        }
        if ($columna['tipo'] === 'entero' && ! is_int($numero)) {
            return "{$columna['titulo']}: escriba un número entero.";
        }
        $min = $columna['min'] ?? null;
        $max = $columna['max'] ?? null;
        if (($min !== null && $numero < $min) || ($max !== null && $numero > $max)) {
            $formato = fn ($n) => is_int($n) ? number_format($n, 0, ',', '.') : str_replace('.', ',', (string) $n);

            return $max !== null
                ? "{$columna['titulo']} debe estar entre {$formato($min)} y {$formato($max)}."
                : "{$columna['titulo']} debe ser mayor o igual a {$formato($min)}.";
        }

        return null;
    }

    /** Listas cerradas: el valor debe estar en la lista (las de técnicos y planes se validan contra SIGMA). */
    private function revisarLista(array $columna, mixed $valor): ?string
    {
        $opciones = Plantilla::lista($columna['lista']);
        if (($columna['libre'] ?? false) || $opciones === []) {
            return null;
        }
        $texto = Plantilla::normalizar((string) $valor);
        foreach ($opciones as $opcion) {
            if (Plantilla::normalizar($opcion) === $texto) {
                return null;
            }
        }

        return "{$columna['titulo']}: «{$valor}» no está en la lista. Use una de estas opciones: ".implode(', ', $opciones).'.';
    }

    private function errores(string $hoja, Throwable $e): array
    {
        if ($e instanceof FilaInvalida) {
            return array_map(fn ($error) => ['columna' => $this->titulo($hoja, $error['columna']), 'motivo' => $error['motivo']], $e->errores);
        }
        if ($e instanceof ValidationException) {
            $errores = [];
            foreach ($e->errors() as $campo => $mensajes) {
                $clave = self::COLUMNAS[$hoja][$campo] ?? (Plantilla::columna($hoja, $campo) ? $campo : null);
                // En la plantilla la velocidad va en m/s (el formulario la guarda en m/min).
                $motivo = $campo === 'ascensor.velocidad_mpm' ? 'La velocidad debe estar entre 0,1 y 10 m/s.' : $mensajes[0];
                $errores[] = ['columna' => $this->titulo($hoja, $clave), 'motivo' => $motivo];
            }

            return $errores;
        }
        if ($e instanceof ReglaNegocioException || $e instanceof HttpException || $e instanceof AuthorizationException) {
            return [['columna' => null, 'motivo' => $e->getMessage() ?: 'Su rol no tiene permiso para esta acción.']];
        }
        report($e);

        return [['columna' => null, 'motivo' => 'No se pudo procesar la fila por un error inesperado. Revise sus datos o comuníquese con soporte.']];
    }

    private function titulo(string $hoja, ?string $clave): ?string
    {
        return $clave ? (Plantilla::columna($hoja, $clave)['titulo'] ?? null) : null;
    }

    private function exigirPermiso(string $hoja, bool $actualiza): void
    {
        $permiso = self::PERMISOS[$hoja][$actualiza ? 1 : 0];
        if (! $this->usuario->tienePermiso($permiso)) {
            throw new ReglaNegocioException('Su rol no tiene permiso para '.($actualiza ? 'actualizar' : 'crear').' registros de la hoja '.Plantilla::hoja($hoja)['nombre'].'.');
        }
    }

    /** Detecta la misma clave repetida dentro del archivo. */
    private function unico(string $hoja, string $clave, string $columna, int $fila, string $descripcion): void
    {
        $normal = Str::lower(trim($clave));
        if (isset($this->vistos[$hoja][$normal])) {
            throw new FilaDuplicada("{$descripcion} {$clave} ya aparece en la fila {$this->vistos[$hoja][$normal]} de esta hoja; se omitió esta fila.", $columna);
        }
        $this->vistos[$hoja][$normal] = $fila;
    }

    /** Valida con el mismo FormRequest del formulario (reglas, mensajes y validaciones adicionales). */
    private function validar(string $clase, array $datos, array $parametrosRuta = []): array
    {
        /** @var FormRequest $solicitud */
        $solicitud = $clase::create('/', 'POST', $datos);
        $solicitud->setContainer(app())->setRedirector(app(Redirector::class));
        $solicitud->setUserResolver(fn () => $this->usuario);
        $solicitud->setRouteResolver(fn () => new class($parametrosRuta)
        {
            public function __construct(private array $parametros) {}

            public function parameter($nombre, $defecto = null)
            {
                return $this->parametros[$nombre] ?? $defecto;
            }
        });
        $solicitud->validateResolved();

        return $solicitud->validated();
    }

    private function cliente(?string $codigo, string $columna = 'cliente'): Cliente
    {
        $cliente = Cliente::where('codigo', $codigo)->first();
        if (! $cliente) {
            throw new FilaInvalida([['columna' => $columna, 'motivo' => "No existe un cliente con el código {$codigo}. Regístrelo en la hoja Clientes o revise el código."]]);
        }

        return $cliente;
    }

    private function equipo(?string $codigo, string $columna = 'ascensor'): Equipo
    {
        $equipo = Equipo::with(['ascensor', 'edificio'])->where('codigo', $codigo)->first();
        if (! $equipo) {
            throw new FilaInvalida([['columna' => $columna, 'motivo' => "No existe un ascensor con el código {$codigo}. Regístrelo en la hoja Ascensores o revise el código."]]);
        }

        return $equipo;
    }

    private function tecnico(?string $texto, string $columna = 'tecnico'): ?Tecnico
    {
        if ($texto === null) {
            return null;
        }
        $tecnico = Tecnico::whereHas('usuario', fn ($c) => $c->whereRaw('lower(nombre) = ?', [Str::lower($texto)])->orWhereRaw('lower(correo) = ?', [Str::lower($texto)]))->first();
        if (! $tecnico) {
            throw new FilaInvalida([['columna' => $columna, 'motivo' => "No hay un técnico registrado con el nombre o correo «{$texto}»."]]);
        }

        return $tecnico;
    }

    // ---------- Hojas ----------

    private function importarClientes(array $v, int $fila): string
    {
        $codigo = Plantilla::texto($v['codigo']);
        $this->unico('clientes', $codigo, 'codigo', $fila, 'El código');
        $tipo = Plantilla::opcion($v['tipo'], Opciones::TIPOS_PERSONA);
        $documento = Plantilla::texto($v['documento']);
        $datos = array_filter([
            'codigo' => $codigo,
            'nombre' => Plantilla::texto($v['nombre']),
            'tipo' => $tipo,
            'telefono' => Plantilla::texto($v['telefono'] ?? null),
            'correo' => Plantilla::texto($v['correo'] ?? null),
            'direccion' => Plantilla::texto($v['direccion'] ?? null),
            'estado' => ($e = Plantilla::opcion($v['estado'] ?? null, Opciones::ESTADOS_REGISTRO)) === null ? null : ($e === '1' ? true : ($e === '0' ? false : $e)),
        ], fn ($x) => $x !== null);
        if ($tipo === 'natural') {
            $datos['numero_documento'] = $documento;
            $datos['tipo_documento'] = Plantilla::texto($v['tipo_documento'] ?? null) ?? 'CC';
        } else {
            $datos['nit'] = $documento;
            $datos['tipo_documento'] = 'NIT';
        }

        $existente = Cliente::where('codigo', $codigo)->first();
        $porDocumento = Cliente::where($tipo === 'natural' ? 'numero_documento' : 'nit', $documento)->first();
        if ($porDocumento && $porDocumento->id !== $existente?->id) {
            throw new FilaDuplicada("El NIT o documento {$documento} ya pertenece al cliente {$porDocumento->codigo} ({$porDocumento->nombre}); se omitió esta fila.", 'documento');
        }
        $this->exigirPermiso('clientes', (bool) $existente);
        if ($existente) {
            $validados = $this->validar(ActualizarClienteRequest::class, $datos, ['cliente' => $existente]);
            $cliente = $this->clientes->actualizar($existente, $validados);
        } else {
            $validados = $this->validar(GuardarClienteRequest::class, $datos);
            $cliente = $this->clientes->crear($validados);
        }

        if ($contacto = Plantilla::texto($v['contacto'] ?? null)) {
            $cliente->contactos()->updateOrCreate(['nombre' => $contacto], array_filter(['telefono' => $datos['telefono'] ?? null, 'correo' => $datos['correo'] ?? null]));
        }

        // Contrato: con número existente no cambia; sin número y con vigencia y valor, se crea al final.
        $numeroContrato = Plantilla::texto($v['contrato'] ?? null);
        $desde = Plantilla::fecha($v['vigencia_desde'] ?? null);
        $hasta = Plantilla::fecha($v['vigencia_hasta'] ?? null);
        $valor = Plantilla::numero($v['valor_contrato'] ?? null);
        if ($numeroContrato !== null && ! Contrato::where('numero', $numeroContrato)->exists()) {
            throw new FilaInvalida([['columna' => 'contrato', 'motivo' => "El contrato {$numeroContrato} no existe. Deje la columna vacía para crear uno nuevo con la vigencia y el valor."]]);
        }
        if ($numeroContrato === null && ($desde || $hasta || $valor !== null)) {
            if (! $desde || ! $hasta || $valor === null) {
                throw new FilaInvalida([['columna' => 'vigencia_desde', 'motivo' => 'Para crear el contrato llene Vigencia desde, Vigencia hasta y Valor del contrato.']]);
            }
            $this->contratosPendientes[] = ['cliente_id' => $cliente->id, 'fila' => $fila, 'fecha_inicio' => $desde, 'fecha_fin' => $hasta, 'valor' => $valor];
        }

        return $existente ? 'actualizado' : 'creado';
    }

    private function crearContratosPendientes(): void
    {
        foreach ($this->contratosPendientes as $pendiente) {
            $resumen = &$this->resultado['hojas']['clientes'];
            try {
                if (! $this->usuario->tienePermiso('contratos.crear')) {
                    throw new ReglaNegocioException('Su rol no tiene permiso para crear contratos.');
                }
                DB::transaction(function () use ($pendiente) {
                    $equipos = Equipo::whereHas('edificio', fn ($c) => $c->where('cliente_id', $pendiente['cliente_id']))->where('estado', '!=', 'inactivo')->pluck('id')->all();
                    if ($equipos === []) {
                        throw new ReglaNegocioException('El contrato no se creó: el cliente aún no tiene ascensores. Cárguelos en la hoja Ascensores.');
                    }
                    $datos = $this->validar(GuardarContratoRequest::class, [
                        'cliente_id' => $pendiente['cliente_id'],
                        'fecha_inicio' => $pendiente['fecha_inicio'],
                        'fecha_fin' => $pendiente['fecha_fin'],
                        'valor' => $pendiente['valor'],
                        'equipos' => $equipos,
                    ]);
                    $this->contratos->crear($datos);
                });
                $this->resultado['derivados']['contratos_creados']++;
            } catch (Throwable $e) {
                $resumen['filas'][$pendiente['fila']] = ['estado' => 'error', 'motivo' => $e->getMessage()];
                $resumen['errores']++;
                $this->resultado['errores'][] = ['hoja' => 'Clientes', 'fila' => $pendiente['fila'], 'columna' => 'Vigencia desde'] + ['motivo' => $this->errores('clientes', $e)[0]['motivo']];
            }
        }
        $this->contratosPendientes = [];
    }

    private function importarEdificios(array $v, int $fila): string
    {
        $cliente = $this->cliente(Plantilla::texto($v['cliente']));
        $nombre = Plantilla::texto($v['nombre']);
        $this->unico('edificios', $cliente->codigo.'|'.$nombre, 'nombre', $fila, 'El edificio');
        $estado = Plantilla::opcion($v['estado'] ?? null, Opciones::ESTADOS_REGISTRO);
        $datos = array_filter([
            'cliente_id' => $cliente->id,
            'tipo' => Plantilla::opcion($v['tipo'], Opciones::tiposEdificio()),
            'nombre' => $nombre,
            'direccion' => Plantilla::texto($v['direccion']),
            'ciudad' => Plantilla::texto($v['ciudad']),
            'departamento' => Plantilla::texto($v['departamento'] ?? null),
            'pisos' => Plantilla::numero($v['pisos'] ?? null),
            'nombre_contacto' => Plantilla::texto($v['contacto']),
            'telefono_contacto' => Plantilla::texto($v['telefono'] ?? null),
            'correo_contacto' => Plantilla::texto($v['correo'] ?? null),
            'activo' => $estado === null ? null : ($estado === '1' ? true : ($estado === '0' ? false : $estado)),
        ], fn ($x) => $x !== null);

        $existente = Edificio::where('cliente_id', $cliente->id)->whereRaw('lower(nombre) = ?', [Str::lower($nombre)])->first();
        $this->exigirPermiso('edificios', (bool) $existente);
        if ($existente) {
            $this->edificios->actualizar($existente, $this->validar(ActualizarEdificioRequest::class, $datos, ['edificio' => $existente]));

            return 'actualizado';
        }
        $this->edificios->crear($this->validar(GuardarEdificioRequest::class, $datos));

        return 'creado';
    }

    private function importarAscensores(array $v, int $fila): string
    {
        $codigo = Plantilla::texto($v['codigo']);
        $this->unico('ascensores', $codigo, 'codigo', $fila, 'El código');
        $cliente = $this->cliente(Plantilla::texto($v['cliente']));
        $nombreEdificio = Plantilla::texto($v['edificio']);
        $edificio = Edificio::where('cliente_id', $cliente->id)->whereRaw('lower(nombre) = ?', [Str::lower($nombreEdificio)])->first();
        if (! $edificio) {
            throw new FilaInvalida([['columna' => 'edificio', 'motivo' => "El cliente {$cliente->codigo} no tiene un edificio llamado «{$nombreEdificio}». Regístrelo en la hoja Edificios."]]);
        }
        $anio = Plantilla::numero($v['anio_instalacion'] ?? null);
        $velocidad = Plantilla::numero($v['velocidad_ms']);
        $paradas = Plantilla::numero($v['paradas']);
        $tecnico = $this->tecnico(Plantilla::texto($v['tecnico'] ?? null));

        $datos = array_filter([
            'edificio_id' => $edificio->id,
            'codigo' => $codigo,
            'nombre' => Plantilla::texto($v['nombre'] ?? null),
            'marca' => Plantilla::texto($v['marca'] ?? null),
            'modelo' => Plantilla::texto($v['modelo'] ?? null),
            'numero_serie' => Plantilla::texto($v['numero_serie'] ?? null),
            'estado' => Plantilla::opcion($v['estado'] ?? null, Opciones::ESTADOS_EQUIPO),
            // El año de instalación se guarda como 1 de enero de ese año (o hoy, si es el año en curso y aún no llega).
            'fecha_instalacion' => is_int($anio) ? min("{$anio}-01-01", now()->toDateString()) : ($anio === null ? null : (string) $anio),
            'observaciones' => Plantilla::texto($v['observaciones'] ?? null),
            'tecnico_id' => $tecnico?->id,
        ], fn ($x) => $x !== null);
        $datos['ascensor'] = array_filter([
            'tipo_ascensor' => Plantilla::opcion($v['tipo_ascensor'] ?? null, Opciones::tiposAscensor()),
            'capacidad_kg' => Plantilla::numero($v['capacidad_kg']),
            'capacidad_personas' => Plantilla::numero($v['personas'] ?? null),
            'velocidad_mpm' => is_numeric($velocidad) ? round($velocidad * 60, 2) : $velocidad,
            'paradas' => $paradas,
            'puertas_piso' => is_int($paradas) ? $paradas : null,
            'puertas_cabina' => 1,
            'tipo_traccion' => Plantilla::texto($v['accionamiento'] ?? null),
            'controlador' => Plantilla::texto($v['controlador'] ?? null),
            'tipo_puerta' => Plantilla::texto($v['tipo_puerta'] ?? null),
        ], fn ($x) => $x !== null);

        $existente = Equipo::where('codigo', $codigo)->first();
        // Mismo año que el registrado: se conserva la fecha completa de instalación.
        if ($existente && is_int($anio) && $existente->fecha_instalacion?->year === $anio) {
            unset($datos['fecha_instalacion']);
        }
        $this->exigirPermiso('ascensores', (bool) $existente);
        if ($existente) {
            $equipo = $this->equipos->actualizar($existente, $this->validar(ActualizarEquipoRequest::class, $datos, ['equipo' => $existente]));
        } else {
            $equipo = $this->equipos->crear($this->validar(GuardarEquipoRequest::class, $datos));
            $this->resultado['derivados']['puertas_creadas'] += $equipo->puertasPiso()->count();
        }

        $this->asignarPlan($equipo, $v);

        return $existente ? 'actualizado' : 'creado';
    }

    /** Periodicidad, plan, fecha del primer mantenimiento y valor por visita → asignación del plan (si no tiene una). */
    private function asignarPlan(Equipo $equipo, array $v): void
    {
        $meses = Plantilla::numero($v['periodicidad'] ?? null);
        $nombrePlan = Plantilla::texto($v['plan'] ?? null);
        $fecha = Plantilla::fecha($v['fecha_primer_mantenimiento'] ?? null);
        if ($meses === null && $nombrePlan === null && $fecha === null) {
            return;
        }
        if ($equipo->asignacionActiva()->exists()) {
            return; // El cambio de plan se hace desde la ficha del ascensor, con su motivo.
        }
        if (! $this->usuario->tienePermiso('asignaciones.crear')) {
            throw new ReglaNegocioException('Su rol no tiene permiso para asignar planes de mantenimiento.');
        }
        if ($meses === null || $fecha === null) {
            throw new FilaInvalida([['columna' => $meses === null ? 'periodicidad' : 'fecha_primer_mantenimiento', 'motivo' => 'Para programar el mantenimiento llene la periodicidad y la fecha del primer mantenimiento.']]);
        }
        $periodicidad = Periodicidad::where('meses', $meses)->where('activo', true)->first();
        if (! $periodicidad) {
            throw new FilaInvalida([['columna' => 'periodicidad', 'motivo' => 'La periodicidad debe estar entre 1 y 6 meses y estar activa en la configuración.']]);
        }
        $plan = $nombrePlan
            ? PlanMantenimiento::whereRaw('lower(nombre) = ?', [Str::lower($nombrePlan)])->first()
            : PlanMantenimiento::where('activo', true)->orderBy('id')->get()->first(fn ($p) => $p->precioPara($periodicidad->id) !== null);
        if (! $plan) {
            throw new FilaInvalida([['columna' => 'plan', 'motivo' => $nombrePlan ? "No existe el plan «{$nombrePlan}»." : 'No hay un plan activo con precio para esa periodicidad. Indique el plan o el valor por visita.']]);
        }
        $datos = array_filter([
            'equipo_id' => $equipo->id,
            'plan_id' => $plan->id,
            'periodicidad_id' => $periodicidad->id,
            'fecha_inicio' => $fecha,
            'precio' => Plantilla::numero($v['valor_visita'] ?? null),
        ], fn ($x) => $x !== null);
        $this->asignaciones->asignar($this->validar(GuardarAsignacionRequest::class, $datos));
        $this->resultado['derivados']['planes_asignados']++;
    }

    private function importarPuertas(array $v, int $fila): string
    {
        $equipo = $this->equipo(Plantilla::texto($v['ascensor']));
        $piso = Plantilla::numero($v['piso']);
        $this->unico('puertas', $equipo->codigo.'|'.$piso, 'piso', $fila, 'La puerta del piso');
        $this->exigirPermiso('puertas', true);
        $paradas = (int) ($equipo->ascensor?->paradas ?? 0);
        if (! is_int($piso) || $piso < 1 || $piso > $paradas) {
            throw new FilaInvalida([['columna' => 'piso', 'motivo' => "El piso debe ser un número entre 1 y {$paradas} (paradas del ascensor {$equipo->codigo})."]]);
        }
        $tipo = Plantilla::opcion($v['tipo'], Opciones::tiposPuertaPiso());
        $estado = Plantilla::opcion($v['estado'], Opciones::estadosPuerta());
        $observacion = Plantilla::texto($v['observacion'] ?? null);
        $errores = [];
        if (! array_key_exists((string) $tipo, Opciones::tiposPuertaPiso())) {
            $errores[] = ['columna' => 'tipo', 'motivo' => 'El tipo debe ser Automática o Manual.'];
        }
        if (! array_key_exists((string) $estado, Opciones::estadosPuerta())) {
            $errores[] = ['columna' => 'estado', 'motivo' => 'El estado debe ser Bueno, Regular, Malo o Pendiente.'];
        } elseif (in_array($estado, ['R', 'M'], true) && $observacion === null) {
            $errores[] = ['columna' => 'observacion', 'motivo' => 'Una puerta en estado Regular o Malo exige una observación.'];
        }
        if ($errores) {
            throw new FilaInvalida($errores);
        }
        $puerta = $equipo->puertasPiso()->firstOrNew(['piso' => $piso]);
        $nueva = ! $puerta->exists;
        $puerta->fill(['tipo' => $tipo, 'estado' => $estado, 'observacion' => $observacion])->save();

        return $nueva ? 'creado' : 'actualizado';
    }

    private function importarOrdenes(array $v, int $fila): string
    {
        $numero = Plantilla::texto($v['numero'] ?? null);
        if ($numero !== null) {
            $this->unico('ordenes', $numero, 'numero', $fila, 'La orden');
            if (OrdenTrabajo::where('numero', $numero)->exists()) {
                return 'existente';
            }
            throw new FilaInvalida([['columna' => 'numero', 'motivo' => "La orden {$numero} no existe. El número lo asigna SIGMA: déjelo vacío para crear la orden."]]);
        }
        $this->exigirPermiso('ordenes', false);
        $equipo = $this->equipo(Plantilla::texto($v['ascensor']));
        $tecnico = $this->tecnico(Plantilla::texto($v['tecnico'] ?? null));
        if ($tecnico && ! $this->usuario->tienePermiso('ordenes.asignar')) {
            throw new ReglaNegocioException('Su rol no tiene permiso para asignar técnicos a las órdenes.');
        }
        $inicio = Plantilla::hora($v['hora_inicio']);
        $fin = Plantilla::hora($v['hora_fin'] ?? null);
        $duracion = null;
        if ($fin !== null && preg_match('/^\d{2}:\d{2}$/', (string) $inicio) && preg_match('/^\d{2}:\d{2}$/', $fin)) {
            $duracion = CarbonImmutable::parse($inicio)->diffInMinutes(CarbonImmutable::parse($fin), false);
            if ($duracion < 15) {
                throw new FilaInvalida([['columna' => 'hora_fin', 'motivo' => 'La hora de fin debe ser al menos 15 minutos después de la hora de inicio.']]);
            }
        }
        $datos = array_filter([
            'servicio' => Plantilla::opcion($v['tipo'], Opciones::TIPOS_ORDEN),
            'cliente_id' => $equipo->edificio->cliente_id,
            'edificio_id' => $equipo->edificio_id,
            'equipo_id' => $equipo->id,
            'fecha' => Plantilla::fecha($v['fecha']),
            'hora' => $inicio,
            'duracion_estimada' => $duracion,
            'prioridad' => Plantilla::opcion($v['prioridad'] ?? null, Opciones::PRIORIDADES),
            'descripcion' => Plantilla::texto($v['descripcion'] ?? null),
            'observaciones' => Plantilla::texto($v['observaciones'] ?? null),
            'tecnicos' => $tecnico ? [$tecnico->id] : null,
        ], fn ($x) => $x !== null);
        $this->ordenes->crear($this->validar(GuardarOrdenRequest::class, $datos));

        return 'creado';
    }

    private function importarChecklist(array $v, int $fila): string
    {
        $numero = Plantilla::texto($v['orden']);
        $orden = OrdenTrabajo::where('numero', $numero)->first();
        if (! $orden) {
            throw new FilaInvalida([['columna' => 'orden', 'motivo' => "No existe la orden {$numero}."]]);
        }
        $zona = Opciones::zona($v['zona']);
        $texto = Plantilla::texto($v['item']);
        $item = ChecklistItem::where('seccion', $zona)
            ->where(fn ($c) => $c->whereRaw('upper(codigo) = ?', [Str::upper($texto)])->orWhereRaw('lower(nombre) = ?', [Str::lower($texto)]))
            ->first();
        if (! $item) {
            throw new FilaInvalida([['columna' => 'item', 'motivo' => "La zona {$v['zona']} no tiene el ítem «{$texto}». Use el código (CM-01) o el nombre exacto."]]);
        }
        $this->unico('checklist', $numero.'|'.$item->id, 'item', $fila, 'El ítem');
        $estado = Plantilla::opcion($v['resultado'], ['B' => 'B', 'R' => 'R', 'M' => 'M']);
        $resultado = Plantilla::opcion($v['tipo'], Opciones::TIPOS_REVISION);
        $observacion = Plantilla::texto($v['observacion'] ?? null);
        $errores = [];
        if (! in_array($estado, ['B', 'R', 'M'], true)) {
            $errores[] = ['columna' => 'resultado', 'motivo' => 'El resultado debe ser B, R o M.'];
        } elseif ($estado !== 'B' && $observacion === null) {
            $errores[] = ['columna' => 'observacion', 'motivo' => 'Un resultado R o M exige una observación.'];
        }
        if (! array_key_exists((string) $resultado, Opciones::TIPOS_REVISION)) {
            $errores[] = ['columna' => 'tipo', 'motivo' => 'El tipo debe ser INS, EST o N.A.'];
        }
        if ($errores) {
            throw new FilaInvalida($errores);
        }
        $anterior = $orden->planilla?->resultados()->where('item_id', $item->id)->first();
        if ($anterior && $anterior->estado === $estado && $anterior->resultado === $resultado && (string) $anterior->observacion === (string) $observacion) {
            return 'existente'; // Sin cambios: no se toca la planilla (sirve también para órdenes ya finalizadas).
        }
        $existia = (bool) $anterior;
        if (! Gate::forUser($this->usuario)->allows('ejecutar', $orden)) {
            throw new ReglaNegocioException("Solo un técnico asignado a la orden {$numero} puede registrar su checklist.");
        }
        $this->planillas->guardar($orden, ['resultados' => [['item_id' => $item->id, 'resultado' => $resultado, 'estado' => $estado, 'observacion' => $observacion]]]);

        return $existia ? 'actualizado' : 'creado';
    }

    private function importarCertificaciones(array $v, int $fila): string
    {
        $equipo = $this->equipo(Plantilla::texto($v['ascensor']));
        $inspeccion = Plantilla::fecha($v['fecha_inspeccion']);
        $this->unico('certificaciones', $equipo->codigo.'|'.$inspeccion, 'fecha_inspeccion', $fila, 'La certificación del ascensor con esa fecha de inspección');
        if (Certificacion::where('equipo_id', $equipo->id)->whereDate('fecha_inspeccion', $inspeccion)->exists()) {
            return 'existente';
        }
        $this->exigirPermiso('certificaciones', false);
        $datos = array_filter([
            'organismo' => Plantilla::texto($v['organismo']),
            'numero_certificado' => Plantilla::texto($v['numero_certificado'] ?? null),
            'norma' => Plantilla::texto($v['norma'] ?? null),
            'fecha_inspeccion' => $inspeccion,
            'fecha_vencimiento' => Plantilla::fecha($v['fecha_vencimiento']),
            'resultado' => Plantilla::opcion($v['resultado'], Certificacion::RESULTADOS),
            'observaciones' => Plantilla::texto($v['observaciones'] ?? null),
        ], fn ($x) => $x !== null);
        $this->certificaciones->registrar($equipo, $this->validar(GuardarCertificacionRequest::class, $datos));

        return 'creado';
    }

    private function importarEmergencias(array $v, int $fila): string
    {
        $numero = Plantilla::texto($v['numero'] ?? null);
        if ($numero !== null) {
            $this->unico('emergencias', $numero, 'numero', $fila, 'La emergencia');
            if (Emergencia::where('numero', $numero)->exists()) {
                return 'existente';
            }
        }
        $equipo = $this->equipo(Plantilla::texto($v['ascensor']));
        $fecha = Plantilla::fecha($v['fecha_reporte']);
        $reportada = $fecha.' '.Plantilla::hora($v['hora_reporte']);
        $this->unico('emergencias', $equipo->codigo.'|'.$reportada, 'hora_reporte', $fila, 'La emergencia del ascensor a esa hora');
        if (Emergencia::where('equipo_id', $equipo->id)->where('reportada_en', $reportada)->exists()) {
            return 'existente';
        }
        $this->exigirPermiso('emergencias', false);
        $tecnico = $this->tecnico(Plantilla::texto($v['tecnico'] ?? null));
        $datos = $this->validar(ReportarEmergenciaRequest::class, array_filter([
            'equipo_id' => $equipo->id,
            'tipo' => Plantilla::opcion($v['tipo'], Emergencia::TIPOS),
            'descripcion' => Plantilla::texto($v['descripcion'] ?? null),
            'reportante_nombre' => Plantilla::texto($v['reportante'] ?? null),
            'reportante_telefono' => Plantilla::texto($v['telefono'] ?? null),
            'tecnico_id' => $tecnico?->id,
        ], fn ($x) => $x !== null));
        $llegada = Plantilla::hora($v['hora_llegada'] ?? null);
        $fechaSolucion = Plantilla::fecha($v['fecha_solucion'] ?? null);
        $this->emergencias->registrarHistorica($equipo, $datos + [
            'reportada_en' => $reportada,
            'llegada_en' => $llegada ? "{$fecha} {$llegada}" : null,
            'resuelta_en' => $fechaSolucion ? $fechaSolucion.' '.(Plantilla::hora($v['hora_solucion'] ?? null) ?? '23:59') : null,
            'solucion' => Plantilla::texto($v['solucion'] ?? null),
            'tecnico_id' => $tecnico?->id,
        ]);

        return 'creado';
    }

    private function importarCotizaciones(array $v, int $fila): string
    {
        $numero = Plantilla::texto($v['numero'] ?? null);
        if ($numero !== null) {
            $this->unico('cotizaciones', $numero, 'numero', $fila, 'La cotización');
            if (Cotizacion::where('numero', $numero)->exists()) {
                return 'existente';
            }
        }
        $equipo = $this->equipo(Plantilla::texto($v['ascensor']));
        $titulo = Plantilla::texto($v['titulo']);
        $this->exigirPermiso('cotizaciones', false);
        $datos = array_filter([
            'equipo_id' => $equipo->id,
            'titulo' => $titulo,
            'descripcion' => Plantilla::texto($v['descripcion'] ?? null),
            'iva_porcentaje' => Plantilla::numero($v['iva'] ?? null),
            'vigente_hasta' => Plantilla::fecha($v['vigente_hasta'] ?? null),
            'items' => [['descripcion' => mb_substr((string) $titulo, 0, 200), 'cantidad' => 1, 'valor_unitario' => Plantilla::numero($v['valor'])]],
        ], fn ($x) => $x !== null);
        $this->cotizaciones->crear($this->validar(GuardarCotizacionRequest::class, $datos));

        return 'creado';
    }

    private function importarFacturas(array $v, int $fila): string
    {
        $numero = Plantilla::texto($v['numero']);
        $this->unico('facturas', $numero, 'numero', $fila, 'La factura');
        if (Factura::where('numero', $numero)->exists()) {
            return 'existente';
        }
        $this->exigirPermiso('facturas', false);
        $cliente = $this->cliente(Plantilla::texto($v['cliente']));
        $datos = array_filter([
            'cliente_id' => $cliente->id,
            'numero' => $numero,
            'concepto' => Plantilla::texto($v['concepto'] ?? null),
            'fecha_emision' => Plantilla::fecha($v['fecha_emision']),
            'fecha_vencimiento' => Plantilla::fecha($v['fecha_vencimiento']),
            'valor' => Plantilla::numero($v['valor']),
        ], fn ($x) => $x !== null);
        $this->cartera->crearFactura($this->validar(GuardarFacturaRequest::class, $datos));

        return 'creado';
    }

    private function importarPagos(array $v, int $fila): string
    {
        $cliente = $this->cliente(Plantilla::texto($v['cliente']));
        $numero = Plantilla::texto($v['factura']);
        $factura = Factura::where('numero', $numero)->first();
        if (! $factura || $factura->cliente_id !== $cliente->id) {
            throw new FilaInvalida([['columna' => 'factura', 'motivo' => $factura ? "La factura {$numero} no pertenece al cliente {$cliente->codigo}." : "No existe la factura {$numero}. Regístrela en la hoja Facturas."]]);
        }
        $referencia = Plantilla::texto($v['referencia']);
        $this->unico('pagos', $numero.'|'.$referencia, 'referencia', $fila, 'El pago con referencia');
        if (Pago::where('factura_id', $factura->id)->where('referencia', $referencia)->exists()) {
            return 'existente';
        }
        $this->exigirPermiso('pagos', false);
        $datos = [
            'factura_id' => $factura->id,
            'valor' => Plantilla::numero($v['valor']),
            'fecha' => Plantilla::fecha($v['fecha']),
            'medio' => Plantilla::opcion($v['medio'], Opciones::mediosPago()),
            'referencia' => $referencia,
        ];
        $this->cartera->registrarPago($this->validar(RegistrarPagoRequest::class, $datos));

        return 'creado';
    }

    private function importarUsuarios(array $v, int $fila): string
    {
        $correo = Str::lower((string) Plantilla::texto($v['correo']));
        $this->unico('usuarios', $correo, 'correo', $fila, 'El correo');
        $rolNombre = Plantilla::opcion($v['rol'], Opciones::ROLES);
        $rol = Rol::where('nombre', $rolNombre)->first();
        $estado = Plantilla::opcion($v['estado'], Opciones::ESTADOS_REGISTRO);
        $codigoCliente = Plantilla::texto($v['cliente'] ?? null);
        $datos = array_filter([
            'nombre' => Plantilla::texto($v['nombre']),
            'correo' => $correo,
            'rol_id' => $rol?->id ?? $rolNombre,
            'activo' => $estado === '1' ? true : ($estado === '0' ? false : $estado),
            'cliente_id' => $codigoCliente ? $this->cliente($codigoCliente)->id : null,
        ], fn ($x) => $x !== null);

        $existente = Usuario::where('correo', $correo)->first();
        $this->exigirPermiso('usuarios', (bool) $existente);
        if ($existente) {
            $this->usuarios->actualizar($existente, $this->validar(ActualizarUsuarioRequest::class, $datos, ['usuario' => $existente]));

            return 'actualizado';
        }
        // Contraseña aleatoria: el usuario crea la suya con el enlace que recibe por correo.
        $datos['password'] = Str::password(16);
        $this->usuarios->crear($this->validar(GuardarUsuarioRequest::class, $datos));
        $this->autenticacion->enviarEnlaceRecuperacion($correo);
        $this->resultado['derivados']['invitaciones']++;

        return 'creado';
    }
}

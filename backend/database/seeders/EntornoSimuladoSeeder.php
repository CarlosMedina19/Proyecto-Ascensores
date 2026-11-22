<?php

namespace Database\Seeders;

use App\Models\AsignacionPlan;
use App\Models\Certificacion;
use App\Models\Cliente;
use App\Models\Conjunto;
use App\Models\Contrato;
use App\Models\Cotizacion;
use App\Models\Edificio;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\Especialidad;
use App\Models\Factura;
use App\Models\MantenimientoProgramado;
use App\Models\MovimientoInventario;
use App\Models\OrdenTrabajo;
use App\Models\Pago;
use App\Models\Periodicidad;
use App\Models\Repuesto;
use App\Models\Rol;
use App\Models\Tecnico;
use App\Models\UbicacionTecnico;
use App\Models\Usuario;
use App\Services\ProgramacionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Entorno simulado: una empresa de mantenimiento con un año de operación inventada (proyecto formativo).
 *
 *   php artisan db:seed --class=EntornoSimuladoSeeder --force
 *
 * Crea 14 clientes en 7 ciudades, 36 ascensores (ASC-201 a ASC-236), 6 técnicos, contratos y planes con su
 * calendario, 12 meses de órdenes preventivas y correctivas, emergencias, facturas y pagos, repuestos,
 * certificaciones y cotizaciones; además, el trabajo de hoy (órdenes en curso y una emergencia abierta).
 *
 * Solo agrega registros (nunca borra ni cambia los existentes) y no envía avisos. Es idempotente: si ya
 * se cargó, no hace nada. Los clientes llevan en «observaciones» la marca MARCA para reconocerlos.
 * Se puede ejecutar en producción porque todo el sistema es de prueba.
 */
class EntornoSimuladoSeeder extends Seeder
{
    public const MARCA = 'Datos simulados del proyecto formativo SIGMA (información inventada).';

    /** Centro aproximado de cada ciudad (para las ubicaciones de los técnicos en el mapa). */
    private const CIUDADES = [
        'Bogotá' => ['Bogotá D.C.', 4.6760, -74.0480],
        'Medellín' => ['Antioquia', 6.2088, -75.5680],
        'Envigado' => ['Antioquia', 6.1710, -75.5870],
        'Cali' => ['Valle del Cauca', 3.4370, -76.5300],
        'Barranquilla' => ['Atlántico', 11.0000, -74.8070],
        'Cartagena' => ['Bolívar', 10.4100, -75.5500],
        'Bucaramanga' => ['Santander', 7.1190, -73.1170],
        'Pereira' => ['Risaralda', 4.8130, -75.6950],
    ];

    /**
     * [nombre, tipo de cliente, documento, ciudad, tipo de sitio, meses entre visitas, precio por visita,
     *  [[sitio, dirección, [[edificio, pisos, ascensores, uso], …]], …]]
     */
    private const CLIENTES = [
        ['Conjunto Residencial Torres de Castilla P.H.', 'juridica', '900418273-5', 'Bogotá', 'conjunto', 1, 420000, [
            ['Torres de Castilla', 'Calle 147 # 58-40', [['Torre 1', 16, 2, 'pasajeros'], ['Torre 2', 16, 2, 'pasajeros'], ['Torre 3', 16, 2, 'pasajeros']]],
        ]],
        ['Edificio Empresarial Calle 93 P.H.', 'juridica', '901233871-2', 'Bogotá', 'institucional', 1, 560000, [
            ['Empresarial 93', 'Calle 93 # 11A-28', [['Edificio principal', 14, 3, 'pasajeros']]],
        ]],
        ['Clínica Santa Lucía S.A.S.', 'juridica', '890912456-8', 'Medellín', 'institucional', 1, 650000, [
            ['Sede El Poblado', 'Carrera 43A # 9 Sur-91', [['Torre médica', 9, 2, 'camillero'], ['Torre administrativa', 6, 1, 'pasajeros']]],
        ]],
        ['Universidad Tecnológica del Caribe', 'juridica', '802004517-1', 'Barranquilla', 'institucional', 2, 480000, [
            ['Campus Norte', 'Carrera 51B # 84-150', [['Bloque A', 7, 2, 'pasajeros'], ['Bloque de laboratorios', 5, 1, 'carga']]],
        ]],
        ['Hotel Mirador de la Bahía S.A.S.', 'juridica', '806015742-9', 'Cartagena', 'institucional', 1, 590000, [
            ['Hotel Mirador de la Bahía', 'Avenida San Martín # 6-112, Bocagrande', [['Torre hotelera', 18, 2, 'pasajeros'], ['Servicios', 18, 1, 'carga']]],
        ]],
        ['Centro Comercial Plaza Bolívar P.H.', 'juridica', '804007318-4', 'Bucaramanga', 'institucional', 1, 520000, [
            ['Plaza Bolívar', 'Carrera 27 # 34-56', [['Edificio comercial', 5, 2, 'pasajeros'], ['Muelle de carga', 5, 1, 'carga']]],
        ]],
        ['Conjunto Residencial Reserva de los Andes P.H.', 'juridica', '901502338-6', 'Envigado', 'conjunto', 2, 390000, [
            ['Reserva de los Andes', 'Carrera 27 # 37B Sur-30', [['Torre Nogal', 12, 2, 'pasajeros'], ['Torre Cedro', 12, 1, 'pasajeros']]],
        ]],
        ['Edificio Altamira P.H.', 'juridica', '900887124-3', 'Cali', 'residencial', 2, 360000, [
            ['Edificio Altamira', 'Avenida 6N # 28N-45', [['Edificio Altamira', 10, 2, 'pasajeros']]],
        ]],
        ['Agrupación de Vivienda Portal del Sol P.H.', 'juridica', '901114093-7', 'Pereira', 'conjunto', 3, 340000, [
            ['Portal del Sol', 'Avenida 30 de Agosto # 87-210', [['Torre A', 9, 1, 'pasajeros'], ['Torre B', 9, 1, 'pasajeros']]],
        ]],
        ['Edificio Mónaco P.H.', 'juridica', '900356712-0', 'Bogotá', 'residencial', 2, 330000, [
            ['Edificio Mónaco', 'Carrera 11 # 86-32', [['Edificio Mónaco', 8, 1, 'pasajeros']]],
        ]],
        ['Torre Médica Los Andes P.H.', 'juridica', '805029841-5', 'Cali', 'institucional', 1, 610000, [
            ['Torre Médica Los Andes', 'Calle 5 # 38-14', [['Torre médica', 11, 1, 'camillero'], ['Torre médica', 11, 1, 'pasajeros']]],
        ]],
        ['Conjunto Residencial Parque Central Suba P.H.', 'juridica', '901687230-1', 'Bogotá', 'conjunto', 1, 380000, [
            ['Parque Central Suba', 'Calle 145 # 91-19', [['Torre 1', 15, 1, 'pasajeros'], ['Torre 2', 15, 1, 'pasajeros'], ['Torre 3', 15, 1, 'pasajeros']]],
        ]],
        ['Laura Patricia Gómez Ríos', 'natural', '52834190', 'Bogotá', 'residencial', 3, 290000, [
            ['Edificio familiar Gómez', 'Calle 63 # 4-18, Chapinero Alto', [['Edificio familiar Gómez', 5, 1, 'pasajeros']]],
        ]],
        ['Colegio Bilingüe Montessori del Norte', 'juridica', '802019874-2', 'Barranquilla', 'institucional', 2, 400000, [
            ['Sede Riomar', 'Calle 106 # 50-89', [['Bloque académico', 4, 1, 'pasajeros']]],
        ]],
    ];

    private const TECNICOS = [
        ['Jorge Andrés Pineda', 'jorge.pineda@sigma-mantenimiento.co', '1019087345', '3104567821', ['Mantenimiento preventivo', 'Mecánica de ascensores']],
        ['Luisa Fernanda Cárdenas', 'luisa.cardenas@sigma-mantenimiento.co', '1032456789', '3157894512', ['Electricidad y control', 'Electrónica y variadores']],
        ['Camilo Esteban Herrera', 'camilo.herrera@sigma-mantenimiento.co', '1098765432', '3016549873', ['Puertas de cabina y de piso', 'Puertas automáticas']],
        ['Mauricio Valencia Ortiz', 'mauricio.valencia@sigma-mantenimiento.co', '71789456', '3128765430', ['Sistemas hidráulicos', 'Mecánica de ascensores']],
        ['Natalia Restrepo Gil', 'natalia.restrepo@sigma-mantenimiento.co', '1036654321', '3175432198', ['Inspección y seguridad', 'Mantenimiento preventivo']],
        ['Yeison David Mosquera', 'yeison.mosquera@sigma-mantenimiento.co', '1143987650', '3008761234', ['Mantenimiento preventivo', 'Electricidad y control']],
    ];

    private const REPUESTOS = [
        ['RP-0101', 'Zapata deslizante de cabina', 'Hollister', 'ZD-45', 'unidad', 85000, 24, 8],
        ['RP-0102', 'Aceite hidráulico ISO 46 (galón)', 'Mobil', 'DTE-25', 'galón', 98000, 30, 10],
        ['RP-0103', 'Grasa para guías (kg)', 'Shell', 'Gadus S2', 'kg', 52000, 18, 6],
        ['RP-0104', 'Cable de tracción 10 mm (metro)', 'Gustav Wolf', 'GW-10-8x19', 'metro', 46000, 180, 60],
        ['RP-0105', 'Contactor principal 3P 32 A', 'Schneider', 'LC1D32', 'unidad', 310000, 6, 2],
        ['RP-0106', 'Relé de control 24 V', 'Omron', 'MY4N-24', 'unidad', 64000, 15, 5],
        ['RP-0107', 'Botón de cabina iluminado', 'Dewhurst', 'US91', 'unidad', 78000, 20, 6],
        ['RP-0108', 'Cortina fotoeléctrica de puerta', 'Cedes', 'cegard/Max', 'unidad', 1450000, 3, 1],
        ['RP-0109', 'Operador de puertas VVVF', 'Fermator', 'VVVF-4+', 'unidad', 4850000, 1, 1],
        ['RP-0110', 'Patín de puerta de piso (nylon)', 'Fermator', 'PN-20', 'unidad', 18000, 60, 20],
        ['RP-0111', 'Rodamiento polea de desvío', 'SKF', '6310-2RS', 'unidad', 125000, 8, 3],
        ['RP-0112', 'Amortiguador hidráulico de foso', 'Oleo', 'LB-35', 'unidad', 980000, 2, 1],
        ['RP-0113', 'Pastilla de freno de máquina', 'Montanari', 'PF-160', 'juego', 420000, 5, 2],
        ['RP-0114', 'Fusible de control 10 A', 'Bussmann', 'FNQ-10', 'unidad', 9500, 80, 25],
        ['RP-0115', 'Luminaria LED de cabina', 'Philips', 'LED-18W', 'unidad', 72000, 14, 4],
        ['RP-0116', 'Batería de luz de emergencia 12 V', 'Yuasa', 'NP7-12', 'unidad', 96000, 10, 4],
        ['RP-0117', 'Final de carrera', 'Telemecanique', 'XCK-J', 'unidad', 185000, 6, 2],
        ['RP-0118', 'Cerradura electromecánica de piso', 'Wittur', 'SLC-2', 'unidad', 690000, 4, 2],
        ['RP-0119', 'Codificador del motor', 'Heidenhain', 'ERN-1387', 'unidad', 2350000, 1, 1],
        ['RP-0120', 'Kit de limpieza de contactos', 'CRC', 'QD-Contact', 'unidad', 38000, 25, 8],
    ];

    private const FALLAS = [
        ['La cabina se detiene entre pisos y vuelve a arrancar.', 'Ajuste del encoder y limpieza de contactos del controlador.', 'RP-0120'],
        ['La puerta de piso no cierra por completo en el piso 4.', 'Cambio de patines de nylon y alineación de la hoja de piso.', 'RP-0110'],
        ['Ruido metálico al pasar por los pisos intermedios.', 'Lubricación de guías y cambio de zapatas deslizantes.', 'RP-0101'],
        ['El botón de llamada del piso 2 no responde.', 'Cambio del pulsador y revisión del cableado de la botonera.', 'RP-0107'],
        ['Desnivel de 3 cm al llegar al primer piso.', 'Ajuste de las banderas de nivelación y de la velocidad de aproximación.', null],
        ['Se dispara el breaker del cuarto de máquinas.', 'Cambio del contactor principal y reapriete de bornes.', 'RP-0105'],
        ['La luz de la cabina parpadea.', 'Cambio de la luminaria LED y del fusible de control.', 'RP-0115'],
        ['Fuga de aceite en la unidad hidráulica.', 'Cambio de sellos, reposición de aceite y prueba de presión.', 'RP-0102'],
        ['La cortina fotoeléctrica reabre la puerta sin obstáculo.', 'Limpieza y recalibración de la cortina fotoeléctrica.', null],
        ['Vibración al arrancar desde el último piso.', 'Ajuste del freno de la máquina y cambio de pastillas.', 'RP-0113'],
    ];

    private const REPORTANTES = ['Portería', 'Administración', 'Recepción', 'Residente apto 802', 'Coordinación de mantenimiento', 'Seguridad', 'Jefe de enfermería', 'Residente apto 1104'];

    private CarbonImmutable $hoy;

    private Usuario $actor;

    /** @var list<Tecnico> */
    private array $tecnicos = [];

    /** @var array<string, Repuesto> */
    private array $repuestos = [];

    /** Consecutivo de los códigos ASC-201 a ASC-236. */
    private int $ascensores = 200;

    /** @var array<string, int> prefijo-año => último consecutivo */
    private array $consecutivos = [];

    /** Órdenes y emergencias por crear, en orden cronológico (los números quedan consecutivos). */
    private array $ordenes = [];

    private array $emergencias = [];

    public function run(): void
    {
        if (Cliente::where('observaciones', self::MARCA)->exists()) {
            $this->command?->warn('El entorno simulado ya estaba cargado: no se agregó nada.');

            return;
        }
        if (Rol::where('nombre', 'tecnico')->doesntExist() || Periodicidad::doesntExist() || Especialidad::doesntExist()) {
            throw new RuntimeException('Ejecute primero «php artisan db:seed» (roles, permisos, periodicidades y especialidades).');
        }

        mt_srand(2026);
        $this->hoy = CarbonImmutable::today();
        // Sin eventos de tiempo real mientras se cargan miles de registros.
        config(['broadcasting.default' => 'null']);
        $this->actor = Usuario::whereHas('rol', fn ($r) => $r->where('nombre', 'administrador'))->orderBy('id')->first()
            ?? throw new RuntimeException('Se necesita un usuario administrador.');
        Auth::setUser($this->actor);

        DB::transaction(function () {
            $this->crearTecnicos();
            $this->crearRepuestos();
            foreach (self::CLIENTES as $indice => $definicion) {
                $this->crearCliente($definicion, $indice);
            }
            $this->crearOrdenes();
            $this->crearEmergencias();
            $this->trabajoDeHoy();
        });

        Auth::forgetUser();
        $this->command?->info('Entorno simulado cargado: '.Equipo::where('codigo', 'like', 'ASC-2__')->count().' ascensores, '
            .OrdenTrabajo::count().' órdenes y '.Emergencia::count().' emergencias en total.');
    }

    // ---------- Personas y almacén ----------

    private function crearTecnicos(): void
    {
        $rol = Rol::where('nombre', 'tecnico')->value('id');
        $especialidades = Especialidad::pluck('id', 'nombre');
        $jornada = ['inicio' => '07:00', 'fin' => '17:00'];
        $horario = ['1' => $jornada, '2' => $jornada, '3' => $jornada, '4' => $jornada, '5' => $jornada, '6' => ['inicio' => '08:00', 'fin' => '12:00']];

        foreach (self::TECNICOS as $k => [$nombre, $correo, $documento, $telefono, $habilidades]) {
            $usuario = Usuario::firstOrCreate(['correo' => $correo], [
                'nombre' => $nombre,
                'password' => Str::password(20),
                'rol_id' => $rol,
                'activo' => true,
                'telefono' => Usuario::where('telefono', $telefono)->exists() ? null : $telefono,
            ]);
            $usuario->forceFill(['correo_verificado_en' => $this->hoy->subYear(), 'created_at' => $this->hoy->subMonths(13)])->save();
            $tecnico = Tecnico::firstOrCreate(['usuario_id' => $usuario->id], [
                'tipo_documento' => 'CC',
                'numero_documento' => $documento,
                'telefono' => $telefono,
                'disponibilidad' => $k === 3 ? 'no_disponible' : 'disponible',
                'motivo_no_disponible' => $k === 3 ? 'Vacaciones' : null,
                'no_disponible_hasta' => $k === 3 ? $this->hoy->addDays(6)->toDateString() : null,
                'horario' => $horario,
            ]);
            $tecnico->especialidades()->syncWithoutDetaching(collect($habilidades)->map(fn ($e) => $especialidades[$e] ?? null)->filter()->all());
            $this->tecnicos[] = $tecnico;
        }
    }

    private function crearRepuestos(): void
    {
        foreach (self::REPUESTOS as [$codigo, $nombre, $marca, $referencia, $unidad, $precio, $stock, $minimo]) {
            $repuesto = Repuesto::firstOrCreate(['codigo' => $codigo], [
                'nombre' => $nombre, 'marca' => $marca, 'referencia' => $referencia, 'unidad' => $unidad,
                'precio' => $precio, 'stock' => 0, 'stock_minimo' => $minimo, 'activo' => true,
            ]);
            if ($repuesto->wasRecentlyCreated) {
                $this->movimiento($repuesto, 'entrada', $stock, 'Inventario inicial', null, $this->hoy->subMonths(12)->setTime(8, 0));
            }
            $this->repuestos[$codigo] = $repuesto;
        }
    }

    // ---------- Clientes, sitios, edificios y ascensores ----------

    private function crearCliente(array $definicion, int $indice): void
    {
        [$nombre, $tipo, $documento, $ciudad, $tipoSitio, $meses, $precio, $sitios] = $definicion;
        $departamento = self::CIUDADES[$ciudad][0];
        $slug = Str::of($nombre)->ascii()->lower()->replaceMatches('/[^a-z ]/', '')->explode(' ')->filter()->take(3)->implode('');
        $alta = $this->hoy->subMonths(13 - $indice % 3)->startOfMonth();
        $contacto = ['Andrea Morales', 'Ricardo Salazar', 'Paola Jiménez', 'Hernán Castro', 'Marcela Quintero', 'Óscar Lozano', 'Diana Patiño'][$indice % 7];

        $cliente = Cliente::create([
            'tipo' => $tipo,
            'nombre' => $nombre,
            'tipo_documento' => $tipo === 'natural' ? 'CC' : 'NIT',
            'nit' => $tipo === 'natural' ? null : $documento,
            'numero_documento' => $tipo === 'natural' ? $documento : null,
            'direccion' => $sitios[0][1],
            'telefono' => '60'.mt_rand(1, 8).mt_rand(3000000, 7999999),
            'correo' => $tipo === 'natural' ? 'lauragomez.rios@correo-demo.co' : "administracion@{$slug}.co",
            'regimen_tributario' => $tipo === 'natural' ? 'No responsable de IVA' : 'Responsable de IVA',
            'estado' => true,
            'observaciones' => self::MARCA,
        ]);
        $cliente->forceFill(['created_at' => $alta, 'updated_at' => $alta])->saveQuietly();
        $cliente->contactos()->create(['nombre' => $contacto, 'cargo' => $tipoSitio === 'institucional' ? 'Jefe de mantenimiento' : 'Administrador(a)', 'telefono' => '3'.mt_rand(100000000, 229999999), 'correo' => Str::of($contacto)->ascii()->lower()->replace(' ', '.')."@{$slug}.co"]);

        $equipos = collect();
        foreach ($sitios as [$sitio, $direccion, $edificios]) {
            $conjunto = Conjunto::create([
                'cliente_id' => $cliente->id, 'tipo' => $tipoSitio, 'nombre' => $sitio, 'direccion' => $direccion,
                'ciudad' => $ciudad, 'departamento' => $departamento, 'nombre_contacto' => $contacto,
                'telefono_contacto' => '3'.mt_rand(100000000, 229999999), 'activo' => true,
            ]);
            foreach ($edificios as [$nombreEdificio, $pisos, $cantidad, $uso]) {
                $edificio = Edificio::firstOrCreate(['cliente_id' => $cliente->id, 'nombre' => $nombreEdificio], [
                    'conjunto_id' => $conjunto->id, 'tipo' => $tipoSitio, 'direccion' => $direccion, 'ciudad' => $ciudad,
                    'departamento' => $departamento, 'pisos' => $pisos, 'nombre_contacto' => $contacto, 'activo' => true,
                ]);
                foreach (range(1, $cantidad) as $_) {
                    $equipos->push($this->crearAscensor($edificio, $uso, $alta));
                }
            }
        }

        $this->crearContrato($cliente, $equipos, $meses, $precio, $alta, $indice);
    }

    private function crearAscensor(Edificio $edificio, string $uso, CarbonImmutable $alta): Equipo
    {
        $consecutivo = ++$this->ascensores;
        $codigo = "ASC-{$consecutivo}";
        $marca = ['Otis', 'Schindler', 'Mitsubishi Electric', 'Kone', 'TK Elevator', 'Orona'][$consecutivo % 6];
        $hidraulico = $edificio->pisos <= 5 && $uso !== 'carga';
        $instalado = $this->hoy->subYears(mt_rand(2, 26))->subDays(mt_rand(0, 300));

        $equipo = Equipo::create([
            'edificio_id' => $edificio->id,
            'tecnico_id' => $this->tecnicos[$edificio->id % count($this->tecnicos)]->id,
            'codigo' => $codigo,
            'tipo' => 'ascensor',
            'nombre' => $uso === 'carga' ? 'Ascensor de carga' : ($uso === 'camillero' ? 'Ascensor camillero' : 'Ascensor de pasajeros'),
            'marca' => $marca,
            'modelo' => match ($marca) {
                'Otis' => 'Gen2 Comfort', 'Schindler' => '3300', 'Mitsubishi Electric' => 'NexWay-S', 'Kone' => 'MonoSpace 500', 'TK Elevator' => 'synergy', default => 'Orona 3G',
            },
            'numero_serie' => strtoupper(Str::substr(md5($codigo.'sigma'), 0, 10)),
            'ubicacion' => $edificio->ubicacionEquipos(),
            'estado' => 'activo',
            'fecha_instalacion' => $instalado->toDateString(),
        ]);
        $equipo->ascensor()->create([
            'capacidad_kg' => match ($uso) {
                'carga' => 1600, 'camillero' => 1275, default => [630, 800, 1000][$consecutivo % 3]
            },
            'capacidad_personas' => match ($uso) {
                'carga' => null, 'camillero' => 17, default => [8, 10, 13][$consecutivo % 3]
            },
            'velocidad_mpm' => $hidraulico ? 38 : ($edificio->pisos >= 14 ? 150 : 90),
            'paradas' => $edificio->pisos,
            'tipo_ascensor' => $uso,
            'tipo_traccion' => $hidraulico ? 'Hidráulico' : 'Eléctrico de tracción',
            'motor' => $hidraulico ? 'Unidad hidráulica de 11 kW' : 'Motor sin engranajes (gearless)',
            'controlador' => 'Control de maniobra colectiva selectiva',
            'tipo_puerta' => $uso === 'carga' ? 'Automática de apertura lateral' : 'Automática de apertura central',
            'puertas_cabina' => 1,
            'puertas_piso' => $edificio->pisos,
            'operador_puertas' => 'Operador VVVF',
        ]);
        $equipo->historial()->forceCreate(['evento' => 'alta_equipo', 'descripcion' => "Ascensor registrado con código {$codigo}.", 'created_at' => $alta]);

        // Certificación anual (inspección de un organismo acreditado): la mayoría vigente, algunas por vencer o vencidas.
        $inspeccion = $this->hoy->subDays(mt_rand(20, 400));
        Certificacion::create([
            'equipo_id' => $equipo->id,
            'organismo' => ['Inspecciones Técnicas Andinas S.A.S.', 'Certificadora Colombiana de Ascensores', 'ICONTEC Inspección'][$consecutivo % 3],
            'numero_certificado' => 'RA-'.$inspeccion->year.'-'.str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT),
            'norma' => 'NTC 5926-1',
            'fecha_inspeccion' => $inspeccion->toDateString(),
            'fecha_vencimiento' => $inspeccion->addYear()->toDateString(),
            'resultado' => mt_rand(1, 10) <= 8 ? 'conforme' : 'condicionado',
            'observaciones' => mt_rand(1, 10) <= 8 ? null : 'Ajustar el limitador de velocidad y la iluminación del foso antes de la próxima revisión.',
            'registrado_por' => $this->actor->id,
        ]);

        return $equipo;
    }

    // ---------- Contrato, plan, calendario y facturación ----------

    private function crearContrato(Cliente $cliente, $equipos, int $meses, int $precio, CarbonImmutable $alta, int $indice): void
    {
        $inicio = $alta;
        // Algunos contratos vencen pronto, para ver las alertas de renovación.
        $fin = $indice % 5 === 2 ? $this->hoy->addDays(18 + $indice) : $inicio->addYear()->subDay();
        $periodicidad = Periodicidad::where('meses', $meses)->firstOrFail();
        $visitasPorAnio = intdiv(12, $meses);
        $valor = $precio * $visitasPorAnio * $equipos->count();

        $contrato = Contrato::create([
            'numero' => $this->numero('CT', $inicio->year, 4),
            'cliente_id' => $cliente->id,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
            'valor' => $valor,
            'servicios' => 'Mantenimiento preventivo, atención de emergencias 24/7 y correctivos con repuestos facturados aparte.',
            'condiciones' => "Visitas cada {$meses} mes(es) en días hábiles. Facturación mensual con pago a 30 días.",
            'estado' => 'vigente',
            'estado_renovacion' => 'pendiente',
            'creado_por' => $this->actor->id,
        ]);
        $contrato->forceFill(['created_at' => $inicio, 'updated_at' => $inicio])->saveQuietly();
        $contrato->equipos()->attach($equipos->pluck('id')->all());

        $programacion = app(ProgramacionService::class);
        foreach ($equipos as $k => $equipo) {
            $asignacion = AsignacionPlan::create([
                'equipo_id' => $equipo->id,
                'periodicidad_id' => $periodicidad->id,
                'contrato_id' => $contrato->id,
                'precio' => $precio,
                'aplica_iva' => true,
                'porcentaje_iva' => 19,
                'fecha_inicio' => $inicio->toDateString(),
                'fecha_fin' => $fin->toDateString(),
                'estado' => 'activa',
                'hora_preferida' => ['07:30', '09:00', '10:30', '14:00', '15:30'][($indice + $k) % 5],
                'creado_por' => $this->actor->id,
            ]);
            $programacion->programar($asignacion);
            $this->ejecutarHistorial($asignacion, $equipo, $cliente);
        }

        $this->facturar($cliente, $inicio, $valor / 12);
    }

    /** Lo ya vencido del calendario queda ejecutado (con su orden), y unas pocas visitas canceladas o atrasadas. */
    private function ejecutarHistorial(AsignacionPlan $asignacion, Equipo $equipo, Cliente $cliente): void
    {
        $ultimo = null;
        $visitas = MantenimientoProgramado::where('asignacion_id', $asignacion->id)->orderBy('fecha_programada')->get();
        foreach ($visitas as $visita) {
            if ($visita->fecha_programada->gte($this->hoy)) {
                continue;
            }
            $azar = mt_rand(1, 100);
            if ($azar <= 4) {
                $visita->forceFill(['estado' => 'cancelado', 'motivo' => 'El cliente pidió aplazarla por un evento en el edificio.'])->save();

                continue;
            }
            if ($azar <= 7 && $visita->fecha_programada->gte($this->hoy->subDays(45))) {
                continue; // Atrasada: sigue programada y baja el cumplimiento del plan.
            }
            $fecha = CarbonImmutable::parse($visita->fecha_programada);
            $visita->forceFill(['estado' => 'ejecutado', 'fecha_ejecucion' => $fecha->toDateString()])->save();
            $ultimo = $fecha;
            $this->ordenes[] = [
                'servicio' => 'preventivo', 'equipo' => $equipo, 'cliente_id' => $cliente->id, 'mantenimiento_id' => $visita->id,
                'fecha' => $fecha, 'hora' => substr((string) $visita->hora, 0, 5) ?: '08:00', 'duracion' => mt_rand(55, 110),
                'descripcion' => 'Mantenimiento preventivo según el plan.', 'prioridad' => 'media',
                'informe' => 'Se ejecutaron las actividades de la planilla. Limpieza y lubricación de guías, ajuste de puertas y prueba de seguridades sin novedad.',
                'repuesto' => mt_rand(1, 100) <= 35 ? ['RP-0103', 1] : (mt_rand(1, 100) <= 30 ? ['RP-0120', 1] : null),
            ];
        }

        $proximo = MantenimientoProgramado::where('asignacion_id', $asignacion->id)->whereIn('estado', MantenimientoProgramado::PENDIENTES)
            ->whereDate('fecha_programada', '>=', $this->hoy->toDateString())->orderBy('fecha_programada')->value('fecha_programada');
        $asignacion->forceFill(['ultimo_mantenimiento' => $ultimo?->toDateString(), 'proximo_mantenimiento' => $proximo ? substr((string) $proximo, 0, 10) : null])->saveQuietly();

        // Correctivos, inspecciones y emergencias del último año: los equipos más viejos fallan más.
        $edad = $equipo->fecha_instalacion ? $equipo->fecha_instalacion->diffInYears($this->hoy) : 10;
        $probabilidad = 6 + (int) min(14, $edad / 2);
        for ($mes = 11; $mes >= 0; $mes--) {
            $dia = $this->hoy->subMonths($mes)->startOfMonth()->addDays(mt_rand(0, 26));
            if ($dia->gte($this->hoy) || $dia->lt(CarbonImmutable::parse($asignacion->fecha_inicio))) {
                continue;
            }
            if (mt_rand(1, 100) <= $probabilidad) {
                $falla = self::FALLAS[mt_rand(0, count(self::FALLAS) - 1)];
                $this->ordenes[] = [
                    'servicio' => 'correctivo', 'equipo' => $equipo, 'cliente_id' => $cliente->id, 'mantenimiento_id' => null,
                    'fecha' => $dia, 'hora' => ['08:00', '10:00', '13:30', '15:00'][mt_rand(0, 3)], 'duracion' => mt_rand(80, 240),
                    'descripcion' => $falla[0], 'prioridad' => ['media', 'alta', 'alta', 'critica'][mt_rand(0, 3)],
                    'informe' => $falla[1], 'repuesto' => $falla[2] ? [$falla[2], $falla[2] === 'RP-0104' ? 12 : 1] : null,
                ];
            }
            if (mt_rand(1, 100) <= (int) round($probabilidad * 0.7)) {
                $this->emergencias[] = ['equipo' => $equipo, 'reportada' => $dia->addDays(mt_rand(0, 2))->setTime(mt_rand(6, 21), mt_rand(0, 59))];
            }
            if (mt_rand(1, 100) <= 3) {
                $this->ordenes[] = [
                    'servicio' => 'inspeccion', 'equipo' => $equipo, 'cliente_id' => $cliente->id, 'mantenimiento_id' => null,
                    'fecha' => $dia->addDays(1), 'hora' => '11:00', 'duracion' => 60, 'prioridad' => 'baja',
                    'descripcion' => 'Inspección solicitada por la administración antes de la revisión anual.',
                    'informe' => 'Inspección general sin hallazgos críticos. Se recomienda cambiar las zapatas en el próximo preventivo.', 'repuesto' => null,
                ];
            }
        }
    }

    /** Factura mensual del contrato y sus pagos: casi todo al día, algunas con abono y unas pocas vencidas. */
    private function facturar(Cliente $cliente, CarbonImmutable $inicio, float $mensual): void
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $valor = round($mensual * 1.19, -2);
        for ($emision = $inicio; $emision->lte($this->hoy); $emision = $emision->addMonth()) {
            $vence = $emision->addDays(30);
            $azar = mt_rand(1, 100);
            $vencida = $vence->lt($this->hoy);
            $pagado = ! $vencida ? 0 : ($azar <= 84 ? $valor : ($azar <= 92 ? round($valor / 2, -2) : 0));
            $factura = Factura::create([
                'cliente_id' => $cliente->id,
                'numero' => $this->numero('FV', null, 4),
                'concepto' => 'Mantenimiento preventivo de ascensores · '.$meses[$emision->month - 1].' de '.$emision->year,
                'fecha_emision' => $emision->toDateString(),
                'fecha_vencimiento' => $vence->toDateString(),
                'valor' => $valor,
                'saldo' => $valor - $pagado,
                'estado' => $pagado <= 0 ? 'pendiente' : ($pagado < $valor ? 'parcial' : 'pagada'),
                'creado_por' => $this->actor->id,
            ]);
            $factura->forceFill(['created_at' => $emision, 'updated_at' => $emision])->saveQuietly();
            if ($pagado > 0) {
                $medio = ['transferencia', 'transferencia', 'pse', 'consignacion'][mt_rand(0, 3)];
                Pago::create([
                    'cliente_id' => $cliente->id, 'factura_id' => $factura->id, 'valor' => $pagado,
                    'fecha' => min($emision->addDays(mt_rand(8, 34)), $this->hoy)->toDateString(),
                    'medio' => $medio, 'referencia' => strtoupper(substr($medio, 0, 3)).'-'.mt_rand(10000000, 99999999),
                    'registrado_por' => $this->actor->id,
                ]);
            }
        }
    }

    // ---------- Órdenes y emergencias del año ----------

    private function crearOrdenes(): void
    {
        usort($this->ordenes, fn ($a, $b) => [$a['fecha']->toDateString(), $a['hora']] <=> [$b['fecha']->toDateString(), $b['hora']]);
        foreach ($this->ordenes as $datos) {
            $inicio = CarbonImmutable::parse($datos['fecha']->toDateString().' '.$datos['hora'])->addMinutes(mt_rand(0, 25));
            $fin = $inicio->addMinutes($datos['duracion']);
            $orden = $this->orden($datos, 'finalizada', $inicio, $fin);
            if ($datos['repuesto']) {
                [$codigo, $cantidad] = $datos['repuesto'];
                $this->usarRepuesto($orden, $codigo, $cantidad, $fin);
            }
        }
    }

    private function orden(array $datos, string $estado, ?CarbonImmutable $inicio = null, ?CarbonImmutable $fin = null, array $tecnicos = []): OrdenTrabajo
    {
        $equipo = $datos['equipo'];
        $creada = CarbonImmutable::parse($datos['fecha']->toDateString())->subDays($datos['servicio'] === 'preventivo' ? 5 : 1)->setTime(8, 15);
        $orden = OrdenTrabajo::create([
            'numero' => $this->numero('OT', $datos['fecha']->year, 5),
            'servicio' => $datos['servicio'],
            'cliente_id' => $datos['cliente_id'],
            'edificio_id' => $equipo->edificio_id,
            'equipo_id' => $equipo->id,
            'mantenimiento_id' => $datos['mantenimiento_id'],
            'fecha' => $datos['fecha']->toDateString(),
            'hora' => $datos['hora'],
            'duracion_estimada' => $datos['servicio'] === 'preventivo' ? 90 : 120,
            'prioridad' => $datos['prioridad'],
            'estado' => $estado,
            'descripcion' => $datos['descripcion'],
            'informe' => $estado === 'finalizada' ? $datos['informe'] : null,
            'inicio_en' => $inicio,
            'fin_en' => $fin,
            'duracion_minutos' => $inicio && $fin ? (int) $inicio->diffInMinutes($fin) : null,
            'creado_por' => $this->actor->id,
        ]);
        $orden->forceFill(['created_at' => $creada, 'updated_at' => $fin ?? $creada])->saveQuietly();

        $asignados = $tecnicos ?: [$this->tecnicoPara($equipo)];
        if ($datos['servicio'] !== 'preventivo' && ! $tecnicos && mt_rand(1, 100) <= 30) {
            $asignados[] = $this->tecnicos[mt_rand(0, count($this->tecnicos) - 1)];
        }
        foreach (collect($asignados)->unique('id') as $tecnico) {
            $orden->tecnicos()->attach($tecnico->id, ['asignado_por' => $this->actor->id, 'asignado_en' => $creada]);
        }
        $orden->trazabilidad()->forceCreate(['usuario_id' => $this->actor->id, 'accion' => 'creada', 'detalle' => 'Orden creada.', 'created_at' => $creada]);
        if ($estado === 'finalizada') {
            $orden->trazabilidad()->forceCreate(['usuario_id' => $asignados[0]->usuario_id, 'accion' => 'estado', 'campo' => 'estado', 'valor_anterior' => 'en_proceso', 'valor_nuevo' => 'finalizada', 'created_at' => $fin]);
        }

        return $orden;
    }

    /** Cada edificio tiene un técnico habitual; a veces atiende otro. */
    private function tecnicoPara(Equipo $equipo): Tecnico
    {
        $habitual = $this->tecnicos[$equipo->edificio_id % count($this->tecnicos)];

        return mt_rand(1, 100) <= 80 ? $habitual : $this->tecnicos[mt_rand(0, count($this->tecnicos) - 1)];
    }

    private function usarRepuesto(OrdenTrabajo $orden, string $codigo, float $cantidad, CarbonImmutable $cuando): void
    {
        $repuesto = $this->repuestos[$codigo]->refresh();
        $descontada = min($cantidad, (float) $repuesto->stock);
        DB::table('orden_repuestos')->insert([
            'orden_id' => $orden->id, 'repuesto_id' => $repuesto->id, 'cantidad' => $cantidad,
            'cantidad_descontada' => $descontada, 'faltante' => $cantidad - $descontada, 'registrado_por' => $this->actor->id,
            'created_at' => $cuando, 'updated_at' => $cuando,
        ]);
        if ($descontada > 0) {
            $this->movimiento($repuesto, 'consumo', $descontada, "Consumo en la orden {$orden->numero}", $orden->id, $cuando);
        }
    }

    private function movimiento(Repuesto $repuesto, string $tipo, float $cantidad, string $motivo, ?int $ordenId, CarbonImmutable $cuando): void
    {
        $variacion = in_array($tipo, ['entrada', 'devolucion'], true) ? $cantidad : -$cantidad;
        $stock = (float) $repuesto->stock + $variacion;
        MovimientoInventario::forceCreate([
            'repuesto_id' => $repuesto->id, 'tipo' => $tipo, 'cantidad' => $cantidad, 'variacion' => $variacion,
            'stock_resultante' => $stock, 'motivo' => $motivo, 'orden_id' => $ordenId, 'usuario_id' => $this->actor->id, 'created_at' => $cuando,
        ]);
        $repuesto->forceFill(['stock' => $stock])->save();
    }

    private function crearEmergencias(): void
    {
        usort($this->emergencias, fn ($a, $b) => $a['reportada'] <=> $b['reportada']);
        $soluciones = [
            'atrapamiento' => ['Rescate de los pasajeros en 12 minutos y ajuste del freno de la máquina.', 'Liberación de la cabina y cambio del contacto de la puerta de piso.'],
            'falla' => ['Reinicio del controlador y cambio del relé de control.', 'Cambio del fusible de control y prueba de funcionamiento.', 'Ajuste de la cerradura de la puerta de piso.'],
            'ruido' => ['Lubricación de guías y ajuste de zapatas.', 'Ajuste de la polea de desvío y cambio del rodamiento.'],
            'otro' => ['Revisión general sin falla; se orientó al personal de portería.'],
        ];
        foreach ($this->emergencias as ['equipo' => $equipo, 'reportada' => $reportada]) {
            if ($reportada->gte(now())) {
                continue;
            }
            $tipo = ['falla', 'falla', 'falla', 'falla', 'falla', 'atrapamiento', 'atrapamiento', 'ruido', 'ruido', 'otro'][mt_rand(0, 9)];
            $atrapadas = $tipo === 'atrapamiento';
            $tecnico = $this->tecnicoPara($equipo);
            $asignada = $reportada->addMinutes(mt_rand(3, 12));
            $llegada = $asignada->addMinutes($atrapadas ? mt_rand(12, 38) : mt_rand(25, 95));
            $resuelta = $llegada->addMinutes(mt_rand(20, 150));
            $descripcion = match ($tipo) {
                'atrapamiento' => 'Cabina detenida entre pisos con '.mt_rand(1, 4).' persona(s) adentro.',
                'falla' => ['El ascensor no responde a las llamadas.', 'El ascensor quedó fuera de servicio en el primer piso.', 'La puerta no abre al llegar al piso.'][mt_rand(0, 2)],
                'ruido' => 'Golpe fuerte al frenar y vibración en la cabina.',
                default => 'El tablero de la cabina muestra un código de error.',
            };
            $emergencia = Emergencia::create([
                'numero' => $this->numero('EM', $reportada->year, 5),
                'equipo_id' => $equipo->id, 'tipo' => $tipo, 'origen' => ['panel', 'panel', 'app', 'qr'][mt_rand(0, 3)],
                'personas_atrapadas' => $atrapadas,
                'reportante_nombre' => self::REPORTANTES[mt_rand(0, count(self::REPORTANTES) - 1)],
                'reportante_telefono' => '3'.mt_rand(100000000, 229999999),
                'descripcion' => $descripcion, 'estado' => 'resuelta', 'minutos_objetivo' => $atrapadas ? 30 : 60,
                'tecnico_id' => $tecnico->id, 'reportada_en' => $reportada, 'asignada_en' => $asignada,
                'llegada_en' => $llegada, 'resuelta_en' => $resuelta,
                'solucion' => $soluciones[$tipo][mt_rand(0, count($soluciones[$tipo]) - 1)],
                'registrada_por' => $this->actor->id,
            ]);
            $emergencia->forceFill(['created_at' => $reportada, 'updated_at' => $resuelta])->saveQuietly();
            foreach (['reportada' => $reportada, 'asignada' => $asignada, 'en_sitio' => $llegada, 'resuelta' => $resuelta] as $estado => $cuando) {
                DB::table('seguimientos_emergencia')->insert(['emergencia_id' => $emergencia->id, 'estado' => $estado, 'usuario_id' => $estado === 'reportada' ? $this->actor->id : $tecnico->usuario_id, 'created_at' => $cuando]);
            }
            $equipo->historial()->forceCreate(['evento' => 'emergencia', 'descripcion' => "Emergencia {$emergencia->numero}: {$descripcion}", 'created_at' => $reportada]);
        }
    }

    // ---------- Hoy: trabajo en curso ----------

    private function trabajoDeHoy(): void
    {
        $hoy = $this->hoy;
        $equipos = Equipo::where('codigo', 'like', 'ASC-2__')->with('edificio')->orderBy('codigo')->get()->values();
        $clienteDe = fn (Equipo $e) => $e->edificio->cliente_id;
        $disponibles = array_values(array_filter($this->tecnicos, fn ($t) => $t->disponibilidad === 'disponible'));

        // Visitas del plan para hoy y mañana: órdenes asignadas (una ya en proceso y otra en camino).
        $visitas = MantenimientoProgramado::whereIn('equipo_id', $equipos->pluck('id'))->whereIn('estado', MantenimientoProgramado::PENDIENTES)
            ->whereBetween('fecha_programada', [$hoy->toDateString(), $hoy->addDays(1)->toDateString()])->orderBy('fecha_programada')->orderBy('hora')->limit(6)->get();
        foreach ($visitas as $k => $visita) {
            $equipo = $equipos->firstWhere('id', $visita->equipo_id);
            $esHoy = $visita->fecha_programada->isSameDay($hoy);
            $estado = $esHoy ? ([0 => 'en_proceso', 1 => 'en_camino'][$k] ?? 'asignada') : 'asignada';
            $tecnico = $disponibles[$k % count($disponibles)];
            $inicio = $estado === 'en_proceso' ? CarbonImmutable::now()->subMinutes(35) : null;
            $orden = $this->orden([
                'servicio' => 'preventivo', 'equipo' => $equipo, 'cliente_id' => $clienteDe($equipo), 'mantenimiento_id' => $visita->id,
                'fecha' => CarbonImmutable::parse($visita->fecha_programada), 'hora' => substr((string) $visita->hora, 0, 5) ?: '08:00',
                'prioridad' => 'media', 'descripcion' => 'Mantenimiento preventivo según el plan.', 'informe' => null,
            ], $estado, $inicio, null, [$tecnico]);
            if (in_array($estado, ['en_proceso', 'en_camino'], true)) {
                $this->ubicar($tecnico, $orden, $equipo, $estado === 'en_camino');
            }
        }

        // Correctivo crítico de hoy y otro asignado para mañana.
        if ($equipo = $equipos->get(4)) {
            $tecnico = $disponibles[2 % count($disponibles)];
            $orden = $this->orden([
                'servicio' => 'correctivo', 'equipo' => $equipo, 'cliente_id' => $clienteDe($equipo), 'mantenimiento_id' => null,
                'fecha' => $hoy, 'hora' => '07:30', 'prioridad' => 'critica', 'informe' => null,
                'descripcion' => 'Ascensor fuera de servicio: la puerta de cabina no cierra y bloquea el arranque.',
            ], 'en_proceso', CarbonImmutable::now()->subMinutes(70), null, [$tecnico]);
            $this->ubicar($tecnico, $orden, $equipo, false);
            $equipo->forceFill(['estado' => 'mantenimiento'])->save();
        }
        if ($equipo = $equipos->get(17)) {
            $this->orden([
                'servicio' => 'correctivo', 'equipo' => $equipo, 'cliente_id' => $clienteDe($equipo), 'mantenimiento_id' => null,
                'fecha' => $hoy->addDay(), 'hora' => '10:00', 'prioridad' => 'alta', 'informe' => null,
                'descripcion' => 'Desnivel de 4 cm en el piso 6 reportado por la administración.',
            ], 'asignada', null, null, [$disponibles[0]]);
        }

        // Una emergencia abierta reportada por QR hace 20 minutos y otra con el técnico en camino.
        foreach ([[9, 'ruido', 'qr', 'Residente apto 503', 'Golpe fuerte al frenar en el piso 5.', null, 20], [22, 'atrapamiento', 'panel', 'Portería', 'Cabina detenida en el piso 3 con 2 personas.', $disponibles[1] ?? null, 12]] as [$i, $tipo, $origen, $quien, $descripcion, $tecnico, $minutos]) {
            if (! ($equipo = $equipos->get($i))) {
                continue;
            }
            $reportada = CarbonImmutable::now()->subMinutes($minutos);
            $emergencia = Emergencia::create([
                'numero' => $this->numero('EM', $reportada->year, 5), 'equipo_id' => $equipo->id, 'tipo' => $tipo, 'origen' => $origen,
                'personas_atrapadas' => $tipo === 'atrapamiento', 'reportante_nombre' => $quien, 'reportante_telefono' => '3'.mt_rand(100000000, 229999999),
                'descripcion' => $descripcion, 'estado' => $tecnico ? 'asignada' : 'reportada', 'minutos_objetivo' => $tipo === 'atrapamiento' ? 30 : 60,
                'tecnico_id' => $tecnico?->id, 'reportada_en' => $reportada, 'asignada_en' => $tecnico ? $reportada->addMinutes(3) : null,
                'registrada_por' => $this->actor->id,
            ]);
            $emergencia->forceFill(['created_at' => $reportada])->saveQuietly();
            DB::table('seguimientos_emergencia')->insert(['emergencia_id' => $emergencia->id, 'estado' => 'reportada', 'usuario_id' => $this->actor->id, 'created_at' => $reportada]);
        }

        // Cotizaciones de los hallazgos: una esperando respuesta, una aprobada y una rechazada.
        foreach ([[2, 'Cambio de amortiguadores del foso', 'RP-0112', 2, 'enviada'], [7, 'Modernización del operador de puertas', 'RP-0109', 1, 'aprobada'], [13, 'Cambio de cortina fotoeléctrica', 'RP-0108', 1, 'rechazada']] as [$i, $titulo, $codigo, $cantidad, $estado]) {
            if (! ($equipo = $equipos->get($i))) {
                continue;
            }
            $repuesto = $this->repuestos[$codigo];
            $subtotal = $repuesto->precio * $cantidad + 450000;
            $emitida = $hoy->subDays(mt_rand(3, 20));
            $cotizacion = Cotizacion::create([
                'numero' => $this->numero('CT', $emitida->year, 5, Cotizacion::class),
                'equipo_id' => $equipo->id, 'cliente_id' => $clienteDe($equipo), 'titulo' => $titulo,
                'descripcion' => "Hallazgo del último preventivo. Incluye {$cantidad} × {$repuesto->nombre}, instalación y pruebas.",
                'subtotal' => $subtotal, 'iva' => round($subtotal * 0.19), 'total' => round($subtotal * 1.19), 'descuento' => 0,
                'fecha_emision' => $emitida->toDateString(), 'vigente_hasta' => $emitida->addDays(30)->toDateString(),
                'estado' => $estado, 'respondida_en' => $estado === 'enviada' ? null : $emitida->addDays(2),
                'motivo_rechazo' => $estado === 'rechazada' ? 'La administración lo dejará para el presupuesto del próximo año.' : null,
                'creada_por' => $this->actor->id,
            ]);
            $cotizacion->items()->createMany([
                ['descripcion' => $repuesto->nombre, 'cantidad' => $cantidad, 'valor_unitario' => $repuesto->precio, 'valor_total' => $repuesto->precio * $cantidad],
                ['descripcion' => 'Mano de obra, instalación y pruebas', 'cantidad' => 1, 'valor_unitario' => 450000, 'valor_total' => 450000],
            ]);
        }
    }

    private function ubicar(Tecnico $tecnico, OrdenTrabajo $orden, Equipo $equipo, bool $enCamino): void
    {
        [, $lat, $lng] = self::CIUDADES[$equipo->edificio->ciudad] ?? self::CIUDADES['Bogotá'];
        $lat += mt_rand(-150, 150) / 10000;
        $lng += mt_rand(-150, 150) / 10000;
        foreach (range(3, 0) as $paso) {
            UbicacionTecnico::create([
                'tecnico_id' => $tecnico->id, 'orden_id' => $orden->id,
                'latitud' => round($lat + ($enCamino ? $paso * 0.0025 : 0), 7), 'longitud' => round($lng - ($enCamino ? $paso * 0.0018 : 0), 7),
                'precision_metros' => mt_rand(5, 18), 'origen' => 'app', 'registrada_en' => now()->subMinutes($paso * 4),
            ]);
        }
    }

    // ---------- Utilidades ----------

    /** Siguiente número consecutivo con el formato del sistema (OT-2026-00001, EM-…, CT-2026-0001, FV-0001). */
    private function numero(string $prefijo, ?int $anio, int $digitos, ?string $modelo = null): string
    {
        $base = $anio ? "{$prefijo}-{$anio}-" : "{$prefijo}-";
        $clave = ($modelo ?? $prefijo).'|'.$base;
        if (! isset($this->consecutivos[$clave])) {
            $modelo ??= match ($prefijo) {
                'OT' => OrdenTrabajo::class, 'EM' => Emergencia::class, 'CT' => Contrato::class, default => Factura::class
            };
            $ultimo = $modelo::where('numero', 'like', $base.'%')->pluck('numero')
                ->map(fn ($n) => (int) substr($n, strlen($base)))->max();
            $this->consecutivos[$clave] = (int) $ultimo;
        }

        return $base.str_pad((string) ++$this->consecutivos[$clave], $digitos, '0', STR_PAD_LEFT);
    }
}

<?php

namespace App\Services\Excel;

use App\Exceptions\ReglaNegocioException;
use App\Models\Cliente;
use App\Models\Edificio;
use App\Models\Equipo;
use App\Models\Factura;
use App\Models\Pago;
use App\Models\PlanillaResultado;
use App\Models\PlanMantenimiento;
use App\Models\PuertaPiso;
use App\Models\Tecnico;
use App\Models\Usuario;
use App\Services\OrdenTrabajoService;
use App\Support\Excel\Opciones;
use App\Support\Excel\Plantilla;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Exporta los datos con la misma estructura de la plantilla: parte del archivo de la plantilla (validaciones,
 * listas, comentarios y estilos), quita la fila de ejemplo de las hojas exportadas y escribe los registros.
 */
class ExportacionService
{
    /** Hojas que descarga cada módulo. */
    public const MODULOS = [
        'clientes' => ['clientes', 'edificios'],
        'ascensores' => ['ascensores', 'puertas'],
        'ordenes' => ['ordenes', 'checklist'],
        'cartera' => ['facturas', 'pagos'],
        'usuarios' => ['usuarios'],
        'todo' => ['clientes', 'edificios', 'ascensores', 'puertas', 'ordenes', 'checklist', 'facturas', 'pagos', 'usuarios'],
    ];

    private const PERMISOS = [
        'clientes' => 'clientes.ver', 'edificios' => 'edificios.ver', 'ascensores' => 'equipos.ver', 'puertas' => 'equipos.ver',
        'ordenes' => 'ordenes.ver', 'checklist' => 'ordenes.ver', 'facturas' => 'cartera.ver', 'pagos' => 'cartera.ver', 'usuarios' => 'usuarios.ver',
    ];

    public function __construct(private readonly OrdenTrabajoService $ordenes) {}

    /** Genera el archivo y devuelve su ruta temporal. */
    public function exportar(string $modulo, Usuario $usuario): string
    {
        $hojas = array_values(array_filter(self::MODULOS[$modulo] ?? [], fn ($h) => $usuario->tienePermiso(self::PERMISOS[$h])));
        if ($hojas === []) {
            throw new ReglaNegocioException('Su rol no tiene permiso para exportar esta información.');
        }

        $libro = IOFactory::load(Plantilla::rutaArchivo());
        $this->llenarListas($libro->getSheetByName('Listas'));

        foreach ($hojas as $clave) {
            $hoja = $libro->getSheetByName(Plantilla::hoja($clave)['nombre']);
            $this->escribir($hoja, $clave, $this->{'filas'.ucfirst($clave)}($usuario));
        }
        $libro->setActiveSheetIndexByName(Plantilla::hoja($hojas[0])['nombre']);

        $ruta = tempnam(sys_get_temp_dir(), 'sigma').'.xlsx';
        IOFactory::createWriter($libro, 'Xlsx')->save($ruta);

        return $ruta;
    }

    /** Nombre del archivo descargado: SIGMA_Ascensores_2026-10-20.xlsx */
    public static function nombreArchivo(string $modulo): string
    {
        $nombres = ['clientes' => 'Clientes', 'ascensores' => 'Ascensores', 'ordenes' => 'Ordenes', 'cartera' => 'Cartera', 'usuarios' => 'Usuarios', 'todo' => 'Datos'];

        return 'SIGMA_'.($nombres[$modulo] ?? 'Datos').'_'.now()->toDateString().'.xlsx';
    }

    private function llenarListas(Worksheet $listas): void
    {
        $dinamicas = [
            'tecnicos' => Tecnico::with('usuario')->get()->filter(fn ($t) => $t->usuario?->activo)->map(fn ($t) => $t->usuario->nombre)->sort()->values()->all(),
            'planes' => PlanMantenimiento::where('activo', true)->orderBy('nombre')->pluck('nombre')->all(),
        ];
        foreach ($dinamicas as $nombre => $valores) {
            $columna = Plantilla::columnaLista($nombre);
            foreach ($valores as $i => $valor) {
                $listas->setCellValue($columna.($i + 2), $valor);
            }
        }
    }

    private function escribir(Worksheet $hoja, string $clave, iterable $filas): void
    {
        $columnas = Plantilla::hoja($clave)['columnas'];
        // Fila 2: quita el ejemplo y su estilo resaltado.
        $ultima = Plantilla::letra(count($columnas));
        $hoja->getStyle("A2:{$ultima}2")->applyFromArray(['font' => ['italic' => false, 'color' => ['argb' => 'FF22124A']], 'fill' => ['fillType' => 'none']]);
        foreach (range(1, count($columnas)) as $i) {
            $hoja->setCellValue(Plantilla::letra($i).'2', null);
        }

        $numero = 2;
        foreach ($filas as $fila) {
            foreach ($columnas as $i => $columna) {
                $valor = $fila[$columna['clave']] ?? null;
                if ($valor === null || $valor === '') {
                    continue;
                }
                $celda = Plantilla::letra($i + 1).$numero;
                $valor = match ($columna['tipo']) {
                    'fecha' => Plantilla::aFechaExcel($valor),
                    'hora' => Plantilla::aHoraExcel($valor),
                    default => $valor,
                };
                $hoja->setCellValue($celda, $valor);
            }
            $numero++;
        }

        // Formatos colombianos en las columnas de fecha, hora y pesos.
        if ($numero > 2) {
            foreach ($columnas as $i => $columna) {
                $formato = ['fecha' => 'dd/mm/yyyy', 'hora' => 'hh:mm', 'moneda' => '"$" #,##0', 'decimal' => '0.0#'][$columna['tipo']] ?? null;
                if ($formato) {
                    $letra = Plantilla::letra($i + 1);
                    $hoja->getStyle("{$letra}2:{$letra}".($numero - 1))->getNumberFormat()->setFormatCode($formato);
                }
            }
        }
    }

    private function deCliente(Builder $consulta, Usuario $usuario, string $columna = 'cliente_id'): Builder
    {
        return $usuario->esCliente() ? $consulta->where($columna, $usuario->cliente_id) : $consulta;
    }

    // ---------- Filas por hoja ----------

    private function filasClientes(Usuario $usuario): iterable
    {
        $consulta = Cliente::with(['contactos', 'contratos' => fn ($c) => $c->where('estado', 'vigente')->latest('fecha_inicio')])->orderBy('codigo');
        foreach ($this->deCliente($consulta, $usuario, 'id')->cursor() as $cliente) {
            $contrato = $cliente->contratos->first();
            yield [
                'codigo' => $cliente->codigo,
                'nombre' => $cliente->nombre,
                'tipo' => Opciones::TIPOS_PERSONA[$cliente->tipo] ?? $cliente->tipo,
                'documento' => $cliente->tipo === 'natural' ? $cliente->numero_documento : $cliente->nit,
                'tipo_documento' => $cliente->tipo === 'natural' ? $cliente->tipo_documento : null,
                'contacto' => $cliente->contactos->first()?->nombre,
                'telefono' => $cliente->telefono,
                'correo' => $cliente->correo,
                'direccion' => $cliente->direccion,
                'contrato' => $contrato?->numero,
                'vigencia_desde' => $contrato?->fecha_inicio?->toDateString(),
                'vigencia_hasta' => $contrato?->fecha_fin?->toDateString(),
                'valor_contrato' => $contrato ? (float) $contrato->valor : null,
                'estado' => $cliente->estado ? 'Activo' : 'Inactivo',
            ];
        }
    }

    private function filasEdificios(Usuario $usuario): iterable
    {
        $consulta = Edificio::with('cliente')->orderBy('cliente_id')->orderBy('nombre');
        foreach ($this->deCliente($consulta, $usuario)->cursor() as $edificio) {
            yield [
                'cliente' => $edificio->cliente?->codigo,
                'nombre' => $edificio->nombre,
                'tipo' => Opciones::tiposEdificio()[$edificio->tipo] ?? $edificio->tipo,
                'direccion' => $edificio->direccion,
                'ciudad' => $edificio->ciudad,
                'departamento' => $edificio->departamento,
                'pisos' => $edificio->pisos,
                'contacto' => $edificio->nombre_contacto,
                'telefono' => $edificio->telefono_contacto,
                'correo' => $edificio->correo_contacto,
                'estado' => $edificio->activo ? 'Activo' : 'Inactivo',
            ];
        }
    }

    private function filasAscensores(Usuario $usuario): iterable
    {
        $consulta = Equipo::with(['edificio.cliente', 'ascensor', 'tecnico.usuario', 'asignacionActiva.plan', 'asignacionActiva.periodicidad'])->orderBy('codigo');
        if ($usuario->esCliente()) {
            $consulta->whereHas('edificio', fn ($c) => $c->where('cliente_id', $usuario->cliente_id));
        }
        foreach ($consulta->cursor() as $equipo) {
            $ficha = $equipo->ascensor;
            $asignacion = $equipo->asignacionActiva;
            yield [
                'codigo' => $equipo->codigo,
                'cliente' => $equipo->edificio?->cliente?->codigo,
                'edificio' => $equipo->edificio?->nombre,
                'nombre' => $equipo->nombre,
                'marca' => $equipo->marca,
                'modelo' => $equipo->modelo,
                'anio_instalacion' => $equipo->fecha_instalacion?->year,
                'numero_serie' => $equipo->numero_serie,
                'capacidad_kg' => $ficha?->capacidad_kg,
                'personas' => $ficha?->capacidad_personas,
                'velocidad_ms' => $ficha?->velocidad_mpm ? round((float) $ficha->velocidad_mpm / 60, 2) : null,
                'paradas' => $ficha?->paradas,
                'accionamiento' => $ficha?->tipo_traccion,
                'controlador' => $ficha?->controlador,
                'tipo_puerta' => $ficha?->tipo_puerta,
                'periodicidad' => $asignacion?->periodicidad?->meses,
                'plan' => $asignacion?->plan?->nombre,
                'fecha_primer_mantenimiento' => $asignacion?->fecha_inicio?->toDateString(),
                'tecnico' => $equipo->tecnico?->usuario?->nombre,
                'valor_visita' => $asignacion ? (float) $asignacion->precio : null,
                'estado' => Opciones::ESTADOS_EQUIPO[$equipo->estado] ?? $equipo->estado,
                'observaciones' => $equipo->observaciones,
            ];
        }
    }

    private function filasPuertas(Usuario $usuario): iterable
    {
        $consulta = PuertaPiso::with('equipo')->whereHas('equipo')->orderBy('equipo_id')->orderBy('piso');
        if ($usuario->esCliente()) {
            $consulta->whereHas('equipo.edificio', fn ($c) => $c->where('cliente_id', $usuario->cliente_id));
        }
        foreach ($consulta->cursor() as $puerta) {
            yield [
                'ascensor' => $puerta->equipo->codigo,
                'piso' => $puerta->piso,
                'tipo' => Opciones::tiposPuertaPiso()[$puerta->tipo] ?? $puerta->tipo,
                'estado' => Opciones::estadosPuerta()[$puerta->estado] ?? $puerta->estado,
                'observacion' => $puerta->observacion,
            ];
        }
    }

    private function filasOrdenes(Usuario $usuario): iterable
    {
        $consulta = $this->ordenes->consultaVisible($usuario)->with(['equipo', 'tecnicos.usuario'])->orderBy('fecha')->orderBy('hora');
        foreach ($consulta->cursor() as $orden) {
            $inicio = substr((string) $orden->hora, 0, 5);
            yield [
                'numero' => $orden->numero,
                'ascensor' => $orden->equipo?->codigo,
                'tipo' => Opciones::TIPOS_ORDEN[$orden->servicio] ?? $orden->servicio,
                'prioridad' => Opciones::PRIORIDADES[$orden->prioridad] ?? $orden->prioridad,
                'tecnico' => $orden->tecnicos->first()?->usuario?->nombre,
                'fecha' => $orden->fecha?->toDateString(),
                'hora_inicio' => $inicio,
                'hora_fin' => $inicio ? now()->setTimeFromTimeString($inicio)->addMinutes((int) $orden->duracion_estimada)->format('H:i') : null,
                'estado' => Opciones::ESTADOS_ORDEN[$orden->estado] ?? $orden->estado,
                'descripcion' => $orden->descripcion,
                'observaciones' => $orden->observaciones,
            ];
        }
    }

    private function filasChecklist(Usuario $usuario): iterable
    {
        $ordenes = $this->ordenes->consultaVisible($usuario)->select('id');
        $consulta = PlanillaResultado::with(['item', 'planilla.orden'])
            ->whereHas('planilla', fn ($c) => $c->whereIn('orden_id', $ordenes))
            ->orderBy('planilla_id')->orderBy('item_id');
        foreach ($consulta->cursor() as $resultado) {
            yield [
                'orden' => $resultado->planilla?->orden?->numero,
                'zona' => Opciones::ZONAS[$resultado->item?->seccion] ?? $resultado->item?->seccion,
                'item' => $resultado->item?->codigo,
                'resultado' => $resultado->estado,
                'tipo' => Opciones::TIPOS_REVISION[$resultado->resultado] ?? $resultado->resultado,
                'observacion' => $resultado->observacion,
            ];
        }
    }

    private function filasFacturas(Usuario $usuario): iterable
    {
        $consulta = Factura::with('cliente')->orderBy('fecha_emision')->orderBy('numero');
        foreach ($this->deCliente($consulta, $usuario)->cursor() as $factura) {
            yield [
                'cliente' => $factura->cliente?->codigo,
                'numero' => $factura->numero,
                'concepto' => $factura->concepto,
                'fecha_emision' => $factura->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $factura->fecha_vencimiento?->toDateString(),
                'valor' => (float) $factura->valor,
                'saldo' => (float) $factura->saldo,
                'estado' => Opciones::ESTADOS_FACTURA[$factura->estado] ?? $factura->estado,
            ];
        }
    }

    private function filasPagos(Usuario $usuario): iterable
    {
        $consulta = Pago::with(['cliente', 'factura'])->orderBy('fecha')->orderBy('id');
        foreach ($this->deCliente($consulta, $usuario)->cursor() as $pago) {
            yield [
                'cliente' => $pago->cliente?->codigo,
                'factura' => $pago->factura?->numero,
                'valor' => (float) $pago->valor,
                'fecha' => $pago->fecha?->toDateString(),
                'medio' => Opciones::mediosPago()[$pago->medio] ?? $pago->medio,
                'referencia' => $pago->referencia,
            ];
        }
    }

    private function filasUsuarios(Usuario $usuario): iterable
    {
        foreach (Usuario::with(['rol', 'cliente'])->orderBy('nombre')->cursor() as $fila) {
            yield [
                'nombre' => $fila->nombre,
                'correo' => $fila->correo,
                'rol' => Opciones::ROLES[$fila->rol?->nombre] ?? $fila->rol?->nombre,
                'estado' => $fila->activo ? 'Activo' : 'Inactivo',
                'cliente' => $fila->cliente?->codigo,
            ];
        }
    }
}

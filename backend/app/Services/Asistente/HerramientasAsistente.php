<?php

namespace App\Services\Asistente;

use App\Http\Controllers\Api\BusquedaController;
use App\Models\Contrato;
use App\Models\Emergencia;
use App\Models\Equipo;
use App\Models\Factura;
use App\Models\InformeOrden;
use App\Models\MantenimientoProgramado;
use App\Models\Usuario;
use App\Models\VisitaTecnica;
use App\Services\OrdenTrabajoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Acciones del asistente del panel. Cada una revisa el permiso del rol y, si el usuario es cliente,
 * solo muestra lo de su cliente: el asistente nunca ve más de lo que el usuario puede ver en el panel.
 * Las usa tanto el motor local como el agente de n8n (vía /asistente/herramienta con un token temporal).
 *
 * Respuesta: ['texto' => string, 'acciones' => [['tipo' => 'enlace'|'pdf', 'texto' => ..., 'ruta' => ...]]]
 */
class HerramientasAsistente
{
    public const ACCIONES = [
        'buscar' => 'Buscar ascensores, edificios, clientes, contratos u órdenes (parámetro: texto)',
        'ordenes_activas' => 'Órdenes de trabajo activas (el técnico ve solo las suyas)',
        'emergencias_abiertas' => 'Emergencias reportadas que siguen abiertas',
        'proximos_mantenimientos' => 'Mantenimientos programados de los próximos días (parámetro opcional: dias)',
        'estado_ascensor' => 'Estado, plan y próximo mantenimiento de un ascensor (parámetro: codigo)',
        'cartera_vencida' => 'Facturas vencidas o con saldo pendiente',
        'pdf_informe_orden' => 'PDF del informe de una orden (parámetro: numero, ej. OT-2026-00013)',
        'pdf_contrato' => 'PDF de un contrato (parámetro: numero)',
        'pdf_indicadores' => 'PDF de los indicadores del servicio',
        'pdf_planilla_visita' => 'PDF de la última planilla de asistencia técnica de un ascensor (parámetro: codigo)',
    ];

    public function __construct(private readonly OrdenTrabajoService $ordenes) {}

    /** Acciones que este usuario puede usar (el administrador, todas). */
    public function disponibles(Usuario $usuario): array
    {
        $permiso = [
            'buscar' => null,
            'ordenes_activas' => 'ordenes.ver',
            'emergencias_abiertas' => 'emergencias.ver',
            'proximos_mantenimientos' => 'mantenimientos.ver',
            'estado_ascensor' => 'equipos.ver',
            'cartera_vencida' => 'cartera.ver',
            'pdf_informe_orden' => 'ordenes.ver',
            'pdf_contrato' => 'contratos.ver',
            'pdf_indicadores' => 'indicadores.ver',
            'pdf_planilla_visita' => 'equipos.ver',
        ];

        return array_filter(self::ACCIONES, fn ($_, $accion) => $permiso[$accion] === null || $usuario->tienePermiso($permiso[$accion]), ARRAY_FILTER_USE_BOTH);
    }

    public function ejecutar(Usuario $usuario, string $accion, array $parametros = []): array
    {
        if (! array_key_exists($accion, $this->disponibles($usuario))) {
            return $this->texto(array_key_exists($accion, self::ACCIONES)
                ? 'Su rol no tiene acceso a esa información. Pídasela al administrador.'
                : 'No reconozco esa acción.');
        }

        return match ($accion) {
            'buscar' => $this->buscar($usuario, (string) ($parametros['texto'] ?? '')),
            'ordenes_activas' => $this->ordenesActivas($usuario),
            'emergencias_abiertas' => $this->emergenciasAbiertas($usuario),
            'proximos_mantenimientos' => $this->proximosMantenimientos($usuario, (int) ($parametros['dias'] ?? 7)),
            'estado_ascensor' => $this->estadoAscensor($usuario, (string) ($parametros['codigo'] ?? '')),
            'cartera_vencida' => $this->carteraVencida($usuario),
            'pdf_informe_orden' => $this->pdfInformeOrden($usuario, (string) ($parametros['numero'] ?? '')),
            'pdf_contrato' => $this->pdfContrato($usuario, (string) ($parametros['numero'] ?? '')),
            'pdf_indicadores' => ['texto' => 'Aquí tiene el PDF de los indicadores del servicio (últimos 90 días).', 'acciones' => [['tipo' => 'pdf', 'texto' => 'Descargar indicadores', 'ruta' => '/indicadores/servicio/pdf', 'nombre' => 'SIGMA_Indicadores.pdf']]],
            'pdf_planilla_visita' => $this->pdfPlanillaVisita($usuario, (string) ($parametros['codigo'] ?? '')),
        };
    }

    private function buscar(Usuario $usuario, string $texto): array
    {
        $texto = trim($texto);
        if (mb_strlen($texto) < 2) {
            return $this->texto('Escriba qué busca, por ejemplo: «buscar Torre A» o «buscar ASC-101».');
        }
        $solicitud = Request::create('/api/v1/buscar', 'GET', ['q' => $texto]);
        $solicitud->setUserResolver(fn () => $usuario);
        $grupos = app(BusquedaController::class)($solicitud)->getData(true)['datos'] ?? [];
        $nombres = ['ascensores' => 'Ascensor', 'edificios' => 'Edificio', 'clientes' => 'Cliente', 'contratos' => 'Contrato', 'ordenes' => 'Orden'];
        $acciones = [];
        foreach ($grupos as $grupo => $filas) {
            foreach ($filas as $fila) {
                $acciones[] = ['tipo' => 'enlace', 'texto' => ($nombres[$grupo] ?? ucfirst($grupo)).': '.$fila['titulo'].(! empty($fila['detalle']) ? ' · '.$fila['detalle'] : ''), 'ruta' => $fila['enlace']];
            }
        }

        return $acciones
            ? ['texto' => 'Encontré '.count($acciones)." resultado(s) para «{$texto}»:", 'acciones' => array_slice($acciones, 0, 10)]
            : $this->texto("No encontré nada para «{$texto}» con los permisos de su rol.");
    }

    private function ordenesActivas(Usuario $usuario): array
    {
        $ordenes = $this->ordenes->consultaVisible($usuario)->with(['equipo', 'edificio'])
            ->whereNotIn('estado', ['finalizada', 'cancelada'])->orderBy('fecha')->orderBy('hora')->limit(10)->get();
        if ($ordenes->isEmpty()) {
            return $this->texto('No hay órdenes de trabajo activas.');
        }

        return [
            'texto' => ($usuario->esTecnico() ? 'Sus órdenes activas' : 'Órdenes activas').' (las 10 más próximas):',
            'acciones' => $ordenes->map(fn ($o) => ['tipo' => 'enlace', 'texto' => "{$o->numero} · {$o->equipo?->codigo} · ".str_replace('_', ' ', $o->estado).' · '.$o->fecha?->format('d/m/Y'), 'ruta' => "/ordenes/{$o->id}"])->all(),
        ];
    }

    private function emergenciasAbiertas(Usuario $usuario): array
    {
        $emergencias = Emergencia::with('equipo.edificio')->whereIn('estado', ['reportada', 'asignada', 'en_sitio'])
            ->when($usuario->esCliente(), fn ($c) => $c->whereHas('equipo.edificio', fn ($e) => $e->where('cliente_id', $usuario->cliente_id)))
            ->latest('reportada_en')->limit(10)->get();
        if ($emergencias->isEmpty()) {
            return $this->texto('No hay emergencias abiertas en este momento.');
        }

        return [
            'texto' => "Hay {$emergencias->count()} emergencia(s) abierta(s):",
            'acciones' => $emergencias->map(fn ($e) => ['tipo' => 'enlace', 'texto' => ($e->numero ?? "#{$e->id}").' · '.($e->equipo?->codigo).' · '.(Emergencia::ESTADOS[$e->estado] ?? $e->estado).' · '.$e->reportada_en?->format('d/m H:i'), 'ruta' => '/emergencias'])->all(),
        ];
    }

    private function proximosMantenimientos(Usuario $usuario, int $dias): array
    {
        $dias = max(1, min($dias, 60));
        $visitas = MantenimientoProgramado::with('equipo.edificio')->whereIn('estado', MantenimientoProgramado::PENDIENTES)
            ->whereBetween('fecha_programada', [now()->toDateString(), now()->addDays($dias)->toDateString()])
            ->when($usuario->esCliente(), fn ($c) => $c->whereHas('equipo.edificio', fn ($e) => $e->where('cliente_id', $usuario->cliente_id)))
            ->orderBy('fecha_programada')->limit(12)->get();
        if ($visitas->isEmpty()) {
            return $this->texto("No hay mantenimientos programados en los próximos {$dias} días.");
        }

        return [
            'texto' => "Mantenimientos de los próximos {$dias} días:",
            'acciones' => $visitas->map(fn ($v) => ['tipo' => 'enlace', 'texto' => $v->fecha_programada->format('d/m/Y').' · '.$v->equipo?->codigo.' · '.$v->equipo?->edificio?->nombre, 'ruta' => "/equipos/{$v->equipo_id}"])->all(),
        ];
    }

    private function equipoVisible(Usuario $usuario, string $codigo): ?Equipo
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        return Equipo::with(['edificio', 'asignacionActiva'])
            ->when($usuario->esCliente(), fn ($c) => $c->whereHas('edificio', fn ($e) => $e->where('cliente_id', $usuario->cliente_id)))
            ->whereRaw('upper(codigo) = ?', [mb_strtoupper($codigo)])
            ->first();
    }

    private function estadoAscensor(Usuario $usuario, string $codigo): array
    {
        $equipo = $this->equipoVisible($usuario, $codigo);
        if (! $equipo) {
            return $this->texto("No encontré el ascensor «{$codigo}» entre los que usted puede ver.");
        }
        $proximo = MantenimientoProgramado::where('equipo_id', $equipo->id)->whereIn('estado', MantenimientoProgramado::PENDIENTES)->orderBy('fecha_programada')->first();
        $ultima = VisitaTecnica::where('equipo_id', $equipo->id)->latest('salida_en')->first();

        return [
            'texto' => "Ascensor {$equipo->codigo} ({$equipo->edificio?->nombre}): estado {$equipo->estado}."
                .($proximo ? ' Próximo mantenimiento: '.$proximo->fecha_programada->format('d/m/Y').'.' : ' Sin mantenimientos programados.')
                .($ultima ? " Última visita técnica: {$ultima->salida_en->format('d/m/Y')} (planilla N.º {$ultima->numero})." : ''),
            'acciones' => array_values(array_filter([
                ['tipo' => 'enlace', 'texto' => "Abrir ficha de {$equipo->codigo}", 'ruta' => "/equipos/{$equipo->id}"],
                $ultima ? ['tipo' => 'pdf', 'texto' => "Planilla N.º {$ultima->numero}", 'ruta' => "/visitas/{$ultima->id}/pdf", 'nombre' => "Planilla-{$ultima->numero}.pdf"] : null,
            ])),
        ];
    }

    private function carteraVencida(Usuario $usuario): array
    {
        $facturas = Factura::with('cliente')->where('saldo', '>', 0)->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->when($usuario->esCliente(), fn ($c) => $c->where('cliente_id', $usuario->cliente_id))
            ->orderBy('fecha_vencimiento')->limit(10)->get();
        if ($facturas->isEmpty()) {
            return $this->texto('No hay facturas vencidas. ¡Cartera al día!');
        }
        $total = number_format((float) $facturas->sum('saldo'), 0, ',', '.');

        return [
            'texto' => "Facturas vencidas (saldo de las mostradas: \${$total}):",
            'acciones' => $facturas->map(fn ($f) => ['tipo' => 'enlace', 'texto' => "{$f->numero} · {$f->cliente?->nombre} · \$".number_format((float) $f->saldo, 0, ',', '.').' · venció '.$f->fecha_vencimiento?->format('d/m/Y'), 'ruta' => '/cartera'])->all(),
        ];
    }

    private function pdfInformeOrden(Usuario $usuario, string $numero): array
    {
        $orden = $this->ordenes->consultaVisible($usuario)->whereRaw('upper(numero) = ?', [mb_strtoupper(trim($numero))])->first();
        if (! $orden) {
            return $this->texto("No encontré la orden «{$numero}» entre las que usted puede ver. Escriba el número completo, ej. OT-2026-00013.");
        }
        $informe = InformeOrden::where('orden_id', $orden->id)->orderByDesc('version')->first();
        if (! $informe) {
            return ['texto' => "La orden {$orden->numero} todavía no tiene informe PDF (se genera al finalizarla).", 'acciones' => [['tipo' => 'enlace', 'texto' => "Abrir {$orden->numero}", 'ruta' => "/ordenes/{$orden->id}"]]];
        }

        return ['texto' => "Informe de la orden {$orden->numero} (versión {$informe->version}):", 'acciones' => [
            ['tipo' => 'pdf', 'texto' => "Descargar informe {$orden->numero}", 'ruta' => "/ordenes/{$orden->id}/informes/{$informe->id}/pdf", 'nombre' => "Informe-{$orden->numero}-v{$informe->version}.pdf"],
        ]];
    }

    private function pdfContrato(Usuario $usuario, string $numero): array
    {
        $contrato = Contrato::query()->when($usuario->esCliente(), fn ($c) => $c->where('cliente_id', $usuario->cliente_id))
            ->whereRaw('upper(numero) = ?', [mb_strtoupper(trim($numero))])->first();
        if (! $contrato) {
            return $this->texto("No encontré el contrato «{$numero}» entre los que usted puede ver.");
        }

        return ['texto' => "Contrato {$contrato->numero}:", 'acciones' => [
            ['tipo' => 'pdf', 'texto' => "Descargar contrato {$contrato->numero}", 'ruta' => "/contratos/{$contrato->id}/pdf", 'nombre' => "Contrato-{$contrato->numero}.pdf"],
        ]];
    }

    private function pdfPlanillaVisita(Usuario $usuario, string $codigo): array
    {
        $equipo = $this->equipoVisible($usuario, $codigo);
        $visita = $equipo ? VisitaTecnica::where('equipo_id', $equipo->id)->latest('salida_en')->first() : null;
        if (! $visita) {
            return $this->texto($equipo ? "El ascensor {$equipo->codigo} aún no tiene planillas de asistencia técnica." : "No encontré el ascensor «{$codigo}» entre los que usted puede ver.");
        }

        return ['texto' => "Última planilla de {$equipo->codigo}: N.º {$visita->numero} del {$visita->salida_en->format('d/m/Y')}.", 'acciones' => [
            ['tipo' => 'pdf', 'texto' => "Descargar planilla N.º {$visita->numero}", 'ruta' => "/visitas/{$visita->id}/pdf", 'nombre' => "Planilla-{$visita->numero}.pdf"],
        ]];
    }

    private function texto(string $texto): array
    {
        return ['texto' => Str::limit($texto, 2000), 'acciones' => []];
    }
}

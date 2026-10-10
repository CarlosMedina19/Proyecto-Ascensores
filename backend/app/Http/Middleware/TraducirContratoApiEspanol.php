<?php

namespace App\Http\Middleware;

use App\Database\DatabaseNames;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class TraducirContratoApiEspanol
{
    private const CLAVES = [
        'success' => 'exito',
        'message' => 'mensaje',
        'errors' => 'errores',
        'data' => 'datos',
        'user' => 'usuario',
        'token' => 'token_acceso',
        'file' => 'archivo',
        'current_page' => 'pagina_actual',
        'last_page' => 'ultima_pagina',
        'per_page' => 'elementos_por_pagina',
        'search' => 'buscar',
        'page' => 'pagina',
        'sort_by' => 'ordenar_por',
        'sort_direction' => 'sentido_orden',
        'first_page_url' => 'url_primera_pagina',
        'last_page_url' => 'url_ultima_pagina',
        'next_page_url' => 'url_pagina_siguiente',
        'prev_page_url' => 'url_pagina_anterior',
        'by_status' => 'por_estado',
        'overdue' => 'vencidas',
        'active_parts' => 'repuestos_activos',
        'below_minimum' => 'bajo_existencia_minima',
        'unpaid_invoices' => 'facturas_pendientes',
        'outstanding_balance' => 'saldo_pendiente',
        'equipment' => 'equipos',
        'work_orders' => 'ordenes_trabajo',
        'inventory' => 'inventario',
        'finance' => 'finanzas',
        'total' => 'total',
        'active' => 'activos',
        'maintenance' => 'mantenimiento',
        'id' => 'id',
    ];

    private const VALORES = [
        'service_type' => [
            'preventivo' => 'preventive',
            'correctivo' => 'corrective',
            'emergencia' => 'emergency',
            'inspeccion' => 'inspection',
        ],
        'status' => [
            'activo' => 'active',
            'inactivo' => 'inactive',
            'vencido' => 'expired',
            'borrador' => 'draft',
            'programada' => 'scheduled',
            'asignada' => 'assigned',
            'en_camino' => 'on_the_way',
            'en_progreso' => 'in_progress',
            'pendiente' => 'pending',
            'completada' => 'completed',
            'cancelada' => 'cancelled',
            'omitida' => 'skipped',
            'emitida' => 'issued',
            'enviada' => 'sent',
            'aprobada' => 'approved',
            'rechazada' => 'rejected',
            'pagada_parcialmente' => 'partially_paid',
            'pagada' => 'paid',
            'vencida' => 'overdue',
        ],
        'priority' => [
            'baja' => 'low',
            'media' => 'medium',
            'alta' => 'high',
            'critica' => 'critical',
        ],
        'movement_type' => [
            'entrada' => 'in',
            'salida' => 'out',
            'devolucion' => 'return',
            'ajuste' => 'adjustment',
            'pago' => 'payment',
        ],
        'method' => [
            'transferencia' => 'transfer',
            'efectivo' => 'cash',
            'tarjeta' => 'card',
            'cheque' => 'check',
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $previousLocale = app()->getLocale();
        app()->setLocale('es');

        try {
            $entrada = $request->isJson() ? $request->json() : $request->request;
            $entrada->replace($this->traducirClaves($entrada->all(), true));
            $request->query->replace($this->traducirClaves($request->query->all(), true));
            $request->files->replace($this->traducirClaves($request->files->all(), true));

            $respuesta = $next($request);

            if ($respuesta instanceof JsonResponse) {
                $respuesta->setData($this->traducirClaves($respuesta->getData(true), false));
            }

            return $respuesta;
        } catch (ValidationException $exception) {
            $errores = [];
            foreach ($exception->errors() as $campo => $mensajes) {
                $errores[$this->traducirClave($campo, false)] = $mensajes;
            }

            return response()->json([
                'mensaje' => 'Los datos proporcionados no son válidos.',
                'errores' => $errores,
            ], $exception->status);
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    private function traducirClaves(mixed $values, bool $toInternal): mixed
    {
        if (! is_array($values)) {
            return $values;
        }

        $traducido = [];
        foreach ($values as $clave => $valor) {
            $clave = is_string($clave) ? $this->traducirClave($clave, $toInternal) : $clave;

            if (array_key_exists($clave, $traducido)) {
                throw ValidationException::withMessages([
                    $clave => ['No envíe el mismo campo más de una vez con nombres diferentes.'],
                ]);
            }

            $traducido[$clave] = is_array($valor)
                ? $this->traducirClaves($valor, $toInternal)
                : $this->traducirValor($clave, $valor, $toInternal);
        }

        return $traducido;
    }

    private function traducirClave(string $clave, bool $toInternal): string
    {
        $mapa = self::CLAVES + DatabaseNames::tables() + DatabaseNames::columns();

        if ($toInternal) {
            $mapa = array_flip($mapa);
        }

        return $mapa[$clave] ?? $clave;
    }

    private function traducirValor(string $clave, mixed $valor, bool $toInternal): mixed
    {
        $claveInterna = $toInternal ? $clave : $this->traducirClave($clave, true);
        $mapa = self::VALORES[$claveInterna] ?? [];

        if (! is_string($valor) || $mapa === []) {
            return $valor;
        }

        if (! $toInternal) {
            $mapa = array_flip($mapa);
        }

        return $mapa[$valor] ?? $valor;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PostgreSQL no crea índices para las llaves foráneas. Sin ellos, los listados por cliente, ascensor,
 * orden o usuario y los borrados con llaves foráneas recorren la tabla completa cuando crecen los datos.
 * Crea solo los que falten (idempotente).
 */
return new class extends Migration
{
    /** tabla => columnas con llave foránea sin índice (revisado en producción el 19-nov-2026). */
    private const COLUMNAS = [
        'asignaciones_plan' => ['contrato_id', 'creado_por', 'periodicidad_id'],
        'cambios_asignacion' => ['usuario_id'],
        'certificaciones' => ['registrado_por'],
        'contratos' => ['contrato_anterior_id', 'creado_por', 'periodicidad_id', 'plan_id'],
        'cotizacion_items' => ['cotizacion_id'],
        'cotizaciones' => ['cliente_id', 'creada_por', 'equipo_id', 'factura_id', 'orden_id', 'respondida_por', 'resultado_origen_id'],
        'edificios' => ['conjunto_id'],
        'emergencias' => ['equipo_id', 'orden_id', 'registrada_por', 'tecnico_id'],
        'envios_notificacion' => ['usuario_id'],
        'equipos' => ['tecnico_id'],
        'especialidad_tecnico' => ['especialidad_id'],
        'facturas' => ['creado_por'],
        'firmas_orden' => ['registrada_por'],
        'fotos_equipo' => ['usuario_id'],
        'historial_equipos' => ['usuario_id'],
        'historial_precios' => ['periodicidad_id', 'usuario_id'],
        'informes_orden' => ['generado_por'],
        'mantenimientos_programados' => ['actualizado_por'],
        'movimientos_inventario' => ['orden_id', 'usuario_id'],
        'orden_repuestos' => ['registrado_por', 'repuesto_id'],
        'orden_tecnico' => ['asignado_por'],
        'ordenes_trabajo' => ['cliente_id', 'creado_por', 'edificio_id', 'orden_origen_id'],
        'pagos' => ['cliente_id', 'registrado_por'],
        'pagos_en_linea' => ['pago_id', 'usuario_id'],
        'planilla_fotos' => ['item_id', 'planilla_id', 'usuario_id'],
        'planilla_resultados' => ['actualizado_por', 'item_id'],
        'planillas' => ['tecnico_usuario_id'],
        'precios_plan' => ['periodicidad_id'],
        'rol_permisos' => ['permiso_id'],
        'seguimientos_emergencia' => ['emergencia_id', 'usuario_id'],
        'seguimientos_renovacion' => ['usuario_id'],
        'trazabilidad_ordenes' => ['usuario_id'],
        'visitas_tecnicas' => ['edificio_id', 'orden_id', 'tecnico_usuario_id'],
    ];

    public function up(): void
    {
        foreach (self::COLUMNAS as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            $faltantes = array_filter($columnas, fn ($c) => Schema::hasColumn($tabla, $c) && ! Schema::hasIndex($tabla, [$c]));
            if ($faltantes) {
                Schema::table($tabla, function (Blueprint $t) use ($tabla, $faltantes) {
                    foreach ($faltantes as $columna) {
                        $t->index($columna, "{$tabla}_{$columna}_idx");
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNAS as $tabla => $columnas) {
            if (! Schema::hasTable($tabla)) {
                continue;
            }
            Schema::table($tabla, function (Blueprint $t) use ($tabla, $columnas) {
                foreach ($columnas as $columna) {
                    if (Schema::hasIndex($tabla, "{$tabla}_{$columna}_idx")) {
                        $t->dropIndex("{$tabla}_{$columna}_idx");
                    }
                }
            });
        }
    }
};

<?php

namespace App\Support;

use App\Models\ChecklistItem;
use App\Models\ChecklistSeccion;
use Illuminate\Support\Facades\DB;

/**
 * Plantilla base de la «Planilla de asistencia técnica» (control de mantenimiento y reparaciones).
 * Es la que se instala la primera vez; después el administrador la edita desde Configuración.
 */
class PlantillaPlanilla
{
    /**
     * Secciones con su clave (la usa el esquema del ascensor), nombre y actividades [código, actividad, crítica].
     * Las críticas (seguridad o emergencia) en estado Malo avisan de inmediato al coordinador.
     */
    public const SECCIONES = [
        'cuarto_maquinas' => ['Cuarto de máquinas', [
            ['MQ-01', 'Limpieza y ajuste del control de maniobra.', false],
            ['MQ-02', 'Limpieza y ajuste del breaker de protección.', false],
            ['MQ-03', 'Inspección de resistencias de frenado.', false],
            ['MQ-04', 'Limpieza e inspección de máquina tractora.', false],
            ['MQ-05', 'Limpieza e inspección de bancada y poleas.', false],
            ['MQ-06', 'Limpieza e inspección de unidad hidráulica.', false],
            ['MQ-07', 'Limpieza, verificación y ajuste de bomba y válvulas.', false],
            ['MQ-08', 'Verificación y ajuste de mangueras hidráulicas.', false],
            ['MQ-09', 'Verificación y ajuste de electrofreno.', true],
            ['MQ-10', 'Verificación de los niveles de aceite.', false],
            ['MQ-11', 'Limpieza y verificación del limitador de velocidad.', true],
        ]],
        'cabina' => ['Cabina', [
            ['CB-01', 'Verificación de cabina (falso techo, iluminación, espejo y piso).', false],
            ['CB-02', 'Verificación y limpieza de la botonera.', false],
            ['CB-03', 'Verificación y ajuste de nivelación.', false],
            ['CB-04', 'Verificación y ajuste de cortina fotoeléctrica.', true],
            ['CB-05', 'Limpieza de la caja de inspección sobre cabina.', false],
            ['CB-06', 'Verificación y limpieza del ventilador.', false],
            ['CB-07', 'Verificación y ajuste de señales de emergencia.', true],
            ['CB-08', 'Verificación y limpieza de inductores.', false],
            ['CB-09', 'Verificación de aceiteras y niveles.', false],
        ]],
        'contrapeso' => ['Contrapeso', [
            ['CP-01', 'Ajuste de las zapatas.', false],
            ['CP-02', 'Verificación de aceiteras y niveles.', false],
            ['CP-03', 'Verificación de alargamiento de cables.', true],
        ]],
        'puertas' => ['Mecanismos de puertas', [
            ['PT-01', 'Limpieza y lubricación del operador de puertas en cabina.', false],
            ['PT-02', 'Ajuste y alineación de puertas de cabina y piso.', false],
            ['PT-03', 'Ajuste de las zapatas de nylon de puertas.', false],
            ['PT-04', 'Limpieza y ajuste de contactos eléctricos de puerta.', true],
            ['PT-05', 'Limpieza de quicios.', false],
            ['PT-06', 'Limpieza y lubricación de mecanismos de piso.', false],
            ['PT-07', 'Verificación y limpieza de puertas manuales.', false],
            ['PT-08', 'Verificación y ajuste de electrocerraduras.', true],
            ['PT-09', 'Verificación y ajuste de electroimanes.', false],
            ['PT-10', 'Verificación y ajuste del cierre permanente.', true],
            ['PT-11', 'Verificación de las botoneras de hall.', false],
        ]],
        'hueco' => ['Recorrido de foso', [
            ['RF-01', 'Limpieza y verificación de rieles.', false],
            ['RF-02', 'Ajuste y limpieza de brakets.', false],
            ['RF-03', 'Ajuste y verificación de cables de tracción.', true],
            ['RF-04', 'Ajuste y verificación de terminales del cable de tracción.', true],
            ['RF-05', 'Verificación de banderas de nivelación.', false],
            ['RF-06', 'Verificación y limpieza de micros antenales y finales.', true],
            ['RF-07', 'Inspección, ajuste y limpieza de actuador hidráulico.', false],
        ]],
        'foso' => ['En foso', [
            ['EF-01', 'Limpieza y ajuste de polea tensora.', false],
            ['EF-02', 'Verificación del stop de emergencia.', true],
            ['EF-03', 'Verificación de la iluminación en foso.', false],
            ['EF-04', 'Verificación del buffer.', true],
        ]],
    ];

    /** Cantidad de actividades de la plantilla base. */
    public static function totalActividades(): int
    {
        return array_sum(array_map(fn ($s) => count($s[1]), self::SECCIONES));
    }

    /**
     * Instala la plantilla si todavía no hay secciones. Las actividades anteriores quedan inactivas
     * (conservan el historial de las planillas ya diligenciadas). Idempotente.
     */
    public static function instalar(): void
    {
        if (ChecklistSeccion::exists()) {
            return;
        }

        DB::transaction(function () {
            ChecklistItem::query()->update(['activo' => false]);
            $ordenSeccion = 0;
            foreach (self::SECCIONES as $clave => [$nombre, $actividades]) {
                ChecklistSeccion::create(['clave' => $clave, 'nombre' => $nombre, 'orden' => ++$ordenSeccion, 'activo' => true]);
                foreach ($actividades as $indice => [$codigo, $actividad, $critica]) {
                    ChecklistItem::updateOrCreate(
                        ['codigo' => $codigo],
                        ['seccion' => $clave, 'nombre' => $actividad, 'orden' => $indice + 1, 'critico' => $critica, 'activo' => true]
                    );
                }
            }
        });
    }
}

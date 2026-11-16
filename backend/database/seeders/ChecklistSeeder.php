<?php

namespace Database\Seeders;

use App\Support\PlantillaPlanilla;
use Illuminate\Database\Seeder;

/**
 * RF-031 a RF-036: actividades que se revisan en cada sección del ascensor (plantilla editable).
 * Las marcadas como críticas (seguridad o emergencia) en estado M avisan de inmediato al coordinador.
 */
class ChecklistSeeder extends Seeder
{
    /** Instala la plantilla de la planilla de asistencia técnica si aún no existe (no pisa ediciones). */
    public function run(): void
    {
        PlantillaPlanilla::instalar();
    }
}

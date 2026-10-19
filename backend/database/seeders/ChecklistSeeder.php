<?php

namespace Database\Seeders;

use App\Models\ChecklistItem;
use Illuminate\Database\Seeder;

/**
 * RF-031 a RF-036: componentes que se revisan en cada sección del ascensor.
 * Los marcados como críticos (seguridad o emergencia) en estado M avisan de inmediato al coordinador.
 */
class ChecklistSeeder extends Seeder
{
    /** [código, nombre, crítico] por sección. */
    public const ITEMS = [
        'cuarto_maquinas' => [
            ['CM-01', 'Tablero de control', false],
            ['CM-02', 'Breaker y protecciones', false],
            ['CM-03', 'Resistencias', false],
            ['CM-04', 'Máquina de tracción', false],
            ['CM-05', 'Poleas', false],
            ['CM-06', 'Unidad hidráulica', false],
            ['CM-07', 'Bomba', false],
            ['CM-08', 'Válvulas', false],
            ['CM-09', 'Mangueras', false],
            ['CM-10', 'Freno', true],
            ['CM-11', 'Nivel y estado del aceite', false],
            ['CM-12', 'Limitador de velocidad', true],
        ],
        'cabina' => [
            ['CA-01', 'Cielo falso', false],
            ['CA-02', 'Iluminación', false],
            ['CA-03', 'Espejo', false],
            ['CA-04', 'Piso', false],
            ['CA-05', 'Botonera', false],
            ['CA-06', 'Nivelación', false],
            ['CA-07', 'Cortina fotoeléctrica', true],
            ['CA-08', 'Caja de inspección', false],
            ['CA-09', 'Inductores', false],
            ['CA-10', 'Sistema de emergencia (alarma, citófono, luz)', true],
            ['CA-11', 'Lubricadores', false],
        ],
        'contrapeso' => [
            ['CP-01', 'Zapatas', false],
            ['CP-02', 'Lubricadores', false],
            ['CP-03', 'Niveles', false],
            ['CP-04', 'Cables', true],
            ['CP-05', 'Elongación de cables', false],
        ],
        'puertas' => [
            ['PU-01', 'Operador de puerta de cabina', false],
            ['PU-02', 'Alineación de puertas de cabina y de piso', false],
            ['PU-03', 'Zapatas de puertas', false],
            ['PU-04', 'Contactos de puertas', true],
            ['PU-05', 'Mecanismos de puertas', false],
            ['PU-06', 'Cerraduras de puertas de piso', true],
            ['PU-07', 'Electroimanes', false],
            ['PU-08', 'Cierre de puertas', false],
            ['PU-09', 'Botones de piso', false],
        ],
        'hueco' => [
            ['HU-01', 'Rieles', false],
            ['HU-02', 'Soportes de rieles', false],
            ['HU-03', 'Cables de tracción', true],
            ['HU-04', 'Terminales de cables', false],
            ['HU-05', 'Banderas', false],
            ['HU-06', 'Microinterruptores y finales de carrera', true],
            ['HU-07', 'Actuador hidráulico', false],
        ],
        'foso' => [
            ['FO-01', 'Polea tensora', false],
            ['FO-02', 'Parada de emergencia', true],
            ['FO-03', 'Iluminación del foso', false],
            ['FO-04', 'Amortiguadores', true],
        ],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $seccion => $items) {
            foreach ($items as $indice => [$codigo, $nombre, $critico]) {
                ChecklistItem::updateOrCreate(
                    ['codigo' => $codigo],
                    ['seccion' => $seccion, 'nombre' => $nombre, 'orden' => $indice + 1, 'critico' => $critico, 'activo' => true]
                );
            }
        }
    }
}

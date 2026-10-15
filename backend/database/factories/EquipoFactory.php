<?php

namespace Database\Factories;

use App\Models\Edificio;
use App\Models\Equipo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EquipoFactory extends Factory
{
    protected $model = Equipo::class;

    public function definition(): array
    {
        return [
            'identificador_uuid' => (string) Str::uuid(),
            'edificio_id' => Edificio::factory(),
            'codigo' => 'ASC-'.fake()->unique()->numerify('####'),
            'tipo' => 'ascensor',
            'marca' => fake()->randomElement(['Otis', 'Schindler', 'Mitsubishi', 'Kone']),
            'modelo' => fake()->bothify('M-###'),
            'numero_serie' => fake()->bothify('SN-########'),
            'ubicacion' => 'Torre A',
            'estado' => 'activo',
            'fecha_instalacion' => fake()->date(),
            'observaciones' => null,
        ];
    }
}

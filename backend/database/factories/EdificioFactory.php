<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Edificio;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class EdificioFactory extends Factory
{
    protected $model = Edificio::class;

    public function definition(): array
    {
        return [
            'identificador_uuid' => (string) Str::uuid(),
            'cliente_id' => Cliente::factory(),
            'nombre' => fake()->streetName().' Building',
            'direccion' => fake()->streetAddress(),
            'ciudad' => fake()->city(),
            'departamento' => fake()->state(),
            'codigo_postal' => fake()->postcode(),
            'pisos' => fake()->numberBetween(3, 30),
            'observaciones' => null,
            'nombre_contacto' => fake()->name(),
            'telefono_contacto' => fake()->phoneNumber(),
            'correo_contacto' => fake()->safeEmail(),
        ];
    }
}

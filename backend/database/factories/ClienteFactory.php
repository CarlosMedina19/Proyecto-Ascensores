<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        return [
            'identificador_uuid' => (string) Str::uuid(),
            'nombre' => fake()->company(),
            'tipo' => fake()->randomElement(['natural', 'juridica']),
            'tipo_documento' => 'NIT',
            'numero_documento' => fake()->unique()->numerify('#########'),
            'nit' => fake()->unique()->numerify('#########-#'),
            'direccion' => fake()->address(),
            'telefono' => fake()->numerify('3#########'),
            'correo' => fake()->unique()->companyEmail(),
            'estado' => true,
            'observaciones' => null,
        ];
    }
}

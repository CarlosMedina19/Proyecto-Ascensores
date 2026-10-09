<?php

namespace Database\Factories;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsuarioFactory extends Factory
{
    protected $modelo = Usuario::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'nombre' => fake()->name(),
            'correo' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'rol_id' => Rol::firstOrCreate(['nombre' => 'administrador'], ['descripcion' => 'Admin'])->id,
            'activo' => true,
            'remember_token' => Str::random(10),
        ];
    }
}

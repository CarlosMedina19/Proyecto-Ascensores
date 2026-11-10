<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Copia cifrada (APP_KEY) de la contraseña para que el administrador consulte la credencial de cada rol
 * en «Roles y permisos». El acceso sigue validándose con el hash; esta copia nunca se usa para iniciar sesión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->text('contrasena_cifrada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->dropColumn('contrasena_cifrada');
        });
    }
};

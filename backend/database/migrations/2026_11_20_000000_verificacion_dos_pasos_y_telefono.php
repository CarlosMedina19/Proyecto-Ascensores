<?php

use App\Support\Telefono;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verificación en dos pasos por correo y recuperación de la contraseña con el número de teléfono.
 *
 * - usuarios.telefono: celular de 10 dígitos (sin +57). Se llena con el teléfono del perfil de técnico cuando existe.
 * - usuarios.doble_factor: si está activo, después de la contraseña se pide un código enviado al correo.
 * - codigos_verificacion: códigos de un solo uso (se guarda su huella, nunca el código) con vencimiento e intentos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->string('telefono', 20)->nullable()->index();
            $tabla->boolean('doble_factor')->default(false);
        });

        Schema::create('codigos_verificacion', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $tabla->string('proposito', 30);
            $tabla->string('canal', 10);
            $tabla->string('desafio', 64)->unique();
            $tabla->string('codigo', 64);
            $tabla->unsignedSmallInteger('intentos')->default(0);
            $tabla->timestamp('expira_en');
            $tabla->timestamp('usado_en')->nullable();
            $tabla->timestamp('reenviado_en')->nullable();
            $tabla->string('ip', 45)->nullable();
            $tabla->timestamps();
            $tabla->index(['usuario_id', 'proposito']);
        });

        // Los técnicos ya tienen su celular en el perfil: se copia al usuario para la recuperación por teléfono.
        DB::table('tecnicos')->whereNotNull('telefono')->whereNotNull('usuario_id')->orderBy('id')
            ->get(['usuario_id', 'telefono'])
            ->each(function ($tecnico) {
                $telefono = Telefono::normalizar($tecnico->telefono);
                if ($telefono !== null) {
                    DB::table('usuarios')->where('id', $tecnico->usuario_id)->whereNull('telefono')->update(['telefono' => $telefono]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('codigos_verificacion');
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->dropIndex(['telefono']);
            $tabla->dropColumn(['telefono', 'doble_factor']);
        });
    }
};

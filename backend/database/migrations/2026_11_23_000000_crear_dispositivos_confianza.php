<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dispositivos de confianza de la verificación en dos pasos: después de verificar el código en un
 * navegador o celular, ese dispositivo recibe una llave (aquí solo su huella) y no vuelve a pedir el
 * código mientras la llave esté vigente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos_confianza', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $tabla->string('huella', 64)->unique();
            $tabla->string('nombre', 160)->nullable();
            $tabla->string('ip', 45)->nullable();
            $tabla->timestamp('ultimo_uso_en')->nullable();
            $tabla->timestamp('expira_en');
            $tabla->timestamps();
            $tabla->index(['usuario_id', 'expira_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispositivos_confianza');
    }
};

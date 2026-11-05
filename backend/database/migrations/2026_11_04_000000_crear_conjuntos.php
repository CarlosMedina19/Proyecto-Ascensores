<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Cliente → conjunto (conjunto, institución o residencia) → edificio → ascensor.
 * El conjunto guarda la dirección y el contacto que heredan sus edificios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conjuntos', function (Blueprint $tabla) {
            $tabla->id();
            $tabla->uuid('identificador_uuid')->unique();
            $tabla->foreignId('cliente_id')->constrained('clientes');
            $tabla->string('tipo', 20);
            $tabla->string('nombre');
            $tabla->string('direccion');
            $tabla->string('ciudad', 100);
            $tabla->string('departamento', 100)->nullable();
            $tabla->string('codigo_postal', 20)->nullable();
            $tabla->string('nombre_contacto')->nullable();
            $tabla->string('telefono_contacto', 50)->nullable();
            $tabla->string('correo_contacto')->nullable();
            $tabla->text('observaciones')->nullable();
            $tabla->boolean('activo')->default(true);
            $tabla->timestamps();
            $tabla->softDeletes();
            $tabla->index(['cliente_id', 'tipo']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE conjuntos ADD CONSTRAINT conjuntos_tipo_check CHECK (tipo IN ('conjunto', 'institucional', 'residencial'))");
        }

        Schema::table('edificios', function (Blueprint $tabla) {
            $tabla->foreignId('conjunto_id')->nullable()->after('cliente_id')->constrained('conjuntos');
        });

        // Los edificios que ya existen quedan en un conjunto por cliente y tipo, con los datos del primero.
        $grupos = DB::table('edificios')->whereNull('deleted_at')->orderBy('id')->get()->groupBy(fn ($e) => "{$e->cliente_id}|{$e->tipo}");
        foreach ($grupos as $edificios) {
            $primero = $edificios->first();
            $cliente = DB::table('clientes')->where('id', $primero->cliente_id)->value('nombre');
            $id = DB::table('conjuntos')->insertGetId([
                'identificador_uuid' => (string) Str::uuid(),
                'cliente_id' => $primero->cliente_id,
                'tipo' => $primero->tipo,
                'nombre' => $cliente ?: $primero->nombre,
                'direccion' => $primero->direccion,
                'ciudad' => $primero->ciudad,
                'departamento' => $primero->departamento,
                'codigo_postal' => $primero->codigo_postal,
                'nombre_contacto' => $primero->nombre_contacto,
                'telefono_contacto' => $primero->telefono_contacto,
                'correo_contacto' => $primero->correo_contacto,
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('edificios')->whereIn('id', $edificios->pluck('id'))->update(['conjunto_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('edificios', function (Blueprint $tabla) {
            $tabla->dropConstrainedForeignId('conjunto_id');
        });
        Schema::dropIfExists('conjuntos');
    }
};

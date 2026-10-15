<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->renameColumn('email_verified_at', 'correo_verificado_en');
    }

    public function down(): void
    {
        $this->renameColumn('correo_verificado_en', 'email_verified_at');
    }

    private function renameColumn(string $from, string $to): void
    {
        if (! Schema::hasColumn('usuarios', $from) || Schema::hasColumn('usuarios', $to)) {
            throw new RuntimeException(
                "No se puede renombrar usuarios.{$from} a {$to}: revisa el estado del esquema."
            );
        }

        Schema::table('usuarios', function (Blueprint $table) use ($from, $to): void {
            $table->renameColumn($from, $to);
        });
    }
};

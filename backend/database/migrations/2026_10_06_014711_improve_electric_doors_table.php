<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('electric_doors', function (Blueprint $table) {
            $table->enum('opening_type', ['simple', 'doble'])->nullable()->after('door_type');
            $table->enum('access_type', ['entrada', 'salida', 'ambos'])->nullable()->after('opening_type');
            $table->string('serial_number')->nullable()->after('model');
            $table->text('technical_specs')->nullable()->after('installation_date');
        });
    }

    public function down(): void
    {
        Schema::table('electric_doors', function (Blueprint $table) {
            $table->dropColumn([
                'opening_type', 'access_type', 'serial_number', 'technical_specs'
            ]);
        });
    }
};
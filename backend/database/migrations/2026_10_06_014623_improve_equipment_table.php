<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->string('brand')->nullable()->after('code');
            $table->string('model')->nullable()->after('brand');
            $table->string('serial_number')->nullable()->after('model');
            $table->date('installation_date')->nullable()->after('status');
            $table->text('observations')->nullable()->after('installation_date');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'brand', 'model', 'serial_number',
                'installation_date', 'observations'
            ]);
        });
    }
};
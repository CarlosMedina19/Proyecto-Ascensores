<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elevators', function (Blueprint $table) {
            $table->decimal('speed_mpm', 8, 2)->nullable()->after('capacity_kg');
            $table->string('drive_type')->nullable()->after('stops');
            $table->string('motor')->nullable()->after('drive_type');
            $table->string('controller')->nullable()->after('motor');
            $table->string('door_type')->nullable()->after('controller');
            $table->text('technical_specs')->nullable()->after('door_type');
        });
    }

    public function down(): void
    {
        Schema::table('elevators', function (Blueprint $table) {
            $table->dropColumn([
                'speed_mpm', 'drive_type', 'motor',
                'controller', 'door_type', 'technical_specs',
            ]);
        });
    }
};

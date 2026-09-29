<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electric_doors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('door_type')->nullable(); // corrediza, batiente, etc.
            $table->date('installation_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('electric_doors');
    }
};

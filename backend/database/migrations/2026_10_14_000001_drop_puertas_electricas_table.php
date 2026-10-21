<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {

        Schema::dropIfExists('puertas_electricas');
        Schema::dropIfExists('electric_doors');
    }

    public function down(): void {}
};

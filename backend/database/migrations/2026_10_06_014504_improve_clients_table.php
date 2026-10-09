<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->enum('type', ['natural', 'juridica'])->default('juridica')->after('uuid');
            $table->string('document_type')->nullable()->after('type');
            $table->string('document_number')->nullable()->after('document_type');
            $table->string('tax_regime')->nullable()->after('email');
            $table->string('economic_activity')->nullable()->after('tax_regime');
            $table->text('observations')->nullable()->after('status');

            $table->string('nit')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'type', 'document_type', 'document_number',
                'tax_regime', 'economic_activity', 'observations',
            ]);
        });
    }
};

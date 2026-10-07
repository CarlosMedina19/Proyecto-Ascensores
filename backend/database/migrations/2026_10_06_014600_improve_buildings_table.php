<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->string('city')->nullable()->change();
            $table->string('department')->nullable()->after('city');
            $table->string('postal_code')->nullable()->after('department');
            $table->text('observations')->nullable()->after('floors');
            $table->string('contact_name')->nullable()->after('observations');
            $table->string('contact_phone')->nullable()->after('contact_name');
            $table->string('contact_email')->nullable()->after('contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('buildings', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'department', 'postal_code', 'observations',
                'contact_name', 'contact_phone', 'contact_email'
            ]);
        });
    }
};
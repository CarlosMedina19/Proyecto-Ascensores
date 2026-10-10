<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->ipAddress('ip_address')->nullable()->after('user_id');
            $table->index(['model', 'model_id']);
            $table->index('created_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['model', 'model_id']);
            $table->dropIndex(['created_at']);
            $table->dropColumn('ip_address');
        });
    }
};

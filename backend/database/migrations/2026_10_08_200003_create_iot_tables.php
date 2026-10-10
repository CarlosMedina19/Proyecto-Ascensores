<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iot_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('device_id')->unique();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('gateway_id')->nullable()->constrained('iot_devices')->nullOnDelete();
            $table->string('manufacturer')->nullable();
            $table->string('firmware_version')->nullable();
            $table->string('status')->default('inactive');
            $table->timestampTz('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'status']);
        });

        Schema::create('iot_sensors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('iot_device_id')->constrained()->cascadeOnDelete();
            $table->string('sensor_id');
            $table->string('sensor_type');
            $table->string('unit')->nullable();
            $table->string('status')->default('active');
            $table->json('configuration')->nullable();
            $table->timestamps();

            $table->unique(['iot_device_id', 'sensor_id']);
            $table->index(['sensor_type', 'status']);
        });

        Schema::create('iot_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('iot_sensor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 20, 8);
            $table->string('unit')->nullable();
            $table->string('source')->nullable();
            $table->timestampTz('recorded_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['iot_sensor_id', 'recorded_at']);
            $table->index(['equipment_id', 'recorded_at']);
        });

        Schema::create('iot_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('iot_device_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->string('event_type');
            $table->string('severity')->default('info');
            $table->json('data')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->index(['equipment_id', 'occurred_at']);
            $table->index(['severity', 'occurred_at']);
        });

        Schema::create('iot_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('equipment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('iot_sensor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('severity')->default('warning');
            $table->json('condition');
            $table->unsignedInteger('cooldown_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['equipment_id', 'is_active']);
        });

        Schema::create('iot_alerts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('iot_sensor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('iot_event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('iot_alert_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->json('rule');
            $table->string('severity')->default('warning');
            $table->string('status')->default('open');
            $table->timestampTz('triggered_at');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['equipment_id', 'status']);
            $table->index(['status', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iot_alerts');
        Schema::dropIfExists('iot_alert_rules');
        Schema::dropIfExists('iot_events');
        Schema::dropIfExists('iot_readings');
        Schema::dropIfExists('iot_sensors');
        Schema::dropIfExists('iot_devices');
    }
};

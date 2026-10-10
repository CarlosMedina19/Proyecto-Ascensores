<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('service_type')->default('preventive');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_assignments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('maintenance_plan_id')->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('periodicity_months');
            $table->decimal('price', 14, 2);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->date('next_maintenance_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['equipment_id', 'status']);
            $table->index(['maintenance_plan_id', 'status']);
        });

        Schema::create('plan_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_assignment_id')->constrained()->restrictOnDelete();
            $table->decimal('previous_price', 14, 2)->nullable();
            $table->decimal('new_price', 14, 2);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestampTz('changed_at')->useCurrent();

            $table->index(['plan_assignment_id', 'changed_at']);
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->string('number')->unique();
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->decimal('total_value', 14, 2)->default(0);
            $table->json('tax_configuration')->nullable();
            $table->text('terms')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index('ends_at');
        });

        Schema::create('contract_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_description')->nullable();
            $table->decimal('price', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['contract_id', 'equipment_id']);
        });

        Schema::create('technicians', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('document_number')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->string('specialty')->nullable();
            $table->json('availability')->nullable();
            $table->string('status')->default('active');
            $table->text('observations')->nullable();
            $table->timestamps();
        });

        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('number')->unique();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->foreignId('equipment_id')->constrained()->restrictOnDelete();
            $table->string('service_type');
            $table->timestampTz('scheduled_at')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('scheduled');
            $table->text('observations')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('source')->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'status']);
            $table->index(['building_id', 'status']);
            $table->index(['equipment_id', 'status']);
            $table->index(['scheduled_at', 'status']);
            $table->index(['status', 'priority']);
        });

        Schema::create('work_order_technicians', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->useCurrent();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['work_order_id', 'technician_id']);
            $table->index(['technician_id', 'assigned_at']);
        });

        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_assignment_id')->constrained()->restrictOnDelete();
            $table->timestampTz('scheduled_at');
            $table->string('status')->default('scheduled');
            $table->foreignId('work_order_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->index(['scheduled_at', 'status']);
            $table->index(['plan_assignment_id', 'scheduled_at']);
        });

        Schema::create('work_order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['work_order_id', 'created_at']);
        });

        Schema::create('checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('equipment_type')->default('elevator');
            $table->string('service_type')->default('preventive');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['equipment_type', 'service_type', 'is_active']);
        });

        Schema::create('checklist_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_template_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['checklist_template_id', 'sort_order']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_section_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->text('instructions')->nullable();
            $table->boolean('requires_observation')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['checklist_section_id', 'sort_order']);
        });

        Schema::create('work_order_checklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('checklist_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('section_name');
            $table->string('item_label');
            $table->string('result_code')->nullable();
            $table->string('condition')->nullable();
            $table->text('observations')->nullable();
            $table->boolean('corrective_required')->default(false);
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();
            $table->timestamps();

            $table->index(['work_order_id', 'result_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_checklist');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_sections');
        Schema::dropIfExists('checklist_templates');
        Schema::dropIfExists('work_order_events');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('work_order_technicians');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('technicians');
        Schema::dropIfExists('contract_equipment');
        Schema::dropIfExists('contracts');
        Schema::dropIfExists('plan_price_history');
        Schema::dropIfExists('plan_assignments');
        Schema::dropIfExists('maintenance_plans');
    }
};

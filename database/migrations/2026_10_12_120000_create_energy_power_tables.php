<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('electricity_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('meter_code');
            $table->string('name');
            $table->string('location')->nullable();
            $table->decimal('last_reading', 14, 3)->default(0);
            $table->timestamp('last_reading_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'meter_code'], 'electricity_meters_tenant_code_unique');
        });

        Schema::create('electricity_meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('electricity_meter_id')->constrained()->restrictOnDelete();
            $table->timestamp('read_at');
            $table->decimal('previous_reading', 14, 3);
            $table->decimal('current_reading', 14, 3);
            $table->decimal('consumption', 14, 3);
            $table->boolean('is_reset')->default(false);
            $table->text('reset_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('generators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('generator_code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'generator_code'], 'generators_tenant_code_unique');
        });

        Schema::create('generator_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('generator_id')->constrained()->restrictOnDelete();
            $table->timestamp('logged_at');
            $table->decimal('runtime_hours', 10, 2);
            $table->decimal('fuel_quantity', 12, 3);
            $table->string('fuel_unit')->default('liters');
            $table->boolean('flagged')->default(false);
            $table->string('flag_reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('power_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('backup_source')->nullable();
            $table->string('status')->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('meter_chamber_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('electricity_meter_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('peak_load_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('electricity_meter_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('threshold_kw', 12, 3);
            $table->string('status')->default('pending');
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('peak_load_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('electricity_meter_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('peak_load_threshold_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('electricity_meter_reading_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('alerted_at');
            $table->decimal('measured_load', 12, 3);
            $table->decimal('threshold_kw', 12, 3);
            $table->string('status')->default('open');
            $table->string('source')->default('manual_reading');
            $table->timestamps();
        });

        Schema::create('energy_cost_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('energy_cost', 14, 2);
            $table->decimal('kwh_total', 14, 3)->nullable();
            $table->string('allocation_basis')->default('manual_proposal');
            $table->text('calculation_notes')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('storage_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('billing_adjustment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('energy_cost_allocations');
        Schema::dropIfExists('peak_load_alerts');
        Schema::dropIfExists('peak_load_thresholds');
        Schema::dropIfExists('meter_chamber_mappings');
        Schema::dropIfExists('power_events');
        Schema::dropIfExists('generator_logs');
        Schema::dropIfExists('generators');
        Schema::dropIfExists('electricity_meter_readings');
        Schema::dropIfExists('electricity_meters');
    }
};

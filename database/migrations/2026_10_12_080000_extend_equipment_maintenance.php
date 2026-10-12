<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facility_assets', function (Blueprint $table) {
            $table->string('asset_code')->nullable()->after('branch_id');
            $table->string('name')->nullable()->after('asset_code');
            $table->string('category')->nullable()->after('name');
            $table->string('location')->nullable()->after('category');
            $table->string('serial_number')->nullable()->after('location');
            $table->date('installed_at')->nullable()->after('serial_number');
            $table->string('operational_status')->default('active')->after('installed_at');
            $table->decimal('purchase_cost', 14, 2)->nullable()->after('operational_status');
            $table->foreignId('chamber_id')->nullable()->after('purchase_cost')->constrained()->nullOnDelete();
            $table->unique(['tenant_id', 'asset_code'], 'facility_assets_tenant_code_unique');
        });

        Schema::create('pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_asset_id')->constrained()->restrictOnDelete();
            $table->string('maintenance_type');
            $table->unsignedInteger('frequency_days')->default(30);
            $table->date('next_due_on');
            $table->date('last_completed_on')->nullable();
            $table->foreignId('assigned_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('scheduled');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('breakdown_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_asset_id')->constrained()->restrictOnDelete();
            $table->string('number')->nullable();
            $table->text('description');
            $table->string('priority')->default('medium');
            $table->timestamp('reported_at')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('assigned_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_asset_id')->constrained()->restrictOnDelete();
            $table->foreignId('breakdown_ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pm_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->nullable();
            $table->string('kind')->default('work_order');
            $table->text('description');
            $table->foreignId('assigned_vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_start')->nullable();
            $table->date('planned_end')->nullable();
            $table->decimal('estimated_cost', 14, 2)->default(0);
            $table->decimal('actual_cost', 14, 2)->nullable();
            $table->string('cost_status')->default('none');
            $table->foreignId('cost_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cost_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open');
            $table->text('completion_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('work_order_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('maintenance_work_order_id')->constrained()->restrictOnDelete();
            $table->text('message');
            $table->string('status_from')->nullable();
            $table->string('status_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('spare_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('part_code');
            $table->string('name');
            $table->string('unit')->default('pcs');
            $table->decimal('quantity_on_hand', 14, 3)->default(0);
            $table->decimal('min_stock', 14, 3)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'part_code'], 'spare_parts_tenant_code_unique');
        });

        Schema::create('spare_part_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('spare_part_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->foreignId('maintenance_work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('asset_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('facility_asset_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('provider_name')->nullable();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_no')->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('coverage_notes')->nullable();
            $table->decimal('cost', 14, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_contracts');
        Schema::dropIfExists('spare_part_movements');
        Schema::dropIfExists('spare_parts');
        Schema::dropIfExists('work_order_logs');
        Schema::dropIfExists('maintenance_work_orders');
        Schema::dropIfExists('breakdown_tickets');
        Schema::dropIfExists('pm_schedules');
        Schema::table('facility_assets', function (Blueprint $table) {
            $table->dropUnique('facility_assets_tenant_code_unique');
            $table->dropConstrainedForeignId('chamber_id');
            $table->dropColumn([
                'asset_code', 'name', 'category', 'location', 'serial_number',
                'installed_at', 'operational_status', 'purchase_cost',
            ]);
        });
    }
};

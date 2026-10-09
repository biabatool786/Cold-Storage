<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weighments', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id');
            $table->foreignId('booking_id')->nullable()->after('gate_entry_id')->constrained()->nullOnDelete();
            $table->string('vehicle_no')->nullable()->after('booking_id');
            $table->decimal('gross_weight', 14, 3)->nullable()->after('kind');
            $table->decimal('tare_weight', 14, 3)->nullable()->after('gross_weight');
            $table->decimal('net_weight', 14, 3)->nullable()->after('tare_weight');
            $table->boolean('is_manual')->default(false)->after('net_weight');
            $table->string('manual_reason')->nullable()->after('is_manual');
            $table->string('status')->default('recorded')->after('manual_reason');
            $table->string('tolerance_status')->default('ok')->after('status');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->foreignId('acknowledged_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable()->after('acknowledged_by');
            $table->string('acknowledge_comment')->nullable()->after('acknowledged_at');
            $table->timestamp('weighed_at')->nullable()->after('acknowledge_comment');
            $table->unique(['tenant_id', 'number']);
        });

        Schema::create('weighbridge_tolerances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('limit_kg', 14, 3);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id']);
        });

        Schema::create('weighment_edit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('weighment_id')->constrained()->restrictOnDelete();
            $table->decimal('gross_weight', 14, 3)->nullable();
            $table->decimal('tare_weight', 14, 3)->nullable();
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('weighbridge_calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('weighbridge_name')->default('Main');
            $table->date('calibrated_on');
            $table->date('next_due_on')->nullable();
            $table->string('result')->default('pass');
            $table->string('certificate_ref')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weighbridge_calibrations');
        Schema::dropIfExists('weighment_edit_requests');
        Schema::dropIfExists('weighbridge_tolerances');
        Schema::table('weighments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'number']);
            $table->dropConstrainedForeignId('booking_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('acknowledged_by');
            $table->dropColumn([
                'number', 'vehicle_no', 'gross_weight', 'tare_weight', 'net_weight', 'is_manual', 'manual_reason',
                'status', 'tolerance_status', 'acknowledged_at', 'acknowledge_comment', 'weighed_at',
            ]);
        });
    }
};

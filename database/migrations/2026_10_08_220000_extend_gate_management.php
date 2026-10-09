<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('vehicle_no')->nullable()->after('reference');
            $table->string('driver_name')->nullable()->after('vehicle_no');
        });

        Schema::table('gate_entries', function (Blueprint $table) {
            $table->timestamp('entered_at')->nullable()->after('status');
            $table->timestamp('exited_at')->nullable()->after('entered_at');
            $table->string('token')->nullable()->after('exited_at');
            $table->string('seal_number')->nullable()->after('token');
            $table->string('vehicle_condition')->nullable()->after('seal_number');
            $table->string('photo_path')->nullable()->after('vehicle_condition');
            $table->timestamp('pass_verified_at')->nullable()->after('photo_path');
            $table->foreignId('override_approved_by')->nullable()->after('pass_verified_at')->constrained('users')->nullOnDelete();
            $table->string('override_reason')->nullable()->after('override_approved_by');
            $table->foreignId('gate_pass_id')->nullable()->after('booking_id')->constrained()->nullOnDelete();
        });

        Schema::table('gate_passes', function (Blueprint $table) {
            $table->string('code')->nullable()->unique()->after('status');
        });

        Schema::create('gate_visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('purpose')->nullable();
            $table->string('vehicle_no')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('gate_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('vehicle_no')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_blocks');
        Schema::dropIfExists('gate_visitors');
        Schema::table('gate_passes', function (Blueprint $table) {
            $table->dropColumn('code');
        });
        Schema::table('gate_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gate_pass_id');
            $table->dropConstrainedForeignId('override_approved_by');
            $table->dropColumn(['entered_at', 'exited_at', 'token', 'seal_number', 'vehicle_condition', 'photo_path', 'pass_verified_at', 'override_reason']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['vehicle_no', 'driver_name']);
        });
    }
};

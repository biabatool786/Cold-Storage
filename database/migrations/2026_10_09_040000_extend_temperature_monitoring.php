<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chambers', function (Blueprint $table) {
            $table->decimal('alarm_temp_min', 8, 2)->nullable()->after('humidity_set_point');
            $table->decimal('alarm_temp_max', 8, 2)->nullable()->after('alarm_temp_min');
            $table->decimal('alarm_humidity_min', 8, 2)->nullable()->after('alarm_temp_max');
            $table->decimal('alarm_humidity_max', 8, 2)->nullable()->after('alarm_humidity_min');
            $table->unsignedInteger('escalation_minutes')->nullable()->after('alarm_humidity_max');
            $table->string('escalation_role')->nullable()->after('escalation_minutes');
        });

        Schema::create('monitoring_sensors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('type')->default('temperature');
            $table->string('status')->default('offline');
            $table->json('config')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->foreignId('sensor_id')->nullable()->after('chamber_id')->constrained('monitoring_sensors')->nullOnDelete();
            $table->string('source')->default('manual')->after('humidity');
            $table->string('notes')->nullable()->after('source');
        });

        Schema::create('monitoring_config_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->string('kind')->default('thresholds');
            $table->decimal('alarm_temp_min', 8, 2)->nullable();
            $table->decimal('alarm_temp_max', 8, 2)->nullable();
            $table->decimal('alarm_humidity_min', 8, 2)->nullable();
            $table->decimal('alarm_humidity_max', 8, 2)->nullable();
            $table->unsignedInteger('escalation_minutes')->nullable();
            $table->string('escalation_role')->nullable();
            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('monitoring_alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->foreignId('sensor_id')->nullable()->constrained('monitoring_sensors')->nullOnDelete();
            $table->foreignId('sensor_reading_id')->nullable()->constrained('sensor_readings')->nullOnDelete();
            $table->string('type')->default('threshold');
            $table->string('severity')->default('warning');
            $table->string('message');
            $table->decimal('reading_temperature', 8, 2)->nullable();
            $table->decimal('reading_humidity', 8, 2)->nullable();
            $table->string('status')->default('open');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_comment')->nullable();
            $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('escalated_at')->nullable();
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('suppressed_until')->nullable();
            $table->text('suppression_reason')->nullable();
            $table->foreignId('suppression_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('suppression_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suppression_status')->nullable();
            $table->boolean('has_incident')->default(false);
            $table->string('incident_number')->nullable();
            $table->text('incident_notes')->nullable();
            $table->foreignId('incident_created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('incident_created_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('monitoring_defrost_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('monitoring_calibrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('sensor_id')->constrained('monitoring_sensors')->restrictOnDelete();
            $table->timestamp('calibrated_at');
            $table->decimal('reference_value', 8, 2);
            $table->decimal('measured_value', 8, 2);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('monitoring_reading_annotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('sensor_reading_id')->constrained('sensor_readings')->restrictOnDelete();
            $table->text('note');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_reading_annotations');
        Schema::dropIfExists('monitoring_calibrations');
        Schema::dropIfExists('monitoring_defrost_logs');
        Schema::dropIfExists('monitoring_alarms');
        Schema::dropIfExists('monitoring_config_changes');

        Schema::table('sensor_readings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sensor_id');
            $table->dropColumn(['source', 'notes']);
        });

        Schema::dropIfExists('monitoring_sensors');

        Schema::table('chambers', function (Blueprint $table) {
            $table->dropColumn([
                'alarm_temp_min', 'alarm_temp_max', 'alarm_humidity_min', 'alarm_humidity_max',
                'escalation_minutes', 'escalation_role',
            ]);
        });
    }
};

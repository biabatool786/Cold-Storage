<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('event_type');
            $table->string('channel'); // email|sms|whatsapp|in_app
            $table->string('subject')->nullable();
            $table->text('body');
            $table->json('placeholders')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('communication_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('channel'); // email|sms|whatsapp
            $table->string('provider')->nullable();
            $table->text('credentials_encrypted')->nullable();
            $table->string('from_address')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('status')->default('unconfigured'); // unconfigured|configured|error
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_result')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'channel'], 'comm_gateway_tenant_channel_unique');
        });

        Schema::create('role_alert_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('role_name');
            $table->string('alert_category');
            $table->boolean('enabled')->default(true);
            $table->json('channels')->nullable(); // ["in_app","email"]
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'role_name', 'alert_category'], 'role_alert_pref_unique');
        });

        Schema::create('internal_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('title');
            $table->text('body');
            $table->string('severity')->default('info');
            $table->string('idempotency_key')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'idempotency_key'], 'internal_alert_idempotent_unique');
            $table->index(['user_id', 'read_at']);
        });

        Schema::create('staff_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->json('target_roles')->nullable();
            $table->json('target_branch_ids')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('status')->default('draft'); // draft|published
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name')->nullable();
            $table->string('channel');
            $table->string('category')->nullable();
            $table->string('recipient_type')->nullable(); // customer|user|role
            $table->string('recipient_reference')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->string('status')->default('queued'); // queued|sent|delivered|failed
            $table->string('failure_reason')->nullable();
            $table->string('provider_reference')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'status', 'channel']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
        Schema::dropIfExists('staff_announcements');
        Schema::dropIfExists('internal_alerts');
        Schema::dropIfExists('role_alert_preferences');
        Schema::dropIfExists('communication_gateways');
        Schema::dropIfExists('message_templates');
    }
};

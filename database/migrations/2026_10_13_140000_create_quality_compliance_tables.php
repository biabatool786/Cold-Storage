<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_checklist_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category');
            $table->string('applicable_process')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('current_version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_checklist_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_checklist_template_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->json('items');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['quality_checklist_template_id', 'version_number'], 'quality_template_version_unique');
        });

        Schema::create('quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('category');
            $table->foreignId('quality_checklist_template_id')->constrained()->restrictOnDelete();
            $table->foreignId('quality_checklist_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('inspected_at');
            $table->foreignId('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('overall_result')->default('pending');
            $table->string('status')->default('submitted');
            $table->text('notes')->nullable();
            $table->string('evidence_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_inspection_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_inspection_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('item_index');
            $table->string('item_label');
            $table->boolean('required')->default(true);
            $table->string('response');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('quality_ncrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('reference_number');
            $table->foreignId('quality_inspection_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('storage_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->string('severity')->default('medium');
            $table->decimal('affected_quantity', 14, 3)->nullable();
            $table->string('status')->default('open');
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closure_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closure_evidence')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'reference_number'], 'quality_ncr_ref_unique');
        });

        Schema::create('quality_incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->string('location')->nullable();
            $table->string('category')->nullable();
            $table->text('description');
            $table->string('severity')->default('medium');
            $table->text('people_assets_lots')->nullable();
            $table->text('immediate_actions')->nullable();
            $table->text('investigation_notes')->nullable();
            $table->string('status')->default('open');
            $table->string('evidence_path')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closure_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_corrective_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('quality_ncr_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('quality_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description');
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_team')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority')->default('medium');
            $table->string('status')->default('open');
            $table->text('completion_evidence')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_pest_sanitation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('log_type');
            $table->date('logged_on');
            $table->string('location')->nullable();
            $table->text('findings')->nullable();
            $table->text('actions_taken')->nullable();
            $table->string('service_provider')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type');
            $table->string('reference_number');
            $table->string('issuer')->nullable();
            $table->string('applicable_entity')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status')->default('active');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('insurer');
            $table->string('policy_number');
            $table->string('coverage_type')->nullable();
            $table->string('insured_asset')->nullable();
            $table->decimal('coverage_limit', 14, 2)->nullable();
            $table->decimal('premium_amount', 14, 2)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'policy_number'], 'quality_policy_number_unique');
        });

        Schema::create('quality_insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('quality_insurance_policy_id')->constrained()->restrictOnDelete();
            $table->foreignId('quality_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('storage_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_reference');
            $table->date('event_date')->nullable();
            $table->decimal('claim_amount', 14, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('status')->default('draft');
            $table->text('insurer_response')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['tenant_id', 'claim_reference'], 'quality_claim_ref_unique');
        });

        Schema::create('quality_safety_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('log_type'); // gas_leak|fire_safety
            $table->date('checked_on');
            $table->string('equipment_label')->nullable();
            $table->foreignId('chamber_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('facility_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result')->default('pass'); // pass|fail
            $table->text('findings')->nullable();
            $table->text('follow_up_actions')->nullable();
            $table->date('next_due_on')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_sop_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('category')->nullable();
            $table->unsignedInteger('current_version')->default(1);
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('quality_sop_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quality_sop_document_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->date('effective_on')->nullable();
            $table->date('review_on')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_current')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['quality_sop_document_id', 'version_number'], 'quality_sop_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_sop_versions');
        Schema::dropIfExists('quality_sop_documents');
        Schema::dropIfExists('quality_safety_logs');
        Schema::dropIfExists('quality_insurance_claims');
        Schema::dropIfExists('quality_insurance_policies');
        Schema::dropIfExists('quality_certificates');
        Schema::dropIfExists('quality_pest_sanitation_logs');
        Schema::dropIfExists('quality_corrective_actions');
        Schema::dropIfExists('quality_incidents');
        Schema::dropIfExists('quality_ncrs');
        Schema::dropIfExists('quality_inspection_results');
        Schema::dropIfExists('quality_inspections');
        Schema::dropIfExists('quality_checklist_versions');
        Schema::dropIfExists('quality_checklist_templates');
    }
};

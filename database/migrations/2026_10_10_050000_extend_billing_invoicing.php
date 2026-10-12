<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rate_agreements', function (Blueprint $table) {
            $table->string('charge_type')->default('storage')->after('unit');
            $table->unsignedInteger('free_days')->default(0)->after('charge_type');
            $table->unsignedInteger('grace_days')->default(0)->after('free_days');
            $table->decimal('tax_percent', 8, 2)->default(0)->after('grace_days');
            $table->text('change_reason')->nullable()->after('tax_percent');
            $table->string('approval_status')->default('approved')->after('change_reason');
        });

        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->decimal('default_tax_percent', 8, 2)->default(0);
            $table->unsignedInteger('default_free_days')->default(0);
            $table->unsignedInteger('default_grace_days')->default(0);
            $table->decimal('demurrage_rate', 14, 2)->default(0);
            $table->decimal('late_fee_percent', 8, 2)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'branch_id']);
        });

        Schema::create('billing_bulk_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('pending');
            $table->json('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'branch_id', 'period_start', 'period_end'], 'billing_bulk_runs_period_unique');
        });

        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id');
            $table->string('kind')->default('tax')->after('number');
            $table->foreignId('storage_lot_id')->nullable()->after('delivery_order_id')->constrained('storage_lots')->nullOnDelete();
            $table->foreignId('billing_bulk_run_id')->nullable()->after('storage_lot_id')->constrained('billing_bulk_runs')->nullOnDelete();
            $table->date('invoice_date')->nullable()->after('billing_bulk_run_id');
            $table->date('due_date')->nullable()->after('invoice_date');
            $table->date('period_start')->nullable()->after('due_date');
            $table->date('period_end')->nullable()->after('period_start');
            $table->decimal('subtotal', 14, 2)->default(0)->after('period_end');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('subtotal');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('tax_amount');
            $table->decimal('discount_percent', 8, 2)->default(0)->after('discount_amount');
            $table->string('discount_reason')->nullable()->after('discount_percent');
            $table->string('discount_status')->nullable()->after('discount_reason');
            $table->foreignId('discount_approved_by')->nullable()->after('discount_status')->constrained('users')->nullOnDelete();
            $table->decimal('credited_amount', 14, 2)->default(0)->after('amount');
            $table->decimal('debited_amount', 14, 2)->default(0)->after('credited_amount');
            $table->timestamp('posted_at')->nullable()->after('status');
            $table->foreignId('posted_by')->nullable()->after('posted_at')->constrained('users')->nullOnDelete();
            $table->string('cancel_status')->nullable()->after('posted_by');
            $table->text('cancel_reason')->nullable()->after('cancel_status');
            $table->foreignId('cancel_requested_by')->nullable()->after('cancel_reason')->constrained('users')->nullOnDelete();
            $table->foreignId('cancel_approved_by')->nullable()->after('cancel_requested_by')->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable()->after('cancel_approved_by');
        });

        Schema::create('storage_invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('rate_agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('charge_type')->default('storage');
            $table->string('description');
            $table->decimal('quantity', 14, 3)->default(0);
            $table->string('unit')->default('kg');
            $table->decimal('unit_rate', 14, 4)->default(0);
            $table->unsignedInteger('storage_days')->default(0);
            $table->unsignedInteger('free_days')->default(0);
            $table->unsignedInteger('grace_days')->default(0);
            $table->unsignedInteger('billable_days')->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('tax_percent', 8, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);
            $table->boolean('is_manual')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('billing_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_invoice_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('number')->nullable();
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('billing_waiver_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_waiver_requests');
        Schema::dropIfExists('billing_adjustments');
        Schema::dropIfExists('storage_invoice_lines');

        Schema::table('storage_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('storage_lot_id');
            $table->dropConstrainedForeignId('billing_bulk_run_id');
            $table->dropConstrainedForeignId('discount_approved_by');
            $table->dropConstrainedForeignId('posted_by');
            $table->dropConstrainedForeignId('cancel_requested_by');
            $table->dropConstrainedForeignId('cancel_approved_by');
            $table->dropColumn([
                'number', 'kind', 'invoice_date', 'due_date', 'period_start', 'period_end',
                'subtotal', 'tax_amount', 'discount_amount', 'discount_percent', 'discount_reason',
                'discount_status', 'credited_amount', 'debited_amount', 'posted_at',
                'cancel_status', 'cancel_reason', 'notes',
            ]);
        });

        Schema::dropIfExists('billing_bulk_runs');
        Schema::dropIfExists('billing_settings');

        Schema::table('rate_agreements', function (Blueprint $table) {
            $table->dropColumn([
                'charge_type', 'free_days', 'grace_days', 'tax_percent', 'change_reason', 'approval_status',
            ]);
        });
    }
};

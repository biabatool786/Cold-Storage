<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operating_expenses', function (Blueprint $table) {
            $table->string('kind')->default('expense')->after('branch_id');
            $table->string('category')->nullable()->after('kind');
            $table->date('expense_date')->nullable()->after('category');
            $table->foreignId('chamber_id')->nullable()->after('expense_date')->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('chamber_id')->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->after('customer_id');
            $table->string('payment_method')->nullable()->after('amount');
            $table->string('reference')->nullable()->after('payment_method');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->foreignId('posted_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->unique(['tenant_id', 'reference'], 'operating_expenses_tenant_reference_unique');
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('tax_no')->nullable();
            $table->text('address')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('operating_expenses', function (Blueprint $table) {
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
        });

        Schema::create('expense_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('operating_expense_id')->constrained()->restrictOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->string('reference');
            $table->decimal('amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->string('status')->default('open');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference'], 'vendor_bills_tenant_reference_unique');
        });

        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_id')->constrained()->restrictOnDelete();
            $table->foreignId('vendor_bill_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->string('method')->default('cash');
            $table->string('reference')->nullable();
            $table->string('status')->default('posted');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'reference'], 'vendor_payments_tenant_reference_unique');
        });

        Schema::create('petty_cash_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->date('entry_date');
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('operating_expense_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('statement_date');
            $table->string('description');
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->string('match_status')->default('unmatched');
            $table->string('matched_type')->nullable();
            $table->unsignedBigInteger('matched_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('accounting_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('open');
            $table->foreignId('close_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('close_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'branch_id', 'period_start', 'period_end'], 'accounting_periods_scope_unique');
        });

        Schema::create('accounting_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->string('format')->default('csv');
            $table->string('status')->default('failed');
            $table->text('outcome')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_exports');
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('petty_cash_entries');
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('expense_attachments');
        Schema::table('operating_expenses', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropForeign(['chamber_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['posted_by']);
            $table->dropUnique('operating_expenses_tenant_reference_unique');
            $table->dropColumn([
                'kind', 'category', 'expense_date', 'chamber_id', 'customer_id', 'vendor_id',
                'payment_method', 'reference', 'rejection_reason', 'approved_at', 'posted_by', 'posted_at',
            ]);
        });
        Schema::dropIfExists('vendors');
    }
};

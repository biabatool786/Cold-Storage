<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_payments', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->string('method')->default('cash')->after('storage_invoice_id');
            $table->string('reference')->nullable()->after('method');
            $table->string('kind')->default('invoice')->after('reference');
            $table->date('paid_on')->nullable()->after('kind');
            $table->string('cheque_number')->nullable()->after('paid_on');
            $table->string('cheque_status')->nullable()->after('cheque_number');
            $table->timestamp('cheque_status_at')->nullable()->after('cheque_status');
            $table->decimal('allocated_amount', 14, 2)->default(0)->after('amount');
            $table->decimal('unapplied_amount', 14, 2)->default(0)->after('allocated_amount');
            $table->string('reverse_status')->nullable()->after('status');
            $table->text('reverse_reason')->nullable()->after('reverse_status');
            $table->foreignId('reverse_requested_by')->nullable()->after('reverse_reason')->constrained('users')->nullOnDelete();
            $table->foreignId('reverse_approved_by')->nullable()->after('reverse_requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reverse_approved_by');
            $table->foreignId('posted_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->text('notes')->nullable()->after('posted_at');
            $table->unique(['tenant_id', 'reference'], 'storage_payments_tenant_reference_unique');
        });

        // Allow payments without an immediate invoice (advances/deposits).
        Schema::table('storage_payments', function (Blueprint $table) {
            $table->dropForeign(['storage_invoice_id']);
        });
        Schema::table('storage_payments', function (Blueprint $table) {
            $table->foreignId('storage_invoice_id')->nullable()->change();
            $table->foreign('storage_invoice_id')->references('id')->on('storage_invoices')->nullOnDelete();
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('customer_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind')->default('security');
            $table->decimal('original_amount', 14, 2);
            $table->decimal('used_amount', 14, 2)->default(0);
            $table->decimal('refunded_amount', 14, 2)->default(0);
            $table->string('status')->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('deposit_refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_deposit_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bad_debt_write_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->decimal('outstanding_amount', 14, 2);
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('channel')->default('email');
            $table->string('status')->default('attempted');
            $table->text('message')->nullable();
            $table->text('outcome')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_reminders');
        Schema::dropIfExists('stock_loans');
        Schema::dropIfExists('bad_debt_write_offs');
        Schema::dropIfExists('deposit_refund_requests');
        Schema::dropIfExists('customer_deposits');
        Schema::dropIfExists('payment_allocations');

        Schema::table('storage_payments', function (Blueprint $table) {
            $table->dropUnique('storage_payments_tenant_reference_unique');
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('reverse_requested_by');
            $table->dropConstrainedForeignId('reverse_approved_by');
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn([
                'method', 'reference', 'kind', 'paid_on', 'cheque_number', 'cheque_status', 'cheque_status_at',
                'allocated_amount', 'unapplied_amount', 'reverse_status', 'reverse_reason',
                'reversed_at', 'posted_at', 'notes',
            ]);
        });
    }
};

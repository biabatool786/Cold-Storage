<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id');
            $table->foreignId('weighment_id')->nullable()->after('chamber_id')->constrained()->nullOnDelete();
            $table->string('grade')->nullable()->after('quantity');
            $table->decimal('temperature', 8, 2)->nullable()->after('grade');
            $table->string('condition_notes')->nullable()->after('temperature');
            $table->unsignedInteger('loader_count')->nullable()->after('condition_notes');
            $table->string('unloading_notes')->nullable()->after('loader_count');
            $table->decimal('shortage_qty', 14, 3)->nullable()->after('unloading_notes');
            $table->decimal('damage_qty', 14, 3)->nullable()->after('shortage_qty');
            $table->decimal('accepted_qty', 14, 3)->nullable()->after('damage_qty');
            $table->string('proposed_outcome')->nullable()->after('accepted_qty');
            $table->string('outcome')->nullable()->after('proposed_outcome');
            $table->foreignId('outcome_proposed_by')->nullable()->after('outcome')->constrained('users')->nullOnDelete();
            $table->foreignId('outcome_approved_by')->nullable()->after('outcome_proposed_by')->constrained('users')->nullOnDelete();
            $table->string('inspection_notes')->nullable()->after('outcome_approved_by');
            $table->string('cancel_reason')->nullable()->after('approved_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancel_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->timestamp('sent_to_customer_at')->nullable()->after('cancelled_at');
            $table->string('last_edit_reason')->nullable()->after('sent_to_customer_at');
            $table->unique(['tenant_id', 'number']);
        });

        Schema::table('storage_lots', function (Blueprint $table) {
            $table->string('lot_number')->nullable()->after('id');
            $table->foreignId('slot_id')->nullable()->after('chamber_id')->constrained()->nullOnDelete();
            $table->string('barcode')->nullable()->after('slot_id');
            $table->unique(['tenant_id', 'lot_number']);
        });

        Schema::create('put_away_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->constrained()->restrictOnDelete();
            $table->foreignId('suggested_slot_id')->nullable()->constrained('slots')->nullOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained('slots')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('override_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('grn_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->constrained()->restrictOnDelete();
            $table->string('path');
            $table->string('kind')->default('damage');
            $table->string('caption')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_photos');
        Schema::dropIfExists('put_away_tasks');
        Schema::table('storage_lots', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'lot_number']);
            $table->dropConstrainedForeignId('slot_id');
            $table->dropColumn(['lot_number', 'barcode']);
        });
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'number']);
            $table->dropConstrainedForeignId('weighment_id');
            $table->dropConstrainedForeignId('outcome_proposed_by');
            $table->dropConstrainedForeignId('outcome_approved_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'number', 'grade', 'temperature', 'condition_notes', 'loader_count', 'unloading_notes',
                'shortage_qty', 'damage_qty', 'accepted_qty', 'proposed_outcome', 'outcome',
                'inspection_notes', 'cancel_reason', 'cancelled_at', 'sent_to_customer_at', 'last_edit_reason',
            ]);
        });
    }
};

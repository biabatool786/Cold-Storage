<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storage_lots', function (Blueprint $table) {
            $table->decimal('held_qty', 14, 3)->default(0)->after('quantity');
            $table->decimal('quarantined_qty', 14, 3)->default(0)->after('held_qty');
            $table->decimal('dispatched_qty', 14, 3)->default(0)->after('quarantined_qty');
            $table->string('hold_status')->nullable()->after('status');
            $table->timestamp('received_at')->nullable()->after('hold_status');
            $table->date('expires_on')->nullable()->after('received_at');
            $table->foreignId('commodity_variety_id')->nullable()->after('commodity_id')->constrained()->nullOnDelete();
            $table->foreignId('commodity_grade_id')->nullable()->after('commodity_variety_id')->constrained()->nullOnDelete();
        });

        Schema::create('stock_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->decimal('quantity', 14, 3);
            $table->decimal('balance_after', 14, 3);
            $table->string('reason')->nullable();
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('from_slot_id')->nullable()->constrained('slots')->nullOnDelete();
            $table->foreignId('to_slot_id')->nullable()->constrained('slots')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'type']);
            $table->index(['storage_lot_id', 'created_at']);
        });

        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->foreignId('from_slot_id')->constrained('slots')->restrictOnDelete();
            $table->foreignId('to_slot_id')->constrained('slots')->restrictOnDelete();
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->decimal('quantity', 14, 3);
            $table->string('status')->default('requested');
            $table->string('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('value_added_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->string('service_type');
            $table->decimal('quantity', 14, 3)->nullable();
            $table->string('status')->default('pending');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('lot_shrinkage_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('reason');
            $table->date('recorded_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('chamber_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind');
            $table->string('status')->default('open');
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_count_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->decimal('system_qty', 14, 3);
            $table->decimal('counted_qty', 14, 3)->nullable();
            $table->decimal('variance', 14, 3)->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->decimal('quantity_before', 14, 3);
            $table->decimal('quantity_after', 14, 3);
            $table->decimal('delta', 14, 3);
            $table->string('reason');
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_count_lines');
        Schema::dropIfExists('stock_counts');
        Schema::dropIfExists('lot_shrinkage_records');
        Schema::dropIfExists('value_added_services');
        Schema::dropIfExists('stock_holds');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_ledger_entries');
        Schema::table('storage_lots', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commodity_variety_id');
            $table->dropConstrainedForeignId('commodity_grade_id');
            $table->dropColumn([
                'held_qty', 'quarantined_qty', 'dispatched_qty', 'hold_status', 'received_at', 'expires_on',
            ]);
        });
    }
};

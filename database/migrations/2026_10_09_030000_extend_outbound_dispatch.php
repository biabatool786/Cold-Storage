<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->string('number')->nullable()->after('id');
            $table->foreignId('commodity_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            $table->decimal('picked_qty', 14, 3)->default(0)->after('quantity');
            $table->decimal('dispatched_qty', 14, 3)->default(0)->after('picked_qty');
            $table->decimal('remaining_qty', 14, 3)->nullable()->after('dispatched_qty');
            $table->boolean('stock_ok')->default(true)->after('dues_amount');
            $table->boolean('credit_ok')->default(true)->after('stock_ok');
            $table->boolean('has_dues_flag')->default(false)->after('credit_ok');
            $table->string('pick_mode')->default('fifo')->after('has_dues_flag');
            $table->boolean('fifo_override')->default(false)->after('pick_mode');
            $table->string('fifo_override_reason')->nullable()->after('fifo_override');
            $table->foreignId('fifo_override_by')->nullable()->after('fifo_override_reason')->constrained('users')->nullOnDelete();
            $table->decimal('outgoing_temperature', 8, 2)->nullable()->after('fifo_override_by');
            $table->unsignedInteger('loader_count')->nullable()->after('outgoing_temperature');
            $table->string('loader_notes')->nullable()->after('loader_count');
            $table->boolean('loading_verified')->default(false)->after('loader_notes');
            $table->foreignId('loading_verified_by')->nullable()->after('loading_verified')->constrained('users')->nullOnDelete();
            $table->timestamp('loading_verified_at')->nullable()->after('loading_verified_by');
            $table->foreignId('dues_recommended_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->string('dues_recommend_note')->nullable()->after('dues_recommended_by');
            $table->string('cancel_reason')->nullable()->after('dues_recommend_note');
            $table->foreignId('cancelled_by')->nullable()->after('cancel_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->string('last_edit_reason')->nullable()->after('cancelled_at');
            $table->unique(['tenant_id', 'number']);
        });

        Schema::table('gate_passes', function (Blueprint $table) {
            $table->foreignId('verified_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });

        Schema::create('delivery_pick_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('slot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('suggested_qty', 14, 3);
            $table->decimal('picked_qty', 14, 3)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_override')->default(false);
            $table->string('status')->default('pending');
            $table->foreignId('picked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('picked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('dispatch_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('delivery_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('storage_lot_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('reason');
            $table->string('condition')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('branch_transfer_outs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('to_branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('delivery_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('storage_lot_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('status')->default('pending');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_transfer_outs');
        Schema::dropIfExists('dispatch_returns');
        Schema::dropIfExists('delivery_pick_lines');
        Schema::table('gate_passes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verified_at']);
        });
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'number']);
            $table->dropConstrainedForeignId('commodity_id');
            $table->dropConstrainedForeignId('fifo_override_by');
            $table->dropConstrainedForeignId('loading_verified_by');
            $table->dropConstrainedForeignId('dues_recommended_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'number', 'picked_qty', 'dispatched_qty', 'remaining_qty', 'stock_ok', 'credit_ok', 'has_dues_flag',
                'pick_mode', 'fifo_override', 'fifo_override_reason', 'outgoing_temperature', 'loader_count',
                'loader_notes', 'loading_verified', 'loading_verified_at', 'dues_recommend_note',
                'cancel_reason', 'cancelled_at', 'last_edit_reason',
            ]);
        });
    }
};

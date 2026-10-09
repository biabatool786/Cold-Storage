<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('commodity_variety_id')->nullable()->after('commodity_id')->constrained()->nullOnDelete();
            $table->foreignId('commodity_grade_id')->nullable()->after('commodity_variety_id')->constrained()->nullOnDelete();
            $table->foreignId('chamber_id')->nullable()->after('commodity_grade_id')->constrained()->nullOnDelete();
            $table->foreignId('rate_agreement_id')->nullable()->after('chamber_id')->constrained()->nullOnDelete();
            $table->decimal('rate', 14, 2)->nullable()->after('quantity');
            $table->date('starts_on')->nullable()->after('rate');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->string('reference')->nullable()->after('ends_on');
            $table->text('notes')->nullable()->after('reference');
            $table->foreignId('cancelled_by')->nullable()->after('approved_by')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
            $table->string('cancel_reason')->nullable()->after('cancelled_at');
        });

        Schema::table('rate_agreements', function (Blueprint $table) {
            $table->string('kind')->default('standard')->after('unit');
            $table->date('starts_on')->nullable()->after('kind');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->string('season_status')->nullable()->after('status');
            $table->foreignId('approved_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });

        Schema::create('booking_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('booking_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->decimal('rate', 14, 2)->nullable();
            $table->foreignId('rate_agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['booking_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_revisions');
        Schema::dropIfExists('booking_deposits');
        Schema::table('rate_agreements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['kind', 'starts_on', 'ends_on', 'season_status']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commodity_variety_id');
            $table->dropConstrainedForeignId('commodity_grade_id');
            $table->dropConstrainedForeignId('chamber_id');
            $table->dropConstrainedForeignId('rate_agreement_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['rate', 'starts_on', 'ends_on', 'reference', 'notes', 'cancelled_at', 'cancel_reason']);
        });
    }
};

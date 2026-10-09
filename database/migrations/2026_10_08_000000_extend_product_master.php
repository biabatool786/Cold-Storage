<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commodities', function (Blueprint $table) {
            $table->string('code')->nullable()->after('name');
            $table->decimal('storage_temperature', 8, 2)->nullable()->after('status');
            $table->decimal('storage_humidity', 8, 2)->nullable()->after('storage_temperature');
            $table->unsignedInteger('shelf_life_days')->nullable()->after('storage_humidity');
            $table->decimal('shrinkage_percent', 8, 3)->nullable()->after('shelf_life_days');
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('commodity_varieties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['commodity_id', 'code']);
        });

        Schema::create('commodity_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_variety_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['commodity_variety_id', 'code']);
        });

        Schema::create('commodity_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->string('unit');
            $table->decimal('factor_to_kg', 14, 3);
            $table->timestamps();
            $table->unique(['commodity_id', 'unit']);
        });

        Schema::create('commodity_incompatibilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->foreignId('incompatible_commodity_id')->constrained('commodities')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['commodity_id', 'incompatible_commodity_id'], 'commodity_incompatible_unique');
        });

        Schema::create('commodity_shrinkage_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('commodity_id')->constrained()->restrictOnDelete();
            $table->decimal('percent', 8, 3);
            $table->string('status')->default('pending');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['commodity_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commodity_shrinkage_changes');
        Schema::dropIfExists('commodity_incompatibilities');
        Schema::dropIfExists('commodity_units');
        Schema::dropIfExists('commodity_grades');
        Schema::dropIfExists('commodity_varieties');
        Schema::table('commodities', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'code']);
            $table->dropColumn(['code', 'storage_temperature', 'storage_humidity', 'shelf_life_days', 'shrinkage_percent']);
        });
    }
};

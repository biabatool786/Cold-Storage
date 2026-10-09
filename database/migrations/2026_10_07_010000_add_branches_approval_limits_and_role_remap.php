<?php

use App\Services\Authorization\RemapLegacyRoles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('ACTIVE')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('user_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'branch_id']);
        });

        Schema::create('approval_limits', function (Blueprint $table) {
            $table->id();
            $table->string('scope_key')->default('platform');
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->decimal('manager_limit', 14, 2);
            $table->string('unit');
            $table->timestamps();

            $table->unique(['scope_key', 'code']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('role_name')->nullable()->after('user_id');
            $table->foreignId('branch_id')->nullable()->after('role_name')->constrained()->nullOnDelete();
            $table->string('module')->nullable()->after('action');
            $table->json('old_values')->nullable()->after('properties');
            $table->json('new_values')->nullable()->after('old_values');
            $table->text('reason')->nullable()->after('new_values');
            $table->string('ip_address', 45)->nullable()->after('reason');
            $table->text('user_agent')->nullable()->after('ip_address');
        });

        (new RemapLegacyRoles)->run();
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn([
                'role_name',
                'module',
                'old_values',
                'new_values',
                'reason',
                'ip_address',
                'user_agent',
            ]);
        });

        Schema::dropIfExists('approval_limits');
        Schema::dropIfExists('user_branches');
        Schema::dropIfExists('branches');
    }

};

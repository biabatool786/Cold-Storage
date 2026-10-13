<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Module 17 — Labor & Attendance.
 *
 * MySQL auto-commits DDL, so a prior partial run can leave early tables while
 * this migration remains Pending. Each create is guarded with hasTable().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('labor_employees')) {
            Schema::create('labor_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->string('employee_code');
                $table->string('name');
                $table->string('labor_category')->nullable();
                $table->string('engagement_type')->default('daily_wage');
                $table->string('phone')->nullable();
                $table->string('team_code')->nullable();
                $table->foreignId('supervisor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status')->default('active');
                $table->boolean('is_gate_staff')->default(false);
                $table->boolean('is_visitor_labor')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'employee_code'], 'labor_employees_tenant_code_unique');
            });
        }

        if (! Schema::hasTable('labor_wage_rates')) {
            Schema::create('labor_wage_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->decimal('rate_amount', 14, 2);
                $table->string('rate_unit')->default('day');
                $table->date('effective_from');
                $table->string('status')->default('proposed');
                $table->text('reason')->nullable();
                $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('labor_shifts')) {
            Schema::create('labor_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->string('name');
                $table->date('shift_date');
                $table->time('starts_at');
                $table->time('ends_at');
                $table->string('team_code')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('labor_shift_assignments')) {
            Schema::create('labor_shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('labor_shift_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->timestamps();
                $table->unique(['labor_shift_id', 'labor_employee_id'], 'labor_shift_employee_unique');
            });
        }

        if (! Schema::hasTable('labor_attendance')) {
            Schema::create('labor_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_shift_id')->nullable()->constrained()->nullOnDelete();
                $table->date('attendance_date');
                $table->string('status')->default('present');
                $table->timestamp('check_in_at')->nullable();
                $table->timestamp('check_out_at')->nullable();
                $table->string('subject_type')->default('employee');
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['labor_employee_id', 'attendance_date', 'labor_shift_id'], 'labor_attendance_unique');
            });
        }

        if (! Schema::hasTable('labor_attendance_corrections')) {
            Schema::create('labor_attendance_corrections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('labor_attendance_id')->constrained('labor_attendance')->restrictOnDelete();
                $table->json('original_values');
                $table->json('corrected_values');
                $table->text('reason');
                $table->foreignId('corrected_by')->constrained('users')->restrictOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('labor_loading_tasks')) {
            Schema::create('labor_loading_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->string('task_type');
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_label')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->string('status')->default('open');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('labor_task_assignments')) {
            Schema::create('labor_task_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('labor_loading_task_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->timestamps();
                $table->unique(['labor_loading_task_id', 'labor_employee_id'], 'labor_task_employee_unique');
            });
        }

        if (! Schema::hasTable('labor_work_records')) {
            Schema::create('labor_work_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_loading_task_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->date('work_date');
                $table->decimal('quantity', 14, 3);
                $table->string('unit')->default('bags');
                $table->string('status')->default('recorded');
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('labor_wage_sheets')) {
            Schema::create('labor_wage_sheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->decimal('gross_amount', 14, 2)->default(0);
                $table->string('status')->default('draft');
                $table->text('calculation_notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('labor_wage_sheet_lines')) {
            Schema::create('labor_wage_sheet_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('labor_wage_sheet_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->decimal('days_present', 8, 2)->default(0);
                $table->decimal('work_quantity', 14, 3)->default(0);
                $table->decimal('rate_amount', 14, 2)->default(0);
                $table->decimal('line_amount', 14, 2)->default(0);
                $table->text('source_notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('labor_overtime')) {
            Schema::create('labor_overtime', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->date('overtime_date');
                $table->decimal('hours', 8, 2);
                $table->text('reason')->nullable();
                $table->string('status')->default('pending');
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('labor_advances')) {
            Schema::create('labor_advances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->decimal('outstanding_amount', 14, 2);
                $table->string('status')->default('requested');
                $table->text('reason')->nullable();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('labor_payroll_runs')) {
            Schema::create('labor_payroll_runs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->date('period_start');
                $table->date('period_end');
                $table->foreignId('labor_wage_sheet_id')->nullable()->constrained()->nullOnDelete();
                $table->decimal('gross_amount', 14, 2)->default(0);
                $table->decimal('advances_deducted', 14, 2)->default(0);
                $table->decimal('net_payable', 14, 2)->default(0);
                $table->string('status')->default('draft');
                $table->text('calculation_notes')->nullable();
                $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('finalized_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['tenant_id', 'branch_id', 'period_start', 'period_end'], 'labor_payroll_period_unique');
            });
        }

        if (! Schema::hasTable('labor_ppe_logs')) {
            Schema::create('labor_ppe_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->string('item_or_training');
                $table->string('log_type')->default('ppe');
                $table->date('logged_on');
                $table->decimal('quantity', 10, 2)->nullable();
                $table->date('expires_on')->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (! Schema::hasTable('labor_leave_requests')) {
            Schema::create('labor_leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
                $table->foreignId('branch_id')->constrained()->restrictOnDelete();
                $table->foreignId('labor_employee_id')->constrained()->restrictOnDelete();
                $table->string('leave_type')->default('unpaid');
                $table->date('starts_on');
                $table->date('ends_on');
                $table->text('reason')->nullable();
                $table->string('status')->default('requested');
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('labor_leave_requests');
        Schema::dropIfExists('labor_ppe_logs');
        Schema::dropIfExists('labor_payroll_runs');
        Schema::dropIfExists('labor_advances');
        Schema::dropIfExists('labor_overtime');
        Schema::dropIfExists('labor_wage_sheet_lines');
        Schema::dropIfExists('labor_wage_sheets');
        Schema::dropIfExists('labor_work_records');
        Schema::dropIfExists('labor_task_assignments');
        Schema::dropIfExists('labor_loading_tasks');
        Schema::dropIfExists('labor_attendance_corrections');
        Schema::dropIfExists('labor_attendance');
        Schema::dropIfExists('labor_shift_assignments');
        Schema::dropIfExists('labor_shifts');
        Schema::dropIfExists('labor_wage_rates');
        Schema::dropIfExists('labor_employees');
    }
};

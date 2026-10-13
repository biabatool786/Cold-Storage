<?php

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Module 17 Labor, Module 18 Quality, Module 19 Notifications schema integrity.
 */
class Modules171819SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_labor_quality_notification_tables_exist_after_migrate(): void
    {
        $tables = [
            // Module 17
            'labor_employees', 'labor_wage_rates', 'labor_shifts', 'labor_shift_assignments',
            'labor_attendance', 'labor_attendance_corrections', 'labor_loading_tasks',
            'labor_task_assignments', 'labor_work_records', 'labor_wage_sheets',
            'labor_wage_sheet_lines', 'labor_overtime', 'labor_advances', 'labor_payroll_runs',
            'labor_ppe_logs', 'labor_leave_requests',
            // Module 18
            'quality_checklist_templates', 'quality_checklist_versions', 'quality_inspections',
            'quality_inspection_results', 'quality_ncrs', 'quality_incidents',
            'quality_corrective_actions', 'quality_pest_sanitation_logs', 'quality_certificates',
            'quality_insurance_policies', 'quality_insurance_claims', 'quality_safety_logs',
            'quality_sop_documents', 'quality_sop_versions',
            // Module 19
            'message_templates', 'communication_gateways', 'role_alert_preferences',
            'internal_alerts', 'staff_announcements', 'message_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }

        $this->assertTrue(Schema::hasColumns('quality_checklist_templates', [
            'tenant_id', 'branch_id', 'name', 'category', 'is_active', 'current_version', 'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('labor_employees', [
            'tenant_id', 'branch_id', 'employee_code', 'name', 'deleted_at',
        ]));
        $this->assertTrue(Schema::hasColumns('message_templates', [
            'tenant_id', 'channel', 'body', 'is_active', 'deleted_at',
        ]));
    }

    public function test_labor_migration_is_idempotent_when_tables_already_exist(): void
    {
        $this->assertTrue(Schema::hasTable('labor_employees'));

        // Re-running the labor migration file must not throw "table already exists".
        $path = database_path('migrations/2026_10_12_160000_create_labor_attendance_tables.php');
        $migration = require $path;
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('labor_employees'));
        $this->assertTrue(Schema::hasTable('labor_leave_requests'));
    }

    public function test_migrate_reports_nothing_pending_in_sqlite_test_db(): void
    {
        $exit = Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(0, $exit);
        $this->assertTrue(Schema::hasTable('quality_checklist_templates'));
    }
}

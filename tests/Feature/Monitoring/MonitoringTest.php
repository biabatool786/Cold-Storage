<?php

namespace Tests\Feature\Monitoring;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Exceptions\ImmutableRecordException;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Chamber;
use App\Models\Csms\MonitoringAlarm;
use App\Models\Csms\MonitoringCalibration;
use App\Models\Csms\MonitoringConfigChange;
use App\Models\Csms\MonitoringDefrostLog;
use App\Models\Csms\MonitoringSensor;
use App\Models\Csms\SensorReading;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Branch $branchA;

    private Branch $branchB;

    private User $superAdmin;

    private User $adminA;

    private User $managerA;

    private User $manager2;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

    private Chamber $chamber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'Tenant A', 'slug' => 'tenant-a', 'email' => 'a@example.com', 'status' => TenantStatus::Active,
            ]);
            $other = Tenant::query()->create([
                'name' => 'Tenant B', 'slug' => 'tenant-b', 'email' => 'b@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NORTH', 'status' => BranchStatus::Active,
            ]);
            $this->branchB = Branch::query()->create([
                'tenant_id' => $other->id, 'name' => 'Other', 'code' => 'OTHER', 'status' => BranchStatus::Active,
            ]);
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager@example.com');
            $this->manager2 = $this->user($this->tenantA, RoleName::Manager, 'manager2@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant@example.com');
            foreach ([$this->managerA, $this->manager2, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
                $user->branches()->sync([$this->branchA->id]);
            }
            $this->chamber = Chamber::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'name' => 'Cold One',
                'code' => 'C1',
                'set_point' => -18,
                'status' => 'active',
                'created_by' => $this->managerA->id,
            ]);
        });
    }

    public function test_temperature_monitoring_follows_the_matrix(): void
    {
        $this->actingAs($this->accountantA)->get('/monitoring')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/monitoring')->assertRedirect(route('monitoring.history'));
        $this->actingAs($this->gatekeeperA)->get('/monitoring/history')->assertOk();
        $this->actingAs($this->supervisorA)->get('/monitoring')->assertOk()->assertSee('Live chamber dashboard');

        $this->actingAs($this->supervisorA)->post('/monitoring/sensors', [
            'chamber_id' => $this->chamber->id, 'code' => 'S1', 'name' => 'Probe 1', 'type' => 'temperature',
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/monitoring/sensors', [
            'chamber_id' => $this->chamber->id, 'code' => 'S1', 'name' => 'Probe 1', 'type' => 'temperature', 'status' => 'offline',
        ])->assertRedirect();
        $sensor = MonitoringSensor::query()->firstOrFail();
        $this->assertSame('offline', $sensor->status);
        $this->actingAs($this->supervisorA)->get('/monitoring')->assertOk()->assertSee('offline / unavailable');

        $this->actingAs($this->supervisorA)->put('/monitoring/sensors/'.$sensor->id, [
            'name' => 'Probe One', 'type' => 'temperature', 'status' => 'offline',
        ])->assertRedirect();
        $this->assertSame('Probe One', $sensor->fresh()->name);

        $this->actingAs($this->managerA)->post('/monitoring/chambers/'.$this->chamber->id.'/config', [
            'kind' => 'thresholds', 'alarm_temp_min' => -22, 'alarm_temp_max' => -14, 'escalation_minutes' => 30,
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/monitoring/chambers/'.$this->chamber->id.'/config', [
            'kind' => 'thresholds',
            'alarm_temp_min' => -22,
            'alarm_temp_max' => -14,
            'escalation_minutes' => 30,
            'escalation_role' => RoleName::Manager->value,
        ])->assertRedirect();
        $change = MonitoringConfigChange::query()->firstOrFail();
        $this->actingAs($this->adminA)->post('/monitoring/config/'.$change->id, ['approve' => 1])->assertForbidden();
        $this->actingAs($this->managerA)->post('/monitoring/config/'.$change->id, ['approve' => 1])->assertRedirect();
        $this->chamber->refresh();
        $this->assertEquals(-22, (float) $this->chamber->alarm_temp_min);
        $this->assertEquals(-14, (float) $this->chamber->alarm_temp_max);

        $this->actingAs($this->adminA)->post('/monitoring/readings', [
            'chamber_id' => $this->chamber->id, 'temperature' => -10,
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/monitoring/readings', [
            'chamber_id' => $this->chamber->id, 'temperature' => -10,
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/monitoring/readings', [
            'chamber_id' => $this->chamber->id, 'temperature' => -10, 'humidity' => 90, 'sensor_id' => $sensor->id,
        ])->assertRedirect();
        $reading = SensorReading::query()->firstOrFail();
        $this->assertSame('manual', $reading->source);
        $alarm = MonitoringAlarm::query()->firstOrFail();
        $this->assertSame('open', $alarm->status);
        $this->assertFalse($alarm->has_incident);

        try {
            $reading->update(['temperature' => 0]);
            $this->fail('Sensor reading was edited.');
        } catch (ImmutableRecordException) {
            $this->assertEquals(-10, (float) $reading->fresh()->temperature);
        }
        try {
            $reading->delete();
            $this->fail('Sensor reading was deleted.');
        } catch (ImmutableRecordException) {
            $this->assertNotNull($reading->fresh());
        }

        $this->actingAs($this->adminA)->post('/monitoring/alarms/'.$alarm->id.'/resolve', [
            'comment' => 'Admin cannot resolve',
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->post('/monitoring/alarms/'.$alarm->id.'/acknowledge')->assertRedirect();
        $this->assertSame('acknowledged', $alarm->fresh()->status);
        $this->actingAs($this->supervisorA)->post('/monitoring/alarms/'.$alarm->id.'/escalate')->assertRedirect();
        $this->assertSame('escalated', $alarm->fresh()->status);

        $this->actingAs($this->supervisorA)->post('/monitoring/alarms/'.$alarm->id.'/suppress', [
            'until' => now()->addHours(2)->format('Y-m-d H:i:s'),
            'reason' => 'Planned maintenance',
        ])->assertRedirect();
        $this->assertSame('pending', $alarm->fresh()->suppression_status);
        $this->actingAs($this->supervisorA)->post('/monitoring/alarms/'.$alarm->id.'/suppress/decide', [
            'approve' => 1,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/monitoring/alarms/'.$alarm->id.'/suppress/decide', [
            'approve' => 1,
        ])->assertRedirect();
        $this->assertSame('suppressed', $alarm->fresh()->status);

        $this->actingAs($this->managerA)->post('/monitoring/alarms/'.$alarm->id.'/incident', [
            'notes' => 'Follow up after maintenance',
        ])->assertRedirect();
        $this->assertTrue($alarm->fresh()->has_incident);

        $this->actingAs($this->supervisorA)->post('/monitoring/alarms/'.$alarm->id.'/resolve', [
            'comment' => 'Back in range after defrost',
        ])->assertRedirect();
        $this->assertSame('resolved', $alarm->fresh()->status);

        $this->actingAs($this->gatekeeperA)->post('/monitoring/defrost', [
            'chamber_id' => $this->chamber->id, 'started_at' => now()->toDateTimeString(),
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/monitoring/defrost', [
            'chamber_id' => $this->chamber->id,
            'started_at' => now()->subHour()->toDateTimeString(),
            'ended_at' => now()->toDateTimeString(),
            'notes' => 'Scheduled',
        ])->assertRedirect();
        $this->assertTrue(MonitoringDefrostLog::query()->exists());

        $this->actingAs($this->supervisorA)->post('/monitoring/calibrations', [
            'sensor_id' => $sensor->id,
            'calibrated_at' => now()->toDateTimeString(),
            'reference_value' => -18,
            'measured_value' => -17.8,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->get('/monitoring/calibrations')->assertOk();
        $this->actingAs($this->adminA)->post('/monitoring/calibrations', [
            'sensor_id' => $sensor->id,
            'calibrated_at' => now()->toDateTimeString(),
            'reference_value' => -18,
            'measured_value' => -17.8,
        ])->assertRedirect();
        $this->assertTrue(MonitoringCalibration::query()->exists());

        $this->actingAs($this->gatekeeperA)->get('/monitoring/excursions')->assertForbidden();
        $this->actingAs($this->managerA)->get('/monitoring/excursions')->assertOk();
        $this->actingAs($this->supervisorA)->get('/monitoring/excursions/print')->assertOk();

        $foreign = Chamber::query()->create([
            'tenant_id' => $this->branchB->tenant_id,
            'branch_id' => $this->branchB->id,
            'name' => 'Foreign',
            'code' => 'FX',
            'status' => 'active',
        ]);
        $this->actingAs($this->supervisorA)->post('/monitoring/readings', [
            'chamber_id' => $foreign->id, 'temperature' => -18,
        ])->assertForbidden();

        $this->assertTrue(AuditLog::query()->where('action', 'monitoring.config_approved')->exists());
        $this->assertTrue(AuditLog::query()->where('action', 'monitoring.alarm_resolved')->exists());
        $this->assertTrue(AuditLog::query()->where('module', 'monitoring')->exists());
    }

    private function user(?Tenant $tenant, RoleName $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id,
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

        return $user;
    }
}

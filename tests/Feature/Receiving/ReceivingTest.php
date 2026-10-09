<?php

namespace Tests\Feature\Receiving;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\GateEntry;
use App\Models\Csms\GoodsReceipt;
use App\Models\Csms\PutAwayTask;
use App\Models\Csms\Slot;
use App\Models\Csms\StorageLot;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\ApprovalLimitSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceivingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Branch $branchA;

    private Branch $branchOther;

    private User $superAdmin;

    private User $adminA;

    private User $managerA;

    private User $manager2;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

    private User $managerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ApprovalLimitSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'Tenant A', 'slug' => 'tenant-a', 'email' => 'a@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->tenantB = Tenant::query()->create([
                'name' => 'Tenant B', 'slug' => 'tenant-b', 'email' => 'b@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = $this->branch($this->tenantA, 'North', 'NORTH');
            $this->branchOther = $this->branch($this->tenantB, 'Other', 'OTHER');
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super@example.com', null);
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin@example.com', $this->branchA);
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager@example.com', $this->branchA);
            $this->manager2 = $this->user($this->tenantA, RoleName::Manager, 'manager2@example.com', $this->branchA);
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor@example.com', $this->branchA);
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate@example.com', $this->branchA);
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant@example.com', $this->branchA);
            $this->managerB = $this->user($this->tenantB, RoleName::Manager, 'manager-b@example.com', $this->branchOther);
        });
    }

    public function test_receiving_permissions_workflow_isolation_and_audit(): void
    {
        Storage::fake('public');

        $this->actingAs($this->managerA)->post('/commodities', [
            'name' => 'Apple', 'variety' => 'Gala', 'grade' => 'A',
        ])->assertRedirect();
        $commodity = Commodity::query()->firstOrFail();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/customers', [
            'name' => 'Fresh Co', 'email' => 'fresh@example.com',
        ])->assertRedirect();
        $customer = Customer::query()->firstOrFail();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/chambers', [
            'name' => 'Chamber 1', 'code' => 'C1',
        ])->assertRedirect();
        $chamberId = \App\Models\Csms\Chamber::query()->value('id');
        $this->actingAs($this->managerA)->post('/chambers/'.$chamberId.'/racks', ['name' => 'Rack', 'code' => 'R1'])->assertRedirect();
        $rackId = \App\Models\Csms\Rack::query()->value('id');
        $this->actingAs($this->managerA)->post('/racks/'.$rackId.'/levels', ['name' => 'L1', 'code' => 'L1'])->assertRedirect();
        $levelId = \App\Models\Csms\Level::query()->value('id');
        $this->actingAs($this->managerA)->post('/levels/'.$levelId.'/slots', ['code' => 'S1'])->assertRedirect();
        $this->actingAs($this->managerA)->post('/levels/'.$levelId.'/slots', ['code' => 'S2'])->assertRedirect();
        $slot1 = Slot::query()->where('code', 'S1')->firstOrFail();
        $slot2 = Slot::query()->where('code', 'S2')->firstOrFail();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 50,
        ])->assertRedirect();
        $booking = \App\Models\Csms\Booking::query()->firstOrFail();
        $this->actingAs($this->manager2)->post('/bookings/'.$booking->id.'/approve')->assertRedirect();

        $this->actingAs($this->gatekeeperA)->post('/branches/'.$this->branchA->id.'/gates', [
            'vehicle_no' => 'ABC-1', 'purpose' => 'inbound', 'customer_id' => $customer->id, 'booking_id' => $booking->id,
        ])->assertRedirect();
        $entry = GateEntry::query()->firstOrFail();
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$entry->id.'/weighments', [
            'kind' => 'gross', 'weight' => 1200,
        ])->assertRedirect();
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$entry->id.'/weighments', [
            'kind' => 'tare', 'weight' => 400,
        ])->assertRedirect();

        $this->actingAs($this->accountantA)->post('/gates/'.$entry->id.'/grn', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 50,
        ])->assertForbidden();
        $this->actingAs($this->adminA)->post('/gates/'.$entry->id.'/grn', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 50,
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/gates/'.$entry->id.'/grn', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 50,
        ])->assertForbidden();

        $this->actingAs($this->supervisorA)->get('/receiving/expected')->assertOk();
        $this->actingAs($this->gatekeeperA)->get('/receiving/expected')->assertOk();
        $this->actingAs($this->accountantA)->get('/receiving/expected')->assertOk();

        $this->actingAs($this->supervisorA)->post('/gates/'.$entry->id.'/grns', [
            'customer_id' => $customer->id,
            'commodity_id' => $commodity->id,
            'quantity' => 50,
            'booking_id' => $booking->id,
            'grade' => 'A',
            'temperature' => 4.5,
            'loader_count' => 3,
            'condition_notes' => 'Good',
            'unloading_notes' => 'Dock 2',
        ])->assertRedirect();
        $receipt = GoodsReceipt::query()->firstOrFail();
        $this->assertSame('draft', $receipt->status);
        $this->assertNotNull($receipt->weighment_id);
        $this->assertNotNull($receipt->number);

        $this->actingAs($this->accountantA)->post('/grns/'.$receipt->id.'/inspect', [
            'shortage_qty' => 2, 'damage_qty' => 1, 'inspection_notes' => 'Minor damage',
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->post('/grns/'.$receipt->id.'/inspect', [
            'shortage_qty' => 2, 'damage_qty' => 1, 'inspection_notes' => 'Minor damage',
        ])->assertForbidden();

        $this->actingAs($this->supervisorA)->post('/grns/'.$receipt->id.'/inspect', [
            'shortage_qty' => 2, 'damage_qty' => 1, 'inspection_notes' => 'Minor damage', 'loader_count' => 3,
        ])->assertRedirect();

        $this->actingAs($this->supervisorA)->post('/grns/'.$receipt->id.'/photos', [
            'photo' => UploadedFile::fake()->image('damage.jpg'),
            'kind' => 'damage',
            'caption' => 'Corner crush',
        ])->assertRedirect();
        $this->assertDatabaseCount('grn_photos', 1);

        $this->actingAs($this->supervisorA)->post('/grns/'.$receipt->id.'/outcome', [
            'outcome' => 'partial', 'accepted_qty' => 47,
        ])->assertRedirect();
        $this->assertSame('partial', $receipt->fresh()->proposed_outcome);
        $this->assertSame('pending', $receipt->fresh()->status);

        $this->actingAs($this->supervisorA)->post('/grns/'.$receipt->id.'/approve', [
            'chamber_id' => $chamberId,
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/grns/'.$receipt->id.'/outcome', [
            'outcome' => 'partial', 'accepted_qty' => 47, 'confirm' => 1,
        ])->assertRedirect();
        $this->assertSame('partial', $receipt->fresh()->outcome);

        $this->actingAs($this->managerA)->post('/grns/'.$receipt->id.'/approve', [
            'chamber_id' => $chamberId, 'suggested_slot_id' => $slot1->id,
        ])->assertRedirect();
        $lot = StorageLot::query()->where('goods_receipt_id', $receipt->id)->firstOrFail();
        $this->assertSame('awaiting_putaway', $lot->status);
        $this->assertNotEmpty($lot->lot_number);
        $this->assertEquals(47, (float) $lot->quantity);
        $this->assertTrue(AuditLog::query()->where('action', 'grn.approved')->exists());

        $task = PutAwayTask::query()->where('storage_lot_id', $lot->id)->firstOrFail();
        $this->assertSame($slot1->id, $task->suggested_slot_id);

        $this->actingAs($this->supervisorA)->post('/slots/'.$slot1->id.'/block', [
            'blocked' => 1, 'reason' => 'Wet floor',
        ])->assertRedirect();

        $this->actingAs($this->supervisorA)->post('/put-aways/'.$task->id.'/execute', [
            'slot_id' => $slot1->id, 'reason' => 'Trying blocked',
        ])->assertStatus(422);

        $this->actingAs($this->supervisorA)->from(route('putaways.index'))->post('/put-aways/'.$task->id.'/execute', [
            'slot_id' => $slot2->id,
        ])->assertRedirect(route('putaways.index'))->assertSessionHasErrors('reason');

        $this->actingAs($this->supervisorA)->post('/put-aways/'.$task->id.'/execute', [
            'slot_id' => $slot2->id, 'reason' => 'S1 blocked for cleaning',
        ])->assertRedirect();
        $this->assertSame('stored', $lot->fresh()->status);
        $this->assertSame($slot2->id, $lot->fresh()->slot_id);
        $this->assertSame('occupied', $slot2->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'putaway.completed')->exists());

        $this->actingAs($this->managerA)->from(route('grns.edit', $receipt))->put('/grns/'.$receipt->id, [
            'inspection_notes' => 'Corrected note',
        ])->assertRedirect(route('grns.edit', $receipt))->assertSessionHasErrors('reason');
        $this->actingAs($this->managerA)->put('/grns/'.$receipt->id, [
            'inspection_notes' => 'Corrected note', 'reason' => 'Typo fix',
        ])->assertRedirect();
        $this->assertTrue(AuditLog::query()->where('action', 'grn.edited_after_approval')->exists());

        $this->actingAs($this->gatekeeperA)->get('/grns/'.$receipt->id)->assertOk()->assertDontSee('Fresh Co')->assertSee('ABC-1');
        $this->actingAs($this->accountantA)->get('/grns/'.$receipt->id)->assertOk()->assertSee('Fresh Co');
        $this->actingAs($this->accountantA)->get('/grns/'.$receipt->id.'/print')->assertOk();
        $this->actingAs($this->gatekeeperA)->get('/grns/'.$receipt->id.'/print')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/grns/'.$receipt->id.'/labels')->assertOk()->assertSee($lot->lot_number);

        $this->actingAs($this->accountantA)->post('/grns/'.$receipt->id.'/send', ['channel' => 'email'])->assertRedirect();
        $this->assertNotNull($receipt->fresh()->sent_to_customer_at);

        $this->actingAs($this->supervisorA)->post('/grns/'.$receipt->id.'/cancel', ['reason' => 'Wrong'])->assertForbidden();
        $this->actingAs($this->managerA)->from(route('grns.show', $receipt))->post('/grns/'.$receipt->id.'/cancel', [
            'reason' => 'Already stored',
        ])->assertRedirect(route('grns.show', $receipt))->assertSessionHasErrors('status');

        $this->actingAs($this->supervisorA)->post('/gates/'.$entry->id.'/grns', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 10, 'submit' => 1,
        ])->assertRedirect();
        $draft2 = GoodsReceipt::query()->where('quantity', 10)->firstOrFail();
        $this->actingAs($this->managerA)->post('/grns/'.$draft2->id.'/cancel', ['reason' => 'Duplicate'])->assertRedirect();
        $this->assertSame('cancelled', $draft2->fresh()->status);

        $this->actingAs($this->managerA)->delete('/grns/'.$draft2->id, ['reason' => 'Cleanup'])->assertForbidden();
        $this->actingAs($this->superAdmin)->delete('/grns/'.$draft2->id, ['reason' => 'Cleanup'])->assertRedirect();
        $this->assertSoftDeleted('goods_receipts', ['id' => $draft2->id]);

        $this->actingAs($this->managerB)->get('/grns/'.$receipt->id)->assertNotFound();
        $this->actingAs($this->managerB)->post('/put-aways/'.$task->id.'/execute', [
            'slot_id' => $slot2->id,
        ])->assertNotFound();
    }

    private function branch(Tenant $tenant, string $name, string $code): Branch
    {
        return Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'code' => $code,
            'status' => BranchStatus::Active,
        ]);
    }

    private function user(?Tenant $tenant, RoleName $role, string $email, ?Branch $branch): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant?->id,
            'name' => $email,
            'email' => $email,
            'password' => 'password',
            'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());
        if ($branch) {
            $user->branches()->sync([$branch->id]);
        }

        return $user;
    }
}

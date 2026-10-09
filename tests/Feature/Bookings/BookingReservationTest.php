<?php

namespace Tests\Feature\Bookings;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\Booking;
use App\Models\Csms\BookingRevision;
use App\Models\Csms\Chamber;
use App\Models\Csms\Commodity;
use App\Models\Csms\Customer;
use App\Models\Csms\RateAgreement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingReservationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Branch $branchA;

    private Branch $branchWest;

    private Branch $branchB;

    private User $adminA;

    private User $managerA;

    private User $manager2;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create(['name' => 'A', 'slug' => 'a', 'email' => 'a@example.com', 'status' => TenantStatus::Active]);
            $other = Tenant::query()->create(['name' => 'B', 'slug' => 'b', 'email' => 'b@example.com', 'status' => TenantStatus::Active]);
            $this->branchA = Branch::query()->create(['tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NORTH', 'status' => BranchStatus::Active]);
            $this->branchWest = Branch::query()->create(['tenant_id' => $this->tenantA->id, 'name' => 'West', 'code' => 'WEST', 'status' => BranchStatus::Active]);
            $this->branchB = Branch::query()->create(['tenant_id' => $other->id, 'name' => 'Other', 'code' => 'OTHER', 'status' => BranchStatus::Active]);
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager@example.com');
            $this->manager2 = $this->user($this->tenantA, RoleName::Manager, 'manager2@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant@example.com');
            foreach ([$this->managerA, $this->manager2, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
                $user->branches()->sync([$this->branchA->id]);
            }
        });
    }

    public function test_space_booking_follows_the_matrix(): void
    {
        $this->actingAs($this->managerA)->post('/commodities', ['name' => 'Potato', 'code' => 'POT'])->assertRedirect();
        $commodity = Commodity::query()->where('code', 'POT')->firstOrFail();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/customers', ['name' => 'Ali Traders'])->assertRedirect();
        $customer = Customer::query()->where('name', 'Ali Traders')->firstOrFail();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/chambers', ['name' => 'One', 'code' => 'C1', 'capacity' => 100])->assertRedirect();
        $chamber = Chamber::query()->where('code', 'C1')->firstOrFail();

        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 10,
        ])->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/bookings')->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchWest->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 10,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchB->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 10,
        ])->assertNotFound();

        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 20, 'chamber_id' => $chamber->id, 'notes' => 'Own',
        ])->assertRedirect();
        $own = Booking::query()->where('notes', 'Own')->firstOrFail();
        $this->assertSame('pending', $own->status);
        $this->assertTrue(AuditLog::query()->where('action', 'booking.requested')->exists());
        $this->actingAs($this->supervisorA)->put('/bookings/'.$own->id, $this->payload($customer, $commodity, $chamber, 25, 'Own edit'))->assertRedirect();
        $this->assertSame('25.000', (string) $own->fresh()->quantity);

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 150, 'chamber_id' => $chamber->id,
        ])->assertRedirect();
        $waiting = Booking::query()->where('quantity', 150)->firstOrFail();
        $this->assertSame('waiting', $waiting->status);
        $this->actingAs($this->supervisorA)->get('/bookings/waiting')->assertOk()->assertSee('150');
        $this->actingAs($this->supervisorA)->post('/bookings/'.$waiting->id.'/queue', ['waiting' => 0])->assertForbidden();
        $this->actingAs($this->supervisorA)->put('/bookings/'.$waiting->id, $this->payload($customer, $commodity, $chamber, 10, 'No'))->assertForbidden();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/bookings', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'quantity' => 10, 'notes' => 'Mine',
        ])->assertRedirect();
        $mine = Booking::query()->where('notes', 'Mine')->firstOrFail();
        $this->actingAs($this->managerA)->post('/bookings/'.$mine->id.'/approve')->assertForbidden();
        $this->actingAs($this->manager2)->post('/bookings/'.$own->id.'/approve')->assertRedirect();
        $this->actingAs($this->adminA)->put('/bookings/'.$own->id, [...$this->payload($customer, $commodity, $chamber, 25, 'Own'), 'reason' => 'Adjust'])->assertForbidden();
        $this->actingAs($this->managerA)->put('/bookings/'.$own->id, [...$this->payload($customer, $commodity, $chamber, 25, 'Own'), 'rate' => 9, 'reason' => 'Season rate'])->assertRedirect();
        $this->assertNull($own->fresh()->rate);
        $revision = BookingRevision::query()->firstOrFail();
        $this->actingAs($this->managerA)->post('/booking-revisions/'.$revision->id, ['approve' => 1])->assertForbidden();
        $this->actingAs($this->manager2)->post('/booking-revisions/'.$revision->id, ['approve' => 1])->assertRedirect();
        $this->assertEquals(9, (float) $own->fresh()->rate);
        $this->assertTrue(AuditLog::query()->where('action', 'booking.rate_change_approved')->exists());

        $this->actingAs($this->managerA)->post('/bookings/'.$own->id.'/deposit', ['amount' => 100])->assertForbidden();
        $this->actingAs($this->accountantA)->post('/bookings/'.$own->id.'/deposit', ['amount' => 100])->assertRedirect();
        $this->actingAs($this->adminA)->post('/bookings/'.$own->id.'/cancel', ['reason' => 'No'])->assertForbidden();
        $this->actingAs($this->manager2)->post('/bookings/'.$own->id.'/cancel', ['reason' => 'Customer withdrew'])->assertRedirect();
        $this->assertSame('cancelled', $own->fresh()->status);
        $this->assertNotNull($own->fresh()->cancelled_at);

        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/contracts', [
            'customer_id' => $customer->id, 'rate' => 4,
        ])->assertForbidden();
        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/contracts', [
            'customer_id' => $customer->id, 'commodity_id' => $commodity->id, 'rate' => 4, 'starts_on' => '2026-11-01', 'ends_on' => '2027-02-01',
        ])->assertRedirect();
        $contract = RateAgreement::query()->where('kind', 'seasonal')->firstOrFail();
        $this->actingAs($this->managerA)->post('/contracts/'.$contract->id, ['approve' => 1])->assertForbidden();
        $this->actingAs($this->manager2)->post('/contracts/'.$contract->id, ['approve' => 1])->assertRedirect();
        $this->assertSame('approved', $contract->fresh()->season_status);

        $this->actingAs($this->gatekeeperA)->get('/bookings/'.$waiting->id.'/print')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/bookings/'.$waiting->id.'/print')->assertOk();
        $this->assertTrue(AuditLog::query()->where('action', 'booking.printed')->exists());
        $this->actingAs($this->accountantA)->get('/bookings/availability')->assertOk()->assertSee('C1');
        $this->actingAs($this->managerA)->get('/branches/'.$this->branchA->id.'/bookings')->assertOk();

        $foreign = app(TenantContext::class)->bypass(fn () => Booking::query()->create([
            'tenant_id' => $this->branchB->tenant_id, 'branch_id' => $this->branchB->id,
            'customer_id' => Customer::query()->create(['tenant_id' => $this->branchB->tenant_id, 'branch_id' => $this->branchB->id, 'name' => 'Other'])->id,
            'commodity_id' => $commodity->id, 'quantity' => 1, 'status' => 'pending',
        ]));
        $this->actingAs($this->adminA)->get('/bookings/'.$foreign->id)->assertNotFound();
    }

    private function payload(Customer $customer, Commodity $commodity, Chamber $chamber, float $quantity, string $notes): array
    {
        return [
            'customer_id' => $customer->id,
            'commodity_id' => $commodity->id,
            'chamber_id' => $chamber->id,
            'quantity' => $quantity,
            'notes' => $notes,
        ];
    }

    private function user(Tenant $tenant, RoleName $role, string $email): User
    {
        $user = User::query()->create([
            'tenant_id' => $tenant->id, 'name' => $email, 'email' => $email, 'password' => 'password', 'status' => UserStatus::Active,
        ]);
        $user->roles()->attach(Role::query()->where('name', $role->value)->firstOrFail());

        return $user;
    }
}

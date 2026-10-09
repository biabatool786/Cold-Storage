<?php

namespace Tests\Feature\Tenancy;

use App\Enums\BillingCycle;
use App\Enums\PlanStatus;
use App\Enums\RoleName;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\BelongsToCurrentTenant;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $userA;

    private User $userB;

    private User $superAdmin;

    private Subscription $subscriptionA;

    private Subscription $subscriptionB;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->group(function (): void {
            Route::get('/testing/tenancy/context', function () {
                return response()->json([
                    'tenant_id' => app(TenantContext::class)->id(),
                    'is_super_admin' => app(TenantContext::class)->isSuperAdmin(),
                    'submitted_tenant_id' => request()->input('tenant_id'),
                ]);
            });

            Route::get('/testing/tenancy/subscriptions/{subscription}', function (Subscription $subscription) {
                return response()->json([
                    'id' => $subscription->id,
                    'tenant_id' => $subscription->tenant_id,
                ]);
            });

            Route::post('/testing/tenancy/subscriptions', function () {
                $subscription = Subscription::query()->create([
                    'plan_id' => request('plan_id'),
                    'status' => SubscriptionStatus::Active,
                    'starts_at' => now(),
                    'tenant_id' => request('tenant_id'),
                ]);

                return response()->json([
                    'tenant_id' => $subscription->tenant_id,
                ]);
            });
        });

        $context = app(TenantContext::class);

        $context->bypass(function (): void {
            $this->plan = Plan::query()->create([
                'name' => 'Starter',
                'price' => 49,
                'billing_cycle' => BillingCycle::Monthly,
                'status' => PlanStatus::Active,
            ]);

            $this->tenantA = Tenant::query()->create([
                'name' => 'Tenant A',
                'slug' => 'tenant-a',
                'email' => 'tenant-a@example.com',
                'status' => TenantStatus::Active,
            ]);

            $this->tenantB = Tenant::query()->create([
                'name' => 'Tenant B',
                'slug' => 'tenant-b',
                'email' => 'tenant-b@example.com',
                'status' => TenantStatus::Active,
            ]);

            $this->userA = User::query()->create([
                'tenant_id' => $this->tenantA->id,
                'name' => 'User A',
                'email' => 'user-a@example.com',
                'password' => 'password',
                'status' => UserStatus::Active,
            ]);

            $this->userB = User::query()->create([
                'tenant_id' => $this->tenantB->id,
                'name' => 'User B',
                'email' => 'user-b@example.com',
                'password' => 'password',
                'status' => UserStatus::Active,
            ]);

            $superAdminRole = Role::query()->create([
                'name' => RoleName::SuperAdmin->value,
            ]);

            $this->superAdmin = User::query()->create([
                'name' => 'Super Admin',
                'email' => 'super-admin@example.com',
                'password' => 'password',
                'status' => UserStatus::Active,
            ]);

            $this->superAdmin->roles()->attach($superAdminRole);

            $this->subscriptionA = Subscription::query()->create([
                'tenant_id' => $this->tenantA->id,
                'plan_id' => $this->plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
            ]);

            $this->subscriptionB = Subscription::query()->create([
                'tenant_id' => $this->tenantB->id,
                'plan_id' => $this->plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now(),
            ]);
        });
    }

    public function test_tenant_a_can_access_tenant_a_data(): void
    {
        $this->actingAs($this->userA)
            ->getJson('/testing/tenancy/subscriptions/'.$this->subscriptionA->id)
            ->assertOk()
            ->assertJson([
                'id' => $this->subscriptionA->id,
                'tenant_id' => $this->tenantA->id,
            ]);

        $this->assertTrue(Gate::forUser($this->userA)->allows('view', $this->subscriptionA));
    }

    public function test_tenant_a_cannot_access_tenant_b_data(): void
    {
        $this->actingAs($this->userA)
            ->getJson('/testing/tenancy/subscriptions/'.$this->subscriptionB->id)
            ->assertNotFound();

        app(TenantContext::class)->resolveForTenant($this->tenantA->id);

        $this->assertNull(Subscription::query()->find($this->subscriptionB->id));
        $this->assertNull(User::query()->find($this->userB->id));
        $this->assertFalse(Gate::forUser($this->userA)->allows('view', $this->subscriptionB));
    }

    public function test_tenant_b_cannot_access_tenant_a_data(): void
    {
        $this->actingAs($this->userB)
            ->getJson('/testing/tenancy/subscriptions/'.$this->subscriptionA->id)
            ->assertNotFound();

        app(TenantContext::class)->resolveForTenant($this->tenantB->id);

        $this->assertNull(Subscription::query()->find($this->subscriptionA->id));
        $this->assertNull(User::query()->find($this->userA->id));
        $this->assertFalse(Gate::forUser($this->userB)->allows('view', $this->subscriptionA));
    }

    public function test_changing_ids_manually_cannot_bypass_tenant_isolation(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->actingAs($this->userA)
            ->getJson('/testing/tenancy/context?tenant_id='.$this->tenantB->id)
            ->assertOk()
            ->assertJson([
                'tenant_id' => $this->tenantA->id,
                'is_super_admin' => false,
                'submitted_tenant_id' => null,
            ]);

        $this->actingAs($this->userA)
            ->postJson('/testing/tenancy/subscriptions', [
                'plan_id' => $this->plan->id,
                'tenant_id' => $this->tenantB->id,
            ])
            ->assertOk()
            ->assertJson([
                'tenant_id' => $this->tenantA->id,
            ]);

        app(TenantContext::class)->resolveForTenant($this->tenantA->id);

        $validator = Validator::make(
            ['subscription_id' => $this->subscriptionB->id],
            ['subscription_id' => ['required', new BelongsToCurrentTenant(Subscription::class)]],
        );

        $this->assertTrue($validator->fails());

        $this->actingAs($this->userA)
            ->getJson('/testing/tenancy/subscriptions/'.$this->subscriptionB->id)
            ->assertNotFound();
    }

    public function test_super_admin_can_access_every_tenant(): void
    {
        $this->actingAs($this->superAdmin)
            ->getJson('/testing/tenancy/subscriptions/'.$this->subscriptionA->id)
            ->assertOk()
            ->assertJson(['tenant_id' => $this->tenantA->id]);

        $this->actingAs($this->superAdmin)
            ->getJson('/testing/tenancy/subscriptions/'.$this->subscriptionB->id)
            ->assertOk()
            ->assertJson(['tenant_id' => $this->tenantB->id]);

        $this->actingAs($this->superAdmin)
            ->getJson('/testing/tenancy/context?tenant_id='.$this->tenantB->id)
            ->assertOk()
            ->assertJson([
                'tenant_id' => null,
                'is_super_admin' => true,
                'submitted_tenant_id' => null,
            ]);
    }
}

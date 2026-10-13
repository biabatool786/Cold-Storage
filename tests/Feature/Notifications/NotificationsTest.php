<?php

namespace Tests\Feature\Notifications;

use App\Enums\BranchStatus;
use App\Enums\RoleName;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Jobs\DeliverOutboundMessage;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Csms\CommunicationGateway;
use App\Models\Csms\Customer;
use App\Models\Csms\InternalAlert;
use App\Models\Csms\MessageLog;
use App\Models\Csms\MessageTemplate;
use App\Models\Csms\RoleAlertPreference;
use App\Models\Csms\StaffAnnouncement;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notifications\AlertDispatcher;
use App\Services\Notifications\MessageGatewayDriver;
use App\Services\Notifications\NotificationsDesk;
use App\Services\Tenancy\TenantContext;
use App\Support\Navigation;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Branch $branchA;

    private Branch $branchB;

    private User $superAdmin;

    private User $adminA;

    private User $managerA;

    private User $supervisorA;

    private User $gatekeeperA;

    private User $accountantA;

    private User $managerB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        app(TenantContext::class)->bypass(function (): void {
            $this->tenantA = Tenant::query()->create([
                'name' => 'Notify Co', 'slug' => 'notify-co', 'email' => 'n@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->tenantB = Tenant::query()->create([
                'name' => 'Other Co', 'slug' => 'other-co', 'email' => 'o@example.com', 'status' => TenantStatus::Active,
            ]);
            $this->branchA = Branch::query()->create([
                'tenant_id' => $this->tenantA->id, 'name' => 'North', 'code' => 'NNORTH', 'status' => BranchStatus::Active,
            ]);
            $this->branchB = Branch::query()->create([
                'tenant_id' => $this->tenantB->id, 'name' => 'West', 'code' => 'NWEST', 'status' => BranchStatus::Active,
            ]);
            $this->superAdmin = $this->user(null, RoleName::SuperAdmin, 'super-n@example.com');
            $this->adminA = $this->user($this->tenantA, RoleName::Admin, 'admin-n@example.com');
            $this->managerA = $this->user($this->tenantA, RoleName::Manager, 'manager-n@example.com');
            $this->supervisorA = $this->user($this->tenantA, RoleName::Supervisor, 'supervisor-n@example.com');
            $this->gatekeeperA = $this->user($this->tenantA, RoleName::Gatekeeper, 'gate-n@example.com');
            $this->accountantA = $this->user($this->tenantA, RoleName::Accountant, 'accountant-n@example.com');
            $this->managerB = $this->user($this->tenantB, RoleName::Manager, 'manager-b@example.com');
            foreach ([$this->managerA, $this->supervisorA, $this->gatekeeperA, $this->accountantA] as $user) {
                $user->branches()->sync([$this->branchA->id]);
            }
            $this->managerB->branches()->sync([$this->branchB->id]);
        });
    }

    public function test_role_permissions_for_pages_and_actions(): void
    {
        $this->actingAs($this->managerA)->get('/notifications')->assertOk();
        $this->actingAs($this->gatekeeperA)->get('/notifications')->assertOk();
        $this->actingAs($this->gatekeeperA)->get('/notifications/templates')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/notifications/gateways')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/notifications/logs')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/notifications/compose')->assertForbidden();
        $this->actingAs($this->gatekeeperA)->get('/notifications/announcements')->assertForbidden();

        $this->actingAs($this->supervisorA)->get('/notifications/templates')->assertOk();
        $this->actingAs($this->supervisorA)->post('/branches/'.$this->branchA->id.'/notifications/templates', [
            'name' => 'X', 'event_type' => 't', 'channel' => 'email', 'body' => 'Hi',
        ])->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/notifications/compose')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/notifications/logs')->assertForbidden();
        $this->actingAs($this->supervisorA)->get('/notifications/gateways')->assertForbidden();

        $this->actingAs($this->managerA)->get('/notifications/gateways')->assertForbidden();
        $this->actingAs($this->managerA)->get('/notifications/preferences')->assertForbidden();
        $this->actingAs($this->managerA)->get('/notifications/compose')->assertOk();
        $this->actingAs($this->managerA)->get('/notifications/announcements')->assertOk();
        $this->actingAs($this->managerA)->get('/notifications/logs')->assertOk();

        $this->actingAs($this->adminA)->get('/notifications/gateways')->assertOk();
        $this->actingAs($this->adminA)->get('/notifications/preferences')->assertOk();
        $this->actingAs($this->accountantA)->get('/notifications/compose')->assertOk();
        $this->actingAs($this->accountantA)->get('/notifications/logs')->assertOk();
        $this->actingAs($this->accountantA)->get('/notifications/announcements')->assertForbidden();
        $this->actingAs($this->accountantA)->get('/notifications/templates')->assertForbidden();
    }

    public function test_template_create_edit_preview_and_placeholder_validation(): void
    {
        $this->actingAs($this->adminA)->post('/branches/'.$this->branchA->id.'/notifications/templates', [
            'name' => 'AdminTpl', 'event_type' => 'invoice', 'channel' => 'email', 'body' => 'Hello',
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/notifications/templates', [
            'name' => 'Welcome', 'event_type' => 'welcome', 'channel' => 'email',
            'subject' => 'Hi {{customer_name}}', 'body' => 'Dear {{customer_name}}, bal {{amount}}',
            'tenant_id' => 999,
        ])->assertRedirect();

        $tpl = MessageTemplate::query()->firstOrFail();
        $this->assertSame((int) $this->tenantA->id, (int) $tpl->tenant_id);
        $this->assertTrue(AuditLog::query()->where('action', 'notifications.template_created')->exists());

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/notifications/templates', [
            'name' => 'Bad', 'event_type' => 'x', 'channel' => 'email', 'body' => 'Hi {{evil_script}}',
        ])->assertSessionHasErrors('body');

        $this->actingAs($this->adminA)->post('/notifications/templates/'.$tpl->id, [
            'name' => 'Welcome', 'event_type' => 'welcome', 'channel' => 'email',
            'subject' => 'Hi {{customer_name}}', 'body' => 'Updated {{customer_name}}', 'is_active' => 1,
        ])->assertRedirect();
        $this->assertStringContainsString('Updated', $tpl->fresh()->body);

        $this->actingAs($this->supervisorA)->post('/notifications/templates/'.$tpl->id, [
            'name' => 'Welcome', 'event_type' => 'welcome', 'channel' => 'sms',
            'body' => 'SMS for {{customer_name}} at {{customer_phone}}', 'is_active' => 1,
        ])->assertRedirect();

        $this->actingAs($this->managerA)->get('/notifications/templates/'.$tpl->id.'/preview')
            ->assertOk()
            ->assertSee('Sample Customer')
            ->assertSee('0300-0000000');
    }

    public function test_gateway_secrecy_and_access(): void
    {
        $this->actingAs($this->managerA)->post('/notifications/gateways', [
            'channel' => 'sms', 'api_key' => 'secret-key', 'is_enabled' => 1,
        ])->assertForbidden();

        $response = $this->actingAs($this->adminA)->post('/notifications/gateways', [
            'channel' => 'email',
            'provider' => 'smtp',
            'from_address' => 'noreply@example.com',
            'api_key' => 'super-secret-key',
            'is_enabled' => 1,
        ]);
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $gw = app(TenantContext::class)->bypass(fn () => CommunicationGateway::query()->where('channel', 'email')->firstOrFail());
        $this->assertSame('configured', $gw->status);
        $this->assertSame('super-secret-key', $gw->credentials_encrypted['api_key']);

        $this->actingAs($this->adminA)->get('/notifications/gateways')
            ->assertOk()
            ->assertDontSee('super-secret-key')
            ->assertSee('configured');

        $this->assertTrue(AuditLog::query()->where('action', 'notifications.gateway_updated')->exists());
        $payload = AuditLog::query()->where('action', 'notifications.gateway_updated')->latest('id')->first();
        $this->assertStringNotContainsString('super-secret-key', json_encode([
            $payload->new_values,
            $payload->old_values,
            $payload->properties ?? null,
        ]));
    }

    public function test_alert_dispatch_role_recipients_and_idempotency(): void
    {
        $dispatcher = app(AlertDispatcher::class);

        $temp = $dispatcher->dispatch([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'category' => 'temperature',
            'title' => 'Temp high',
            'body' => 'Chamber hot',
            'idempotency_key' => 'temp-1',
        ]);
        $this->assertNotEmpty($temp);
        $userIds = collect($temp)->pluck('user_id');
        $this->assertTrue($userIds->contains($this->managerA->id));
        $this->assertTrue($userIds->contains($this->supervisorA->id));
        $this->assertFalse($userIds->contains($this->gatekeeperA->id));
        $this->assertFalse($userIds->contains($this->accountantA->id));

        $again = $dispatcher->dispatch([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'category' => 'temperature',
            'title' => 'Temp high',
            'body' => 'Chamber hot',
            'idempotency_key' => 'temp-1',
        ]);
        $this->assertSame([], $again);

        $gate = $dispatcher->dispatch([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'category' => 'gate',
            'title' => 'Gate alert',
            'body' => 'Vehicle ABC customer: Acme amount 1500.00',
            'idempotency_key' => 'gate-1',
        ]);
        $gkAlert = collect($gate)->firstWhere('user_id', $this->gatekeeperA->id);
        $this->assertNotNull($gkAlert);
        $this->assertStringContainsString('[restricted]', $gkAlert->body);
        $this->assertStringNotContainsString('1500', $gkAlert->body);

        $invoice = $dispatcher->dispatch([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'category' => 'overdue_invoice',
            'title' => 'Overdue',
            'body' => 'Invoice due',
            'idempotency_key' => 'inv-1',
        ]);
        $invIds = collect($invoice)->pluck('user_id');
        $this->assertTrue($invIds->contains($this->accountantA->id));
        $this->assertTrue($invIds->contains($this->managerA->id));
        $this->assertFalse($invIds->contains($this->gatekeeperA->id));

        foreach (['humidity', 'chamber_occupancy', 'low_stock_dispatch', 'critical_quality', 'pending_approval'] as $cat) {
            $created = $dispatcher->dispatch([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'category' => $cat,
                'title' => $cat,
                'body' => $cat.' body',
                'idempotency_key' => $cat.'-event',
            ]);
            $this->assertNotEmpty($created, $cat);
        }
    }

    public function test_alert_preferences_cannot_override_rbac(): void
    {
        $this->actingAs($this->adminA)->post('/notifications/preferences', [
            'role_name' => RoleName::Gatekeeper->value,
            'categories' => [
                'gate' => ['enabled' => 1, 'channels' => ['in_app']],
                'overdue_invoice' => ['enabled' => 1, 'channels' => ['in_app']],
                'temperature' => ['enabled' => 1, 'channels' => ['in_app']],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $prefs = app(TenantContext::class)->bypass(fn () => RoleAlertPreference::query()
            ->where('tenant_id', $this->tenantA->id)
            ->where('role_name', RoleName::Gatekeeper->value)
            ->pluck('enabled', 'alert_category'));
        $this->assertTrue((bool) ($prefs['gate'] ?? false));
        $this->assertArrayNotHasKey('temperature', $prefs->all());
        $this->assertArrayNotHasKey('overdue_invoice', $prefs->all());

        $this->actingAs($this->adminA)->post('/notifications/preferences', [
            'role_name' => RoleName::Manager->value,
            'categories' => [
                'pending_approval' => ['enabled' => 0, 'channels' => ['in_app']],
                'temperature' => ['enabled' => 0, 'channels' => ['in_app']],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $mgrPending = app(TenantContext::class)->bypass(fn () => RoleAlertPreference::query()
            ->where('tenant_id', $this->tenantA->id)
            ->where('role_name', RoleName::Manager->value)
            ->where('alert_category', 'pending_approval')
            ->first());
        $this->assertTrue((bool) $mgrPending->enabled);
    }

    public function test_manual_customer_message_and_delivery(): void
    {
        $customer = app(TenantContext::class)->bypass(fn () => Customer::query()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'name' => 'Cust N',
            'email' => 'cust@example.com',
            'phone' => '03001234567',
            'status' => 'active',
            'created_by' => $this->managerA->id,
        ]));

        $this->actingAs($this->supervisorA)->post('/notifications/compose', [
            'customer_id' => $customer->id, 'channel' => 'email', 'body' => 'Hello',
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/notifications/compose', [
            'customer_id' => $customer->id, 'channel' => 'email', 'body' => 'Hello there',
        ])->assertRedirect();
        $failed = app(TenantContext::class)->bypass(fn () => MessageLog::query()->latest('id')->firstOrFail());
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('unconfigured', (string) $failed->failure_reason);

        $this->actingAs($this->adminA)->post('/notifications/gateways', [
            'channel' => 'email',
            'from_address' => 'noreply@example.com',
            'api_key' => 'key',
            'is_enabled' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $driver = app(MessageGatewayDriver::class);
        $driver->sent = [];
        $driver->shouldFail = false;

        $this->actingAs($this->accountantA)->post('/notifications/compose', [
            'customer_id' => $customer->id, 'channel' => 'email', 'body' => 'Invoice reminder', 'subject' => 'Due',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $log = app(TenantContext::class)->bypass(fn () => MessageLog::query()->latest('id')->firstOrFail());
        $this->assertSame('sent', $log->status);
        $this->assertNotEmpty($log->provider_reference);
        $this->assertNotEmpty($driver->sent);

        $driver->shouldFail = true;
        $this->actingAs($this->managerA)->post('/notifications/compose', [
            'customer_id' => $customer->id, 'channel' => 'email', 'body' => 'Will fail',
        ])->assertRedirect();
        $this->assertSame('failed', app(TenantContext::class)->bypass(fn () => MessageLog::query()->latest('id')->value('status')));
    }

    public function test_staff_announcement_targeting_and_tenant_isolation(): void
    {
        $this->actingAs($this->accountantA)->post('/branches/'.$this->branchA->id.'/notifications/announcements', [
            'title' => 'Nope', 'message' => 'x', 'target_roles' => [RoleName::Manager->value],
        ])->assertForbidden();

        $this->actingAs($this->managerA)->post('/branches/'.$this->branchA->id.'/notifications/announcements', [
            'title' => 'Warehouse update',
            'message' => 'Please check chamber A',
            'target_roles' => [RoleName::Supervisor->value, RoleName::Manager->value],
        ])->assertRedirect();

        app(TenantContext::class)->bypass(function (): void {
            $this->assertTrue(StaffAnnouncement::query()->where('title', 'Warehouse update')->exists());
            $this->assertTrue(InternalAlert::query()
                ->where('user_id', $this->supervisorA->id)
                ->where('category', 'staff_announcement')
                ->exists());
            $this->assertFalse(InternalAlert::query()
                ->where('user_id', $this->gatekeeperA->id)
                ->where('category', 'staff_announcement')
                ->exists());
            $this->assertFalse(InternalAlert::query()
                ->where('user_id', $this->managerB->id)
                ->exists());
        });
    }

    public function test_message_log_filters_and_visibility(): void
    {
        app(TenantContext::class)->bypass(function (): void {
            MessageLog::query()->create([
                'tenant_id' => $this->tenantA->id,
                'branch_id' => $this->branchA->id,
                'channel' => 'sms',
                'category' => 'customer_manual',
                'recipient_reference' => '0300',
                'status' => 'sent',
                'sent_by' => $this->managerA->id,
                'sent_at' => now(),
            ]);
            MessageLog::query()->create([
                'tenant_id' => $this->tenantB->id,
                'branch_id' => $this->branchB->id,
                'channel' => 'email',
                'category' => 'customer_manual',
                'recipient_reference' => 'other@x.com',
                'status' => 'failed',
                'sent_by' => $this->managerB->id,
            ]);
        });

        $this->actingAs($this->managerA)->get('/notifications/logs?channel=sms&status=sent')
            ->assertOk()
            ->assertSee('0300')
            ->assertDontSee('other@x.com');

        $this->actingAs($this->gatekeeperA)->get('/notifications/logs')->assertForbidden();
        $this->actingAs($this->managerB)->get('/notifications/logs')
            ->assertOk()
            ->assertSee('other@x.com')
            ->assertDontSee('0300');
    }

    public function test_queued_delivery_job_skips_already_sent(): void
    {
        $log = app(TenantContext::class)->bypass(fn () => MessageLog::query()->create([
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'channel' => 'sms',
            'recipient_reference' => '03001112222',
            'body_preview' => 'Hi',
            'status' => 'sent',
            'provider_reference' => 'SMS-1',
            'sent_by' => $this->managerA->id,
            'sent_at' => now(),
        ]));

        $driver = app(MessageGatewayDriver::class);
        $driver->sent = [];
        (new DeliverOutboundMessage($log->id))->handle($driver, app(TenantContext::class));
        $this->assertSame([], $driver->sent);
    }

    public function test_dashboard_stats_are_live_and_navigation_group_exists(): void
    {
        app(TenantContext::class)->bypass(function (): void {
            MessageLog::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'channel' => 'email', 'status' => 'sent', 'recipient_reference' => 'a@b.com',
            ]);
            MessageLog::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'channel' => 'sms', 'status' => 'failed', 'recipient_reference' => '0300',
            ]);
            MessageLog::query()->create([
                'tenant_id' => $this->tenantA->id, 'branch_id' => $this->branchA->id,
                'channel' => 'whatsapp', 'status' => 'queued', 'recipient_reference' => '0300',
            ]);
        });

        $this->actingAs($this->managerA)->get('/notifications')
            ->assertOk()
            ->assertSee('3')
            ->assertSee('Gateway');

        $mgr = collect(Navigation::modules($this->managerA))->firstWhere('key', 'notifications');
        $this->assertNotNull($mgr);
        $labels = collect($mgr['type'] === 'link' ? [['label' => $mgr['label']]] : $mgr['items'])->pluck('label');
        $this->assertTrue($labels->contains('Notification Dashboard'));
        $this->assertFalse($labels->contains('Gateway Settings'));

        $gk = collect(Navigation::modules($this->gatekeeperA))->firstWhere('key', 'notifications');
        $this->assertNotNull($gk);
        $gkLabels = collect($gk['type'] === 'link' ? [['label' => $gk['label']]] : $gk['items'])->pluck('label');
        $this->assertTrue($gkLabels->contains('Notification Dashboard') || $gk['label'] === 'Notifications & Communication');
        if ($gk['type'] === 'group') {
            $this->assertCount(1, $gk['items']);
        }
    }

    public function test_operational_raise_helpers(): void
    {
        $desk = app(NotificationsDesk::class);
        $alerts = $desk->raiseOperationalAlert('low_stock_dispatch', [
            'tenant_id' => $this->tenantA->id,
            'branch_id' => $this->branchA->id,
            'title' => 'Low stock',
            'body' => 'Lot LOT-1 insufficient',
            'idempotency_key' => 'stock-1',
        ]);
        $this->assertNotEmpty($alerts);
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

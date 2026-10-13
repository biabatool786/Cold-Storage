<?php

namespace App\Services\Notifications;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Jobs\DeliverOutboundMessage;
use App\Models\Branch;
use App\Models\Csms\CommunicationGateway;
use App\Models\Csms\Customer;
use App\Models\Csms\InternalAlert;
use App\Models\Csms\MessageLog;
use App\Models\Csms\MessageTemplate;
use App\Models\Csms\RoleAlertPreference;
use App\Models\Csms\StaffAnnouncement;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Authorization\BranchAccess;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class NotificationsDesk
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly BranchAccess $branches,
        private readonly AlertDispatcher $alerts,
        private readonly MessageGatewayDriver $driver,
    ) {}

    public function canCreateTemplate(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('notifications.create') && $user->hasRole(RoleName::Manager));
    }

    public function canEditTemplate(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('notifications.edit') && $user->hasAnyRole([
                RoleName::Admin, RoleName::Supervisor,
            ]))
            || $this->canCreateTemplate($user);
    }

    public function canConfigureGateway(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('notifications.edit') && $user->hasRole(RoleName::Admin));
    }

    public function canConfigureAlertPreferences(User $user): bool
    {
        return $this->canConfigureGateway($user);
    }

    public function canSendCustomerMessage(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('notifications.execute') && $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Accountant,
            ]));
    }

    public function canBroadcastAnnouncement(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('notifications.execute') && $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager,
            ]));
    }

    public function canViewMessageLog(User $user): bool
    {
        return $user->isSuperAdmin()
            || ($user->hasPermission('notifications.view') && $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Accountant,
            ]));
    }

    public function canViewDashboard(User $user): bool
    {
        return $user->hasPermission('notifications.view')
            || $user->isSuperAdmin()
            || $user->hasAnyRole([
                RoleName::Admin, RoleName::Manager, RoleName::Supervisor,
                RoleName::Gatekeeper, RoleName::Accountant,
            ]);
    }

    public function assertBranchAccess(User $actor, int $branchId): void
    {
        abort_unless($this->branches->allows($actor, Branch::query()->findOrFail($branchId)), 403);
    }

    public function createTemplate(User $actor, ?Branch $branch, array $data): MessageTemplate
    {
        abort_unless($this->canCreateTemplate($actor), 403);
        if ($branch) {
            abort_unless($this->branches->allows($actor, $branch), 403);
        }
        $this->assertTemplatePayload($data);
        $template = MessageTemplate::query()->create([
            'tenant_id' => $branch?->tenant_id ?? $actor->tenant_id,
            'branch_id' => $branch?->id,
            'name' => $data['name'],
            'event_type' => $data['event_type'],
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'body' => strip_tags($data['body']),
            'placeholders' => $this->extractPlaceholders($data['body'].' '.($data['subject'] ?? '')),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $this->audit->record('notifications.template_created', $template, [], $template->tenant_id, [
            'module' => 'notifications', 'branch_id' => $template->branch_id,
        ]);

        return $template;
    }

    public function updateTemplate(User $actor, MessageTemplate $template, array $data): MessageTemplate
    {
        abort_unless($this->canEditTemplate($actor), 403);
        if ($template->branch_id) {
            $this->assertBranchAccess($actor, (int) $template->branch_id);
        }
        $this->assertTemplatePayload($data);
        $template->update([
            'name' => $data['name'],
            'event_type' => $data['event_type'],
            'channel' => $data['channel'],
            'subject' => $data['subject'] ?? null,
            'body' => strip_tags($data['body']),
            'placeholders' => $this->extractPlaceholders($data['body'].' '.($data['subject'] ?? '')),
            'is_active' => (bool) ($data['is_active'] ?? $template->is_active),
            'updated_by' => $actor->id,
        ]);
        $this->audit->record('notifications.template_updated', $template, [], $template->tenant_id, [
            'module' => 'notifications', 'branch_id' => $template->branch_id,
        ]);

        return $template->fresh();
    }

    /**
     * @param  array<string, string>  $sample
     * @return array{subject: ?string, body: string}
     */
    public function previewTemplate(MessageTemplate $template, array $sample = []): array
    {
        $defaults = [
            'customer_name' => 'Sample Customer',
            'customer_email' => 'sample@example.com',
            'customer_phone' => '0300-0000000',
            'invoice_number' => 'INV-1001',
            'amount' => '1000.00',
            'chamber_name' => 'Chamber A',
            'branch_name' => 'Main Branch',
            'lot_number' => 'LOT-1',
            'alert_title' => 'Sample Alert',
            'alert_body' => 'Sample alert body',
        ];
        $data = array_merge($defaults, $sample);

        return [
            'subject' => $template->subject ? $this->render($template->subject, $data) : null,
            'body' => $this->render($template->body, $data),
        ];
    }

    public function saveGateway(User $actor, int $tenantId, string $channel, array $data): CommunicationGateway
    {
        abort_unless($this->canConfigureGateway($actor), 403);
        abort_unless(in_array($channel, ['email', 'sms', 'whatsapp'], true), 422);

        $existing = CommunicationGateway::query()
            ->where('tenant_id', $tenantId)
            ->where('channel', $channel)
            ->first();

        $creds = $existing?->credentials_encrypted ?? [];
        foreach (['api_key', 'token', 'password', 'host', 'username', 'sender_id'] as $key) {
            if (array_key_exists($key, $data) && filled($data[$key])) {
                $creds[$key] = $data[$key];
            }
        }

        $configured = filled($creds['api_key'] ?? null)
            || filled($creds['token'] ?? null)
            || filled($creds['password'] ?? null)
            || ($channel === 'email' && filled($data['from_address'] ?? $existing?->from_address));

        $gateway = CommunicationGateway::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'channel' => $channel],
            [
                'provider' => $data['provider'] ?? $existing?->provider,
                'credentials_encrypted' => $creds,
                'from_address' => $data['from_address'] ?? $existing?->from_address,
                'is_enabled' => (bool) ($data['is_enabled'] ?? false) && $configured,
                'status' => $configured ? 'configured' : 'unconfigured',
                'updated_by' => $actor->id,
            ]
        );

        $this->audit->record('notifications.gateway_updated', $gateway, [], $tenantId, [
            'module' => 'notifications',
            'channel' => $channel,
            'status' => $gateway->status,
            // never audit secret values
        ]);

        return $gateway;
    }

    public function testGateway(User $actor, CommunicationGateway $gateway): array
    {
        abort_unless($this->canConfigureGateway($actor), 403);
        $result = $this->driver->testConnection($gateway->channel, $gateway->credentials_encrypted ?? []);
        $gateway->update([
            'last_tested_at' => now(),
            'last_test_result' => $result['message'],
            'status' => ($result['ok'] ?? false) ? 'configured' : 'unconfigured',
        ]);
        $this->audit->record('notifications.gateway_tested', $gateway, [], $gateway->tenant_id, [
            'module' => 'notifications', 'channel' => $gateway->channel, 'ok' => $result['ok'] ?? false,
        ]);

        return $result;
    }

    public function saveAlertPreferences(User $actor, int $tenantId, string $roleName, array $categories): void
    {
        abort_unless($this->canConfigureAlertPreferences($actor), 403);
        $allowed = RoleAlertPreference::DEFAULT_ROLES;
        foreach ($categories as $category => $settings) {
            abort_unless(in_array($category, RoleAlertPreference::CATEGORIES, true), 422);
            // Cannot enable categories outside role's matrix defaults for Gatekeeper/Accountant
            if ($roleName === RoleName::Gatekeeper->value && $category !== 'gate') {
                continue;
            }
            if ($roleName === RoleName::Accountant->value && ! in_array($category, ['overdue_invoice', 'pending_approval'], true)) {
                // Accountant finance-only — ignore attempts to grant operational categories
                continue;
            }
            $enabled = (bool) ($settings['enabled'] ?? false);
            // Preserve essential security: pending_approval for manager/admin always on when listed
            if (in_array($roleName, [
                RoleName::Manager->value,
                RoleName::Admin->value,
                RoleName::SuperAdmin->value,
            ], true) && $category === 'pending_approval') {
                $enabled = true;
            }
            RoleAlertPreference::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'role_name' => $roleName, 'alert_category' => $category],
                [
                    'enabled' => $enabled,
                    'channels' => $settings['channels'] ?? ['in_app'],
                    'updated_by' => $actor->id,
                ]
            );
        }
        $this->audit->record('notifications.alert_preferences_updated', null, [], $tenantId, [
            'module' => 'notifications', 'role' => $roleName,
        ]);
    }

    public function sendCustomerMessage(User $actor, Customer $customer, array $data): MessageLog
    {
        abort_unless($this->canSendCustomerMessage($actor), 403);
        if ($customer->branch_id) {
            $this->assertBranchAccess($actor, (int) $customer->branch_id);
        }
        $channel = $data['channel'];
        abort_unless(in_array($channel, ['email', 'sms', 'whatsapp'], true), 422);

        $template = null;
        $subject = $data['subject'] ?? null;
        $body = $data['body'] ?? null;
        if (! empty($data['message_template_id'])) {
            $template = MessageTemplate::query()->findOrFail($data['message_template_id']);
            abort_unless((int) $template->tenant_id === (int) $customer->tenant_id, 404);
            abort_unless($template->is_active && $template->channel === $channel, 422);
            $rendered = $this->previewTemplate($template, [
                'customer_name' => $customer->name,
                'customer_email' => (string) $customer->email,
                'customer_phone' => (string) $customer->phone,
            ]);
            $subject = $rendered['subject'];
            $body = $rendered['body'];
        }
        if (! filled($body)) {
            throw ValidationException::withMessages(['body' => 'Message body or template is required.']);
        }

        $to = match ($channel) {
            'email' => $customer->email,
            default => $customer->phone,
        };
        if (! filled($to)) {
            throw ValidationException::withMessages(['channel' => 'Customer contact for this channel is missing.']);
        }

        $gateway = CommunicationGateway::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('channel', $channel)
            ->first();
        $status = ($gateway && $gateway->status === 'configured' && $gateway->is_enabled) ? 'queued' : 'failed';
        $failure = $status === 'failed' ? 'Gateway unconfigured or disabled for '.$channel : null;

        $log = MessageLog::query()->create([
            'tenant_id' => $customer->tenant_id,
            'branch_id' => $customer->branch_id,
            'message_template_id' => $template?->id,
            'template_name' => $template?->name,
            'channel' => $channel,
            'category' => 'customer_manual',
            'recipient_type' => 'customer',
            'recipient_reference' => $to,
            'customer_id' => $customer->id,
            'subject' => $subject,
            'body_preview' => Str::limit(strip_tags((string) $body), 2000),
            'status' => $status,
            'failure_reason' => $failure,
            'sent_by' => $actor->id,
        ]);

        $this->audit->record('notifications.customer_message_queued', $log, [], $customer->tenant_id, [
            'module' => 'notifications', 'channel' => $channel, 'customer_id' => $customer->id, 'status' => $status,
        ]);

        if ($status === 'queued') {
            DeliverOutboundMessage::dispatch($log->id);
        }

        return $log->fresh();
    }

    public function broadcastAnnouncement(User $actor, Branch $branch, array $data): StaffAnnouncement
    {
        abort_unless($this->canBroadcastAnnouncement($actor), 403);
        abort_unless($this->branches->allows($actor, $branch), 403);

        return DB::transaction(function () use ($actor, $branch, $data) {
            $announcement = StaffAnnouncement::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'title' => strip_tags($data['title']),
                'message' => strip_tags($data['message']),
                'target_roles' => $data['target_roles'] ?? [],
                'target_branch_ids' => $data['target_branch_ids'] ?? [$branch->id],
                'published_at' => now(),
                'status' => 'published',
                'created_by' => $actor->id,
            ]);

            $roles = $announcement->target_roles ?: [
                RoleName::Admin->value, RoleName::Manager->value, RoleName::Supervisor->value,
                RoleName::Gatekeeper->value, RoleName::Accountant->value,
            ];
            $users = User::query()
                ->where('tenant_id', $branch->tenant_id)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
                ->where('status', UserStatus::Active->value)
                ->get();

            foreach ($users as $user) {
                if (! empty($announcement->target_branch_ids)
                    && ! $user->isSuperAdmin()
                    && ! $user->hasRole(RoleName::Admin)
                    && $user->branches()->whereIn('branches.id', $announcement->target_branch_ids)->doesntExist()) {
                    continue;
                }
                try {
                    InternalAlert::query()->create([
                        'tenant_id' => $branch->tenant_id,
                        'branch_id' => $branch->id,
                        'user_id' => $user->id,
                        'category' => 'staff_announcement',
                        'title' => 'Announcement: '.$announcement->title,
                        'body' => $announcement->message,
                        'severity' => 'info',
                        'idempotency_key' => 'staff-announce:'.$announcement->id.':'.$user->id,
                        'reference_type' => StaffAnnouncement::class,
                        'reference_id' => $announcement->id,
                    ]);
                } catch (\Throwable) {
                    // duplicate idempotency key
                }
            }

            $this->audit->record('notifications.announcement_published', $announcement, [], $branch->tenant_id, [
                'module' => 'notifications', 'branch_id' => $branch->id,
            ]);

            return $announcement;
        });
    }

    public function dashboardStats(User $user): array
    {
        abort_unless($this->canViewDashboard($user), 403);
        $logs = MessageLog::query()->visibleTo($user);
        $alerts = InternalAlert::query()->where('user_id', $user->id)->latest()->limit(10)->get();

        return [
            'total' => (clone $logs)->count(),
            'sent' => (clone $logs)->whereIn('status', ['sent', 'delivered'])->count(),
            'failed' => (clone $logs)->where('status', 'failed')->count(),
            'pending' => (clone $logs)->where('status', 'queued')->count(),
            'recent_alerts' => $alerts,
            'gateways' => CommunicationGateway::query()
                ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('tenant_id', $user->tenant_id))
                ->get()
                ->map->publicStatus(),
        ];
    }

    public function myAlerts(User $user): Collection
    {
        return InternalAlert::query()->where('user_id', $user->id)->latest()->limit(50)->get();
    }

    public function markAlertRead(User $user, InternalAlert $alert): void
    {
        abort_unless((int) $alert->user_id === (int) $user->id, 403);
        $alert->update(['read_at' => now()]);
    }

    /**
     * @param  array{tenant_id: int, branch_id?: ?int, title: string, body: string, reference_type?: ?string, reference_id?: ?int, idempotency_key: string}  $payload
     * @return list<InternalAlert>
     */
    public function raiseOperationalAlert(string $category, array $payload): array
    {
        return $this->alerts->dispatch(array_merge($payload, ['category' => $category]));
    }

    private function assertTemplatePayload(array $data): void
    {
        if (! in_array($data['channel'] ?? '', ['email', 'sms', 'whatsapp', 'in_app'], true)) {
            throw ValidationException::withMessages(['channel' => 'Unsupported channel.']);
        }
        $placeholders = $this->extractPlaceholders(($data['body'] ?? '').' '.($data['subject'] ?? ''));
        foreach ($placeholders as $ph) {
            if (! in_array($ph, MessageTemplate::SUPPORTED_PLACEHOLDERS, true)) {
                throw ValidationException::withMessages(['body' => 'Unsupported placeholder: {{'.$ph.'}}']);
            }
        }
    }

    /**
     * @return list<string>
     */
    private function extractPlaceholders(string $text): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $text, $m);

        return array_values(array_unique(array_map('strtolower', $m[1] ?? [])));
    }

    /**
     * @param  array<string, string>  $data
     */
    private function render(string $text, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($data) {
            $key = strtolower($m[1]);

            return e($data[$key] ?? '');
        }, $text) ?? $text;
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Csms\CommunicationGateway;
use App\Models\Csms\Customer;
use App\Models\Csms\InternalAlert;
use App\Models\Csms\MessageLog;
use App\Models\Csms\MessageTemplate;
use App\Models\Csms\RoleAlertPreference;
use App\Models\Csms\StaffAnnouncement;
use App\Services\Notifications\NotificationsDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationsController extends Controller
{
    public function __construct(private readonly NotificationsDesk $desk) {}

    public function dashboard(Request $request): View
    {
        abort_unless($this->desk->canViewDashboard($request->user()), 403);

        return view('notifications.dashboard', [
            'stats' => $this->desk->dashboardStats($request->user()),
            'desk' => $this->desk,
        ]);
    }

    public function templates(Request $request): View
    {
        $user = $request->user();
        abort_unless($this->desk->canCreateTemplate($user) || $this->desk->canEditTemplate($user), 403);

        return view('notifications.templates', [
            'templates' => MessageTemplate::query()->visibleTo($user)->latest()->limit(50)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
            'placeholders' => MessageTemplate::SUPPORTED_PLACEHOLDERS,
        ]);
    }

    public function storeTemplate(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:100'],
            'channel' => ['required', 'in:email,sms,whatsapp,in_app'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->desk->createTemplate($request->user(), $branch, $data);

        return back()->with('status', 'Template created.');
    }

    public function updateTemplate(Request $request, MessageTemplate $message_template): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:100'],
            'channel' => ['required', 'in:email,sms,whatsapp,in_app'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $this->desk->updateTemplate($request->user(), $message_template, $data);

        return back()->with('status', 'Template updated.');
    }

    public function previewTemplate(MessageTemplate $message_template): View
    {
        $user = request()->user();
        abort_unless($this->desk->canCreateTemplate($user) || $this->desk->canEditTemplate($user), 403);

        return view('notifications.preview', [
            'template' => $message_template,
            'preview' => $this->desk->previewTemplate($message_template),
        ]);
    }

    public function gateways(Request $request): View
    {
        abort_unless($this->desk->canConfigureGateway($request->user()), 403);
        $tenantId = $this->tenantIdFor($request->user());

        $gateways = CommunicationGateway::query()->where('tenant_id', $tenantId)->get()->keyBy('channel');

        return view('notifications.gateways', [
            'tenantId' => $tenantId,
            'gateways' => collect(['email', 'sms', 'whatsapp'])->mapWithKeys(function ($ch) use ($gateways) {
                $g = $gateways->get($ch);

                return [$ch => $g?->publicStatus() ?? [
                    'channel' => $ch, 'status' => 'unconfigured', 'is_enabled' => false, 'has_credentials' => false,
                ]];
            }),
            'desk' => $this->desk,
        ]);
    }

    public function storeGateway(Request $request): RedirectResponse
    {
        abort_unless($this->desk->canConfigureGateway($request->user()), 403);
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms,whatsapp'],
            'provider' => ['nullable', 'string', 'max:100'],
            'from_address' => ['nullable', 'string', 'max:255'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'token' => ['nullable', 'string', 'max:500'],
            'password' => ['nullable', 'string', 'max:500'],
            'host' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'sender_id' => ['nullable', 'string', 'max:100'],
            'is_enabled' => ['nullable', 'boolean'],
        ]);
        $user = $request->user();
        $tenantId = $this->tenantIdFor($user);
        abort_unless($tenantId > 0, 422);
        $data['is_enabled'] = $request->boolean('is_enabled');
        $this->desk->saveGateway($user, $tenantId, $data['channel'], $data);

        return back()->with('status', 'Gateway settings saved.');
    }

    public function testGateway(Request $request, CommunicationGateway $communication_gateway): RedirectResponse
    {
        $result = $this->desk->testGateway($request->user(), $communication_gateway);

        return back()->with('status', $result['message'] ?? 'Test completed.');
    }

    public function preferences(Request $request): View
    {
        abort_unless($this->desk->canConfigureAlertPreferences($request->user()), 403);
        $tenantId = $this->tenantIdFor($request->user());
        $role = $request->string('role', RoleName::Manager->value)->toString();
        $prefs = RoleAlertPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('role_name', $role)
            ->get()
            ->keyBy('alert_category');

        return view('notifications.preferences', [
            'tenantId' => $tenantId,
            'role' => $role,
            'categories' => RoleAlertPreference::CATEGORIES,
            'defaults' => RoleAlertPreference::DEFAULT_ROLES,
            'prefs' => $prefs,
            'roles' => [
                RoleName::Admin->value, RoleName::Manager->value, RoleName::Supervisor->value,
                RoleName::Gatekeeper->value, RoleName::Accountant->value,
            ],
        ]);
    }

    public function storePreferences(Request $request): RedirectResponse
    {
        abort_unless($this->desk->canConfigureAlertPreferences($request->user()), 403);
        $data = $request->validate([
            'role_name' => ['required', 'string'],
            'categories' => ['required', 'array'],
        ]);
        $tenantId = $this->tenantIdFor($request->user());
        abort_unless($tenantId > 0, 422);
        $this->desk->saveAlertPreferences($request->user(), $tenantId, $data['role_name'], $data['categories']);

        return back()->with('status', 'Alert preferences saved.');
    }

    public function compose(Request $request): View
    {
        abort_unless($this->desk->canSendCustomerMessage($request->user()), 403);
        $user = $request->user();

        return view('notifications.compose', [
            'customers' => Customer::query()->visibleTo($user)->orderBy('name')->limit(100)->get(),
            'templates' => MessageTemplate::query()->visibleTo($user)->where('is_active', true)->orderBy('name')->get(),
            'desk' => $this->desk,
        ]);
    }

    public function sendCustomer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer'],
            'channel' => ['required', 'in:email,sms,whatsapp'],
            'message_template_id' => ['nullable', 'integer'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);
        $customer = Customer::query()->findOrFail($data['customer_id']);
        $this->desk->sendCustomerMessage($request->user(), $customer, $data);

        return back()->with('status', 'Message submitted.');
    }

    public function announcements(Request $request): View
    {
        abort_unless($this->desk->canBroadcastAnnouncement($request->user()), 403);
        $user = $request->user();

        return view('notifications.announcements', [
            'announcements' => StaffAnnouncement::query()->visibleTo($user)->latest()->limit(40)->get(),
            'branches' => $this->branchesFor($user),
            'desk' => $this->desk,
        ]);
    }

    public function storeAnnouncement(Request $request, Branch $branch): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string'],
        ]);
        $this->desk->broadcastAnnouncement($request->user(), $branch, $data);

        return back()->with('status', 'Announcement published.');
    }

    public function logs(Request $request): View
    {
        abort_unless($this->desk->canViewMessageLog($request->user()), 403);
        $user = $request->user();
        $query = MessageLog::query()->visibleTo($user)->latest();
        if ($request->filled('channel')) {
            $query->where('channel', $request->string('channel'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->date('to'));
        }

        return view('notifications.logs', [
            'logs' => $query->limit(100)->get(),
            'desk' => $this->desk,
        ]);
    }

    public function markRead(InternalAlert $internal_alert): RedirectResponse
    {
        $this->desk->markAlertRead(request()->user(), $internal_alert);

        return back();
    }

    private function branchesFor($user)
    {
        if ($user->isSuperAdmin() || $user->hasRole(RoleName::Admin)) {
            return Branch::query()->orderBy('name')->get();
        }

        return $user->branches()->orderBy('name')->get();
    }

    private function tenantIdFor($user): int
    {
        if ($user->tenant_id) {
            return (int) $user->tenant_id;
        }

        return (int) Branch::query()->value('tenant_id');
    }
}

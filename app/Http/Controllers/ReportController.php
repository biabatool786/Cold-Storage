<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $user = request()->user();

        if ($user?->isSuperAdmin() && $user->hasPermission(PermissionName::PlatformReports)) {
            return view('reports.platform', [
                'tenants' => Tenant::query()->count(),
                'users' => User::query()->count(),
                'activeSubscriptions' => Subscription::query()->where('status', SubscriptionStatus::Active)->count(),
                'plans' => Plan::query()->count(),
            ]);
        }

        abort_unless($user?->hasPermission(PermissionName::ReportsView), 403);

        return view('workspace.show', [
            'page' => [
                'label' => 'Reports',
                'description' => 'Summaries for this cold store. No report rows are stored yet.',
                'area' => 'reports',
                'permission' => PermissionName::ReportsView,
            ],
        ]);
    }
}

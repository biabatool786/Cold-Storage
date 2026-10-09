<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Services\Audit\AuditLogger;
use App\Support\WorkspaceCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    public function show(string $area): View
    {
        $page = WorkspaceCatalog::find($area);
        abort_unless($page && request()->user()?->hasPermission($page['permission']), 403);

        return view('workspace.show', ['page' => $page]);
    }

    public function acknowledge(AuditLogger $audit): RedirectResponse
    {
        abort_unless(request()->user()?->hasPermission(PermissionName::MonitoringAcknowledge), 403);

        $audit->record('monitoring.acknowledged', null, [], request()->user()->tenant_id);

        return back()->with('status', 'No alerts are waiting to acknowledge.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLimit;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalLimitController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ApprovalLimit::class);

        return view('approval-limits.index', [
            'limits' => ApprovalLimit::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ApprovalLimit $approvalLimit, AuditLogger $audit): RedirectResponse
    {
        $this->authorize('update', $approvalLimit);

        $data = $request->validate([
            'manager_limit' => ['required', 'numeric', 'min:0'],
        ]);

        $old = $approvalLimit->manager_limit;
        $approvalLimit->update(['manager_limit' => $data['manager_limit']]);

        $audit->record('approval_limit.updated', $approvalLimit, [], null, [
            'module' => 'approval_limits',
            'old' => ['manager_limit' => $old],
            'new' => ['manager_limit' => $approvalLimit->manager_limit],
        ]);

        return back()->with('status', 'Approval limit saved.');
    }
}

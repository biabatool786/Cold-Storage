<?php

namespace App\Http\Controllers;

use App\Enums\PermissionName;
use App\Models\AuditLog;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(): View
    {
        abort_unless(request()->user()?->hasPermission(PermissionName::AuditView), 403);

        return view('audit.index', [
            'logs' => AuditLog::query()->with(['user', 'tenant'])->latest()->paginate(20),
        ]);
    }
}

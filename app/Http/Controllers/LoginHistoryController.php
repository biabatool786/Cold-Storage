<?php

namespace App\Http\Controllers;

use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LoginHistoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->hasPermission('login_history.view'), 403);

        return view('security.login-history', [
            'events' => $this->query($request->user())->latest('id')->paginate(30),
            'canExport' => $request->user()->hasPermission('login_history.print'),
        ]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        abort_unless($request->user()?->hasPermission('login_history.print'), 403);
        $audit->record('login_history.exported', null, [], $request->user()->tenant_id, ['module' => 'login_history']);

        return response()->streamDownload(function () use ($request): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['When', 'User', 'Email', 'Action', 'IP', 'Device']);
            foreach ($this->query($request->user())->with('user')->latest('id')->limit(1000)->get() as $event) {
                fputcsv($out, [
                    $event->created_at,
                    $event->user?->name,
                    $event->email,
                    $event->action,
                    $event->ip_address,
                    $event->user_agent,
                ]);
            }
            fclose($out);
        }, 'login-history.csv', ['Content-Type' => 'text/csv']);
    }

    private function query(User $actor)
    {
        $query = LoginEvent::query()->with('user');
        if ($actor->isSuperAdmin()) {
            return $query;
        }

        $query->where('tenant_id', $actor->tenant_id);
        if (! $actor->hasRole(\App\Enums\RoleName::Admin)) {
            $ids = User::query()->whereHas('branches', fn ($inner) => $inner->whereIn('branches.id', $actor->branches()->pluck('branches.id')))->pluck('id');
            $query->whereIn('user_id', $ids);
        }

        return $query;
    }
}

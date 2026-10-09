<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\DashboardData;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardData $dashboard): View
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles', 'tenant');

        return view('dashboard', [
            'user' => $user,
            'dashboard' => $dashboard->for($user, $request->string('status')->toString()),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $tenants = Tenant::withCount(['users', 'customers', 'orders'])
            ->with('subscription')
            ->latest()
            ->get();

        $stats = [
            'tenants' => Tenant::count(),
            'users' => User::count(),
            // Superadmin bypasses the tenant scope, so this is platform-wide.
            'orders' => Order::count(),
        ];

        return view('superadmin.dashboard', compact('tenants', 'stats'));
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant (store) routes require a completed onboarding (tenant_id set).
 * Superadmins are redirected to their own panel.
 */
class EnsureTenantOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSuperadmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        if ($user && ! $user->hasTenant()) {
            return redirect()->route('onboarding.create');
        }

        return $next($request);
    }
}

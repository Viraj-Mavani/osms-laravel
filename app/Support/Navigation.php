<?php

namespace App\Support;

use App\Models\User;

class Navigation
{
    /**
     * Where a freshly authenticated user should land.
     */
    public static function homeFor(?User $user): string
    {
        if (! $user) {
            return route('login');
        }

        if ($user->isSuperadmin()) {
            return route('superadmin.dashboard');
        }

        if (! $user->hasTenant()) {
            return route('onboarding.create');
        }

        return route('tenant.dashboard');
    }
}

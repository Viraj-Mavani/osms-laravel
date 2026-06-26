<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Application-layer equivalent of Supabase Row-Level Security.
 *
 * Every query on a tenant-owned model is automatically constrained to the
 * authenticated user's tenant_id. Superadmins bypass the scope so they can
 * operate across all stores from the platform panel.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();

        if (! $user) {
            return; // unauthenticated (e.g. console, jobs) — caller is responsible
        }

        if ($user->role === 'superadmin') {
            return; // platform admin sees everything
        }

        if ($user->tenant_id) {
            $builder->where($model->getTable() . '.tenant_id', $user->tenant_id);
        }
    }
}

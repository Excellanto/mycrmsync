<?php

namespace App\Http\Controllers\Api\Crm\Concerns;

use App\Integrations\CrmApiClientResolver;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait ResolvesCrmApiContext
{
    /**
     * @return array{user: User, tenant: Tenant}
     */
    protected function resolveCrmApiContext(Request $request): array
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $authenticated = $request->user();
        if (! $authenticated instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        if ((int) $data['user_id'] !== $authenticated->id) {
            throw ValidationException::withMessages([
                'user_id' => ['The user_id does not match the authenticated user.'],
            ]);
        }

        $user = User::query()
            ->with('tenant')
            ->whereKey($authenticated->id)
            ->firstOrFail();

        $tenant = $user->tenant;

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not linked to a tenant.'],
            ]);
        }

        $tenantIntegration = is_array($tenant->integration) ? $tenant->integration : [];

        if (! CrmApiClientResolver::isSupportedTenant($tenant)) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user tenant is not configured for a supported CRM API integration.'],
            ]);
        }

        return ['user' => $user, 'tenant' => $tenant];
    }
}

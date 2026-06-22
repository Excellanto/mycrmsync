<?php

namespace App\Integrations\Connectors\MyCrmSync;

use App\Integrations\Contracts\CrmIntegrationUsersConnector;
use App\Integrations\MysimconnectApi\CrmExternalUserResource;
use App\Integrations\MysimconnectApi\MappedCrmUsersFetchResult;
use App\Models\Tenant;
use App\Models\User;

/**
 * MyCrmSync uses local MysimConnect users as CRM users (no external API).
 */
final class MyCrmSyncCrmUsersConnector implements CrmIntegrationUsersConnector
{
    public static function integrationSlug(): string
    {
        return 'mycrmsync';
    }

    public function fetchMappedUsers(array $tenantIntegration, ?Tenant $tenant = null): MappedCrmUsersFetchResult
    {
        if ($tenant === null) {
            return new MappedCrmUsersFetchResult(
                [],
                'Tenant context is required for MyCrmSync user listing.',
                422,
            );
        }

        $users = User::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $mapped = [];

        foreach ($users as $user) {
            $mapped[] = new CrmExternalUserResource(
                id: (string) $user->id,
                name: trim($user->name) !== '' ? trim($user->name) : '(no name)',
                email: trim((string) $user->email),
                phone: '',
                role: '',
            );
        }

        return new MappedCrmUsersFetchResult(
            $mapped,
            $mapped === [] ? 'No users found for this tenant.' : null,
            200,
        );
    }
}

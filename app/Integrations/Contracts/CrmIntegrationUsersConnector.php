<?php

namespace App\Integrations\Contracts;

use App\Integrations\MysimconnectApi\MappedCrmUsersFetchResult;
use App\Models\Tenant;

interface CrmIntegrationUsersConnector
{
    /**
     * Slug from {@see \App\Models\Integration::$slug} stored on {@see \App\Models\Tenant::$integration}.
     */
    public static function integrationSlug(): string;

    /**
     * @param  array{slug?: string, values?: array<string, mixed>}  $tenantIntegration  Persisted tenants.integration JSON
     */
    public function fetchMappedUsers(array $tenantIntegration, ?Tenant $tenant = null): MappedCrmUsersFetchResult;
}

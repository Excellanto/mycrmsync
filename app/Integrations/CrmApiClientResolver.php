<?php

namespace App\Integrations;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelCrmUsersConnector;
use App\Integrations\Connectors\MyCrmSync\MyCrmSyncCrmUsersConnector;
use App\Integrations\Connectors\Zoho\ZohoCrmUsersConnector;
use App\Models\Tenant;

final class CrmApiClientResolver
{
    public const SLUG_ZOHO = 'zoho';

    public const SLUG_GOHIGHLEVEL = 'gohighlevel';

    public const SLUG_MYCRMSYNC = 'mycrmsync';

    /**
     * @return list<string>
     */
    public static function supportedSlugs(): array
    {
        return [
            self::SLUG_GOHIGHLEVEL,
            self::SLUG_ZOHO,
            self::SLUG_MYCRMSYNC,
        ];
    }

    public static function slugForTenant(Tenant $tenant): string
    {
        $integration = is_array($tenant->integration) ? $tenant->integration : [];

        return (string) ($integration['slug'] ?? '');
    }

    public static function isSupportedTenant(Tenant $tenant): bool
    {
        return in_array(self::slugForTenant($tenant), self::supportedSlugs(), true);
    }

    public static function isZohoTenant(Tenant $tenant): bool
    {
        return self::slugForTenant($tenant) === ZohoCrmUsersConnector::integrationSlug();
    }

    public static function isGoHighLevelTenant(Tenant $tenant): bool
    {
        return self::slugForTenant($tenant) === GoHighLevelCrmUsersConnector::integrationSlug();
    }

    public static function isMyCrmSyncTenant(Tenant $tenant): bool
    {
        return self::slugForTenant($tenant) === MyCrmSyncCrmUsersConnector::integrationSlug();
    }
}

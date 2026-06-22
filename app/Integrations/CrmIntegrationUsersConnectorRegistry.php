<?php

namespace App\Integrations;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelCrmUsersConnector;
use App\Integrations\Connectors\MyCrmSync\MyCrmSyncCrmUsersConnector;
use App\Integrations\Connectors\Zoho\ZohoCrmUsersConnector;
use App\Integrations\Contracts\CrmIntegrationUsersConnector;

final class CrmIntegrationUsersConnectorRegistry
{
    /** @var array<string, class-string<CrmIntegrationUsersConnector>> */
    private const CONNECTORS = [
        'gohighlevel' => GoHighLevelCrmUsersConnector::class,
        'zoho' => ZohoCrmUsersConnector::class,
        'mycrmsync' => MyCrmSyncCrmUsersConnector::class,
    ];

    public function connectorFor(string $integrationSlug): ?CrmIntegrationUsersConnector
    {
        $class = self::CONNECTORS[$integrationSlug] ?? null;

        if ($class === null) {
            return null;
        }

        return app($class);
    }

    /**
     * @return list<string>
     */
    public static function registeredSlugs(): array
    {
        return array_keys(self::CONNECTORS);
    }
}

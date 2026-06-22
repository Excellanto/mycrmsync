<?php

namespace Database\Seeders;

use App\Models\Integration;
use Illuminate\Database\Seeder;

class IntegrationSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'name' => 'Salesforce',
                'type' => 'CRM',
                'documentation' => 'https://developer.salesforce.com/docs',
                'enabled' => true,
                'is_system' => true,
            ],
            [
                'name' => 'HubSpot',
                'type' => 'CRM',
                'documentation' => 'https://developers.hubspot.com/docs/api/crm/contacts',
                'enabled' => true,
                'is_system' => true,
            ],
            [
                'name' => 'Zoho',
                'type' => 'CRM',
                'documentation' => 'https://www.zoho.com/crm/developer/docs/api/v2/',
                'enabled' => true,
                'is_system' => true,
            ],
            [
                'name' => 'GoHighLevel',
                'type' => 'CRM',
                'documentation' => 'https://marketplace.gohighlevel.com/docs/ghl/',
                'enabled' => true,
                'is_system' => true,
            ],
            [
                'name' => 'Pipedrive',
                'type' => 'CRM',
                'documentation' => 'https://developers.pipedrive.com/docs/api/v1/',
                'enabled' => true,
                'is_system' => true,
            ],
            [
                'name' => 'Freshsales',
                'type' => 'CRM',
                'documentation' => 'https://developers.freshworks.com/crm/api/',
                'enabled' => true,
                'is_system' => true,
            ],
            [
                'name' => 'MyCrmSync',
                'type' => 'CRM',
                'documentation' => '',
                'enabled' => true,
                'is_system' => true,
            ],
        ];

        foreach ($defaults as $row) {
            Integration::query()->firstOrCreate(
                ['name' => $row['name']],
                [
                    'type' => $row['type'],
                    'documentation' => $row['documentation'],
                    'enabled' => $row['enabled'],
                    'is_system' => $row['is_system'],
                ]
            );
        }
    }
}

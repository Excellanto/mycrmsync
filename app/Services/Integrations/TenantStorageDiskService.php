<?php

namespace App\Services\Integrations;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class TenantStorageDiskService
{
    public static function diskForTenant(int $tenantId): Filesystem
    {
        $config = StorageConfigService::forTenant($tenantId);

        if ($config->activeProvider() !== StorageConfigService::PROVIDER_R2) {
            return Storage::disk('r2');
        }

        $r2 = $config->r2DiskConfig();
        if ($r2 === null) {
            return Storage::disk('r2');
        }

        return Storage::build([
            'driver' => 's3',
            'key' => $r2['key'],
            'secret' => $r2['secret'],
            'region' => $r2['region'] ?? 'auto',
            'bucket' => $r2['bucket'],
            'endpoint' => $r2['endpoint'],
            'url' => $r2['url'],
            'use_path_style_endpoint' => false,
            'throw' => false,
        ]);
    }
}

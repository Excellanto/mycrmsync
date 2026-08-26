<?php

namespace App\Services\Integrations;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class TenantStorageDiskService
{
    public static function diskForTenant(int $tenantId, ?string $provider = null): Filesystem
    {
        $config = StorageConfigService::forTenant($tenantId);
        $provider ??= $config->activeProvider();

        if ($provider === StorageConfigService::PROVIDER_SUPABASE) {
            $supabase = $config->supabaseDiskConfig();
            if ($supabase !== null) {
                return self::buildS3Disk($supabase, pathStyle: true);
            }
        }

        if ($provider === StorageConfigService::PROVIDER_R2) {
            $r2 = $config->r2DiskConfig();
            if ($r2 !== null) {
                return self::buildS3Disk($r2, pathStyle: false);
            }
        }

        return Storage::disk('r2');
    }

    /**
     * @param  array<string, string|null>  $disk
     */
    private static function buildS3Disk(array $disk, bool $pathStyle): Filesystem
    {
        return Storage::build([
            'driver' => 's3',
            'key' => $disk['key'],
            'secret' => $disk['secret'],
            'region' => $disk['region'] ?? 'auto',
            'bucket' => $disk['bucket'],
            'endpoint' => $disk['endpoint'],
            'url' => $disk['url'],
            'use_path_style_endpoint' => $pathStyle,
            'visibility' => 'private',
            'throw' => false,
            'requestChecksumCalculation' => 'when_required',
            'responseChecksumValidation' => 'when_required',
        ]);
    }
}

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
     * Write, read-check, and delete a tiny probe object to confirm S3-compatible credentials.
     *
     * @param  array<string, string|null>  $disk
     *
     * @throws \Throwable
     */
    public static function verifyS3Disk(array $disk, bool $pathStyle = true): void
    {
        $filesystem = self::buildS3Disk($disk, pathStyle: $pathStyle, throw: true);
        $path = 'mysimconnect-verify/'.bin2hex(random_bytes(8)).'.txt';
        $payload = 'mysimconnect-storage-verify-'.now()->toIso8601String();

        try {
            if (! $filesystem->put($path, $payload)) {
                throw new \RuntimeException('Could not upload a test file to the storage bucket.');
            }

            if (! $filesystem->exists($path)) {
                throw new \RuntimeException('Uploaded a test file but could not confirm it exists in the bucket.');
            }
        } finally {
            try {
                $filesystem->delete($path);
            } catch (\Throwable) {
                // Best-effort cleanup; verification already succeeded or failed above.
            }
        }
    }

    /**
     * @param  array<string, string|null>  $disk
     */
    public static function buildS3Disk(array $disk, bool $pathStyle, bool $throw = false): Filesystem
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
            'throw' => $throw,
            'requestChecksumCalculation' => 'when_required',
            'responseChecksumValidation' => 'when_required',
        ]);
    }
}

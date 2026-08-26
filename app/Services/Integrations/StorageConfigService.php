<?php

namespace App\Services\Integrations;

use App\Models\TenantSetting;

class StorageConfigService
{
    public const PROVIDER_SUPABASE = 'supabase';

    public const PROVIDER_GOOGLE_DRIVE = 'google_drive';

    public const PROVIDER_DROPBOX = 'dropbox';

    public const PROVIDER_R2 = 'r2';

    public const SYSTEM_KEY_SUPABASE_URL = 'storage.supabase.url';

    public const SYSTEM_KEY_SUPABASE_KEY = 'storage.supabase.key';

    public const SYSTEM_KEY_SUPABASE_ACCESS_KEY = 'storage.supabase.access_key';

    public const SYSTEM_KEY_SUPABASE_SECRET_KEY = 'storage.supabase.secret_key';

    public const SYSTEM_KEY_SUPABASE_REGION = 'storage.supabase.region';

    public const SYSTEM_KEY_SUPABASE_BUCKET = 'storage.supabase.bucket';

    /** @var list<string> */
    public const PROVIDERS = [
        self::PROVIDER_SUPABASE,
        self::PROVIDER_GOOGLE_DRIVE,
        self::PROVIDER_DROPBOX,
        self::PROVIDER_R2,
    ];

    public function __construct(private readonly int $tenantId) {}

    public static function forTenant(int $tenantId): self
    {
        return new self($tenantId);
    }

    public function defaultProvider(): ?string
    {
        $value = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_DEFAULT_PROVIDER);

        if (! is_string($value) || ! in_array($value, self::PROVIDERS, true)) {
            return null;
        }

        return $this->isProviderConfigured($value) ? $value : null;
    }

    public function activeProvider(): ?string
    {
        $default = $this->defaultProvider();
        if ($default !== null) {
            return $default;
        }

        $configured = array_values(array_filter(self::PROVIDERS, fn (string $p) => $this->isProviderConfigured($p)));
        if (count($configured) === 1) {
            return $configured[0];
        }

        if ($this->isProviderConfigured(self::PROVIDER_SUPABASE)) {
            return self::PROVIDER_SUPABASE;
        }

        return $configured[0] ?? null;
    }

    public function isProviderConfigured(string $provider): bool
    {
        return match ($provider) {
            self::PROVIDER_SUPABASE => $this->hasSupabaseS3Config(),
            self::PROVIDER_GOOGLE_DRIVE => TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_GOOGLE_DRIVE_CLIENT_ID)
                && TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_GOOGLE_DRIVE_CLIENT_SECRET),
            self::PROVIDER_DROPBOX => TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_DROPBOX_APP_KEY)
                && TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_DROPBOX_APP_SECRET),
            self::PROVIDER_R2 => self::systemHasR2DiskConfig() && $this->r2PublicUrl() !== null,
            default => false,
        };
    }

    public function hasTenantR2PublicUrl(): bool
    {
        return TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_R2_PUBLIC_URL);
    }

    public function usesSystemR2PublicUrlFallback(): bool
    {
        return ! $this->hasTenantR2PublicUrl() && self::systemHasR2PublicUrl();
    }

    public function r2PublicUrl(): ?string
    {
        $value = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_R2_PUBLIC_URL);
        if (is_string($value) && trim($value) !== '') {
            return rtrim(trim($value), '/');
        }

        return self::systemR2PublicUrl();
    }

    public function r2PublicFileUrl(string $path): ?string
    {
        $base = $this->r2PublicUrl();
        if ($base === null || $path === '') {
            return null;
        }

        return $base.'/'.ltrim($path, '/');
    }

    public static function systemHasR2PublicUrl(): bool
    {
        return self::systemR2PublicUrl() !== null;
    }

    public static function systemR2PublicUrl(): ?string
    {
        $fromSettings = settings('storage.r2.public_url');
        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return rtrim(trim($fromSettings), '/');
        }

        $fromEnv = config('filesystems.disks.r2.url');

        return is_string($fromEnv) && trim($fromEnv) !== '' ? rtrim(trim($fromEnv), '/') : null;
    }

    public static function systemHasR2DiskConfig(): bool
    {
        $disk = config('filesystems.disks.r2', []);

        return is_string($disk['key'] ?? null) && trim($disk['key']) !== ''
            && is_string($disk['secret'] ?? null) && trim($disk['secret']) !== ''
            && is_string($disk['bucket'] ?? null) && trim($disk['bucket']) !== ''
            && is_string($disk['endpoint'] ?? null) && trim($disk['endpoint']) !== '';
    }

    /**
     * @return array<string, string|null>|null
     */
    public function r2DiskConfig(): ?array
    {
        if (! $this->isProviderConfigured(self::PROVIDER_R2)) {
            return null;
        }

        $disk = config('filesystems.disks.r2', []);

        return [
            'key' => $disk['key'] ?? null,
            'secret' => $disk['secret'] ?? null,
            'bucket' => $disk['bucket'] ?? null,
            'endpoint' => $disk['endpoint'] ?? null,
            'url' => $this->r2PublicUrl(),
            'region' => $disk['region'] ?? 'auto',
        ];
    }

    public function isProviderDefault(string $provider): bool
    {
        return $this->defaultProvider() === $provider;
    }

    public function usesSystemSupabaseFallback(): bool
    {
        return ! $this->hasTenantSupabaseConfig() && self::systemHasSupabaseConfig();
    }

    public function hasTenantSupabaseConfig(): bool
    {
        return TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_URL)
            && TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_BUCKET)
            && TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_ACCESS_KEY)
            && TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_SECRET_KEY);
    }

    public function supabaseUrl(): ?string
    {
        return $this->firstNonEmpty(
            TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_URL),
            self::systemSupabaseUrl()
        );
    }

    public function supabaseKey(): ?string
    {
        $tenant = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_KEY);
        if (is_string($tenant) && $tenant !== '') {
            return $tenant;
        }

        return self::systemSupabaseKey();
    }

    public function supabaseAccessKey(): ?string
    {
        return $this->firstNonEmpty(
            TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_ACCESS_KEY),
            self::systemSupabaseAccessKey()
        );
    }

    public function supabaseSecretKey(): ?string
    {
        $tenant = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_SECRET_KEY);
        if (is_string($tenant) && $tenant !== '') {
            return $tenant;
        }

        return self::systemSupabaseSecretKey();
    }

    public function supabaseRegion(): string
    {
        return $this->firstNonEmpty(
            TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_REGION),
            self::systemSupabaseRegion()
        ) ?? 'us-east-1';
    }

    public function supabaseBucket(): ?string
    {
        return $this->firstNonEmpty(
            TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_BUCKET),
            self::systemSupabaseBucket()
        );
    }

    public function hasSupabaseS3Config(): bool
    {
        return $this->supabaseUrl() !== null
            && $this->supabaseAccessKey() !== null
            && $this->supabaseSecretKey() !== null
            && $this->supabaseBucket() !== null;
    }

    public function hasSupabaseVoiceNoteStorage(): bool
    {
        return $this->hasSupabaseS3Config()
            || ($this->supabaseUrl() !== null && $this->supabaseKey() !== null);
    }

    public function voiceNoteStorageProvider(): ?string
    {
        if ($this->hasSupabaseVoiceNoteStorage()) {
            return self::PROVIDER_SUPABASE;
        }

        if ($this->isProviderConfigured(self::PROVIDER_R2)) {
            return self::PROVIDER_R2;
        }

        return null;
    }

    public function supabaseS3Endpoint(): ?string
    {
        $url = $this->supabaseUrl();
        if ($url === null) {
            return null;
        }

        return self::s3EndpointFromProjectUrl($url);
    }

    public static function s3EndpointFromProjectUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        if (str_ends_with($url, '/storage/v1/s3')) {
            return $url;
        }

        $parts = parse_url($url) ?: [];
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        if ($host !== '' && str_contains($host, '.storage.supabase.co')) {
            return "{$scheme}://{$host}{$port}/storage/v1/s3";
        }

        if ($host !== '' && preg_match('/^([a-z0-9-]+)\.supabase\.co$/i', $host, $matches) === 1) {
            return "{$scheme}://{$matches[1]}.storage.supabase.co{$port}/storage/v1/s3";
        }

        return $url.'/storage/v1/s3';
    }

    /**
     * @return array<string, string>|null
     */
    public function supabaseDiskConfig(): ?array
    {
        if (! $this->hasSupabaseS3Config()) {
            return null;
        }

        $bucket = (string) $this->supabaseBucket();
        $projectUrl = rtrim((string) $this->supabaseUrl(), '/');

        return [
            'key' => (string) $this->supabaseAccessKey(),
            'secret' => (string) $this->supabaseSecretKey(),
            'region' => $this->supabaseRegion(),
            'bucket' => $bucket,
            'endpoint' => (string) $this->supabaseS3Endpoint(),
            'url' => $projectUrl.'/storage/v1/object/public/'.$bucket,
        ];
    }

    public function supabasePublicFileUrl(string $path): ?string
    {
        $base = $this->supabaseUrl();
        $bucket = $this->supabaseBucket();
        if ($base === null || $bucket === null || $path === '') {
            return null;
        }

        return rtrim($base, '/').'/storage/v1/object/public/'.$bucket.'/'.ltrim($path, '/');
    }

    public function publicFileUrl(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        return match ($this->activeProvider()) {
            self::PROVIDER_SUPABASE => $this->supabasePublicFileUrl($path),
            default => $this->r2PublicFileUrl($path),
        };
    }

    public function voicenotesBucket(): string
    {
        $configured = $this->supabaseBucket();

        return $configured ?? self::systemVoicenotesBucket();
    }

    public static function systemVoicenotesBucket(): string
    {
        $fromEnv = config('services.supabase.voicenotes_bucket', 'voicenotes');

        return is_string($fromEnv) && trim($fromEnv) !== '' ? trim($fromEnv) : 'voicenotes';
    }

    public static function systemHasSupabaseConfig(): bool
    {
        return self::systemSupabaseUrl() !== null
            && self::systemSupabaseAccessKey() !== null
            && self::systemSupabaseSecretKey() !== null
            && self::systemSupabaseBucket() !== null;
    }

    public static function systemSupabaseUrl(): ?string
    {
        return self::systemString(self::SYSTEM_KEY_SUPABASE_URL, 'services.supabase.url');
    }

    public static function systemSupabaseKey(): ?string
    {
        return self::systemString(self::SYSTEM_KEY_SUPABASE_KEY, 'services.supabase.key');
    }

    public static function systemSupabaseAccessKey(): ?string
    {
        return self::systemString(self::SYSTEM_KEY_SUPABASE_ACCESS_KEY, 'services.supabase.access_key');
    }

    public static function systemSupabaseSecretKey(): ?string
    {
        return self::systemString(self::SYSTEM_KEY_SUPABASE_SECRET_KEY, 'services.supabase.secret_key');
    }

    public static function systemSupabaseRegion(): ?string
    {
        return self::systemString(self::SYSTEM_KEY_SUPABASE_REGION, 'services.supabase.region');
    }

    public static function systemSupabaseBucket(): ?string
    {
        return self::systemString(self::SYSTEM_KEY_SUPABASE_BUCKET, 'services.supabase.bucket');
    }

    /**
     * @return array<string, mixed>
     */
    public function formPayload(): array
    {
        $default = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_DEFAULT_PROVIDER);

        return [
            'default_provider' => is_string($default) && in_array($default, self::PROVIDERS, true) ? $default : null,
            'active_provider' => $this->activeProvider(),
            'providers' => [
                self::PROVIDER_SUPABASE => [
                    'url' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_URL)
                        ?? self::systemSupabaseUrl()
                        ?? '',
                    'access_key' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_ACCESS_KEY)
                        ?? self::systemSupabaseAccessKey()
                        ?? '',
                    'region' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_REGION)
                        ?? self::systemSupabaseRegion()
                        ?? '',
                    'bucket' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_BUCKET)
                        ?? self::systemSupabaseBucket()
                        ?? '',
                    'has_secret' => $this->supabaseSecretKey() !== null,
                    'has_legacy_key' => TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_KEY)
                        && $this->supabaseSecretKey() === null,
                    'using_system_fallback' => $this->usesSystemSupabaseFallback(),
                    'system_has_config' => self::systemHasSupabaseConfig(),
                    'is_configured' => $this->isProviderConfigured(self::PROVIDER_SUPABASE),
                    'is_default' => $default === self::PROVIDER_SUPABASE,
                ],
                self::PROVIDER_GOOGLE_DRIVE => [
                    'client_id' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_GOOGLE_DRIVE_CLIENT_ID, '') ?? '',
                    'folder_id' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_GOOGLE_DRIVE_FOLDER_ID, '') ?? '',
                    'has_client_secret' => TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_GOOGLE_DRIVE_CLIENT_SECRET),
                    'is_configured' => $this->isProviderConfigured(self::PROVIDER_GOOGLE_DRIVE),
                    'is_default' => $default === self::PROVIDER_GOOGLE_DRIVE,
                ],
                self::PROVIDER_DROPBOX => [
                    'app_key' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_DROPBOX_APP_KEY, '') ?? '',
                    'has_app_secret' => TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_DROPBOX_APP_SECRET),
                    'has_refresh_token' => TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_DROPBOX_REFRESH_TOKEN),
                    'is_configured' => $this->isProviderConfigured(self::PROVIDER_DROPBOX),
                    'is_default' => $default === self::PROVIDER_DROPBOX,
                ],
                self::PROVIDER_R2 => [
                    'public_url' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_R2_PUBLIC_URL)
                        ?? self::systemR2PublicUrl()
                        ?? '',
                    'has_public_url' => $this->hasTenantR2PublicUrl(),
                    'using_system_public_url' => $this->usesSystemR2PublicUrlFallback(),
                    'system_has_public_url' => self::systemHasR2PublicUrl(),
                    'system_has_disk_config' => self::systemHasR2DiskConfig(),
                    'is_configured' => $this->isProviderConfigured(self::PROVIDER_R2),
                    'is_default' => $default === self::PROVIDER_R2,
                ],
            ],
        ];
    }

    private function firstNonEmpty(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private static function systemString(string $settingsKey, string $configKey): ?string
    {
        $fromSettings = settings($settingsKey);
        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return trim($fromSettings);
        }

        $fromEnv = config($configKey);

        return is_string($fromEnv) && trim($fromEnv) !== '' ? trim($fromEnv) : null;
    }
}

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
            self::PROVIDER_SUPABASE => $this->supabaseUrl() !== null
                && $this->supabaseKey() !== null
                && $this->supabaseBucket() !== null,
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
            && TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_KEY)
            && TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_BUCKET);
    }

    public function supabaseUrl(): ?string
    {
        $tenant = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_URL);
        if (is_string($tenant) && trim($tenant) !== '') {
            return trim($tenant);
        }

        return self::systemSupabaseUrl();
    }

    public function supabaseKey(): ?string
    {
        $tenant = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_KEY);
        if (is_string($tenant) && $tenant !== '') {
            return $tenant;
        }

        return self::systemSupabaseKey();
    }

    public function supabaseBucket(): ?string
    {
        $tenant = TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_BUCKET);
        if (is_string($tenant) && trim($tenant) !== '') {
            return trim($tenant);
        }

        return self::systemSupabaseBucket();
    }

    public function hasSupabaseVoiceNoteStorage(): bool
    {
        return $this->supabaseUrl() !== null && $this->supabaseKey() !== null;
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
            && self::systemSupabaseKey() !== null
            && self::systemSupabaseBucket() !== null;
    }

    public static function systemSupabaseUrl(): ?string
    {
        $fromSettings = settings(self::SYSTEM_KEY_SUPABASE_URL);
        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return trim($fromSettings);
        }

        $fromEnv = config('services.supabase.url');

        return is_string($fromEnv) && trim($fromEnv) !== '' ? trim($fromEnv) : null;
    }

    public static function systemSupabaseKey(): ?string
    {
        $fromSettings = settings(self::SYSTEM_KEY_SUPABASE_KEY);
        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return trim($fromSettings);
        }

        $fromEnv = config('services.supabase.key');

        return is_string($fromEnv) && trim($fromEnv) !== '' ? trim($fromEnv) : null;
    }

    public static function systemSupabaseBucket(): ?string
    {
        $fromSettings = settings(self::SYSTEM_KEY_SUPABASE_BUCKET);
        if (is_string($fromSettings) && trim($fromSettings) !== '') {
            return trim($fromSettings);
        }

        $fromEnv = config('services.supabase.bucket');

        return is_string($fromEnv) && trim($fromEnv) !== '' ? trim($fromEnv) : null;
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
                    'bucket' => TenantSetting::getValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_BUCKET)
                        ?? self::systemSupabaseBucket()
                        ?? '',
                    'has_key' => TenantSetting::hasValue($this->tenantId, TenantSetting::KEY_STORAGE_SUPABASE_KEY),
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class TenantSetting extends Model
{
    public const KEY_OPENAI_API_KEY = 'openai_api_key';

    public const KEY_OPENAI_GPT_MODEL = 'openai_gpt_model';

    public const KEY_OPENAI_WHISPER_MODEL = 'openai_whisper_model';

    public const KEY_STORAGE_DEFAULT_PROVIDER = 'storage_default_provider';

    public const KEY_STORAGE_SUPABASE_URL = 'storage_supabase_url';

    public const KEY_STORAGE_SUPABASE_KEY = 'storage_supabase_key';

    public const KEY_STORAGE_SUPABASE_BUCKET = 'storage_supabase_bucket';

    public const KEY_STORAGE_GOOGLE_DRIVE_CLIENT_ID = 'storage_google_drive_client_id';

    public const KEY_STORAGE_GOOGLE_DRIVE_CLIENT_SECRET = 'storage_google_drive_client_secret';

    public const KEY_STORAGE_GOOGLE_DRIVE_FOLDER_ID = 'storage_google_drive_folder_id';

    public const KEY_STORAGE_DROPBOX_APP_KEY = 'storage_dropbox_app_key';

    public const KEY_STORAGE_DROPBOX_APP_SECRET = 'storage_dropbox_app_secret';

    public const KEY_STORAGE_DROPBOX_REFRESH_TOKEN = 'storage_dropbox_refresh_token';

    public const KEY_STORAGE_R2_ACCOUNT_ID = 'storage_r2_account_id';

    public const KEY_STORAGE_R2_ACCESS_KEY_ID = 'storage_r2_access_key_id';

    public const KEY_STORAGE_R2_SECRET_ACCESS_KEY = 'storage_r2_secret_access_key';

    public const KEY_STORAGE_R2_BUCKET = 'storage_r2_bucket';

    public const KEY_STORAGE_R2_PUBLIC_URL = 'storage_r2_public_url';

    public const KEY_STORAGE_R2_ENDPOINT = 'storage_r2_endpoint';

    public const TYPE_SECRET = 'secret';

    /** @var list<string> */
    public const ENCRYPTED_KEYS = [
        self::KEY_OPENAI_API_KEY,
        self::KEY_STORAGE_SUPABASE_KEY,
        self::KEY_STORAGE_GOOGLE_DRIVE_CLIENT_SECRET,
        self::KEY_STORAGE_DROPBOX_APP_SECRET,
        self::KEY_STORAGE_DROPBOX_REFRESH_TOKEN,
        self::KEY_STORAGE_R2_SECRET_ACCESS_KEY,
    ];

    protected $fillable = [
        'tenant_id',
        'key',
        'value',
        'type',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function getValue(int $tenantId, string $key, mixed $default = null): mixed
    {
        $settings = cache()->remember(
            static::cacheKey($tenantId),
            3600,
            fn () => static::query()
                ->where('tenant_id', $tenantId)
                ->get()
                ->keyBy('key'),
        );

        $row = $settings->get($key);

        if (! $row || $row->value === null || $row->value === '') {
            return $default;
        }

        if ($row->type === self::TYPE_SECRET || in_array($key, self::ENCRYPTED_KEYS, true)) {
            try {
                return Crypt::decryptString($row->value);
            } catch (\Throwable) {
                return $default;
            }
        }

        if ($row->type === 'boolean') {
            return $row->value === '1';
        }

        if ($row->type === 'json') {
            $decoded = json_decode($row->value, true);

            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        }

        return $row->value;
    }

    public static function hasValue(int $tenantId, string $key): bool
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('key', $key)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->exists();
    }

    public static function setValue(int $tenantId, string $key, mixed $value, string $type = 'string'): void
    {
        $stored = $value;

        if ($type === 'boolean') {
            $stored = filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0';
        } elseif ($type === 'json') {
            $stored = is_string($value) ? $value : json_encode($value);
        } elseif ($type === self::TYPE_SECRET || in_array($key, self::ENCRYPTED_KEYS, true)) {
            $stored = $value !== null && $value !== ''
                ? Crypt::encryptString((string) $value)
                : null;
            $type = self::TYPE_SECRET;
        } else {
            $stored = $value === null ? null : (string) $value;
        }

        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => $stored, 'type' => $type]
        );

        cache()->forget(static::cacheKey($tenantId));
    }

    public static function cacheKey(int $tenantId): string
    {
        return "tenant_settings.{$tenantId}";
    }
}

<?php

namespace App\Models;

use App\Services\Integrations\StorageConfigService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Tenant extends Model
{
    public const STATUS_NEW = 'new';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = ['company_name', 'account_type', 'email', 'pan_card', 'gst_number', 'status', 'company_logo_path', 'email_ingestion_enabled', 'integration', 'integration_status'];

    protected $casts = [
        'email_ingestion_enabled' => 'boolean',
        'integration_status' => 'boolean',
        'integration' => 'array',
    ];

    /**
     * Public URL for company logo via R2 Public Development URL (tenant or R2_PUBLIC_URL).
     */
    public function companyLogoUrl(): ?string
    {
        if (! $this->company_logo_path) {
            return null;
        }

        $storage = StorageConfigService::forTenant((int) $this->id);
        $publicUrl = $storage->r2PublicFileUrl($this->company_logo_path);
        if ($publicUrl !== null) {
            return $publicUrl;
        }

        return Storage::disk('r2')->url($this->company_logo_path);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function allowsLogin(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function loginBlockedMessage(): string
    {
        if ($this->status === self::STATUS_NEW) {
            return 'Your account is pending activation. Please contact an administrator.';
        }

        return 'Your account has been suspended or is inactive.';
    }

    // Email ingestion removed: emailAccounts relation deleted.
}

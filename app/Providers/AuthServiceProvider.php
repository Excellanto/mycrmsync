<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\CallLog;
use App\Models\Contact;
use App\Models\EmailTemplate;
use App\Models\Integration;
use App\Models\LanguageString;
use App\Models\Role;
use App\Models\ShortUrl;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Policies\ActivityLogPolicy;
use App\Policies\CallLogPolicy;
use App\Policies\ContactPolicy;
use App\Policies\EmailTemplatePolicy;
use App\Policies\IntegrationPolicy;
use App\Policies\LanguageStringPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SettingPolicy;
use App\Policies\ShortUrlPolicy;
use App\Policies\TenantPolicy;
use App\Policies\TenantSettingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        User::class => UserPolicy::class,
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        SiteSetting::class => SettingPolicy::class,
        LanguageString::class => LanguageStringPolicy::class,
        ActivityLog::class => ActivityLogPolicy::class,
        CallLog::class => CallLogPolicy::class,
        Contact::class => ContactPolicy::class,
        ShortUrl::class => ShortUrlPolicy::class,
        Tenant::class => TenantPolicy::class,
        Integration::class => IntegrationPolicy::class,
        EmailTemplate::class => EmailTemplatePolicy::class,
        TenantSetting::class => TenantSettingPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        // Platform-scoped roles (e.g. Super Admin) pass all permission checks and policies.
        Gate::before(function ($user, $ability) {
            if (! ($user instanceof User)) {
                return null;
            }

            if ($user->isMaster()) {
                return true;
            }

            return null;
        });
    }
}

<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationEvents;
use App\Models\CallRecording;
use App\Models\Contact;
use App\Models\Integration;
use App\Models\LanguageString;
use App\Models\Role;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Observers\CallRecordingObserver;
use App\Observers\ContactObserver;
use App\Observers\IntegrationObserver;
use App\Observers\LanguageStringObserver;
use App\Observers\PermissionObserver;
use App\Observers\RoleObserver;
use App\Observers\SiteSettingObserver;
use App\Observers\TenantObserver;
use App\Observers\UserObserver;
use App\Scribe\WindowsSafeWriter;
// AiSettingsService removed
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Knuckles\Scribe\Writing\Writer;
use Spatie\Permission\Models\Permission;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Writer::class, WindowsSafeWriter::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register observers for activity logging
        User::observe(UserObserver::class);
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);
        SiteSetting::observe(SiteSettingObserver::class);
        LanguageString::observe(LanguageStringObserver::class);
        Contact::observe(ContactObserver::class);
        Integration::observe(IntegrationObserver::class);
        CallRecording::observe(CallRecordingObserver::class);
        Tenant::observe(TenantObserver::class);

        // Register authentication event listeners
        Event::listen(Login::class, [LogAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthenticationEvents::class, 'handleLogout']);
        Event::listen(Failed::class, [LogAuthenticationEvents::class, 'handleFailed']);
    }
}

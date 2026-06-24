<?php

namespace App\Providers;

use App\Listeners\LogAuthenticationEvents;
use App\Models\CallRecording;
use App\Models\Contact;
use App\Models\EmailTemplate;
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
use App\Services\Email\EmailTemplateService;
// AiSettingsService removed
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
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

        ResetPassword::toMailUsing(function (object $notifiable, string $token) {
            $resetUrl = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $expireMinutes = (string) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60
            );

            $rendered = EmailTemplateService::render(EmailTemplate::SLUG_PASSWORD_RECOVERY, [
                'user_name' => (string) ($notifiable->name ?? 'User'),
                'user_email' => (string) $notifiable->getEmailForPasswordReset(),
                'reset_url' => $resetUrl,
                'app_name' => (string) config('app.name'),
                'expires_minutes' => $expireMinutes,
            ]);

            return (new MailMessage)
                ->subject($rendered['subject'])
                ->view('mail.raw-html', ['html' => $rendered['html']]);
        });
    }
}

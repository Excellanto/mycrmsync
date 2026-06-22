<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\ApiEndpointMapperController;
use App\Http\Controllers\DataConfigurationController;
use App\Http\Controllers\IntegrationSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LanguageStringController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleAssignmentRulesController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\LoadTranslations;
// Removed controller imports for AI, Pricing, SystemSettings and EmailIngestion
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('admin.dashboard')
        : redirect()->route('login');
});

Route::view('/docs/mysimconnect-api', 'docs.mysimconnect-api')->name('docs.mysimconnect-api');

Route::get('/surl/{code}', [\App\Http\Controllers\ShortUrlRedirectController::class, 'show'])
    ->where('code', '[A-Za-z0-9]{8}')
    ->name('short-url.redirect');

// Auth routes (Laravel Breeze - provided in routes/auth.php)
if (file_exists(__DIR__.'/auth.php')) {
    require __DIR__.'/auth.php';
}

// Authenticated user routes (same access rules as /admin — keep login redirect consistent)
Route::middleware([
    'auth',
    'verified',
    LoadTranslations::class,
    'permission:admin-panel-access',
    'tenant.active',
])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

// Admin routes
Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified', LoadTranslations::class, 'permission:admin-panel-access', 'tenant.active'])
    ->group(function () {
        // Dashboard alias
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // API Endpoint Mapper
        Route::get('/api-endpoint-mapper', [ApiEndpointMapperController::class, 'index'])
            ->name('api-endpoint-mapper.index');
        Route::get('/mapped-apis', [ApiEndpointMapperController::class, 'mappedApis'])
            ->name('mapped-apis.index');
        Route::get('/api-endpoint-mapper/system-endpoints', [ApiEndpointMapperController::class, 'systemEndpoints'])
            ->name('api-endpoint-mapper.system-endpoints');
        Route::post('/api-endpoint-mapper/mappings', [ApiEndpointMapperController::class, 'storeMapping'])
            ->name('api-endpoint-mapper.mappings.store');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('/profile/company-logo', [ProfileController::class, 'storeLogo'])->name('profile.company-logo.store');
        Route::delete('/profile/company-logo', [ProfileController::class, 'destroyLogo'])->name('profile.company-logo.destroy');

        // Users
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/integration/external-options', [UserController::class, 'integrationExternalOptions'])->name('users.integration-external-options');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
        Route::post('/users/{user}/assign-roles', [UserController::class, 'assignRoles'])->name('users.assignRoles');
        Route::post('/users/{user}/give-permissions', [UserController::class, 'givePermissions'])->name('users.givePermissions');
        Route::get('/role-assignment-rules', [RoleAssignmentRulesController::class, 'index'])->name('role-assignment-rules.index');

        // Roles
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
        Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions'])->name('roles.syncPermissions');

        // Permissions
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::put('/permissions/{permission}', [PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->name('permissions.destroy');

        // Settings
        Route::get('/settings', [SiteSettingController::class, 'index'])->name('settings.index');
        Route::put('/settings', [SiteSettingController::class, 'update'])->name('settings.update');
        Route::get('/data-configuration', [DataConfigurationController::class, 'index'])->name('data-configuration.index');
        Route::put('/data-configuration', [DataConfigurationController::class, 'update'])->name('data-configuration.update');
        Route::patch('/data-configuration/integrations/{integration}/fields', [DataConfigurationController::class, 'updateIntegrationFields'])
            ->name('data-configuration.integration-fields.update');
        Route::get('/integrations', [IntegrationSettingsController::class, 'index'])->name('integrations.index');
        Route::put('/integrations/openai', [IntegrationSettingsController::class, 'updateOpenAi'])->name('integrations.openai.update');
        Route::put('/integrations/storage/{provider}', [IntegrationSettingsController::class, 'updateStorage'])
            ->where('provider', 'supabase|google_drive|dropbox|r2')
            ->name('integrations.storage.update');
        // Settings routes for AI, Pricing, Pool Allocation and System Email Ingestion removed.

        // (Email management removed)

        // Skills autocomplete removed.

        // Job management and resume/candidate management removed.

        // Languages / Translations
        Route::get('/languages', [LanguageStringController::class, 'index'])->name('languages.index');
        Route::put('/languages', [LanguageStringController::class, 'update'])->name('languages.update');
        Route::post('/languages/sync', [LanguageStringController::class, 'syncLanguageFilesToDB'])->name('languages.sync');

        // Activity Logs
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');
        Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])->name('activity-logs.export');
        Route::get('/activity-logs/{activityLog}', [ActivityLogController::class, 'show'])->name('activity-logs.show');
        Route::delete('/activity-logs/{activityLog}', [ActivityLogController::class, 'destroy'])->name('activity-logs.destroy');

        Route::get('/url-management', [\App\Http\Controllers\Admin\UrlManagementController::class, 'index'])
            ->middleware('permission:short-urls.view')
            ->name('url-management.index');

        Route::get('/call-logs', [\App\Http\Controllers\Admin\CallLogController::class, 'index'])
            ->middleware('permission:call-logs.view')
            ->name('call-logs.index');
        Route::get('/call-logs/{callLog}/recordings', [\App\Http\Controllers\Admin\CallLogController::class, 'recordings'])
            ->middleware('permission:call-logs.view')
            ->name('call-logs.recordings');

        Route::get('/contacts', [\App\Http\Controllers\Admin\ContactController::class, 'index'])
            ->middleware('permission:contacts.view')
            ->name('contacts.index');
        Route::post('/contacts', [\App\Http\Controllers\Admin\ContactController::class, 'store'])
            ->middleware('permission:contacts.create')
            ->name('contacts.store');
        Route::put('/contacts/{contact}', [\App\Http\Controllers\Admin\ContactController::class, 'update'])
            ->middleware('permission:contacts.update')
            ->name('contacts.update');
        Route::delete('/contacts/{contact}', [\App\Http\Controllers\Admin\ContactController::class, 'destroy'])
            ->middleware('permission:contacts.delete')
            ->name('contacts.destroy');
        Route::get('/contacts/{contact}/notes', [\App\Http\Controllers\Admin\ContactController::class, 'notes'])
            ->middleware('permission:contacts.view')
            ->name('contacts.notes.index');
        Route::post('/contacts/{contact}/notes', [\App\Http\Controllers\Admin\ContactController::class, 'storeNote'])
            ->middleware('permission:contacts.update')
            ->name('contacts.notes.store');
        Route::put('/contacts/{contact}/notes/{note}', [\App\Http\Controllers\Admin\ContactController::class, 'updateNote'])
            ->middleware('permission:contacts.update')
            ->name('contacts.notes.update');
        Route::delete('/contacts/{contact}/notes/{note}', [\App\Http\Controllers\Admin\ContactController::class, 'destroyNote'])
            ->middleware('permission:contacts.update')
            ->name('contacts.notes.destroy');

        // Tenants (tenants.view / tenants.update permissions; see TenantPolicy)
        Route::get('/tenants', [\App\Http\Controllers\TenantController::class, 'index'])
            ->middleware('permission:tenants.view')
            ->name('tenants.index');
        Route::get('/tenants/create', [\App\Http\Controllers\TenantController::class, 'create'])
            ->name('tenants.create');
        Route::post('/tenants', [\App\Http\Controllers\TenantController::class, 'store'])
            ->name('tenants.store');
        Route::get('/tenants/{tenant}/edit', [\App\Http\Controllers\TenantController::class, 'edit'])
            ->middleware('permission:tenants.view')
            ->name('tenants.edit');
        Route::put('/tenants/{tenant}', [\App\Http\Controllers\TenantController::class, 'update'])
            ->middleware('permission:tenants.update')
            ->name('tenants.update');
        Route::get('/tenants/{tenant}/integrations/zoho/oauth/start', [\App\Http\Controllers\TenantZohoOAuthController::class, 'start'])
            ->middleware('permission:tenants.update')
            ->name('tenants.integrations.zoho.oauth.start');
        Route::get('/tenants/{tenant}/integrations/zoho/oauth/callback', [\App\Http\Controllers\TenantZohoOAuthController::class, 'callback'])
            ->middleware('permission:tenants.update')
            ->name('tenants.integrations.zoho.oauth.callback');
        Route::get('/tenants/{tenant}/integration/crm-users', [\App\Http\Controllers\TenantController::class, 'integrationCrmUsers'])
            ->middleware('permission:tenants.view')
            ->name('tenants.integration-crm-users');
    });

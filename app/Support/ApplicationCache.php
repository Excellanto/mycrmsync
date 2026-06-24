<?php

namespace App\Support;

use App\Integrations\CrmApiClientResolver;
use App\Models\Integration;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

final class ApplicationCache
{
    public const TTL_TRANSLATIONS = 3600;

    public const TTL_INTEGRATIONS = 3600;

    public const TTL_USER_AUTH = 600;

    public const TTL_PLATFORM = 3600;

    public const TTL_DASHBOARD = 600;

    public const TTL_CONTACT_TAGS = 600;

    private const PERIODS = ['7d', '30d', '90d'];

    /**
     * @param  callable(): array<string, array<string, string>>  $resolver
     * @return array<string, array<string, string>>
     */
    public static function rememberTranslations(string $locale, callable $resolver): array
    {
        return Cache::remember("translations.{$locale}", self::TTL_TRANSLATIONS, $resolver);
    }

    public static function forgetTranslations(string $locale): void
    {
        Cache::forget("translations.{$locale}");
    }

    /**
     * @return list<array{name: string, slug: string|null}>
     */
    public static function rememberEnabledIntegrations(): array
    {
        return Cache::remember('integrations.enabled_list', self::TTL_INTEGRATIONS, function (): array {
            return Integration::query()
                ->where('enabled', true)
                ->orderBy('type')
                ->orderBy('name')
                ->get(['name', 'slug'])
                ->map(fn (Integration $integration) => [
                    'name' => $integration->name,
                    'slug' => $integration->slug,
                ])
                ->values()
                ->all();
        });
    }

    public static function forgetEnabledIntegrations(): void
    {
        Cache::forget('integrations.enabled_list');
    }

    /**
     * @return array{
     *     user: array<string, mixed>|null,
     *     permissions: list<string>,
     *     can: array<string, mixed>,
     *     contact_management_available: bool
     * }
     */
    public static function rememberUserAuth(User $user): array
    {
        return Cache::remember(
            self::userAuthKey($user->id),
            self::TTL_USER_AUTH,
            fn (): array => self::buildUserAuthPayload($user),
        );
    }

    public static function forgetUserAuth(int $userId): void
    {
        Cache::forget(self::userAuthKey($userId));
    }

    public static function bumpUserAuthVersion(): void
    {
        if (! Cache::has(self::userAuthVersionKey())) {
            Cache::put(self::userAuthVersionKey(), 1, now()->addYear());
        }

        Cache::increment(self::userAuthVersionKey());
    }

    /**
     * @param  callable(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public static function rememberDashboardTenant(int $tenantId, string $period, callable $resolver): array
    {
        return Cache::remember(
            self::dashboardTenantKey($tenantId, $period),
            self::TTL_DASHBOARD,
            $resolver,
        );
    }

    /**
     * @param  callable(): array<string, mixed>  $resolver
     * @return array<string, mixed>
     */
    public static function rememberDashboardMaster(int $userId, string $period, callable $resolver): array
    {
        return Cache::remember(
            self::dashboardMasterKey($userId, $period),
            self::TTL_DASHBOARD,
            $resolver,
        );
    }

    public static function forgetDashboardForTenant(int $tenantId): void
    {
        foreach (self::PERIODS as $period) {
            Cache::forget(self::dashboardTenantKey($tenantId, $period));
        }
    }

    public static function bumpDashboardMaster(): void
    {
        if (! Cache::has(self::dashboardMasterVersionKey())) {
            Cache::put(self::dashboardMasterVersionKey(), 1, now()->addYear());
        }

        Cache::increment(self::dashboardMasterVersionKey());
    }

    public static function rememberPlatformHasMyCrmSyncTenant(): bool
    {
        return (bool) Cache::remember('platform.has_mycrmsync_tenant', self::TTL_PLATFORM, function (): bool {
            return Tenant::query()
                ->where('integration->slug', CrmApiClientResolver::SLUG_MYCRMSYNC)
                ->exists();
        });
    }

    public static function forgetPlatformHasMyCrmSyncTenant(): void
    {
        Cache::forget('platform.has_mycrmsync_tenant');
    }

    /**
     * @param  callable(): list<array{id: string, name: string}>  $resolver
     * @return list<array{id: string, name: string}>
     */
    public static function rememberContactTags(int $tenantId, callable $resolver): array
    {
        return Cache::remember(
            "contacts.tags.{$tenantId}",
            self::TTL_CONTACT_TAGS,
            $resolver,
        );
    }

    public static function forgetContactTags(int $tenantId): void
    {
        Cache::forget("contacts.tags.{$tenantId}");
    }

    public static function forgetAuthForTenantUsers(int $tenantId): void
    {
        User::query()
            ->where('tenant_id', $tenantId)
            ->pluck('id')
            ->each(fn (int $userId) => self::forgetUserAuth($userId));
    }

    /**
     * @return array{
     *     user: null,
     *     permissions: list<string>,
     *     can: array<string, mixed>,
     *     contact_management_available: bool
     * }
     */
    public static function emptyAuthBundle(): array
    {
        return [
            'user' => null,
            'permissions' => [],
            'can' => [
                'user_management' => [
                    'users' => false,
                    'roles' => false,
                    'permissions' => false,
                    'activity_logs' => false,
                    'tenants' => false,
                ],
                'call_logs' => false,
                'contacts' => false,
            ],
            'contact_management_available' => false,
        ];
    }

    private static function userAuthKey(int $userId): string
    {
        $version = (int) Cache::get(self::userAuthVersionKey(), 1);

        return "user.auth_payload.v{$version}.{$userId}";
    }

    private static function userAuthVersionKey(): string
    {
        return 'cache_version.user_auth';
    }

    private static function dashboardTenantKey(int $tenantId, string $period): string
    {
        return "dashboard.analytics.tenant.{$tenantId}.{$period}";
    }

    private static function dashboardMasterKey(int $userId, string $period): string
    {
        $version = (int) Cache::get(self::dashboardMasterVersionKey(), 1);

        return "dashboard.analytics.master.v{$version}.{$userId}.{$period}";
    }

    private static function dashboardMasterVersionKey(): string
    {
        return 'cache_version.dashboard_master';
    }

    /**
     * @return array{
     *     user: array<string, mixed>,
     *     permissions: list<string>,
     *     can: array<string, mixed>,
     *     contact_management_available: bool
     * }
     */
    private static function buildUserAuthPayload(User $user): array
    {
        $user->loadMissing(['roles', 'tenant']);

        /** @var \Illuminate\Database\Eloquent\Collection<int, Role> $roles */
        $roles = $user->roles;
        $superAdminNav = $roles->contains(
            fn (Role $role) => $role->is_platform_scope === true
                || $role->slug === 'super_admin'
        );

        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();
        $permissionSet = array_fill_keys($permissions, true);

        $contactManagementAvailable = false;
        if ($user->tenant) {
            $contactManagementAvailable = CrmApiClientResolver::isMyCrmSyncTenant($user->tenant);
        } elseif ($user->isMaster()) {
            $contactManagementAvailable = self::rememberPlatformHasMyCrmSyncTenant();
        }

        return [
            'user' => array_merge($user->only('id', 'name', 'email', 'tenant_id'), [
                'is_master' => $user->isMaster(),
                'super_admin_nav' => $superAdminNav,
                'roles' => $user->getRoleNames()->values()->all(),
                'role_slugs' => $roles->pluck('slug')->filter()->unique()->values()->all(),
                'tenant' => $user->tenant
                    ? array_merge(
                        $user->tenant->only('id', 'company_name', 'account_type', 'email_ingestion_enabled'),
                        [
                            'company_logo_url' => $user->tenant->companyLogoUrl(),
                            'integration_slug' => (string) data_get($user->tenant->integration, 'slug', ''),
                        ]
                    )
                    : null,
            ]),
            'permissions' => $permissions,
            'can' => [
                'user_management' => [
                    'users' => isset($permissionSet['users.view']),
                    'roles' => isset($permissionSet['roles.view']),
                    'permissions' => isset($permissionSet['permissions.view']),
                    'activity_logs' => isset($permissionSet['activity-logs.view']),
                    'tenants' => isset($permissionSet['tenants.view']),
                ],
                'call_logs' => isset($permissionSet['call-logs.view']),
                'contacts' => isset($permissionSet['contacts.view']),
            ],
            'contact_management_available' => $contactManagementAvailable,
        ];
    }
}

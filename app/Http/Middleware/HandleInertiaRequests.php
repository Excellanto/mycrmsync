<?php

namespace App\Http\Middleware;

use App\Integrations\CrmApiClientResolver;
use App\Models\Integration;
use App\Models\Role;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => function () use ($request) {
                    $user = $request->user();
                    if (! $user) {
                        return null;
                    }

                    $user->loadMissing(['roles', 'tenant']);

                    /** @var \Illuminate\Database\Eloquent\Collection<int, Role> $roles */
                    $roles = $user->roles;
                    $superAdminNav = $roles->contains(
                        fn (Role $role) => $role->is_platform_scope === true
                            || $role->slug === 'super_admin'
                    );

                    return array_merge($user->only('id', 'name', 'email', 'tenant_id'), [
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
                    ]);
                },
                'permissions' => fn () => $request->user()
                    ? $request->user()->getAllPermissions()->pluck('name')->values()->all()
                    : [],
                'contact_management_available' => function () use ($request) {
                    $user = $request->user();
                    if (! $user) {
                        return false;
                    }

                    if ($user->tenant) {
                        return CrmApiClientResolver::isMyCrmSyncTenant($user->tenant);
                    }

                    if ($user->isMaster()) {
                        return Tenant::query()
                            ->where('integration->slug', CrmApiClientResolver::SLUG_MYCRMSYNC)
                            ->exists();
                    }

                    return false;
                },
                /*
                 * Policy + Gate-aware flags for sidebar rows. Use this instead of guessing from the flat
                 * `permissions` list when granular `nav.*` toggles would otherwise hide authorized links.
                 */
                'can' => function () use ($request) {
                    $user = $request->user();
                    if (! $user) {
                        return [
                            'user_management' => [
                                'users' => false,
                                'roles' => false,
                                'permissions' => false,
                                'activity_logs' => false,
                                'tenants' => false,
                            ],
                            'call_logs' => false,
                            'contacts' => false,
                        ];
                    }

                    return [
                        'user_management' => [
                            'users' => $user->can('users.view'),
                            'roles' => $user->can('roles.view'),
                            'permissions' => $user->can('permissions.view'),
                            'activity_logs' => $user->can('activity-logs.view'),
                            'tenants' => $user->can('tenants.view'),
                        ],
                        'call_logs' => $user->can('call-logs.view'),
                        'contacts' => $user->can('contacts.view'),
                    ];
                },
            ],
            'crm' => [
                'enabled' => fn () => $request->user()
                    ? Integration::enabledIntegrationNames()
                    : [],
                'integrations' => fn () => $request->user()
                    ? Integration::query()
                        ->where('enabled', true)
                        ->orderBy('type')
                        ->orderBy('name')
                        ->get(['name', 'slug'])
                        ->map(fn (Integration $integration) => [
                            'name' => $integration->name,
                            'slug' => $integration->slug,
                        ])
                        ->values()
                        ->all()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Support\ApplicationCache;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleController extends Controller
{
    /**
     * Permissions required for the roles UI matrix (minimal portal entry toggle).
     */
    private function ensureCorePermissionsExist(): void
    {
        $guard = 'web';

        Permission::firstOrCreate(
            ['name' => 'admin-panel-access', 'guard_name' => $guard],
        );
    }

    private function buildPermissionMatrix($permissions)
    {
        // Exclude user-management.* to avoid duplicate User Management section
        $permissions = $permissions->reject(fn ($p) => str_starts_with($p->name, 'user-management.'));

        $placedIds = [];

        $markPlaced = function ($items) use (&$placedIds) {
            foreach ($items as $p) {
                $placedIds[$p->id] = true;
            }
        };

        $result = [];

        // Portal Permissions: entry to the admin UI (must stay first in the matrix, above User Management)
        $adminPerms = $permissions->filter(fn ($p) => $p->name === 'admin-panel-access');
        if ($adminPerms->isNotEmpty()) {
            $result['Portal Permissions'] = $adminPerms->values()->all();
            $markPlaced($adminPerms);
        }

        // Dashboard (simple)
        $dashboardPerms = $permissions->filter(fn ($p) => str_starts_with($p->name, 'dashboard'));
        if ($dashboardPerms->isNotEmpty()) {
            $result['Dashboard'] = $dashboardPerms->sortBy('name')->values()->all();
            $markPlaced($dashboardPerms);
        }

        // Api Management with sub-headings
        $apiManagementSubs = [
            'API Endpoint Mapper' => $permissions->filter(fn ($p) => $p->name === 'nav.api-management.api-endpoint-mapper.show')->sortBy('name')->values(),
            'Mapped APIs' => $permissions->filter(fn ($p) => $p->name === 'nav.api-management.mapped-apis.show')->sortBy('name')->values(),
        ];
        $apiManagementSubs = array_filter($apiManagementSubs, fn ($arr) => $arr->isNotEmpty());
        foreach ($apiManagementSubs as $subs) {
            $markPlaced($subs);
        }
        if (! empty($apiManagementSubs)) {
            $result['Api Management'] = $apiManagementSubs;
        }

        // CRM / call logs
        $crmSubs = [
            'Call Logs' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'call-logs.') || $p->name === 'nav.crm.call-logs.show')->sortBy('name')->values(),
        ];
        $crmSubs = array_filter($crmSubs, fn ($arr) => $arr->isNotEmpty());
        foreach ($crmSubs as $subs) {
            $markPlaced($subs);
        }
        if (! empty($crmSubs)) {
            $result['CRM'] = $crmSubs;
        }

        // Contact Management (MyCrmSync)
        $contactMgmtSubs = [
            'Contacts' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'contacts.') || $p->name === 'nav.contact-management.contacts.show')->sortBy('name')->values(),
        ];
        $contactMgmtSubs = array_filter($contactMgmtSubs, fn ($arr) => $arr->isNotEmpty());
        foreach ($contactMgmtSubs as $subs) {
            $markPlaced($subs);
        }
        if (! empty($contactMgmtSubs)) {
            $result['Contact Management'] = $contactMgmtSubs;
        }

        // Url Management (top-level module)
        $urlManagementPerms = $permissions->filter(
            fn ($p) => str_starts_with($p->name, 'short-urls.')
                || $p->name === 'nav.url-management.show'
        )->sortBy('name')->values();
        if ($urlManagementPerms->isNotEmpty()) {
            $result['Url Management'] = $urlManagementPerms->all();
            $markPlaced($urlManagementPerms);
        }

        // User Management with sub-headings: Users, Roles, Permissions, Activity
        $userMgmtSubs = [
            'Users' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'users.') || $p->name === 'nav.user-management.users.show')->sortBy('name')->values(),
            'Assignment rules' => $permissions->filter(fn ($p) => $p->name === 'nav.user-management.assignment-rules.show')->sortBy('name')->values(),
            'Roles' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'roles.') || $p->name === 'nav.user-management.roles.show')->sortBy('name')->values(),
            'Permissions' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'permissions.') || $p->name === 'nav.user-management.permissions.show')->sortBy('name')->values(),
            'Activity' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'activity-logs.') || $p->name === 'nav.user-management.activity-logs.show')->sortBy('name')->values(),
            'Tenants' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'tenants.') || $p->name === 'nav.user-management.tenants.show')->sortBy('name')->values(),
        ];
        $userMgmtSubs = array_filter($userMgmtSubs, fn ($arr) => $arr->isNotEmpty());
        foreach ($userMgmtSubs as $subs) {
            $markPlaced($subs);
        }
        if (! empty($userMgmtSubs)) {
            $result['User Management'] = $userMgmtSubs;
        }

        // Settings with sub-headings
        $settingsSubs = [
            'Site Settings' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'settings.'))->sortBy('name')->values(),
            'Languages' => $permissions->filter(fn ($p) => str_starts_with($p->name, 'languages.') || $p->name === 'nav.settings.languages.show')->sortBy('name')->values(),
            // 'AI Settings' and 'Pricing' removed
            'Data Configuration' => $permissions->filter(fn ($p) => $p->name === 'nav.settings.data-configuration.show')->sortBy('name')->values(),
            'Integrations' => $permissions->filter(fn ($p) => $p->name === 'nav.settings.integrations.show')->sortBy('name')->values(),
            'Email Templates' => $permissions->filter(fn ($p) => $p->name === 'nav.settings.email-templates.show')->sortBy('name')->values(),
            'System settings' => $permissions->filter(fn ($p) => $p->name === 'nav.settings.system-settings.show')->sortBy('name')->values(),
        ];
        $settingsSubs = array_filter($settingsSubs, fn ($arr) => $arr->isNotEmpty());
        foreach ($settingsSubs as $subs) {
            $markPlaced($subs);
        }
        if (! empty($settingsSubs)) {
            $result['Settings'] = $settingsSubs;
        }

        // Configurations / Email management removed from matrix

        // Platform nav permissions are not shown in the matrix (managed via roles/seeders only)
        $platformNav = $permissions->filter(fn ($p) => str_starts_with($p->name, 'nav.platform.'));
        if ($platformNav->isNotEmpty()) {
            $markPlaced($platformNav);
        }

        // Module-level nav toggles (accordion headers only; not listed in subsection grids)
        $moduleParentNav = $permissions->filter(fn ($p) => in_array($p->name, [
            'nav.api-management.show',
            'nav.crm.show',
            'nav.url-management.show',
            'nav.user-management.show',
            'nav.settings.show',
        ], true));
        if ($moduleParentNav->isNotEmpty()) {
            $markPlaced($moduleParentNav);
        }

        // Any permission not yet in a module (e.g. new nav.* or API-only names)
        $remaining = $permissions->filter(fn ($p) => ! isset($placedIds[$p->id]))->sortBy('name')->values();
        if ($remaining->isNotEmpty()) {
            $result['Other'] = $remaining->all();
        }

        return $result;
    }

    public function create()
    {
        $this->ensureCorePermissionsExist();
        $permissions = Permission::query()->orderBy('name')->get();
        $permissionMatrix = $this->buildPermissionMatrix($permissions);

        return Inertia::render('Admin/Roles/Create', [
            'permissionMatrix' => $permissionMatrix,
        ]);
    }

    public function edit(Role $role)
    {
        $this->ensureCorePermissionsExist();
        $role->load('permissions');
        $permissions = Permission::query()->orderBy('name')->get();
        $permissionMatrix = $this->buildPermissionMatrix($permissions);

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissionMatrix' => $permissionMatrix,
        ]);
    }

    public function index(Request $request)
    {
        $roles = Role::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['array'],
        ]);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        if (! empty($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ApplicationCache::bumpUserAuthVersion();

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($role->id)],
            'permissions' => ['array'],
        ]);
        $role->name = $data['name'];
        $role->save();
        if (isset($data['permissions'])) {
            $role->syncPermissions($data['permissions']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ApplicationCache::bumpUserAuthVersion();

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return back()->with('success', 'Role deleted.');
    }

    public function syncPermissions(Request $request, Role $role)
    {
        $data = $request->validate([
            'permissions' => ['array'],
        ]);
        $role->syncPermissions($data['permissions'] ?? []);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ApplicationCache::bumpUserAuthVersion();

        return back()->with('success', 'Role permissions updated.');
    }
}

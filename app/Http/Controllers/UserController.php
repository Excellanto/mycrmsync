<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Integrations\FetchTenantIntegrationCrmUsers;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ApplicationCache;
use App\Support\RoleAssignmentRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class UserController extends Controller
{
    public function __construct(
        private FetchTenantIntegrationCrmUsers $fetchIntegrationUsers,
    ) {}

    public function create()
    {
        $this->authorize('create', User::class);
        $actor = auth()->user();

        return Inertia::render('Admin/Users/Create', [
            'roles' => $this->assignableRolesForForm($actor),
            'role_assignment_rules' => RoleAssignmentRules::descriptionsFor($actor),
            'permissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
            'integrated_system_users' => $this->integrationUnmappedUsersForFrontend($actor->tenant_id, null),
        ]);
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);
        $actor = auth()->user();
        $user->load(['roles', 'permissions']);

        return Inertia::render('Admin/Users/Edit', [
            'user' => [
                'id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->permissions->pluck('name'),
                'intsysuser' => $user->intsysuser,
            ],
            'roles' => $this->assignableRolesForForm($actor),
            'role_assignment_rules' => RoleAssignmentRules::descriptionsFor($actor),
            'permissions' => Permission::query()->orderBy('name')->get(['id', 'name']),
            'integrated_system_users' => $this->integrationUnmappedUsersForFrontend($user->tenant_id, $user->id),
        ]);
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);
        $user = auth()->user();
        $isMaster = $user->isMaster();

        $search = $request->string('search')->toString();
        $usersQuery = User::query()
            ->select(['id', 'name', 'email', 'tenant_id', 'intsysuser'])
            ->when(! $isMaster, function ($q) use ($user) {
                $q->where('tenant_id', $user->tenant_id);
            })
            ->when($isMaster && $request->query('tenant_id'), function ($q) use ($request) {
                $q->where('tenant_id', $request->query('tenant_id'));
            })
            ->when($search, function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->with(['roles:id,name', 'tenant:id,company_name,integration']);

        $users = $usersQuery
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        $this->hydrateMappedUserLabelsForPaginator($users);

        $tenants = null;
        if ($isMaster) {
            $tenants = \App\Models\Tenant::select('id', 'company_name')->orderBy('company_name')->get();
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => UserResource::collection($users),
            'tenants' => $tenants,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);
        $currentUser = auth()->user();

        $creatingWithTenantContext = $request->filled('tenant_id');

        if ($creatingWithTenantContext && ! $currentUser->isMaster()) {
            if ((int) $request->input('tenant_id') !== (int) $currentUser->tenant_id) {
                throw ValidationException::withMessages([
                    'tenant_id' => ['Invalid tenant.'],
                ]);
            }
        }

        $resolvedTenantIdForRules = $currentUser->isMaster()
            ? ($creatingWithTenantContext ? (int) $request->input('tenant_id') : null)
            : $currentUser->tenant_id;

        $rules = [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'permissions' => ['array'],
        ];

        if ($creatingWithTenantContext) {
            if ($currentUser->isMaster()) {
                $rules['tenant_id'] = ['required', 'integer', 'exists:tenants,id'];
            }
            $rules['email'] = [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->where('tenant_id', $resolvedTenantIdForRules),
            ];
            $rules['roles'] = ['required', 'array', 'size:1'];
            $rules['roles.*'] = ['required', 'string', Rule::in(['tenant_admin', 'tenant_user'])];
        } else {
            $emailRule = ['required', 'string', 'email', 'max:255'];
            if ($resolvedTenantIdForRules) {
                $emailRule[] = Rule::unique('users', 'email')->where('tenant_id', $resolvedTenantIdForRules);
            } elseif ($currentUser->isMaster()) {
                $emailRule[] = 'unique:users,email';
            } else {
                $emailRule[] = Rule::unique('users', 'email')->where('tenant_id', $currentUser->tenant_id);
            }
            $rules['email'] = $emailRule;
            $rules['roles'] = ['array'];
        }

        $allowedIntegratedIds = $this->integrationExternalUserIdsAllowed($resolvedTenantIdForRules, null, null);
        $rules['intsysuser'] = $allowedIntegratedIds !== []
            ? ['required', 'string', 'max:191', Rule::in($allowedIntegratedIds)]
            : ['nullable', 'string', 'max:191'];

        $data = $request->validate($rules);

        $resolvedTenantId = $currentUser->isMaster()
            ? ($creatingWithTenantContext ? (int) $data['tenant_id'] : null)
            : (int) $currentUser->tenant_id;

        if ($resolvedTenantId) {
            $tenantForGate = Tenant::query()
                ->select(['id', 'integration', 'integration_status'])
                ->find($resolvedTenantId);
            if ($tenantForGate && ! $tenantForGate->integration_status) {
                throw ValidationException::withMessages([
                    'tenant_id' => ['Integration is incomplete'],
                ]);
            }
        }

        // If password not provided, generate a random one
        if (empty($data['password'])) {
            $data['password'] = Str::random(12);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'tenant_id' => $resolvedTenantId,
            'intsysuser' => $data['intsysuser'] ?? null,
        ]);

        if ($creatingWithTenantContext) {
            $roleModels = $this->validatedAssignableRoles($currentUser, $data['roles']);
            $user->syncRoles($roleModels->all());
        } elseif (! empty($data['roles'])) {
            $roleModels = $this->validatedAssignableRoles($currentUser, $data['roles']);
            $user->syncRoles($roleModels->all());
        }
        if (! empty($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        $this->invalidateUserAuthCache($user);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $allowedIntegratedIds = $this->integrationExternalUserIdsAllowed(
            $user->tenant_id,
            $user->id,
            filled((string) ($user->intsysuser ?? '')) ? (string) $user->intsysuser : null
        );
        $intsysRules = $allowedIntegratedIds !== []
            ? ['required', 'string', 'max:191', Rule::in($allowedIntegratedIds)]
            : ['nullable', 'string', 'max:191'];

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['array'],
            'permissions' => ['array'],
            'intsysuser' => $intsysRules,
        ]);

        $user->name = $data['name'];
        $user->email = $data['email'];
        if (array_key_exists('intsysuser', $data)) {
            $user->intsysuser = $data['intsysuser'];
        }
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        $user->save();

        if (isset($data['roles'])) {
            $roleModels = $this->validatedAssignableRoles(auth()->user(), $data['roles']);
            $user->syncRoles($roleModels->all());
        }
        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        $this->invalidateUserAuthCache($user);

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    public function assignRoles(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $data = $request->validate([
            'roles' => ['array'],
        ]);
        $roleModels = $this->validatedAssignableRoles(auth()->user(), $data['roles'] ?? []);
        $user->syncRoles($roleModels->all());

        $this->invalidateUserAuthCache($user);

        return back()->with('success', 'Roles updated.');
    }

    public function givePermissions(Request $request, User $user)
    {
        $this->authorize('update', $user);
        $data = $request->validate([
            'permissions' => ['array'],
        ]);
        $user->syncPermissions($data['permissions'] ?? []);

        return back()->with('success', 'Permissions updated.');
    }

    /**
     * Fetches users from the tenant’s CRM integration API and lists which are still unmapped in MysimConnect.
     *
     * @return JsonResponse array{users: list<array>, unmapped_options: list<array{id: string, label: string}>}
     */
    public function integrationExternalOptions(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $requestedTenantId = $request->query('tenant_id');

        $tenantId = $actor->isMaster()
            ? ($requestedTenantId !== null && $requestedTenantId !== '' ? (int) $requestedTenantId : null)
            : $actor->tenant_id;

        if ($tenantId === null) {
            return response()->json([
                'users' => [],
                'unmapped_options' => [],
            ]);
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return response()->json([
                'users' => [],
                'unmapped_options' => [],
            ], 404);
        }

        $this->authorize('view', $tenant);

        $excludeUserMappedByUserId = null;
        if ($request->filled('for_user_id')) {
            $forUserId = (int) $request->query('for_user_id');
            $scoped = User::query()->find($forUserId);
            if ($scoped === null || (int) $scoped->tenant_id !== $tenantId) {
                abort(403);
            }
            $this->authorize('update', $scoped);
            $excludeUserMappedByUserId = $forUserId;
        }

        $rows = collect($this->fetchIntegrationUsers->mappedUsersOrEmpty($tenant));

        $usersPayload = $rows->map(static function (array $u): array {
            $pieces = array_filter([(string) ($u['name'] ?? ''), (string) ($u['email'] ?? '')]);

            return [
                'id' => (string) $u['id'],
                'name' => (string) ($u['name'] ?? ''),
                'email' => (string) ($u['email'] ?? ''),
                'label' => $pieces !== [] ? implode(' — ', $pieces) : (string) $u['id'],
            ];
        })->values()->all();

        $unmappedOptions = $this->integrationUnmappedUsersForFrontend($tenantId, $excludeUserMappedByUserId);

        return response()->json([
            'users' => $usersPayload,
            'unmapped_options' => $unmappedOptions,
        ]);
    }

    private function assignableRolesForForm(User $actor): Collection
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->assignableByUser($actor)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  array<int, string>  $identifiers  Role display names or slugs (web guard).
     * @return Collection<int, Role>
     */
    private function validatedAssignableRoles(User $actor, array $identifiers): Collection
    {
        $identifiers = array_values(array_filter($identifiers, fn ($r) => is_string($r) && $r !== ''));
        if ($identifiers === []) {
            return collect();
        }

        $roles = collect();
        foreach ($identifiers as $raw) {
            $role = Role::query()
                ->where('guard_name', 'web')
                ->where(function ($q) use ($raw) {
                    $q->where('name', $raw)->orWhere('slug', $raw);
                })
                ->first();
            if (! $role) {
                throw ValidationException::withMessages([
                    'roles' => ['Invalid role selected.'],
                ]);
            }
            $roles->push($role);
        }

        $roles = $roles->unique('id')->values();

        if (! $actor->isMaster()) {
            $forbidden = $roles->first(fn (Role $r) => (bool) $r->is_platform_scope);
            if ($forbidden) {
                throw ValidationException::withMessages([
                    'roles' => ['You cannot assign this role.'],
                ]);
            }
        }

        return $roles;
    }

    /**
     * Integrated CRM user IDs already mapped to another local user within this tenant.
     *
     * @return list<string>
     */
    private function takenIntegratedExternalIds(?int $tenantId, ?int $excludeUserMappedByUserId): array
    {
        if ($tenantId === null) {
            return [];
        }

        return User::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('intsysuser')
            ->where('intsysuser', '!=', '')
            ->when($excludeUserMappedByUserId !== null, fn ($q) => $q->where('id', '!=', $excludeUserMappedByUserId))
            ->pluck('intsysuser')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Choices for picker: integration users whose ID is not already linked to another user on this tenant.
     *
     * @return list<array{id: string, label: string}>
     */
    private function integrationUnmappedUsersForFrontend(?int $tenantId, ?int $excludeUserMappedByUserId): array
    {
        if ($tenantId === null) {
            return [];
        }

        $tenant = Tenant::query()->find($tenantId);

        if ($tenant === null) {
            return [];
        }

        $consumedFlip = collect($this->takenIntegratedExternalIds($tenantId, $excludeUserMappedByUserId))->flip();

        return collect($this->fetchIntegrationUsers->mappedUsersOrEmpty($tenant))
            ->reject(fn (array $u): bool => $consumedFlip->has((string) $u['id']))
            ->map(function (array $u): array {
                $pieces = array_filter([(string) ($u['name'] ?? ''), (string) ($u['email'] ?? '')]);

                return [
                    'id' => (string) $u['id'],
                    'label' => $pieces !== [] ? implode(' — ', $pieces) : (string) $u['id'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Valid intsysuser values when saving (Rule::in).
     *
     * @return list<string>
     */
    private function integrationExternalUserIdsAllowed(?int $tenantId, ?int $excludeUserMappedByUserId, ?string $alsoAllowIntegratedId): array
    {
        $ids = collect($this->integrationUnmappedUsersForFrontend($tenantId, $excludeUserMappedByUserId))
            ->pluck('id')
            ->map(fn ($id): string => (string) $id);

        if ($alsoAllowIntegratedId !== null && $alsoAllowIntegratedId !== '') {
            $ids->push((string) $alsoAllowIntegratedId);
        }

        return $ids->unique()->values()->all();
    }

    private function hydrateMappedUserLabelsForPaginator(\Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator): void
    {
        $tenantMaps = [];

        foreach (collect($paginator->items())->pluck('tenant_id')->unique()->filter()->all() as $tid) {
            $tenant = Tenant::query()->find($tid);
            $tenantMaps[$tid] = $tenant
                ? collect($this->fetchIntegrationUsers->mappedUsersOrEmpty($tenant))->keyBy(fn (array $u): string => (string) $u['id'])
                : collect();
        }

        foreach ($paginator->items() as $model) {
            $display = '—';
            $raw = $model->getAttribute('intsysuser');
            if (filled((string) $raw)) {
                $key = (string) $raw;
                if ($model->tenant_id) {
                    $map = $tenantMaps[$model->tenant_id] ?? collect();
                    $row = $map->get($key);
                    $labelParts = array_filter([(string) ($row['name'] ?? ''), (string) ($row['email'] ?? '')]);
                    $display = $row && $labelParts !== []
                        ? implode(' — ', $labelParts)
                        : $key;
                } else {
                    $display = $key;
                }
            }

            $model->setAttribute('mapped_user_display', $display);
        }
    }

    private function invalidateUserAuthCache(User $user): void
    {
        ApplicationCache::forgetUserAuth((int) $user->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

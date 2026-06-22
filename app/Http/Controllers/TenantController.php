<?php

namespace App\Http\Controllers;

use App\Integrations\Connectors\MyCrmSync\MyCrmSyncCrmUsersConnector;
use App\Integrations\FetchTenantIntegrationCrmUsers;
use App\Models\Integration;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Tenant::class);

        /** @var User $user */
        $user = $request->user();

        if (! $user->isMaster()) {
            if ($user->tenant_id === null) {
                abort(403, 'Your account is not linked to a tenant.');
            }

            $tenant = Tenant::query()->findOrFail($user->tenant_id);
            $this->authorize('view', $tenant);

            return redirect()->route('admin.tenants.edit', $tenant);
        }

        $tenants = Tenant::query()
            ->withCount(['users'])
            ->orderBy('company_name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'canCreate' => $request->user()?->can('create', Tenant::class) ?? false,
            'integrationLabels' => Integration::query()->pluck('name', 'slug')->all(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', Tenant::class);

        return Inertia::render('Admin/Tenants/Create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Tenant::class);

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'account_type' => 'required|string|in:Business,Recruiter',
            'pan_card' => 'nullable|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/',
            'gst_number' => 'nullable|string|max:20|regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]$/',
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:'.User::class,
                'unique:tenants,email',
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $tenant = Tenant::create([
            'company_name' => $data['company_name'],
            'account_type' => $data['account_type'],
            'email' => $data['email'],
            'pan_card' => $data['pan_card'] ?: null,
            'gst_number' => $data['gst_number'] ?: null,
            'status' => 'active',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'tenant_id' => $tenant->id,
        ]);

        $tenantAdminRole = Role::where('slug', 'tenant_admin')->where('guard_name', 'web')->firstOrFail();
        $user->assignRole($tenantAdminRole);

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant created successfully.');
    }

    public function edit(Tenant $tenant)
    {
        $this->authorize('update', $tenant);

        // Include tenant users and their roles for the Manage Users tab (id, name, email, roles)
        $tenant->load(['users.roles']);

        // Add User dropdown: only tenant-scoped roles
        $roles = Role::query()
            ->whereIn('slug', ['tenant_admin', 'tenant_user'])
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $integrationOptions = Integration::query()
            ->where(function ($q) use ($tenant) {
                $q->where('enabled', true);
                $slug = data_get($tenant->integration, 'slug');
                if (filled($slug)) {
                    $q->orWhere('slug', $slug);
                }
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Integration $integration) => [
                'id' => $integration->id,
                'name' => $integration->name,
                'slug' => $integration->slug,
                'type' => $integration->type,
                'fields' => $integration->fieldSpecs(),
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Tenants/Edit', [
            'tenant' => $tenant,
            'roles' => $roles,
            'integrationOptions' => $integrationOptions,
            'zohoOAuthCallbackAbsoluteUrl' => route('admin.tenants.integrations.zoho.oauth.callback', $tenant, absolute: true),
        ]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $this->authorize('update', $tenant);

        $data = $request->validate([
            'company_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:tenants,email,'.$tenant->id,
            'pan_card' => 'nullable|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]$/',
            'gst_number' => 'nullable|string|max:20|regex:/^\d{2}[A-Z]{5}\d{4}[A-Z]\d[Z][A-Z\d]$/',
            'status' => 'required|in:active,inactive,suspended',
            'integration' => ['nullable', 'array'],
            'integration.slug' => ['nullable', 'string', 'max:191'],
            'integration.values' => ['nullable', 'array'],
        ]);

        $integrationPayload = $this->validatedTenantIntegration($data['integration'] ?? null, $tenant);
        unset($data['integration']);

        $integrationChanged = json_encode($tenant->integration) !== json_encode($integrationPayload);

        $attributes = array_merge($data, ['integration' => $integrationPayload]);
        if ($integrationChanged) {
            $newSlug = (string) data_get($integrationPayload, 'slug', '');
            $attributes['integration_status'] = $newSlug === MyCrmSyncCrmUsersConnector::integrationSlug();
        }

        $tenant->update($attributes);

        $editTab = $request->input('edit_tab');
        $url = route('admin.tenants.edit', $tenant);
        $allowedTabs = ['profile', 'integration', 'manage-users'];
        if (is_string($editTab) && in_array($editTab, $allowedTabs, true)) {
            $url .= '?tab='.rawurlencode($editTab);
        }

        return redirect()->to($url)
            ->with('success', 'Tenant updated successfully.');
    }

    public function integrationCrmUsers(Request $request, Tenant $tenant, FetchTenantIntegrationCrmUsers $fetch): JsonResponse
    {
        $this->authorize('view', $tenant);

        return $fetch($tenant);
    }

    /**
     * @param  mixed  $payload
     */
    protected function validatedTenantIntegration($payload, Tenant $tenant): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $slug = $payload['slug'] ?? '';

        if (! filled($slug)) {
            return null;
        }

        $integrationModel = Integration::query()->where('slug', $slug)->first();

        if (! $integrationModel) {
            throw ValidationException::withMessages([
                'integration.slug' => ['Select a valid integration.'],
            ]);
        }

        $currentSlug = data_get($tenant->integration, 'slug');

        if (! $integrationModel->enabled && (string) $slug !== (string) $currentSlug) {
            throw ValidationException::withMessages([
                'integration.slug' => ['This integration is not available.'],
            ]);
        }

        $valuesIn = $payload['values'] ?? [];
        if (! is_array($valuesIn)) {
            $valuesIn = [];
        }

        $valuesOut = [];
        $messages = [];

        foreach ($integrationModel->fieldSpecs() as $spec) {
            $key = $spec['key'];
            $label = $spec['label'];
            $raw = $valuesIn[$key] ?? '';
            if (! is_string($raw)) {
                $raw = '';
            }
            $raw = trim($raw);

            $optionalField = (($spec['optional'] ?? false) === true)
                || Integration::fieldLabelHasOptionalSuffix($label);

            if ($raw === '') {
                if ($optionalField) {
                    continue;
                }

                $messages["integration.values.{$key}"] = ["The {$label} field is required."];

                continue;
            }

            if (strlen($raw) > 5000) {
                $messages["integration.values.{$key}"] = ["The {$label} may not exceed 5000 characters."];

                continue;
            }

            $valuesOut[$key] = $raw;
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }

        return [
            'slug' => $integrationModel->slug,
            'values' => $valuesOut,
        ];
    }
}

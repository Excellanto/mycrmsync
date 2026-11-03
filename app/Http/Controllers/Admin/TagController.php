<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\CrmApiClientResolver;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tags\TagCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class TagController extends Controller
{
    public function __construct(
        private TagCatalogService $tags,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('tags.view'), 403);

        /** @var User $user */
        $user = Auth::user();
        $tenant = $this->resolveTenant($request, $user);
        $loadError = null;
        $rows = [];

        if ($tenant !== null) {
            try {
                $rows = $this->tags->list($tenant);
            } catch (RuntimeException $e) {
                $loadError = $e->getMessage();
            }
        }

        return Inertia::render('Admin/Tags/Index', [
            'tags' => $rows,
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'company_name' => $tenant->company_name,
                'integration_slug' => CrmApiClientResolver::slugForTenant($tenant),
                'integration_label' => $this->tags->labelFor($tenant),
            ] : null,
            'tenants' => $user->isMaster() ? $this->tenantOptions() : null,
            'loadError' => $loadError,
            'canCreate' => $user->can('tags.create'),
            'canUpdate' => $user->can('tags.update'),
            'canDelete' => $user->can('tags.delete'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('tags.create'), 403);

        /** @var User $user */
        $user = Auth::user();
        $tenant = $this->requireTenant($request, $user);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->tags->create($tenant, $data['name']);
        } catch (RuntimeException $e) {
            return $this->backToIndex($request, $tenant)->with('error', $e->getMessage());
        }

        return $this->backToIndex($request, $tenant)->with('success', 'Tag added.');
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('tags.update'), 403);

        /** @var User $user */
        $user = Auth::user();
        $tenant = $this->requireTenant($request, $user);
        $data = $request->validate([
            'id' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->tags->rename($tenant, $data['id'], $data['name']);
        } catch (RuntimeException $e) {
            return $this->backToIndex($request, $tenant)->with('error', $e->getMessage());
        }

        return $this->backToIndex($request, $tenant)->with('success', 'Tag renamed.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('tags.delete'), 403);

        /** @var User $user */
        $user = Auth::user();
        $tenant = $this->requireTenant($request, $user);
        $data = $request->validate([
            'id' => ['required', 'string', 'max:191'],
        ]);

        try {
            $this->tags->delete($tenant, $data['id']);
        } catch (RuntimeException $e) {
            return $this->backToIndex($request, $tenant)->with('error', $e->getMessage());
        }

        return $this->backToIndex($request, $tenant)->with('success', 'Tag removed.');
    }

    private function requireTenant(Request $request, User $user): Tenant
    {
        $tenant = $this->resolveTenant($request, $user);

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'tenant_id' => ['No MyCrmSync, Zoho, or GoHighLevel tenant is configured.'],
            ]);
        }

        return $tenant;
    }

    private function resolveTenant(Request $request, User $user): ?Tenant
    {
        if ($user->isMaster()) {
            $tenantId = $request->integer('tenant_id');

            if ($tenantId <= 0) {
                return $this->supportedTenantQuery()->first();
            }

            $tenant = Tenant::query()->findOrFail($tenantId);
            $this->tags->assertSupportedTenant($tenant);

            return $tenant;
        }

        if ($user->tenant_id === null) {
            abort(403, 'Your account is not linked to a tenant.');
        }

        $tenant = Tenant::query()->findOrFail($user->tenant_id);
        $this->tags->assertSupportedTenant($tenant);

        return $tenant;
    }

    private function backToIndex(Request $request, Tenant $tenant): RedirectResponse
    {
        $params = [];

        /** @var User $user */
        $user = Auth::user();

        if ($user->isMaster()) {
            $params['tenant_id'] = $tenant->id;
        }

        return redirect()->route('admin.tags.index', $params);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Tenant>
     */
    private function supportedTenantQuery()
    {
        return Tenant::query()
            ->whereIn('integration->slug', CrmApiClientResolver::supportedSlugs())
            ->orderBy('company_name');
    }

    /**
     * @return list<array{id: int, company_name: string, integration_label: string}>
     */
    private function tenantOptions(): array
    {
        return $this->supportedTenantQuery()
            ->get(['id', 'company_name', 'integration'])
            ->map(fn (Tenant $tenant) => [
                'id' => (int) $tenant->id,
                'company_name' => (string) $tenant->company_name,
                'integration_label' => $this->tags->labelFor($tenant),
            ])
            ->values()
            ->all();
    }
}

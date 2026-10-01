<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\CrmApiClientResolver;
use App\Models\CrmTask;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Tasks\CrmTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    public function __construct(
        private readonly CrmTaskService $tasks,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = Auth::user();
        $filters = [
            'status' => (string) $request->query('status', CrmTask::STATUS_OPEN),
            'kind' => (string) $request->query('kind', 'all'),
            'tenant_id' => $request->query('tenant_id'),
        ];

        $tenant = $this->resolveTenant($user, $filters['tenant_id']);
        $tasks = $tenant === null
            ? CrmTask::query()->whereRaw('0 = 1')->paginate(20)
            : $this->tasks->paginateForUser($user, [
                ...$filters,
                'tenant_id' => $tenant->id,
            ]);

        return Inertia::render('Admin/Tasks/Index', [
            'tasks' => $tasks->through(fn (CrmTask $task) => $this->tasks->payload($task, $user)),
            'filters' => [
                'status' => $filters['status'],
                'kind' => $filters['kind'],
                'tenant_id' => $tenant?->id,
            ],
            'tenants' => $user->isMaster()
                ? Tenant::query()->orderBy('company_name')->get(['id', 'company_name'])
                : [],
            'contactRequired' => $tenant !== null && $this->tasks->contactRequired($tenant),
            'crmLabel' => $tenant !== null ? $this->crmLabel($tenant) : '',
            'canCreate' => $user->can('tasks.create'),
            'canUpdate' => $user->can('tasks.update'),
            'users' => $tenant === null
                ? []
                : User::query()
                    ->where('tenant_id', $tenant->id)
                    ->orderBy('name')
                    ->get(['id', 'name']),
        ]);
    }

    public function searchContacts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tenant_id' => ['nullable', 'integer'],
        ]);

        $tenant = $this->resolveTenant($user, $data['tenant_id'] ?? null);
        if ($tenant === null) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Choose a company first.'],
            ]);
        }

        return response()->json([
            'contacts' => $this->tasks->searchContacts($tenant, (string) ($data['q'] ?? '')),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer'],
        ]);

        $tenant = $this->resolveTenant($user, $data['tenant_id'] ?? null);
        if ($tenant === null) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Choose a company first.'],
            ]);
        }

        $task = $this->tasks->create($user, $tenant, $data);

        return response()->json([
            'task' => $this->tasks->payload($task),
        ]);
    }

    public function update(Request $request, CrmTask $task): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer'],
        ]);

        $updated = $this->tasks->update($user, $task, $data);

        return response()->json([
            'task' => $this->tasks->payload($updated),
        ]);
    }

    public function status(Request $request, CrmTask $task): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $data = $request->validate([
            'status' => ['required', 'string', 'in:open,completed,cancelled'],
        ]);

        $updated = $this->tasks->setStatus($user, $task, $data['status']);

        return response()->json([
            'task' => $this->tasks->payload($updated),
        ]);
    }

    public function destroy(CrmTask $task): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $this->tasks->destroy($user, $task);

        return response()->json(['ok' => true]);
    }

    public function complete(CrmTask $task): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $updated = $this->tasks->complete($user, $task);

        return response()->json([
            'task' => $this->tasks->payload($updated),
        ]);
    }

    private function resolveTenant(User $user, mixed $tenantId): ?Tenant
    {
        if ($user->isMaster()) {
            $id = (int) $tenantId;
            if ($id <= 0) {
                return null;
            }

            return Tenant::query()->findOrFail($id);
        }

        if ($user->tenant_id === null) {
            return null;
        }

        return Tenant::query()->findOrFail($user->tenant_id);
    }

    private function crmLabel(Tenant $tenant): string
    {
        return match (CrmApiClientResolver::slugForTenant($tenant)) {
            CrmApiClientResolver::SLUG_GOHIGHLEVEL => 'GoHighLevel',
            CrmApiClientResolver::SLUG_ZOHO => 'Zoho',
            CrmApiClientResolver::SLUG_MYCRMSYNC => 'MyCrmSync',
            default => 'CRM',
        };
    }
}

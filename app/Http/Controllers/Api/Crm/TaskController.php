<?php

namespace App\Http\Controllers\Api\Crm;

use App\Http\Controllers\Api\Crm\Concerns\ResolvesCrmApiContext;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\CrmTask;
use App\Models\User;
use App\Services\Tasks\CrmTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group CRM
 *
 * Tasks stored in MysimConnect. GoHighLevel and Zoho tasks are created in that CRM as well.
 * A reminder is also a task (`is_reminder` true). A task created here is not a reminder.
 *
 * Requires `Authorization: Bearer <token>` from email OTP verify. `user_id` must match the authenticated user.
 *
 * @authenticated
 */
final class TaskController extends Controller
{
    use ResolvesCrmApiContext;

    public function __construct(
        private readonly CrmTaskService $tasks,
    ) {}

    /**
     * List tasks
     *
     * Includes reminder tasks. A task with no assignees is visible to every user in the tenant.
     * A task with assignees is visible only to those users.
     *
     * @queryParam user_id int required Must match the authenticated user. Example: 1
     * @queryParam status string Optional. `open` (default), `completed`, `cancelled`, or `all`. Example: open
     * @queryParam kind string Optional. `all` (default), `tasks`, or `reminders`. Example: reminders
     *
     * @response 200 {"success":true,"message":"OK","data":{"tasks":[]}}
     */
    public function index(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'status' => ['nullable', 'string', 'in:open,completed,cancelled,all'],
            'kind' => ['nullable', 'string', 'in:all,tasks,reminders'],
        ]);

        $tasks = $this->tasks->paginateForUser($user, [
            'status' => $data['status'] ?? CrmTask::STATUS_OPEN,
            'kind' => $data['kind'] ?? 'all',
            'tenant_id' => $tenant->id,
        ], 50);

        return ApiResponse::success([
            'tasks' => collect($tasks->items())->map(fn (CrmTask $task) => $this->tasks->payload($task))->values(),
        ]);
    }

    /**
     * Create a task
     *
     * This creates a task, not a reminder. GoHighLevel requires `contactId`. Zoho and MyCrmSync allow a task without a contact.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam title string required Task title. Example: Send the revised quote
     * @bodyParam body string Optional details. Example: Include the installation timeline.
     * @bodyParam due_at string required ISO 8601 due time. Example: 2026-10-04T15:00:00+05:30
     * @bodyParam contactId string CRM contact id. Required for GoHighLevel. Example: 8YoUU2pqEmnI8AoTSLhR
     * @bodyParam contact_name string Optional display name. Example: Jane Doe
     *
     * @response 200 {"success":true,"message":"Task saved.","data":{"task":{"id":"6ba7b810-9dad-11d1-80b4-00c04fd430c8","is_reminder":false,"status":"open"}}}
     */
    public function store(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveCrmApiContext($request);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'contactId' => ['nullable', 'string', 'max:191'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer'],
        ]);

        $create = [
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'due_at' => $data['due_at'],
            'contact_id' => $data['contactId'] ?? $data['contact_id'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
        ];
        if ($request->exists('assignee_ids')) {
            $create['assignee_ids'] = $data['assignee_ids'] ?? [];
        }

        $task = $this->tasks->create($user, $tenant, $create);

        return ApiResponse::success([
            'task' => $this->tasks->payload($task),
        ], 'Task saved.');
    }

    /**
     * Update a task
     *
     * Reminder tasks cannot be edited here. Change those from the contact note.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam taskId string required Task id. Example: 6ba7b810-9dad-11d1-80b4-00c04fd430c8
     * @bodyParam title string required Task title. Example: Send the revised quote
     * @bodyParam body string Optional details. Example: Include the installation timeline.
     * @bodyParam due_at string required ISO 8601 due time. Example: 2026-10-04T15:00:00+05:30
     * @bodyParam contactId string Optional CRM contact id. Example: 8YoUU2pqEmnI8AoTSLhR
     * @bodyParam contact_name string Optional display name. Example: Jane Doe
     */
    public function update(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);
        $task = $this->authorizedTask($request, $user);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'contactId' => ['nullable', 'string', 'max:191'],
            'contact_id' => ['nullable', 'string', 'max:191'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer'],
        ]);

        $payload = [
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'due_at' => $data['due_at'],
        ];

        if ($request->exists('contactId') || $request->exists('contact_id')) {
            $payload['contact_id'] = $data['contactId'] ?? $data['contact_id'] ?? null;
        }

        if ($request->exists('contact_name')) {
            $payload['contact_name'] = $data['contact_name'] ?? '';
        }

        if ($request->exists('assignee_ids')) {
            $payload['assignee_ids'] = $data['assignee_ids'] ?? [];
        }

        $updated = $this->tasks->update($user, $task, $payload);

        return ApiResponse::success([
            'task' => $this->tasks->payload($updated),
        ], 'Task updated.');
    }

    /**
     * Complete a task
     *
     * Completing a reminder task also completes the reminder.
     *
     * @bodyParam user_id int required Must match the authenticated user. Example: 1
     * @bodyParam taskId string required Task id. Example: 6ba7b810-9dad-11d1-80b4-00c04fd430c8
     */
    public function complete(Request $request): JsonResponse
    {
        ['user' => $user] = $this->resolveCrmApiContext($request);
        $task = $this->authorizedTask($request, $user);
        $updated = $this->tasks->complete($user, $task);

        return ApiResponse::success([
            'task' => $this->tasks->payload($updated),
        ], 'Task completed.');
    }

    private function authorizedTask(Request $request, User $user): CrmTask
    {
        $data = $request->validate([
            'taskId' => ['nullable', 'uuid'],
            'task_id' => ['nullable', 'uuid'],
            'taskid' => ['nullable', 'uuid'],
        ]);

        $id = trim((string) ($data['taskId'] ?? $data['task_id'] ?? $data['taskid'] ?? ''));
        if ($id === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'taskId' => ['The taskId field is required.'],
            ]);
        }

        $query = CrmTask::query()->whereKey($id);
        if (! $user->isMaster()) {
            $query->where('tenant_id', $user->tenant_id);
        }

        $task = $query->firstOrFail();
        $this->tasks->assertCanManage($user, $task);

        return $task;
    }
}

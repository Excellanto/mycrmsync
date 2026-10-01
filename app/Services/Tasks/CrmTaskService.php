<?php

namespace App\Services\Tasks;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\Zoho\ZohoCrmApiClient;
use App\Integrations\CrmApiClientResolver;
use App\Models\Contact;
use App\Models\ContactReminder;
use App\Models\CrmTask;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final class CrmTaskService
{
    public function __construct(
        private readonly CrmTaskExternalSync $external,
        private readonly GoHighLevelApiClient $ghl,
        private readonly ZohoCrmApiClient $zoho,
    ) {}

    /**
     * @param  array{status?: string, kind?: string, tenant_id?: int|null}  $filters
     * @return LengthAwarePaginator<int, CrmTask>
     */
    public function paginateForUser(User $user, array $filters, int $perPage = 20): LengthAwarePaginator
    {
        $tenantId = $this->tenantIdFor($user, $filters['tenant_id'] ?? null);

        $query = CrmTask::query()
            ->with(['user:id,name', 'assignees:id,name'])
            ->where('tenant_id', $tenantId)
            ->orderBy('due_at');

        $this->restrictToVisibleTasks($query, $user);

        $status = (string) ($filters['status'] ?? 'open');
        if (in_array($status, [CrmTask::STATUS_OPEN, CrmTask::STATUS_COMPLETED, CrmTask::STATUS_CANCELLED], true)) {
            $query->where('status', $status);
        }

        $kind = (string) ($filters['kind'] ?? 'all');
        if ($kind === 'reminders') {
            $query->where('is_reminder', true);
        } elseif ($kind === 'tasks') {
            $query->where('is_reminder', false);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, Tenant $tenant, array $data): CrmTask
    {
        $this->assertContactWhenRequired($tenant, $data['contact_id'] ?? null);

        $task = CrmTask::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'contact_id' => $this->blankToNull($data['contact_id'] ?? null),
            'contact_name' => trim((string) ($data['contact_name'] ?? '')),
            'title' => trim((string) $data['title']),
            'body' => $this->blankToNull($data['body'] ?? null),
            'due_at' => Carbon::parse($data['due_at']),
            'is_reminder' => false,
            'status' => CrmTask::STATUS_OPEN,
        ]);

        $assigneeIds = array_key_exists('assignee_ids', $data)
            ? (array) $data['assignee_ids']
            : [];
        $this->syncAssignees($task, $tenant, $assigneeIds);

        $this->external->push($task->fresh(['user:id,name,intsysuser']));

        return $task->fresh(['user:id,name', 'assignees:id,name']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, CrmTask $task, array $data): CrmTask
    {
        $this->assertCanManage($user, $task);

        if ($task->is_reminder) {
            throw ValidationException::withMessages([
                'title' => ['This task comes from a reminder. Change it from the contact note.'],
            ]);
        }

        $tenant = Tenant::query()->findOrFail($task->tenant_id);
        if (array_key_exists('contact_id', $data)) {
            $this->assertContactWhenRequired($tenant, $data['contact_id']);
        }

        $task->fill([
            'title' => trim((string) ($data['title'] ?? $task->title)),
            'body' => array_key_exists('body', $data) ? $this->blankToNull($data['body']) : $task->body,
            'due_at' => isset($data['due_at']) ? Carbon::parse($data['due_at']) : $task->due_at,
            'contact_id' => array_key_exists('contact_id', $data) ? $this->blankToNull($data['contact_id']) : $task->contact_id,
            'contact_name' => array_key_exists('contact_name', $data) ? trim((string) $data['contact_name']) : $task->contact_name,
        ])->save();

        if (array_key_exists('assignee_ids', $data)) {
            $this->syncAssignees($task, $tenant, (array) $data['assignee_ids']);
        }

        $this->external->push($task->fresh(['user:id,name,intsysuser']));

        return $task->fresh(['user:id,name', 'assignees:id,name']);
    }

    public function setStatus(User $user, CrmTask $task, string $status): CrmTask
    {
        $this->assertCanManage($user, $task);

        if (! in_array($status, [CrmTask::STATUS_OPEN, CrmTask::STATUS_COMPLETED, CrmTask::STATUS_CANCELLED], true)) {
            throw ValidationException::withMessages([
                'status' => ['Choose an open, completed, or cancelled status.'],
            ]);
        }

        if ($task->status === $status) {
            return $task->fresh(['user:id,name', 'assignees:id,name']);
        }

        if ($status === CrmTask::STATUS_COMPLETED) {
            $this->syncReminderStatus($task, ContactReminder::STATUS_COMPLETED);
            $task->update([
                'status' => CrmTask::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        } elseif ($status === CrmTask::STATUS_CANCELLED) {
            $this->syncReminderStatus($task, ContactReminder::STATUS_DISMISSED);
            $task->update([
                'status' => CrmTask::STATUS_CANCELLED,
                'completed_at' => $task->completed_at ?? now(),
            ]);
        } else {
            $this->syncReminderStatus($task, ContactReminder::STATUS_PENDING);
            $task->update([
                'status' => CrmTask::STATUS_OPEN,
                'completed_at' => null,
            ]);
        }

        $fresh = $task->fresh(['user:id,name,intsysuser', 'assignees:id,name']);
        $this->external->push($fresh);

        return $fresh->fresh(['user:id,name', 'assignees:id,name']);
    }

    public function complete(User $user, CrmTask $task): CrmTask
    {
        $this->assertCanManage($user, $task);

        if ($task->status !== CrmTask::STATUS_OPEN) {
            throw ValidationException::withMessages([
                'task' => ['This task is already closed.'],
            ]);
        }

        if ($task->is_reminder && $task->contact_reminder_id !== null) {
            $reminder = ContactReminder::query()->find($task->contact_reminder_id);
            if ($reminder !== null && $reminder->status === ContactReminder::STATUS_PENDING) {
                $reminder->update([
                    'status' => ContactReminder::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
            }
        }

        $task->update([
            'status' => CrmTask::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $fresh = $task->fresh(['user:id,name,intsysuser']);
        $this->external->push($fresh);

        return $fresh->fresh(['user:id,name']);
    }

    public function syncFromReminder(ContactReminder $reminder): CrmTask
    {
        $task = CrmTask::query()->firstOrNew([
            'contact_reminder_id' => $reminder->id,
        ]);

        $task->fill([
            'tenant_id' => $reminder->tenant_id,
            'user_id' => $reminder->user_id,
            'created_by' => $reminder->created_by ?: $reminder->user_id,
            'contact_id' => $reminder->contact_id,
            'contact_name' => $reminder->contact_name,
            'contact_note_id' => $reminder->contact_note_id,
            'title' => $this->reminderTitle($reminder),
            'body' => $reminder->note_excerpt !== '' ? $reminder->note_excerpt : null,
            'due_at' => $reminder->remind_at,
            'is_reminder' => true,
            'status' => match ($reminder->status) {
                ContactReminder::STATUS_DISMISSED => CrmTask::STATUS_CANCELLED,
                ContactReminder::STATUS_COMPLETED => CrmTask::STATUS_COMPLETED,
                default => CrmTask::STATUS_OPEN,
            },
            'completed_at' => $reminder->status === ContactReminder::STATUS_PENDING ? null : $reminder->completed_at,
        ]);
        $task->save();
        $ownerId = (int) $reminder->user_id;
        $tenant = Tenant::query()->find($task->tenant_id);
        if ($tenant !== null && $ownerId > 0) {
            $this->syncAssignees($task, $tenant, [$ownerId]);
        }

        $taskId = $task->id;
        app()->terminating(function () use ($taskId): void {
            $fresh = CrmTask::query()->find($taskId);
            if ($fresh !== null) {
                app(CrmTaskExternalSync::class)->push($fresh);
            }
        });

        return $task;
    }

    public function deleteForReminder(ContactReminder $reminder): void
    {
        $task = CrmTask::query()->where('contact_reminder_id', $reminder->id)->first();
        if ($task === null) {
            return;
        }

        $this->external->deleteRemote($task);
        $task->delete();
    }

    public function destroy(User $user, CrmTask $task): void
    {
        if (! $user->isMaster() && (int) $task->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        if (! $this->canDelete($user, $task)) {
            abort(403);
        }

        $this->external->deleteRemote($task);

        $reminderId = $task->contact_reminder_id;
        if ($reminderId !== null) {
            ContactReminder::query()->whereKey($reminderId)->delete();
            if (CrmTask::query()->whereKey($task->id)->exists()) {
                $task->delete();
            }

            return;
        }

        $task->delete();
    }

    public function canDelete(User $user, CrmTask $task): bool
    {
        if ($user->isMaster()) {
            return true;
        }

        if ((int) $task->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        if ($this->isTenantAdmin($user)) {
            return true;
        }

        $userId = (int) $user->id;

        return $userId === (int) $task->user_id
            || ($task->created_by !== null && $userId === (int) $task->created_by);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function searchContacts(Tenant $tenant, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return Contact::query()
                ->forTenantId((int) $tenant->id)
                ->where(function ($query) use ($term): void {
                    $like = '%'.$term.'%';
                    $query->where('first_name', 'ilike', $like)
                        ->orWhere('last_name', 'ilike', $like)
                        ->orWhere('email', 'ilike', $like)
                        ->orWhere('phone', 'ilike', $like)
                        ->orWhere('company_name', 'ilike', $like);
                })
                ->orderBy('first_name')
                ->limit(15)
                ->get(['id', 'first_name', 'last_name', 'company_name', 'phone'])
                ->map(function (Contact $contact): array {
                    $name = trim($contact->first_name.' '.$contact->last_name);
                    if ($name === '') {
                        $name = trim((string) $contact->company_name);
                    }
                    if ($contact->phone !== '') {
                        $name = trim($name.' · '.$contact->phone);
                    }

                    return [
                        'id' => (string) $contact->id,
                        'name' => $name !== '' ? $name : (string) $contact->id,
                    ];
                })
                ->all();
        }

        if (CrmApiClientResolver::isZohoTenant($tenant)) {
            [$json] = $this->zoho->searchContacts($tenant, ['query' => $term]);
        } else {
            [$json] = $this->ghl->searchContacts($tenant, [
                'locationId' => $this->ghl->defaultLocationId($tenant),
                'query' => $term,
                'pageLimit' => 15,
            ]);
        }

        $contacts = $json['contacts'] ?? [];
        if (! is_array($contacts)) {
            return [];
        }

        $out = [];
        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }
            $id = trim((string) ($contact['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $name = trim((string) ($contact['name'] ?? ''));
            $phone = trim((string) ($contact['phone'] ?? ''));
            if ($phone !== '') {
                $name = trim($name.' · '.$phone);
            }
            $out[] = [
                'id' => $id,
                'name' => $name !== '' ? $name : $id,
            ];
            if (count($out) >= 15) {
                break;
            }
        }

        return $out;
    }

    public function assertCanManage(User $user, CrmTask $task): void
    {
        if (! $user->isMaster() && (int) $task->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        if ($user->isMaster() || $this->isVisibleTo($user, $task)) {
            return;
        }

        abort(403);
    }

    public function contactRequired(Tenant $tenant): bool
    {
        return CrmApiClientResolver::slugForTenant($tenant) === CrmApiClientResolver::SLUG_GOHIGHLEVEL;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(CrmTask $task, ?User $viewer = null): array
    {
        $task->loadMissing(['user:id,name', 'assignees:id,name']);
        $assignees = $task->assignees;

        return [
            'id' => (string) $task->id,
            'title' => (string) $task->title,
            'body' => (string) ($task->body ?? ''),
            'due_at' => $task->due_at?->toIso8601String() ?? '',
            'status' => (string) $task->status,
            'is_reminder' => (bool) $task->is_reminder,
            'contact_id' => $task->contact_id !== null ? (string) $task->contact_id : null,
            'contact_name' => (string) $task->contact_name,
            'contact_note_id' => $task->contact_note_id !== null ? (string) $task->contact_note_id : null,
            'contact_reminder_id' => $task->contact_reminder_id !== null ? (string) $task->contact_reminder_id : null,
            'user_id' => (int) $task->user_id,
            'user_name' => (string) ($task->user?->name ?? ''),
            'assignee_ids' => $assignees->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            'assignee_names' => $assignees->pluck('name')->map(fn ($name) => (string) $name)->values()->all(),
            'can_delete' => $viewer !== null && $this->canDelete($viewer, $task),
            'external_provider' => $task->external_provider,
            'external_id' => $task->external_id,
            'sync_error' => $task->sync_error,
        ];
    }

    private function isTenantAdmin(User $user): bool
    {
        return $user->roles()
            ->where(function ($query): void {
                $query->where('slug', 'tenant_admin')
                    ->orWhere('name', 'Tenant Admin');
            })
            ->exists();
    }

    private function restrictToVisibleTasks(\Illuminate\Database\Eloquent\Builder $query, User $user): void
    {
        if ($user->isMaster()) {
            return;
        }

        $query->where(function ($visible) use ($user): void {
            $visible->whereDoesntHave('assignees')
                ->orWhereHas('assignees', function ($assigned) use ($user): void {
                    $assigned->where('users.id', $user->id);
                });
        });
    }

    private function isVisibleTo(User $user, CrmTask $task): bool
    {
        if ((int) $task->tenant_id !== (int) $user->tenant_id) {
            return false;
        }

        $task->loadMissing('assignees:id');
        if ($task->assignees->isEmpty()) {
            return true;
        }

        return $task->assignees->contains(fn (User $assignee): bool => (int) $assignee->id === (int) $user->id);
    }

    /**
     * @param  list<mixed>  $ids
     */
    private function syncAssignees(CrmTask $task, Tenant $tenant, array $ids): void
    {
        $ids = array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids,
        ), static fn (int $id): bool => $id > 0)));

        if ($ids === []) {
            $task->assignees()->detach();

            return;
        }

        $valid = User::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages([
                'assignee_ids' => ['Choose users from this company.'],
            ]);
        }

        $task->assignees()->sync($valid);
    }

    private function syncReminderStatus(CrmTask $task, string $status): void
    {
        if (! $task->is_reminder || $task->contact_reminder_id === null) {
            return;
        }

        $reminder = ContactReminder::query()->find($task->contact_reminder_id);
        if ($reminder === null) {
            return;
        }

        $reminder->update([
            'status' => $status,
            'completed_at' => $status === ContactReminder::STATUS_PENDING ? null : ($reminder->completed_at ?? now()),
        ]);
    }

    private function tenantIdFor(User $user, mixed $requested): int
    {
        if ($user->isMaster()) {
            $tenantId = (int) $requested;
            if ($tenantId <= 0) {
                throw ValidationException::withMessages([
                    'tenant_id' => ['Choose a company first.'],
                ]);
            }

            return $tenantId;
        }

        if ($user->tenant_id === null) {
            throw ValidationException::withMessages([
                'tenant_id' => ['The selected user is not linked to a tenant.'],
            ]);
        }

        return (int) $user->tenant_id;
    }

    private function assertContactWhenRequired(Tenant $tenant, mixed $contactId): void
    {
        if (! $this->contactRequired($tenant)) {
            return;
        }

        if (trim((string) $contactId) === '') {
            throw ValidationException::withMessages([
                'contact_id' => ['A GoHighLevel contact is required for this task.'],
            ]);
        }
    }

    private function reminderTitle(ContactReminder $reminder): string
    {
        $name = trim((string) $reminder->contact_name);
        $excerpt = trim((string) $reminder->note_excerpt);

        if ($excerpt !== '') {
            $title = mb_strlen($excerpt) > 120 ? mb_substr($excerpt, 0, 117).'...' : $excerpt;

            return $name !== '' ? $name.': '.$title : $title;
        }

        return $name !== '' ? 'Reminder: '.$name : 'Reminder';
    }

    private function blankToNull(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}

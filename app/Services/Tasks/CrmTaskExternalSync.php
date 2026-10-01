<?php

namespace App\Services\Tasks;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\Zoho\ZohoCrmApiClient;
use App\Integrations\CrmApiClientResolver;
use App\Models\CrmTask;
use App\Models\Tenant;
use App\Models\User;
use RuntimeException;
use Throwable;

final class CrmTaskExternalSync
{
    public function __construct(
        private readonly GoHighLevelApiClient $ghl,
        private readonly ZohoCrmApiClient $zoho,
    ) {}

    public function push(CrmTask $task): void
    {
        $task->loadMissing(['user:id,name,intsysuser', 'assignees:id,name,intsysuser']);
        $tenant = Tenant::query()->find($task->tenant_id);

        if ($tenant === null || ! CrmApiClientResolver::isSupportedTenant($tenant)) {
            return;
        }

        $slug = CrmApiClientResolver::slugForTenant($tenant);

        if ($slug === CrmApiClientResolver::SLUG_MYCRMSYNC) {
            $task->forceFill([
                'external_provider' => null,
                'sync_error' => null,
            ])->save();

            return;
        }

        try {
            if ($slug === CrmApiClientResolver::SLUG_GOHIGHLEVEL) {
                $this->pushGoHighLevel($tenant, $task);
            } elseif ($slug === CrmApiClientResolver::SLUG_ZOHO) {
                $this->pushZoho($tenant, $task);
            }

            $task->forceFill(['sync_error' => null])->save();
        } catch (Throwable $e) {
            $task->forceFill([
                'external_provider' => $slug,
                'sync_error' => mb_substr($e->getMessage() !== '' ? $e->getMessage() : 'CRM task sync failed.', 0, 500),
            ])->save();
        }
    }

    public function deleteRemote(CrmTask $task): void
    {
        $externalId = trim((string) $task->external_id);
        if ($externalId === '') {
            return;
        }

        $tenant = Tenant::query()->find($task->tenant_id);
        if ($tenant === null) {
            return;
        }

        try {
            if ($task->external_provider === CrmApiClientResolver::SLUG_GOHIGHLEVEL) {
                $contactId = trim((string) $task->contact_id);
                if ($contactId !== '') {
                    $this->ghl->deleteContactTask($tenant, $contactId, $externalId);
                }
            } elseif ($task->external_provider === CrmApiClientResolver::SLUG_ZOHO) {
                $this->zoho->deleteTask($tenant, $externalId);
            }
        } catch (Throwable) {
            // The local task is still removed. A leftover CRM task can be closed there.
        }
    }

    private function pushGoHighLevel(Tenant $tenant, CrmTask $task): void
    {
        $contactId = trim((string) $task->contact_id);
        if ($contactId === '') {
            throw new RuntimeException('A contact is required to create a GoHighLevel task.');
        }

        $task->external_provider = CrmApiClientResolver::SLUG_GOHIGHLEVEL;
        $externalId = trim((string) $task->external_id);

        if ($task->status === CrmTask::STATUS_CANCELLED) {
            if ($externalId !== '') {
                $this->ghl->deleteContactTask($tenant, $contactId, $externalId);
                $task->external_id = null;
            }

            return;
        }

        $payload = $this->goHighLevelPayload($task, $task->status === CrmTask::STATUS_COMPLETED);

        if ($externalId === '') {
            $task->external_id = $this->ghl->createContactTask($tenant, $contactId, $payload);
        } elseif ($task->status === CrmTask::STATUS_COMPLETED) {
            $this->ghl->updateContactTask($tenant, $contactId, $externalId, $payload);
            $this->ghl->completeContactTask($tenant, $contactId, $externalId);
        } else {
            $this->ghl->updateContactTask($tenant, $contactId, $externalId, $payload);
        }
    }

    private function pushZoho(Tenant $tenant, CrmTask $task): void
    {
        $task->external_provider = CrmApiClientResolver::SLUG_ZOHO;
        $externalId = trim((string) $task->external_id);

        if ($task->status === CrmTask::STATUS_CANCELLED) {
            if ($externalId !== '') {
                $this->zoho->deleteTask($tenant, $externalId);
                $task->external_id = null;
            }

            return;
        }

        $fields = $this->zohoFields($task, withRemindAt: true);

        try {
            $this->writeZoho($tenant, $task, $externalId, $fields);
        } catch (RuntimeException $e) {
            if (! str_contains(strtolower($e->getMessage()), 'remind')) {
                throw $e;
            }

            $this->writeZoho($tenant, $task, $externalId, $this->zohoFields($task, withRemindAt: false));
        }
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function writeZoho(Tenant $tenant, CrmTask $task, string $externalId, array $fields): void
    {
        if ($externalId === '') {
            $task->external_id = $this->zoho->createTask($tenant, $fields);

            return;
        }

        $this->zoho->updateTask($tenant, $externalId, $fields);
    }

    /**
     * @return array<string, mixed>
     */
    private function goHighLevelPayload(CrmTask $task, bool $completed): array
    {
        $payload = [
            'title' => $task->title,
            'body' => (string) ($task->body ?? ''),
            'dueDate' => $task->due_at?->utc()->format('Y-m-d\TH:i:s\Z'),
            'completed' => $completed,
        ];

        $assignedTo = trim((string) ($this->crmOwner($task)?->intsysuser ?? ''));
        if ($assignedTo !== '') {
            $payload['assignedTo'] = $assignedTo;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function zohoFields(CrmTask $task, bool $withRemindAt): array
    {
        $fields = [
            'Subject' => $task->title,
            'Description' => (string) ($task->body ?? ''),
            'Due_Date' => $task->due_at?->toDateString(),
            'Status' => $task->status === CrmTask::STATUS_COMPLETED ? 'Completed' : 'Not Started',
        ];

        if ($withRemindAt && $task->due_at !== null) {
            $fields['Remind_At'] = $task->due_at->format('Y-m-d\TH:i:sP');
        }

        $contactId = trim((string) $task->contact_id);
        if ($contactId !== '') {
            $fields['Who_Id'] = ['id' => $contactId];
        }

        $ownerId = trim((string) ($this->crmOwner($task)?->intsysuser ?? ''));
        if ($ownerId !== '') {
            $fields['Owner'] = ['id' => $ownerId];
        }

        return $fields;
    }

    private function crmOwner(CrmTask $task): ?User
    {
        $assignee = $task->assignees->first();

        return $assignee ?? $task->user;
    }
}

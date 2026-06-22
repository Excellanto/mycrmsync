<?php

namespace App\Services\VoiceNote;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\GoHighLevel\GoHighLevelCrmUsersConnector;
use App\Integrations\Connectors\MyCrmSync\MyCrmSyncCrmApiClient;
use App\Integrations\Connectors\Zoho\ZohoCrmApiClient;
use App\Integrations\Connectors\Zoho\ZohoCrmUsersConnector;
use App\Integrations\CrmApiClientResolver;
use App\Integrations\FetchTenantIntegrationCrmUsers;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class CrmContactNoteCreator
{
    public function __construct(
        private GoHighLevelApiClient $ghl,
        private ZohoCrmApiClient $zoho,
        private MyCrmSyncCrmApiClient $myCrmSync,
        private FetchTenantIntegrationCrmUsers $fetchTenantIntegrationCrmUsers,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function create(Tenant $tenant, User $user, string $contactId, string $noteBody): ?array
    {
        $integratedUserId = CrmApiClientResolver::isMyCrmSyncTenant($tenant)
            ? (string) $user->id
            : trim((string) ($user->intsysuser ?? ''));

        if ($integratedUserId === '') {
            throw ValidationException::withMessages([
                'user_id' => ['This user is not linked to an integrated system user. Map intsysuser before creating notes.'],
            ]);
        }

        $payload = [
            'body' => trim($noteBody),
            'userId' => $integratedUserId,
        ];

        $noteActorDefaults = [
            'contactId' => $contactId,
            'user_name' => $this->resolvedUserName($user, $tenant),
            'userId' => $integratedUserId,
            'overrideOwner' => true,
        ];

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            [$json] = $this->myCrmSync->createContactNote($tenant, $contactId, $payload, $user, $noteActorDefaults);
        } elseif (CrmApiClientResolver::isZohoTenant($tenant)) {
            [$json] = $this->zoho->createContactNote($tenant, $contactId, $payload, $noteActorDefaults);
        } else {
            [$json] = $this->ghl->createContactNote($tenant, $contactId, $payload, $noteActorDefaults);
        }

        $notes = $json['notes'] ?? [];
        if (! is_array($notes) || $notes === []) {
            return null;
        }

        return $this->latestNote($notes);
    }

    private function resolvedUserName(User $user, Tenant $tenant): string
    {
        $externalId = CrmApiClientResolver::isMyCrmSyncTenant($tenant)
            ? (string) $user->id
            : trim((string) ($user->intsysuser ?? ''));

        if ($externalId !== '') {
            foreach ($this->fetchTenantIntegrationCrmUsers->mappedUsersOrEmpty($tenant) as $row) {
                if ((string) ($row['id'] ?? '') === $externalId) {
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($name !== '') {
                        return $name;
                    }
                }
            }
        }

        return (string) $user->name;
    }

    /**
     * @param  list<mixed>  $notes
     * @return array<string, mixed>|null
     */
    private function latestNote(array $notes): ?array
    {
        $ranked = [];

        foreach (array_values($notes) as $index => $note) {
            if (! is_array($note)) {
                continue;
            }

            $timestamp = strtotime((string) ($note['dateAdded'] ?? $note['dateUpdated'] ?? ''));
            $ranked[] = [
                'note' => $note,
                'timestamp' => $timestamp === false ? 0 : $timestamp,
                'index' => $index,
            ];
        }

        if ($ranked === []) {
            return null;
        }

        usort($ranked, function (array $left, array $right): int {
            $byDate = $right['timestamp'] <=> $left['timestamp'];

            return $byDate !== 0 ? $byDate : $left['index'] <=> $right['index'];
        });

        return $ranked[0]['note'];
    }
}

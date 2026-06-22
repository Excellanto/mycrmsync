<?php

namespace App\Services\Contacts;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\MyCrmSync\MyCrmSyncContactMapper;
use App\Integrations\Connectors\Zoho\ZohoCrmApiClient;
use App\Integrations\CrmApiClientResolver;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

final class ContactDetailService
{
    public function __construct(
        private ContactService $contacts = new ContactService,
        private ContactCallStatsService $callStats = new ContactCallStatsService,
        private GoHighLevelApiClient $ghl = new GoHighLevelApiClient,
        private ZohoCrmApiClient $zoho = new ZohoCrmApiClient,
        private MyCrmSyncContactMapper $mapper = new MyCrmSyncContactMapper,
    ) {}

    /**
     * @return array{contact: array<string, mixed>, calls: array{total_dialed: int, total_talk_time: int, total_received: int, total_notes: int}}
     */
    public function detailForRequest(Tenant $tenant, User $user, ?string $contactId, ?string $phone): array
    {
        $contactId = trim((string) $contactId);
        $phone = trim((string) $phone);

        if ($contactId === '' && $phone === '') {
            throw ValidationException::withMessages([
                'contactId' => ['Either contactId or phone is required.'],
            ]);
        }

        $contact = $this->resolveContact($tenant, $contactId, $phone);

        return [
            'contact' => $contact,
            'calls' => $this->callStats->statsForContact($tenant, $user, $contact),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContact(Tenant $tenant, string $contactId, string $phone): array
    {
        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->resolveMyCrmSyncContact($tenant, $contactId, $phone);
        }

        if ($contactId !== '') {
            return $this->resolveExternalContactById($tenant, $contactId);
        }

        return $this->resolveExternalContactByPhone($tenant, $phone);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMyCrmSyncContact(Tenant $tenant, string $contactId, string $phone): array
    {
        if ($contactId !== '') {
            try {
                $contact = $this->contacts->findForTenant((int) $tenant->id, $contactId);
            } catch (ModelNotFoundException) {
                throw ValidationException::withMessages([
                    'contactId' => ['No contact was found for this id.'],
                ]);
            }

            return $this->mapper->mapContact($contact)->toArray();
        }

        $contact = $this->contacts->findByPhone((int) $tenant->id, $phone);

        if ($contact === null) {
            throw ValidationException::withMessages([
                'phone' => ['No contact was found for this phone number.'],
            ]);
        }

        return $this->mapper->mapContact($contact)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveExternalContactById(Tenant $tenant, string $contactId): array
    {
        if (CrmApiClientResolver::isZohoTenant($tenant)) {
            [$contact] = $this->zoho->getContact($tenant, $contactId);
        } else {
            [$contact] = $this->ghl->getContact($tenant, $contactId);
        }

        return $contact;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveExternalContactByPhone(Tenant $tenant, string $phone): array
    {
        if (CrmApiClientResolver::isZohoTenant($tenant)) {
            [$json] = $this->zoho->searchContacts($tenant, [
                'query' => $phone,
                'pageLimit' => 20,
            ]);
        } else {
            [$json] = $this->ghl->searchContacts($tenant, [
                'query' => $phone,
                'pageLimit' => 20,
                'locationId' => $this->ghl->defaultLocationId($tenant),
            ]);
        }

        $contacts = is_array($json['contacts'] ?? null) ? $json['contacts'] : [];
        $match = $this->bestPhoneMatch($contacts, $phone);

        if ($match === null) {
            throw ValidationException::withMessages([
                'phone' => ['No contact was found for this phone number.'],
            ]);
        }

        return $match;
    }

    /**
     * @param  list<array<string, mixed>>  $contacts
     * @return array<string, mixed>|null
     */
    private function bestPhoneMatch(array $contacts, string $phone): ?array
    {
        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $contactPhone = trim((string) ($contact['phone'] ?? ''));

            if ($contactPhone !== '' && PhoneNormalizer::digitsMatch($contactPhone, $phone)) {
                return $contact;
            }
        }

        foreach ($contacts as $contact) {
            if (is_array($contact)) {
                return $contact;
            }
        }

        return null;
    }
}

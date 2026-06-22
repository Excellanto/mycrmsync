<?php

namespace App\Integrations\Connectors\MyCrmSync;

use App\Integrations\MysimconnectApi\NormalizedCrmContact;
use App\Models\Contact;

/**
 * Map local MyCrmSync contact records to MysimConnect normalized contact JSON.
 */
final class MyCrmSyncContactMapper
{
    public function mapContact(Contact $contact): NormalizedCrmContact
    {
        $contact->loadMissing('assignedUser');

        $firstName = trim((string) $contact->first_name);
        $lastName = trim((string) $contact->last_name);
        $name = trim($firstName.' '.$lastName);
        $tags = is_array($contact->tags) ? $contact->tags : [];

        return new NormalizedCrmContact(
            id: (string) $contact->id,
            name: $name,
            firstName: $firstName,
            lastName: $lastName,
            companyName: trim((string) $contact->company_name),
            businessInfo: trim((string) $contact->business_info),
            email: trim((string) $contact->email),
            phone: trim((string) $contact->phone),
            source: trim((string) $contact->source),
            type: trim((string) $contact->type),
            assignedTo: $contact->assigned_to !== null ? (string) $contact->assigned_to : '',
            city: trim((string) $contact->city),
            state: trim((string) $contact->state),
            postalCode: trim((string) $contact->postal_code),
            address: trim((string) $contact->address),
            dateAdded: $contact->created_at?->toIso8601String() ?? '',
            dateUpdated: $contact->updated_at?->toIso8601String() ?? '',
            dateOfBirth: $contact->date_of_birth?->format('Y-m-d') ?? '',
            tags: NormalizedCrmContact::normalizeTagList($tags),
            country: trim((string) $contact->country),
            website: trim((string) $contact->website),
            timezone: trim((string) $contact->timezone),
            profilePhoto: trim((string) $contact->profile_photo),
        );
    }

    /**
     * @param  iterable<int, Contact>  $contacts
     * @return array{contacts: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function contactsEnvelope(iterable $contacts, int $total, ?int $limit = null): array
    {
        $rows = [];

        foreach ($contacts as $contact) {
            $rows[] = $this->mapContact($contact)->toArray();
        }

        $meta = ['total' => $total, 'count' => count($rows)];

        if ($limit !== null) {
            $meta['pageLimit'] = $limit;
        }

        return [
            'contacts' => $rows,
            'meta' => $meta,
        ];
    }
}

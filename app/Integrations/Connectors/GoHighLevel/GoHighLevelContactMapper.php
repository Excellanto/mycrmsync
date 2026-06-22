<?php

namespace App\Integrations\Connectors\GoHighLevel;

use App\Integrations\MysimconnectApi\NormalizedCrmContact;

/**
 * Map Lead Connector (GoHighLevel) contact payloads to MysimConnect normalized contact JSON.
 */
final class GoHighLevelContactMapper
{
    /**
     * @param  array<string, mixed>  $body  Upstream list/search JSON body
     * @return array{contacts: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function contactsEnvelopeFromResponse(array $body): array
    {
        $contacts = [];

        foreach ($this->iterContactRows($body) as $row) {
            $contacts[] = $this->mapContact($row)->toArray();
        }

        return [
            'contacts' => $contacts,
            'meta' => $this->metaFromBody($body),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function mapContact(array $row): NormalizedCrmContact
    {
        $tags = data_get($row, 'tags', []);
        $tagList = is_array($tags) ? $tags : [];

        $firstName = trim((string) data_get($row, 'firstName', ''));
        $lastName = trim((string) data_get($row, 'lastName', ''));
        $name = trim((string) data_get($row, 'name', ''));
        if ($name === '') {
            $name = trim($firstName.' '.$lastName);
        }

        return new NormalizedCrmContact(
            id: trim((string) data_get($row, 'id', '')),
            name: $name,
            firstName: $firstName,
            lastName: $lastName,
            companyName: trim((string) (data_get($row, 'companyName') ?: data_get($row, 'company_name') ?: '')),
            businessInfo: '',
            email: trim((string) data_get($row, 'email', '')),
            phone: trim((string) (data_get($row, 'phone') ?: data_get($row, 'phoneNumber') ?: '')),
            source: trim((string) (data_get($row, 'source') ?: data_get($row, 'contactSource') ?: '')),
            type: trim((string) data_get($row, 'type', '')),
            assignedTo: trim((string) (data_get($row, 'assignedTo') ?: data_get($row, 'assignedToUserId') ?: '')),
            city: trim((string) data_get($row, 'city', '')),
            state: trim((string) data_get($row, 'state', '')),
            postalCode: trim((string) (data_get($row, 'postalCode') ?: data_get($row, 'zip') ?: '')),
            address: trim((string) (data_get($row, 'address1') ?: data_get($row, 'address') ?: '')),
            dateAdded: trim((string) (data_get($row, 'dateAdded') ?: data_get($row, 'createdAt') ?: '')),
            dateUpdated: trim((string) (data_get($row, 'dateUpdated') ?: data_get($row, 'updatedAt') ?: '')),
            dateOfBirth: trim((string) data_get($row, 'dateOfBirth', '')),
            tags: NormalizedCrmContact::normalizeTagList($tagList),
            country: trim((string) data_get($row, 'country', '')),
            website: trim((string) data_get($row, 'website', '')),
            timezone: trim((string) data_get($row, 'timezone', '')),
            profilePhoto: trim((string) (data_get($row, 'profilePhoto') ?: data_get($row, 'photo') ?: '')),
        );
    }

    /**
     * @param  array<string, mixed>  $body
     * @return \Generator<int, array<string, mixed>>
     */
    private function iterContactRows(array $body): \Generator
    {
        $rows = data_get($body, 'contacts');

        if (! is_array($rows)) {
            $rows = data_get($body, 'data');
        }

        if (! is_array($rows)) {
            return;
        }

        foreach ($rows as $row) {
            if (is_array($row)) {
                yield $row;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function metaFromBody(array $body): array
    {
        $meta = data_get($body, 'meta');

        if (is_array($meta)) {
            return $meta;
        }

        $metaKeys = ['total', 'count', 'page', 'pageLimit', 'startAfterId', 'nextPageUrl'];
        $out = [];

        foreach ($metaKeys as $key) {
            $value = data_get($body, $key);
            if ($value !== null && $value !== '') {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}

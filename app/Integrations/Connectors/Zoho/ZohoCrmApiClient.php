<?php

namespace App\Integrations\Connectors\Zoho;

use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Zoho CRM adapter for MysimConnect's GoHighLevel-compatible `/api/crm` routes.
 */
final class ZohoCrmApiClient
{
    public function __construct(
        private ZohoCrmUsersConnector $auth,
        private ZohoCrmContactMapper $contactMapper = new ZohoCrmContactMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function listContacts(Tenant $tenant, array $query = []): array
    {
        $response = $this->request($tenant, 'GET', '/Contacts', $this->contactListQuery($query));
        $json = $this->successfulJson($response);

        return [$this->contactsEnvelope($json), $response->status()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function searchContacts(Tenant $tenant, array $body = []): array
    {
        $query = $this->contactListQuery($body);
        $term = trim((string) ($body['query'] ?? $body['search'] ?? $body['q'] ?? ''));

        if ($term === '') {
            return $this->listContacts($tenant, $query);
        }

        $query['word'] = $term;
        $response = $this->request($tenant, 'GET', '/Contacts/search', $query);
        $json = $this->successfulJson($response);

        return [$this->contactsEnvelope($json), $response->status()];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function getContact(Tenant $tenant, string $contactId, array $query = []): array
    {
        $response = $this->request($tenant, 'GET', "/Contacts/{$contactId}", $this->contactListQuery($query));
        $json = $this->successfulJson($response);
        $row = data_get($json, 'data.0');

        if (! is_array($row)) {
            throw new RuntimeException('Contact not found.', 404);
        }

        return [$this->contactMapper->mapContact($row)->toArray(), $response->status()];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function listUsers(Tenant $tenant, array $query = []): array
    {
        $response = $this->request($tenant, 'GET', '/users', [
            'type' => $query['type'] ?? 'AllUsers',
            'page' => $this->positiveInt($query['page'] ?? null, 1),
            'per_page' => $this->positiveInt($query['limit'] ?? $query['per_page'] ?? null, 200),
        ]);
        $json = $this->successfulJson($response);

        $users = [];
        foreach ($this->rowsFrom($json, 'users') as $row) {
            $users[] = $this->mapUser($row);
        }

        return [[
            'users' => $users,
            'meta' => $this->metaFromZohoInfo($json),
        ], $response->status()];
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function listLocationTags(Tenant $tenant): array
    {
        $response = $this->request($tenant, 'GET', '/settings/tags', [
            'module' => 'Contacts',
        ]);
        $json = $this->successfulJson($response);

        $tags = [];
        foreach ($this->tagRowsFromSettingsResponse($json) as $row) {
            $tags[] = [
                'id' => (string) data_get($row, 'id', data_get($row, 'name', '')),
                'name' => (string) data_get($row, 'name', data_get($row, 'id', '')),
            ];
        }

        return [['tags' => $tags, 'meta' => $this->metaFromZohoInfo($json)], $response->status()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array{success: bool, status: bool, contact: array<string, mixed>}, 1: int}
     */
    public function createContact(Tenant $tenant, array $body): array
    {
        $record = $this->createContactRecord($body);
        $response = $this->request($tenant, 'POST', '/Contacts', json: [
            'data' => [$record],
        ]);
        $json = $this->successfulJson($response);
        $contactId = trim((string) data_get($json, 'data.0.details.id', ''));

        if ($contactId === '') {
            $message = trim((string) data_get($json, 'data.0.message', ''));

            throw new RuntimeException(
                $message !== '' ? $message : 'Contact creation failed.',
                502
            );
        }

        [$contact, $status] = $this->getContact($tenant, $contactId);

        return [[
            'success' => true,
            'status' => true,
            'contact' => $contact,
        ], $status];
    }

    /**
     * @return array{0: array{success: bool, status: bool, message: string, contactId: string}, 1: int}
     */
    public function deleteContact(Tenant $tenant, string $contactId): array
    {
        if (preg_match('/^\d+$/', $contactId) !== 1) {
            throw new RuntimeException('The selected user is linked to Zoho. Use a Zoho contact id from /api/crm/contacts or /api/crm/contacts/search.', 422);
        }

        $response = $this->request($tenant, 'DELETE', '/Contacts', [
            'ids' => $contactId,
        ]);
        $json = $this->successfulJson($response);
        $code = strtoupper(trim((string) data_get($json, 'data.0.code', 'SUCCESS')));

        if ($code !== 'SUCCESS') {
            $message = trim((string) data_get($json, 'data.0.message', ''));

            throw new RuntimeException(
                $message !== '' ? $message : 'Contact deletion failed.',
                502
            );
        }

        return [[
            'success' => true,
            'status' => true,
            'message' => 'Contact deleted.',
            'contactId' => $contactId,
        ], $response->status() === 204 ? 200 : $response->status()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function addContactTags(Tenant $tenant, string $contactId, array $body): array
    {
        [, $status] = $this->contactTagsAction($tenant, $contactId, 'add_tags', $body);

        return [$this->contactTagsUpdateEnvelope($tenant, $contactId), $status];
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function listContactNotes(Tenant $tenant, string $contactId, array $query = [], array $defaults = []): array
    {
        if (preg_match('/^\d+$/', $contactId) !== 1) {
            throw new RuntimeException('The selected user is linked to Zoho. Use a Zoho contact id from /api/crm/contacts or /api/crm/contacts/search.', 422);
        }

        $response = $this->request($tenant, 'GET', "/Contacts/{$contactId}/Notes", $this->notesListQuery($query));
        $json = $this->successfulJson($response);

        return [$this->notesEnvelope($json, $defaults), $response->status() === 204 ? 200 : $response->status()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function createContactNote(Tenant $tenant, string $contactId, array $body, array $defaults = []): array
    {
        $content = trim((string) ($body['body'] ?? $body['Note_Content'] ?? ''));
        $title = trim((string) ($body['title'] ?? $body['Note_Title'] ?? 'Note'));
        $note = [
            'Note_Title' => $title !== '' ? $title : 'Note',
            'Note_Content' => $content,
        ];

        $ownerId = trim((string) ($body['userId'] ?? data_get($body, 'Owner.id', '')));
        if ($ownerId !== '') {
            $note['Owner'] = ['id' => $ownerId];
        }

        $response = $this->request($tenant, 'POST', "/Contacts/{$contactId}/Notes", json: [
            'data' => [$note],
        ]);
        $this->successfulJson($response);

        return $this->listContactNotes($tenant, $contactId, [], $defaults);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function updateContactNote(Tenant $tenant, string $contactId, string $noteId, array $body): array
    {
        $note = [];
        if (array_key_exists('body', $body)) {
            $note['Note_Content'] = (string) $body['body'];
        }
        if (array_key_exists('title', $body)) {
            $note['Note_Title'] = (string) $body['title'];
        }

        $response = $this->request($tenant, 'PATCH', "/Notes/{$noteId}", json: [
            'data' => [$note],
        ]);
        $this->successfulJson($response);

        return $this->listContactNotes($tenant, $contactId);
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function deleteContactNote(Tenant $tenant, string $noteId): array
    {
        $response = $this->request($tenant, 'DELETE', "/Notes/{$noteId}");
        $json = $this->successfulJson($response);

        return [$this->notesEnvelope($json), $response->status()];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     */
    private function request(Tenant $tenant, string $method, string $path, array $query = [], ?array $json = null): Response
    {
        [$accessToken, $base, $version] = $this->credentialsFromTenant($tenant);
        $path = '/'.ltrim($path, '/');
        $url = "{$base}/crm/{$version}{$path}";

        $request = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken '.trim($accessToken),
            'Accept' => 'application/json',
        ])
            ->timeout(45)
            ->connectTimeout(10);

        if ($json !== null) {
            $request = $request->asJson();
        }

        return $request->send(strtoupper($method), $url, array_filter([
            'query' => array_filter($query, fn ($value): bool => $value !== null && $value !== ''),
            'json' => $json,
        ], fn ($value): bool => $value !== null && $value !== []));
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function credentialsFromTenant(Tenant $tenant): array
    {
        $integration = $tenant->integration;

        if (! is_array($integration) || (string) ($integration['slug'] ?? '') !== ZohoCrmUsersConnector::integrationSlug()) {
            throw new RuntimeException('The selected user is not linked to a Zoho tenant integration.', 422);
        }

        [$credentials] = $this->auth->authorizedCrmCredentials($integration, $tenant);

        return [
            $credentials['access_token'],
            rtrim($credentials['crm_api_base'], '/'),
            trim((string) config('services.zoho.crm_api_version', 'v8'), '/'),
        ];
    }

    private function successfulJson(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException($this->formatHttpErrorMessage($response), $response->status());
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * Zoho CRM Get Records returns "One of the expected parameter is missing" when `fields` is omitted.
     *
     * @param  array<string, mixed>  $source  Query/body forwarded from the parity layer.
     * @return array<string, mixed>
     */
    private function contactListQuery(array $source): array
    {
        return [
            'fields' => $this->contactsFieldsParam($source),
            'page' => $this->positiveInt($source['page'] ?? null, 1),
            'per_page' => $this->positiveInt($source['limit'] ?? $source['pageLimit'] ?? $source['per_page'] ?? null, 20),
        ];
    }

    /**
     * Comma-separated Zoho Contacts API field names (max 50 for one request).
     *
     * @param  array<string, mixed>  $source
     */
    private function contactsFieldsParam(array $source): string
    {
        $raw = $source['fields'] ?? null;
        if (is_string($raw)) {
            $trimmed = trim($raw);

            return $trimmed !== '' ? $trimmed : $this->defaultContactsListFieldsCsv();
        }

        return $this->defaultContactsListFieldsCsv();
    }

    /**
     * Zoho related Notes API requires `fields` even when only listing notes.
     *
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    private function notesListQuery(array $source): array
    {
        return [
            'fields' => $this->notesFieldsParam($source),
            'page' => $this->positiveInt($source['page'] ?? null, 1),
            'per_page' => $this->positiveInt($source['limit'] ?? $source['pageLimit'] ?? $source['per_page'] ?? null, 200),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function notesFieldsParam(array $source): string
    {
        $raw = $source['fields'] ?? null;
        if (is_string($raw)) {
            $trimmed = trim($raw);

            return $trimmed !== '' ? $trimmed : $this->defaultNotesListFieldsCsv();
        }

        return $this->defaultNotesListFieldsCsv();
    }

    /**
     * @return non-empty-string
     */
    private function defaultContactsListFieldsCsv(): string
    {
        return implode(',', [
            'id',
            'First_Name',
            'Last_Name',
            'Account_Name',
            'Email',
            'Phone',
            'Mobile',
            'Lead_Source',
            'Contact_Type',
            'Owner',
            'Mailing_City',
            'Mailing_State',
            'Mailing_Zip',
            'Mailing_Street',
            'Mailing_Country',
            'Website',
            'Created_Time',
            'Modified_Time',
            'Date_of_Birth',
            'Tag',
        ]);
    }

    /**
     * @return non-empty-string
     */
    private function defaultNotesListFieldsCsv(): string
    {
        return implode(',', [
            'id',
            'Owner',
            'Created_Time',
            'Modified_Time',
            'Note_Title',
            'Note_Content',
            'Parent_Id',
            'Created_By',
            'Modified_By',
        ]);
    }

    private function positiveInt(mixed $value, int $default): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($int) && $int > 0 ? $int : $default;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function contactsEnvelope(array $json): array
    {
        $contacts = [];
        foreach ($this->rowsFrom($json) as $row) {
            $contacts[] = $this->mapContact($row);
        }

        return [
            'contacts' => $contacts,
            'meta' => $this->metaFromZohoInfo($json),
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function notesEnvelope(array $json, array $defaults = []): array
    {
        $notes = [];
        foreach ($this->rowsFrom($json) as $row) {
            $notes[] = $this->mapNote($row, $defaults);
        }

        return [
            'success' => true,
            'status' => true,
            'notes' => $notes,
            'meta' => $this->metaFromZohoInfo($json),
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    private function tagRowsFromSettingsResponse(array $json): array
    {
        foreach (['tags', 'data'] as $key) {
            $rows = data_get($json, $key);

            if (is_array($rows) && $rows !== []) {
                return array_values(array_filter($rows, 'is_array'));
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<array<string, mixed>>
     */
    private function rowsFrom(array $json, string $key = 'data'): array
    {
        $rows = data_get($json, $key);

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function metaFromZohoInfo(array $json): array
    {
        $info = data_get($json, 'info');

        return is_array($info) ? $info : [];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapContact(array $row): array
    {
        return $this->contactMapper->mapContact($row)->toArray();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapUser(array $row): array
    {
        $firstName = trim((string) data_get($row, 'first_name', ''));
        $lastName = trim((string) data_get($row, 'last_name', ''));
        $name = trim((string) data_get($row, 'full_name', trim($firstName.' '.$lastName)));
        $role = data_get($row, 'role.name', data_get($row, 'profile.name', ''));

        return [
            'id' => (string) data_get($row, 'id', ''),
            'name' => $name !== '' ? $name : '(no name)',
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => trim((string) data_get($row, 'email', '')),
            'phone' => trim((string) (data_get($row, 'phone') ?: data_get($row, 'mobile') ?: '')),
            'role' => is_string($role) ? $role : '',
            'raw' => $row,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapNote(array $row, array $defaults = []): array
    {
        $body = (string) (data_get($row, 'Note_Content') ?: data_get($row, 'message') ?: data_get($row, 'body') ?: '');
        $attachments = $this->noteAttachments($row, $body);

        $rowUserId = (string) data_get($row, 'Owner.id', '');
        $rowUserName = (string) (
            data_get($row, 'Owner.name')
                ?: data_get($row, 'Created_By.name')
                ?: data_get($row, 'Modified_By.name')
                ?: ''
        );

        $defaultUserId = trim((string) ($defaults['userId'] ?? ''));
        $defaultUserName = trim((string) ($defaults['user_name'] ?? ''));
        $overrideOwner = (bool) ($defaults['overrideOwner'] ?? false);
        $userNamesById = is_array($defaults['userNamesById'] ?? null) ? $defaults['userNamesById'] : [];

        if ($overrideOwner && $defaultUserId !== '') {
            $userId = $defaultUserId;
            $userName = $defaultUserName !== '' ? $defaultUserName : $rowUserName;
        } else {
            $userId = $rowUserId !== '' ? $rowUserId : $defaultUserId;
            $userName = $rowUserName !== '' ? $rowUserName : $defaultUserName;
        }

        if ($userName === '' && $userId !== '' && isset($userNamesById[$userId])) {
            $userName = (string) $userNamesById[$userId];
        }

        return [
            'id' => (string) data_get($row, 'id', ''),
            'body' => $this->noteBodyWithoutAttachments($body, $attachments),
            'attachments' => $attachments,
            'title' => (string) data_get($row, 'Note_Title', ''),
            'user_name' => $userName,
            'userId' => $userId,
            'contactId' => (string) (
                data_get($row, 'Parent_Id.id')
                    ?: data_get($row, 'Parent_Id')
                    ?: data_get($row, 'contactId')
                    ?: data_get($defaults, 'contactId')
                    ?: ''
            ),
            'dateAdded' => (string) data_get($row, 'Created_Time', ''),
            'dateUpdated' => (string) (
                data_get($row, 'Modified_Time')
                    ?: data_get($defaults, 'dateUpdated')
                    ?: ''
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function noteAttachments(array $row, string $body): array
    {
        $urls = [];

        foreach (['attachments', 'files', 'urls'] as $key) {
            $urls = array_merge($urls, $this->urlsFromMixed(data_get($row, $key)));
        }

        $urls = array_merge($urls, $this->urlsFromText($body));

        return array_values(array_unique($urls));
    }

    /**
     * @param  list<string>  $attachments
     */
    private function noteBodyWithoutAttachments(string $body, array $attachments): string
    {
        $clean = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        foreach ($attachments as $url) {
            $clean = preg_replace('~\s*'.preg_quote($url, '~').'\s*~', "\n", $clean) ?? $clean;
        }

        $clean = preg_replace('/^\s*Attachment:\s*.+$/mi', '', $clean) ?? $clean;
        $clean = preg_replace('/^\s*(attachments?|files?)\s*:?\s*$/mi', '', $clean) ?? $clean;
        $clean = preg_replace("/[ \t]+\r?\n/", "\n", $clean) ?? $clean;
        $clean = preg_replace("/\r\n|\r/", "\n", $clean) ?? $clean;
        $clean = preg_replace("/\n{3,}/", "\n\n", $clean) ?? $clean;

        return trim($clean);
    }

    /**
     * @return list<string>
     */
    private function urlsFromMixed(mixed $value): array
    {
        if (is_string($value)) {
            $url = trim($value);

            return filter_var($url, FILTER_VALIDATE_URL) ? [$url] : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $urls = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                foreach (['url', 'URL', 'link', 'fileUrl', 'downloadUrl', 'href'] as $key) {
                    $urls = array_merge($urls, $this->urlsFromMixed(data_get($item, $key)));
                }

                continue;
            }

            $urls = array_merge($urls, $this->urlsFromMixed($item));
        }

        return $urls;
    }

    /**
     * @return list<string>
     */
    private function urlsFromText(string $text): array
    {
        preg_match_all('~https?://[^\s<>"\']+~i', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $matches);

        return array_values(array_filter(
            array_map(
                fn (string $url): string => rtrim($url, ".,;:)]}"),
                $matches[0] ?? []
            ),
            fn (string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false
        ));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function contactTagsAction(Tenant $tenant, string $contactId, string $action, array $body): array
    {
        $response = $this->request($tenant, 'POST', "/Contacts/{$contactId}/actions/{$action}", json: [
            'tags' => $this->tagPayload($body['tags'] ?? []),
        ]);
        $json = $this->successfulJson($response);

        return [$json !== [] ? $json : ['success' => true], $response->status()];
    }

    /**
     * @return array{tags: list<string>, message: string, status: bool}
     */
    private function contactTagsUpdateEnvelope(Tenant $tenant, string $contactId): array
    {
        $response = $this->request($tenant, 'GET', "/Contacts/{$contactId}", [
            'fields' => 'Tag',
        ]);
        $json = $this->successfulJson($response);

        return [
            'tags' => $this->contactTagsFromBody($json),
            'message' => 'tags updated',
            'status' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return list<string>
     */
    private function contactTagsFromBody(array $body): array
    {
        $row = data_get($body, 'data.0');

        if (is_array($row)) {
            return $this->contactMapper->mapContact($row)->tags;
        }

        if (array_key_exists('Tag', $body)) {
            return $this->contactMapper->mapContact($body)->tags;
        }

        return [];
    }

    /**
     * @return list<array<string, string>>
     */
    private function tagPayload(mixed $tags): array
    {
        if (! is_array($tags)) {
            return [];
        }

        $payload = [];
        foreach ($tags as $tag) {
            if (is_array($tag)) {
                $id = trim((string) data_get($tag, 'id', ''));
                $name = trim((string) data_get($tag, 'name', ''));
            } else {
                $id = '';
                $name = trim((string) $tag);
            }

            if ($id !== '') {
                $payload[] = ['id' => $id];
            } elseif ($name !== '') {
                $payload[] = ['name' => $name];
            }
        }

        return $payload;
    }

    private function formatHttpErrorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $msg = data_get($json, 'message')
                ?? data_get($json, 'error_description')
                ?? data_get($json, 'error')
                ?? data_get($json, 'data.0.message')
                ?? data_get($json, 'data.0.details.api_name');

            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }

        return 'Zoho CRM API request failed (HTTP '.$response->status().').';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function createContactRecord(array $body): array
    {
        $firstName = trim((string) ($body['firstName'] ?? $body['first_name'] ?? ''));
        $lastName = trim((string) ($body['lastName'] ?? $body['last_name'] ?? ''));

        if ($lastName === '') {
            $lastName = $firstName !== '' ? $firstName : 'Contact';
        }

        $record = array_filter([
            'First_Name' => $firstName,
            'Last_Name' => $lastName,
            'Email' => trim((string) ($body['email'] ?? '')),
            'Phone' => trim((string) ($body['phone'] ?? '')),
            'Account_Name' => trim((string) ($body['companyName'] ?? $body['company_name'] ?? '')),
            'Mailing_Street' => trim((string) ($body['address'] ?? '')),
            'Mailing_City' => trim((string) ($body['city'] ?? '')),
            'Mailing_State' => trim((string) ($body['state'] ?? '')),
            'Mailing_Zip' => trim((string) ($body['postalCode'] ?? $body['postal_code'] ?? $body['pincode'] ?? '')),
            'Mailing_Country' => trim((string) ($body['country'] ?? '')),
            'Website' => trim((string) ($body['website'] ?? '')),
            'Lead_Source' => trim((string) ($body['source'] ?? '')),
            'Contact_Type' => trim((string) ($body['type'] ?? '')),
        ], fn ($value): bool => $value !== '' && $value !== null);

        $ownerId = trim((string) ($body['assignedTo'] ?? $body['assigned_to'] ?? ''));
        if ($ownerId !== '') {
            $record['Owner'] = ['id' => $ownerId];
        }

        $tags = $body['tags'] ?? null;
        if (is_array($tags) && $tags !== []) {
            $record['Tag'] = array_values(array_filter(array_map(
                fn ($tag) => ['name' => trim((string) $tag)],
                $tags
            ), fn (array $tag): bool => $tag['name'] !== ''));
        }

        return $record;
    }
}

<?php

namespace App\Integrations\Connectors\GoHighLevel;

use App\Models\Integration;
use App\Models\Tenant;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class GoHighLevelApiClient
{
    public function __construct(
        private GoHighLevelContactMapper $contactMapper = new GoHighLevelContactMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function listContacts(Tenant $tenant, array $query = []): array
    {
        $response = $this->request($tenant, 'GET', '/contacts/', $query);
        $json = $this->successfulJson($response);

        return [$this->contactMapper->contactsEnvelopeFromResponse($json), $response->status()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function searchContacts(Tenant $tenant, array $body = []): array
    {
        $response = $this->request($tenant, 'POST', '/contacts/search', json: $body);
        $json = $this->successfulJson($response);

        return [$this->contactMapper->contactsEnvelopeFromResponse($json), $response->status()];
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function getContact(Tenant $tenant, string $contactId): array
    {
        $response = $this->request($tenant, 'GET', "/contacts/{$contactId}");
        $json = $this->successfulJson($response);
        $row = data_get($json, 'contact');

        if (! is_array($row)) {
            throw new RuntimeException('Contact not found.', 404);
        }

        return [$this->contactMapper->mapContact($row)->toArray(), $response->status()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array{success: bool, status: bool, contact: array<string, mixed>}, 1: int}
     */
    public function createContact(Tenant $tenant, array $body): array
    {
        $payload = $this->createContactPayload($tenant, $body);
        $response = $this->request(
            $tenant,
            'POST',
            '/contacts/',
            json: $payload,
            versionOverride: '2021-07-28',
        );
        $json = $this->successfulJson($response);
        $row = data_get($json, 'contact');

        if (! is_array($row)) {
            throw new RuntimeException('Contact creation failed.', 502);
        }

        return [[
            'success' => true,
            'status' => true,
            'contact' => $this->contactMapper->mapContact($row)->toArray(),
        ], $response->status()];
    }

    /**
     * @return array{0: array{success: bool, status: bool, message: string, contactId: string}, 1: int}
     */
    public function deleteContact(Tenant $tenant, string $contactId): array
    {
        $response = $this->request(
            $tenant,
            'DELETE',
            "/contacts/{$contactId}",
            versionOverride: '2021-07-28',
        );
        $this->successfulJson($response);

        return [[
            'success' => true,
            'status' => true,
            'message' => 'Contact deleted.',
            'contactId' => $contactId,
        ], $response->status()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array{tags: list<string>, message: string, status: bool}, 1: int}
     */
    public function addContactTags(Tenant $tenant, string $contactId, array $body): array
    {
        $response = $this->request($tenant, 'POST', "/contacts/{$contactId}/tags", json: $body);
        $this->successfulJson($response);

        return [$this->contactTagsUpdateEnvelope($tenant, $contactId), $response->status()];
    }

    /**
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function listContactNotes(Tenant $tenant, string $contactId, array $defaults = []): array
    {
        $response = $this->request(
            $tenant,
            'GET',
            "/contacts/{$contactId}/notes",
            versionOverride: '2021-07-28',
        );
        $json = $this->successfulJson($response);

        return [$this->notesEnvelope($json, ['contactId' => $contactId, ...$defaults]), $response->status()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function createContactNote(Tenant $tenant, string $contactId, array $body, array $defaults = []): array
    {
        $payload = [
            'body' => (string) ($body['body'] ?? ''),
        ];

        $userId = trim((string) ($body['userId'] ?? ''));
        if ($userId !== '') {
            $payload['userId'] = $userId;
        }

        $response = $this->request(
            $tenant,
            'POST',
            "/contacts/{$contactId}/notes",
            json: $payload,
            versionOverride: '2021-07-28',
        );
        $this->successfulJson($response);

        return $this->listContactNotes($tenant, $contactId, $defaults);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function updateContactNote(Tenant $tenant, string $contactId, string $noteId, array $body, array $defaults = []): array
    {
        $payload = [
            'body' => (string) ($body['body'] ?? ''),
        ];

        $response = $this->request(
            $tenant,
            'PUT',
            "/contacts/{$contactId}/notes/{$noteId}",
            json: $payload,
            versionOverride: '2021-07-28',
        );
        $this->successfulJson($response);

        return $this->listContactNotes($tenant, $contactId, $defaults);
    }

    /**
     * GET /locations/{locationId}/tags — normalized for parity with Zoho `{ tags, meta }` on `/api/crm/tags`.
     *
     * @return array{0: array{tags: list<array{id: string, name: string}>, meta: array<string, mixed>}, 1: int}
     */
    public function listLocationTags(Tenant $tenant): array
    {
        [, $locationId] = $this->credentialsFromTenant($tenant);

        $response = $this->request($tenant, 'GET', "/locations/{$locationId}/tags");
        $json = $this->successfulJson($response);

        return [[
            'tags' => $this->normalizedLocationTagsPayload($json),
            'meta' => $this->metaFromTagsBody($json),
        ], $response->status()];
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $json
     */
    public function request(
        Tenant $tenant,
        string $method,
        string $path,
        array $query = [],
        ?array $json = null,
        ?string $versionOverride = null,
    ): Response {
        [$token, $locationId] = $this->credentialsFromTenant($tenant);

        if ($token === '') {
            throw new RuntimeException('GoHighLevel API token is missing. Save the tenant integration settings first.', 422);
        }

        if ($locationId === '') {
            throw new RuntimeException('GoHighLevel location id is missing. Save the tenant integration settings first.', 422);
        }

        $method = strtoupper($method);
        $path = '/'.ltrim($path, '/');
        $base = rtrim(config('services.gohighlevel.api_base', 'https://services.leadconnectorhq.com'), '/');
        $version = $versionOverride ?: config('services.gohighlevel.api_version', '2023-02-21');

        $query = array_filter(
            $query,
            fn ($value): bool => $value !== null && $value !== ''
        );

        $request = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json',
                'Version' => $version,
            ])
            ->timeout(45)
            ->connectTimeout(10);

        if ($json !== null) {
            $request = $request->asJson();
        }

        return $request->send($method, "{$base}{$path}", array_filter([
            'query' => $query,
            'json' => $json,
        ], fn ($value): bool => $value !== null && $value !== []));
    }

    public function defaultLocationId(Tenant $tenant): string
    {
        return $this->credentialsFromTenant($tenant)[1];
    }

    /**
     * @return array{0: string, 1: string} [accessToken, locationId]
     */
    private function credentialsFromTenant(Tenant $tenant): array
    {
        $integration = $tenant->integration;

        if (! is_array($integration) || (string) ($integration['slug'] ?? '') !== GoHighLevelCrmUsersConnector::integrationSlug()) {
            throw new RuntimeException('The selected user is not linked to a GoHighLevel tenant integration.', 422);
        }

        $values = isset($integration['values']) && is_array($integration['values'])
            ? $integration['values']
            : [];

        $token = '';
        $locationId = '';

        $model = Integration::query()->where('slug', GoHighLevelCrmUsersConnector::integrationSlug())->first();

        if ($model !== null) {
            foreach ($model->fieldSpecs() as $spec) {
                $key = $spec['key'];
                $label = mb_strtolower($spec['label']);
                $raw = $values[$key] ?? null;
                $val = is_string($raw) ? trim($raw) : '';

                if ($val === '') {
                    continue;
                }

                if (str_contains($label, 'location')) {
                    $locationId = $val;
                }

                if (preg_match('/(api|key|token|secret|pit|bearer)/', $label) === 1) {
                    $token = $val;
                }
            }
        }

        if ($token === '') {
            $token = isset($values['api_key']) && is_string($values['api_key'])
                ? trim($values['api_key'])
                : '';
        }

        if ($locationId === '') {
            foreach (['location_id', 'locationid', 'locationId'] as $key) {
                if (! empty($values[$key]) && is_string($values[$key])) {
                    $locationId = trim($values[$key]);

                    break;
                }
            }
        }

        return [$token, $locationId];
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
     * @param  array<string, mixed>  $body
     * @return list<array{id: string, name: string}>
     */
    private function normalizedLocationTagsPayload(array $body): array
    {
        $raw = data_get($body, 'tags');

        if (! is_array($raw)) {
            $raw = data_get($body, 'data');
        }

        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        foreach ($raw as $row) {
            if (is_string($row)) {
                $t = trim($row);

                if ($t !== '') {
                    $out[] = ['id' => $t, 'name' => $t];
                }

                continue;
            }

            if (! is_array($row)) {
                continue;
            }

            $id = trim((string) (
                data_get($row, 'id')
                    ?? data_get($row, '_id')
                    ?? data_get($row, 'tagId')
                    ?? ''
            ));
            $name = trim((string) (
                data_get($row, 'name')
                    ?? data_get($row, 'tag')
                    ?? data_get($row, 'label')
                    ?? ''
            ));

            if ($id === '' && $name === '') {
                continue;
            }

            $out[] = [
                'id' => $id !== '' ? $id : $name,
                'name' => $name !== '' ? $name : $id,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function metaFromTagsBody(array $body): array
    {
        $meta = data_get($body, 'meta');

        if (is_array($meta)) {
            return $meta;
        }

        $nested = data_get($body, 'traceId');

        if (is_string($nested)) {
            return ['traceId' => $nested];
        }

        return [];
    }

    /**
     * @return array{tags: list<string>, message: string, status: bool}
     */
    private function contactTagsUpdateEnvelope(Tenant $tenant, string $contactId): array
    {
        $response = $this->request($tenant, 'GET', "/contacts/{$contactId}");
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
        $contact = data_get($body, 'contact');
        if (is_array($contact)) {
            return $this->contactMapper->mapContact($contact)->tags;
        }

        if (array_key_exists('tags', $body)) {
            return $this->contactMapper->mapContact($body)->tags;
        }

        foreach (['contacts', 'data'] as $key) {
            $rows = data_get($body, $key);

            if (is_array($rows) && isset($rows[0]) && is_array($rows[0])) {
                return $this->contactMapper->mapContact($rows[0])->tags;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{success: bool, status: bool, notes: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    private function notesEnvelope(array $body, array $defaults = []): array
    {
        $rows = data_get($body, 'notes');

        if (! is_array($rows)) {
            $rows = data_get($body, 'data');
        }

        if (! is_array($rows)) {
            $rows = [];
        }

        $notes = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $notes[] = $this->mapNote($row, $defaults);
            }
        }

        return [
            'success' => true,
            'status' => true,
            'notes' => $notes,
            'meta' => $this->metaFromNotesBody($body),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function mapNote(array $row, array $defaults = []): array
    {
        $body = (string) (data_get($row, 'body') ?: data_get($row, 'message') ?: data_get($row, 'note') ?: '');
        $attachments = $this->noteAttachments($row, $body);

        $rowUserId = (string) (data_get($row, 'userId') ?: '');
        $rowUserName = (string) (
            data_get($row, 'userName')
                ?: data_get($row, 'user_name')
                ?: data_get($row, 'user.name')
                ?: data_get($row, 'createdBy.name')
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
            'title' => (string) (data_get($row, 'title') ?: data_get($row, 'name') ?: ''),
            'user_name' => $userName,
            'userId' => $userId,
            'contactId' => (string) (data_get($row, 'contactId') ?: data_get($defaults, 'contactId') ?: ''),
            'dateAdded' => (string) (data_get($row, 'dateAdded') ?: data_get($row, 'createdAt') ?: ''),
            'dateUpdated' => (string) (
                data_get($row, 'dateUpdated')
                    ?: data_get($row, 'dateModified')
                    ?: data_get($row, 'updatedAt')
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
     * @return array<string, mixed>
     */
    private function metaFromNotesBody(array $body): array
    {
        $meta = data_get($body, 'meta');

        if (is_array($meta)) {
            return $meta;
        }

        $traceId = data_get($body, 'traceId');

        if (is_string($traceId) && $traceId !== '') {
            return ['traceId' => $traceId];
        }

        return [];
    }

    private function formatHttpErrorMessage(Response $response): string
    {
        $json = $response->json();

        if (is_array($json)) {
            $msg = data_get($json, 'message') ?? data_get($json, 'error') ?? data_get($json, 'msg');

            if (is_string($msg) && $msg !== '') {
                return $msg;
            }
        }

        return 'GoHighLevel API request failed (HTTP '.$response->status().').';
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function createContactPayload(Tenant $tenant, array $body): array
    {
        $email = trim((string) ($body['email'] ?? ''));
        $phone = trim((string) ($body['phone'] ?? ''));

        if ($email === '' && $phone === '') {
            throw new RuntimeException('Either email or phone is required.', 422);
        }

        $payload = array_filter([
            'locationId' => $this->defaultLocationId($tenant),
            'firstName' => trim((string) ($body['firstName'] ?? $body['first_name'] ?? '')),
            'lastName' => trim((string) ($body['lastName'] ?? $body['last_name'] ?? '')),
            'email' => $email,
            'phone' => $phone,
            'companyName' => trim((string) ($body['companyName'] ?? $body['company_name'] ?? '')),
            'address1' => trim((string) ($body['address'] ?? '')),
            'city' => trim((string) ($body['city'] ?? '')),
            'state' => trim((string) ($body['state'] ?? '')),
            'postalCode' => trim((string) ($body['postalCode'] ?? $body['postal_code'] ?? $body['pincode'] ?? '')),
            'country' => trim((string) ($body['country'] ?? '')),
            'website' => trim((string) ($body['website'] ?? '')),
            'source' => trim((string) ($body['source'] ?? '')),
            'timezone' => trim((string) ($body['timezone'] ?? '')),
        ], fn ($value): bool => $value !== '' && $value !== null);

        $assignedTo = trim((string) ($body['assignedTo'] ?? $body['assigned_to'] ?? ''));
        if ($assignedTo !== '') {
            $payload['assignedTo'] = $assignedTo;
        }

        $tags = $body['tags'] ?? null;
        if (is_array($tags) && $tags !== []) {
            $payload['tags'] = array_values(array_filter(array_map(
                fn ($tag) => trim((string) $tag),
                $tags
            )));
        }

        return $payload;
    }
}

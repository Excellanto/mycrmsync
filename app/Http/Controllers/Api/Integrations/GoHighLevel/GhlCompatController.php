<?php

namespace App\Http\Controllers\Api\Integrations\GoHighLevel;

use App\Http\Controllers\Controller;
use App\Integrations\CrmApiClientResolver;
use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\MyCrmSync\MyCrmSyncCrmApiClient;
use App\Integrations\Connectors\Zoho\ZohoCrmApiClient;
use App\Integrations\FetchTenantIntegrationCrmUsers;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Contacts\ContactCallStatsService;
use App\Services\VoiceNote\ContactNoteListEnricher;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @group CRM
 *
 * Internal routes mirroring Lead Connector (GoHighLevel) API paths and payloads under `/api/crm`.
 * External reference: https://services.leadconnectorhq.com/
 *
 * Requires `Authorization: Bearer <token>` from email OTP verify. `user_id` must match the authenticated user.
 *
 * @authenticated
 */
final class GhlCompatController extends Controller
{
    public function __construct(
        private GoHighLevelApiClient $ghl,
        private ZohoCrmApiClient $zoho,
        private MyCrmSyncCrmApiClient $myCrmSync,
        private FetchTenantIntegrationCrmUsers $fetchTenantIntegrationCrmUsers,
        private ContactNoteListEnricher $contactNoteListEnricher,
        private ContactCallStatsService $contactCallStats,
    ) {}

    /**
     * List contacts
     *
     * @queryParam user_id int required Must match the authenticated user (same as OTP verify response). Example: 1
     * @queryParam limit int Optional. Page size 1–100.
     * @queryParam fields string Optional. **Zoho only:** comma-separated Zoho Contacts API field names (max 50). Defaults to fields required for MysimConnect’s normalized contact shape. Example: First_Name,Last_Name,Email
     *
     * @response 200 {"contacts":[{"id":"550e8400-e29b-41d4-a716-446655440000","name":"Jane Doe","phone":"+919910023290","calls":{"total_dialed":4,"total_talk_time":620,"total_received":3,"total_notes":2}}],"meta":{"total":1}}
     */
    public function listContacts(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->proxyContacts(fn () => $this->myCrmSync->listContacts(
                $tenant,
                $this->queryWithoutLocalContext($request, $tenant)
            ), $tenant, $user);
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyContacts(fn () => $this->zoho->listContacts(
                $tenant,
                $this->queryWithoutLocalContext($request, $tenant)
            ), $tenant, $user);
        }

        return $this->proxyContacts(fn () => $this->ghl->listContacts(
            $tenant,
            $this->queryWithoutLocalContext($request, $tenant, defaultLocationId: true)
        ), $tenant, $user);
    }

    /**
     * Search contacts
     *
     * @bodyParam user_id int required Must match the authenticated user (same as OTP verify response). Example: 1
     * @bodyParam query string Optional. Example: jane@example.com
     * @bodyParam pageLimit int Optional. Example: 20
     * @bodyParam fields string Optional. **Zoho only:** comma-separated Contacts field API names forwarded to `/Contacts/search`. Example: First_Name,Last_Name,Email
     *
     * @response 200 {"contacts":[{"id":"550e8400-e29b-41d4-a716-446655440000","name":"Jane Doe","phone":"+919910023290","calls":{"total_dialed":4,"total_talk_time":620,"total_received":3,"total_notes":2}}],"total":1}
     */
    public function searchContacts(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        $request->validate([
            'query' => ['nullable', 'string'],
            'pageLimit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->proxyContacts(fn () => $this->myCrmSync->searchContacts(
                $tenant,
                $this->bodyWithoutLocalContext($request, $tenant)
            ), $tenant, $user);
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyContacts(fn () => $this->zoho->searchContacts(
                $tenant,
                $this->bodyWithoutLocalContext($request, $tenant)
            ), $tenant, $user);
        }

        return $this->proxyContacts(fn () => $this->ghl->searchContacts(
            $tenant,
            $this->bodyWithoutLocalContext($request, $tenant, defaultLocationId: true)
        ), $tenant, $user);
    }

    /**
     * List users
     *
     * @queryParam user_id int required Must match the authenticated user (same as OTP verify response). Example: 1
     */
    public function listUsers(Request $request): JsonResponse
    {
        ['tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            $users = $this->fetchTenantIntegrationCrmUsers->mappedUsersOrEmpty($tenant);

            return response()->json([
                'users' => $users,
                'meta' => ['total' => count($users)],
            ]);
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyArray(fn () => $this->zoho->listUsers(
                $tenant,
                $this->queryWithoutLocalContext($request, $tenant)
            ));
        }

        return $this->proxy(fn () => $this->ghl->request(
            $tenant,
            'GET',
            '/users/',
            $this->queryWithoutLocalContext($request, $tenant, defaultLocationId: true)
        ));
    }

    /**
     * List Tags
     *
     * @queryParam user_id int required Local MysimConnect user id returned by OTP verification. Example: 1
     */
    public function listLocationTags(Request $request): JsonResponse
    {
        ['tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->proxyArray(fn () => $this->myCrmSync->listLocationTags($tenant));
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyArray(fn () => $this->zoho->listLocationTags($tenant));
        }

        return $this->proxyArray(fn () => $this->ghl->listLocationTags($tenant));
    }

    /**
     * Add, update, or remove contact tags
     *
     * @bodyParam user_id int required Local MysimConnect user id returned by OTP verification. Example: 1
     * @bodyParam contactid string required CRM contact id from list/search (GoHighLevel or Zoho). Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam tags string[] required Tag ids for the contact tag update. Example: ["tag_id_1","tag_id_2"]
     *
     * @response 200 {"tags":["sent whatsapp","friendly","hni"],"message":"tags updated","status":true}
     */
    public function addContactTags(Request $request): JsonResponse
    {
        ['tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        $data = $request->validate([
            'contactid' => ['required', 'string'],
            'tags' => ['required', 'array', 'min:1'],
            'tags.*' => ['string'],
        ]);
        $contactId = trim($data['contactid']);

        if ($contactId === '') {
            throw ValidationException::withMessages([
                'contactid' => ['The contactid field is required.'],
            ]);
        }

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->proxyArray(fn () => $this->myCrmSync->addContactTags(
                $tenant,
                $contactId,
                $this->bodyWithoutLocalContext($request, $tenant, except: ['contactid'])
            ));
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyArray(fn () => $this->zoho->addContactTags(
                $tenant,
                $contactId,
                $this->bodyWithoutLocalContext($request, $tenant, except: ['contactid'])
            ));
        }

        return $this->proxyArray(fn () => $this->ghl->addContactTags(
            $tenant,
            $contactId,
            $this->bodyWithoutLocalContext($request, $tenant, except: ['contactid'])
        ));
    }

    /**
     * List contact notes
     *
     * @queryParam user_id int required Local MysimConnect user id returned by OTP verification. Example: 1
     * @queryParam contactId string required CRM contact id from list/search (GoHighLevel or Zoho). Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @response 200 {"success":true,"status":true,"notes":[{"id":"ZuYmxcy8lNxQyFEJWurd","body":"Call: INCOMING\nNumber: +919910023290\nContact: Ankur Wadhawan\nDuration: 18s\nAt: 2026-05-06 02:46:31","attachments":[],"title":"","user_name":"Excellanto Developers","userId":"TlWn93srwc6WyxUYy98a","contactId":"8YoUU2pqEmnI8AoTSLhR","dateAdded":"2026-05-05T21:16:56.211Z","dateUpdated":""}],"meta":{}}
     */
    public function listContactNotes(Request $request): JsonResponse
    {
        ['tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        $data = $request->validate([
            'contactId' => ['nullable', 'string'],
            'contact' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'string'],
            'contactid' => ['nullable', 'string'],
        ]);
        $contactId = trim((string) (
            $data['contactId']
                ?? $data['contact']
                ?? $data['contact_id']
                ?? $data['contactid']
                ?? ''
        ));

        if ($contactId === '') {
            throw ValidationException::withMessages([
                'contactId' => ['The contactId field is required.'],
            ]);
        }

        $noteDefaults = [
            'contactId' => $contactId,
            'userNamesById' => $this->integratedUserNamesById($tenant),
        ];

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->proxyArray(fn () => $this->enrichedContactNotes(
                $tenant,
                $this->myCrmSync->listContactNotes($tenant, $contactId, $noteDefaults)
            ));
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyArray(fn () => $this->enrichedContactNotes(
                $tenant,
                $this->zoho->listContactNotes(
                    $tenant,
                    $contactId,
                    $this->queryWithoutLocalContext($request, $tenant, except: ['contactId', 'contact', 'contact_id', 'contactid']),
                    $noteDefaults
                )
            ));
        }

        return $this->proxyArray(fn () => $this->enrichedContactNotes(
            $tenant,
            $this->ghl->listContactNotes($tenant, $contactId, $noteDefaults)
        ));
    }

    /**
     * Create contact note
     *
     * @bodyParam user_id int required Local MysimConnect user id returned by OTP verification. Must be mapped to an integrated CRM user (`intsysuser`); that id is sent to GoHighLevel (`userId`) or Zoho (`Owner.id`). Example: 1
     * @bodyParam contactId string required CRM contact id from list/search (GoHighLevel or Zoho). Example: 550e8400-e29b-41d4-a716-446655440000
     * @bodyParam body string required Note text. Example: Prospect wanted to schedule call on Friday at 11:30 AM
     * @bodyParam urls string[] optional Attachment URLs. Example: ["https://your-s3-bucket.com/uploads/audio_brief.mp3"]
     *
     * @response 200 {"success":true,"status":true,"notes":[{"id":"ZuYmxcy8lNxQyFEJWurd","body":"Prospect wanted to schedule call on Friday at 11:30 AM","attachments":["https://your-s3-bucket.com/uploads/audio_brief.mp3"],"title":"","user_name":"Excellanto Developers","userId":"1303041000000453001","contactId":"1303041000000523005","dateAdded":"2026-05-05T21:16:56.211Z","dateUpdated":""}]}
     */
    public function createContactNote(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        $data = $request->validate([
            'contactId' => ['required', 'string'],
            'body' => ['required', 'string'],
            'urls' => ['sometimes', 'nullable', 'array'],
            'urls.*' => ['string', 'url'],
        ]);
        $contactId = trim($data['contactId']);

        if ($contactId === '') {
            throw ValidationException::withMessages([
                'contactId' => ['The contactId field is required.'],
            ]);
        }

        $integratedUserId = $this->requireIntegratedUserId($user, $tenant);
        $payload = $this->contactNoteCreatePayload($data, $integratedUserId);

        $noteActorDefaults = $this->contactNoteResponseDefaults($user, $tenant, $contactId, overrideOwner: true);

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->proxyArray(fn () => $this->myCrmSync->createContactNote(
                $tenant,
                $contactId,
                $payload,
                $user,
                $noteActorDefaults
            ), withoutMeta: true, latestNoteOnly: true);
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyArray(fn () => $this->zoho->createContactNote(
                $tenant,
                $contactId,
                $payload,
                $noteActorDefaults
            ), withoutMeta: true, latestNoteOnly: true);
        }

        return $this->proxyArray(fn () => $this->ghl->createContactNote(
            $tenant,
            $contactId,
            $payload,
            $noteActorDefaults
        ), withoutMeta: true, latestNoteOnly: true);
    }

    /**
     * Update contact note
     *
     * @bodyParam noteid string required CRM note id. Example: f47ac10b-58cc-4372-a567-0e02b2c3d479
     * @bodyParam contactId string required CRM contact id from list/search (GoHighLevel or Zoho). Example: 550e8400-e29b-41d4-a716-446655440000
     *
     * @bodyParam user_id int required Local MysimConnect user id returned by OTP verification. Example: 1
     * @bodyParam body string required Note text. Example: Updated text.
     * @bodyParam urls string[] required Attachment URLs. Example: ["https://your-s3-bucket.com/uploads/audio_brief.mp3"]
     */
    public function updateContactNote(Request $request): JsonResponse
    {
        ['user' => $user, 'tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        $data = $request->validate(array_merge(
            $this->noteIdInputRules(),
            $this->contactIdInputRules(),
            [
                'body' => ['required', 'string'],
                'urls' => ['required', 'array'],
                'urls.*' => ['string', 'url'],
            ]
        ));
        $contactId = $this->resolvedContactIdFromData($data);
        $noteId = $this->resolvedNoteIdFromData($data);

        if ($contactId === '') {
            throw ValidationException::withMessages([
                'contactId' => ['The contactId field is required.'],
            ]);
        }

        if ($noteId === '') {
            throw ValidationException::withMessages([
                'noteid' => ['The noteid field is required.'],
            ]);
        }

        $payload = $this->contactNoteCreatePayload($data);

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->proxyArray(fn () => $this->myCrmSync->updateContactNote(
                $tenant,
                $contactId,
                $noteId,
                $payload,
                $this->contactNoteResponseDefaults($user, $tenant, $contactId, now()->toISOString())
            ), withoutMeta: true, noteId: $noteId);
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyArray(fn () => $this->zoho->updateContactNote(
                $tenant,
                $contactId,
                $noteId,
                $payload
            ), withoutMeta: true, noteId: $noteId);
        }

        return $this->proxyArray(fn () => $this->ghl->updateContactNote(
            $tenant,
            $contactId,
            $noteId,
            $payload,
            $this->contactNoteResponseDefaults($user, $tenant, $contactId, now()->toISOString())
        ), withoutMeta: true, noteId: $noteId);
    }

    /**
     * Delete contact note
     *
     * @bodyParam noteid string required CRM note id. Also accepts `noteId`, `note_id`, or `id`. Example: f47ac10b-58cc-4372-a567-0e02b2c3d479
     * @bodyParam user_id int required Local MysimConnect user id returned by OTP verification. Example: 1
     * @bodyParam contactId string required CRM contact id from list/search (GoHighLevel or Zoho). Also accepts `contact`, `contact_id`, or `contactid`. Example: 550e8400-e29b-41d4-a716-446655440000
     */
    public function deleteContactNote(Request $request): JsonResponse
    {
        ['tenant' => $tenant] = $this->resolveAuthenticatedContext($request);

        $data = $request->validate(array_merge(
            $this->noteIdInputRules(),
            $this->contactIdInputRules(),
        ));
        $contactId = $this->resolvedContactIdFromData($data);
        $noteId = $this->resolvedNoteIdFromData($data);

        if ($contactId === '') {
            throw ValidationException::withMessages([
                'contactId' => ['The contactId field is required.'],
            ]);
        }

        if ($noteId === '') {
            throw ValidationException::withMessages([
                'noteid' => ['The noteid field is required.'],
            ]);
        }

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->proxyArray(fn () => $this->myCrmSync->deleteContactNote($tenant, $contactId, $noteId));
        }

        if ($this->isZohoTenant($tenant)) {
            return $this->proxyArray(fn () => $this->zoho->deleteContactNote($tenant, $noteId));
        }

        return $this->proxy(fn () => $this->ghl->request(
            $tenant,
            'DELETE',
            "/contacts/{$contactId}/notes/{$noteId}"
        ));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function noteIdInputRules(): array
    {
        return [
            'noteid' => ['nullable', 'string'],
            'noteId' => ['nullable', 'string'],
            'note_id' => ['nullable', 'string'],
            'id' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function contactIdInputRules(): array
    {
        return [
            'contactId' => ['nullable', 'string'],
            'contact' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'string'],
            'contactid' => ['nullable', 'string'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvedNoteIdFromData(array $data): string
    {
        return trim((string) (
            $data['noteid']
                ?? $data['noteId']
                ?? $data['note_id']
                ?? $data['id']
                ?? ''
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvedContactIdFromData(array $data): string
    {
        return trim((string) (
            $data['contactId']
                ?? $data['contact']
                ?? $data['contact_id']
                ?? $data['contactid']
                ?? ''
        ));
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function contextRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array{user: User, tenant: Tenant}
     */
    private function resolveAuthenticatedContext(Request $request): array
    {
        $data = $request->validate($this->contextRules());

        $authenticated = $request->user();
        if (! $authenticated instanceof User) {
            abort(401, 'Unauthenticated.');
        }

        if ((int) $data['user_id'] !== $authenticated->id) {
            throw ValidationException::withMessages([
                'user_id' => ['The user_id does not match the authenticated user.'],
            ]);
        }

        $user = User::query()
            ->with('tenant')
            ->whereKey($authenticated->id)
            ->firstOrFail();

        $tenant = $user->tenant;

        if ($tenant === null) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not linked to a tenant.'],
            ]);
        }

        $tenantIntegration = is_array($tenant->integration) ? $tenant->integration : [];

        if (! CrmApiClientResolver::isSupportedTenant($tenant)) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user tenant is not configured for a supported CRM API integration.'],
            ]);
        }

        return ['user' => $user, 'tenant' => $tenant];
    }

    private function isZohoTenant(Tenant $tenant): bool
    {
        return CrmApiClientResolver::isZohoTenant($tenant);
    }

    private function requireIntegratedUserId(User $user, Tenant $tenant): string
    {
        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return (string) $user->id;
        }

        $integratedUserId = trim((string) ($user->intsysuser ?? ''));

        if ($integratedUserId === '') {
            throw ValidationException::withMessages([
                'user_id' => ['This user is not linked to an integrated system user. Map intsysuser before creating notes.'],
            ]);
        }

        return $integratedUserId;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{body: string, userId?: string}
     */
    private function contactNoteCreatePayload(array $data, ?string $integratedUserId = null): array
    {
        $body = trim((string) $data['body']);
        $urls = [];

        foreach (($data['urls'] ?? []) as $url) {
            $url = trim((string) $url);

            if ($url !== '') {
                $urls[] = $url;
            }
        }

        if ($urls !== []) {
            $body = trim($body."\n\n".implode("\n", $urls));
        }

        $payload = ['body' => $body];

        if ($integratedUserId !== null && $integratedUserId !== '') {
            $payload['userId'] = $integratedUserId;
        }

        return $payload;
    }

    /**
     * @return array{user_name: string, userId: string, contactId: string, overrideOwner?: bool, dateUpdated?: string}
     */
    private function contactNoteResponseDefaults(
        User $user,
        Tenant $tenant,
        string $contactId,
        ?string $dateUpdated = null,
        bool $overrideOwner = false,
    ): array {
        $integratedUserId = CrmApiClientResolver::isMyCrmSyncTenant($tenant)
            ? (string) $user->id
            : trim((string) ($user->intsysuser ?? ''));
        $profile = $this->resolvedIntegratedUserProfile($user, $tenant);

        $defaults = [
            'user_name' => trim((string) ($profile['name'] ?? '')) !== ''
                ? (string) $profile['name']
                : (string) $user->name,
            'userId' => $integratedUserId,
            'contactId' => $contactId,
        ];

        if ($overrideOwner) {
            $defaults['overrideOwner'] = true;
        }

        if ($dateUpdated !== null) {
            $defaults['dateUpdated'] = $dateUpdated;
        }

        return $defaults;
    }

    /**
     * @return array<string, string>
     */
    private function integratedUserNamesById(Tenant $tenant): array
    {
        $map = [];

        foreach ($this->fetchTenantIntegrationCrmUsers->mappedUsersOrEmpty($tenant) as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $name = trim((string) ($row['name'] ?? ''));

            if ($id !== '' && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }

    /**
     * @return array{name: string, email: string, id: string}|null
     */
    private function resolvedIntegratedUserProfile(User $user, Tenant $tenant): ?array
    {
        $externalId = CrmApiClientResolver::isMyCrmSyncTenant($tenant)
            ? (string) $user->id
            : trim((string) ($user->intsysuser ?? ''));

        if ($externalId === '') {
            return null;
        }

        foreach ($this->fetchTenantIntegrationCrmUsers->mappedUsersOrEmpty($tenant) as $row) {
            if ((string) ($row['id'] ?? '') !== $externalId) {
                continue;
            }

            return [
                'id' => $externalId,
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function queryWithoutLocalContext(Request $request, Tenant $tenant, bool $defaultLocationId = false, array $except = []): array
    {
        $query = $request->except([
            'user_id',
            'integrated_system',
            'Integrated_system',
            'locationId',
            ...$except,
        ]);

        if ($defaultLocationId) {
            $query['locationId'] = $this->ghl->defaultLocationId($tenant);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function bodyWithoutLocalContext(Request $request, Tenant $tenant, bool $defaultLocationId = false, array $except = []): array
    {
        $body = $request->except([
            'user_id',
            'integrated_system',
            'Integrated_system',
            'locationId',
            ...$except,
        ]);

        if ($defaultLocationId) {
            $body['locationId'] = $this->ghl->defaultLocationId($tenant);
        }

        return $body;
    }

    /**
     * @param  callable(): Response  $request
     */
    private function proxy(callable $request): JsonResponse
    {
        try {
            $response = $request();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'GoHighLevel request failed.',
            ], $status);
        }

        $json = $response->json();

        return response()->json(
            is_array($json) ? $json : ['raw' => $response->body()],
            $response->status()
        );
    }

    /**
     * @param  array{0: array<string, mixed>, 1: int}  $result
     * @return array{0: array<string, mixed>, 1: int}
     */
    private function enrichedContactNotes(Tenant $tenant, array $result): array
    {
        [$json, $status] = $result;

        return [$this->contactNoteListEnricher->enrich($tenant, $json), $status];
    }

    /**
     * @param  callable(): array{0: array<string, mixed>, 1: int}  $request
     */
    private function proxyContacts(callable $request, Tenant $tenant, User $user): JsonResponse
    {
        return $this->proxyArray(function () use ($request, $tenant, $user) {
            [$json, $status] = $request();

            return [$this->contactCallStats->enrichContactsEnvelope($tenant, $user, $json), $status];
        });
    }

    /**
     * @param  callable(): array{0: array<string, mixed>, 1: int}  $request
     */
    private function proxyArray(
        callable $request,
        bool $withoutMeta = false,
        bool $latestNoteOnly = false,
        ?string $noteId = null
    ): JsonResponse {
        try {
            [$json, $status] = $request();
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            $status = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 502;

            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'CRM request failed.',
            ], $status);
        }

        if ($withoutMeta) {
            unset($json['meta']);
        }

        if ($noteId !== null) {
            $json = $this->withOnlyNoteId($json, $noteId);
        } elseif ($latestNoteOnly) {
            $json = $this->withLatestNoteOnly($json);
        }

        return response()->json($json, $status);
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function withOnlyNoteId(array $json, string $noteId): array
    {
        $notes = $json['notes'] ?? [];

        if (! is_array($notes) || $notes === []) {
            $json['notes'] = [];

            return $json;
        }

        $json['notes'] = array_values(array_filter($notes, function (mixed $note) use ($noteId): bool {
            return is_array($note) && (string) ($note['id'] ?? '') === $noteId;
        }));

        return $json;
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array<string, mixed>
     */
    private function withLatestNoteOnly(array $json): array
    {
        $notes = $json['notes'] ?? [];

        if (! is_array($notes) || $notes === []) {
            $json['notes'] = [];

            return $json;
        }

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

        usort($ranked, function (array $left, array $right): int {
            $byDate = $right['timestamp'] <=> $left['timestamp'];

            return $byDate !== 0 ? $byDate : $left['index'] <=> $right['index'];
        });

        $json['notes'] = isset($ranked[0]) ? [$ranked[0]['note']] : [];

        return $json;
    }
}

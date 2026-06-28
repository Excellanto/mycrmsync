<?php

namespace App\Integrations\Connectors\MyCrmSync;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Contacts\ContactService;
use Illuminate\Support\Collection;

final class MyCrmSyncCrmApiClient
{
    public function __construct(
        private ContactService $contacts = new ContactService,
        private MyCrmSyncContactMapper $mapper = new MyCrmSyncContactMapper,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function listContacts(Tenant $tenant, array $query = []): array
    {
        $limit = min(max((int) ($query['limit'] ?? 20), 1), 100);
        $collection = $this->contacts->searchForTenant($tenant, ['limit' => $limit]);
        $total = $this->contacts->countForTenant($tenant->id);

        return [$this->mapper->contactsEnvelope($collection, $total, $limit), 200];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function searchContacts(Tenant $tenant, array $body = []): array
    {
        $collection = $this->contacts->searchForTenant($tenant, $body);
        $filters = [];

        if (trim((string) ($body['query'] ?? '')) !== '') {
            $filters['search'] = trim((string) $body['query']);
        }

        $total = $this->contacts->countForTenant($tenant->id, $filters);
        $limit = min(max((int) ($body['pageLimit'] ?? $body['limit'] ?? 20), 1), 100);

        return [$this->mapper->contactsEnvelope($collection, $total, $limit), 200];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array{success: bool, status: bool, contact: array<string, mixed>}, 1: int}
     */
    public function createContact(Tenant $tenant, array $body): array
    {
        $contact = $this->contacts->create($tenant->id, $body);

        return [[
            'success' => true,
            'status' => true,
            'contact' => $this->mapper->mapContact($contact)->toArray(),
        ], 201];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array{success: bool, status: bool, contact: array<string, mixed>}, 1: int}
     */
    public function updateContact(Tenant $tenant, string $contactId, array $body): array
    {
        $contact = $this->contacts->findForTenant($tenant->id, $contactId);
        $updated = $this->contacts->update($contact, $body);

        return [[
            'success' => true,
            'status' => true,
            'contact' => $this->mapper->mapContact($updated)->toArray(),
        ], 200];
    }

    /**
     * @return array{0: array{success: bool, status: bool, message: string, contactId: string}, 1: int}
     */
    public function deleteContact(Tenant $tenant, string $contactId): array
    {
        $contact = $this->contacts->findForTenant($tenant->id, $contactId);
        $this->contacts->delete($contact);

        return [[
            'success' => true,
            'status' => true,
            'message' => 'Contact deleted.',
            'contactId' => $contactId,
        ], 200];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array{tags: list<string>, message: string, status: bool}, 1: int}
     */
    public function addContactTags(Tenant $tenant, string $contactId, array $body): array
    {
        $contact = $this->contacts->findForTenant($tenant->id, $contactId);
        $tags = is_array($body['tags'] ?? null) ? $body['tags'] : [];
        $updated = $this->contacts->syncTags($contact, $tags);

        return [[
            'tags' => is_array($updated->tags) ? $updated->tags : [],
            'message' => 'tags updated',
            'status' => true,
        ], 200];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function listContactNotes(Tenant $tenant, string $contactId, array $defaults = []): array
    {
        $contact = $this->contacts->findForTenant($tenant->id, $contactId);
        $notes = $this->contacts->listNotes($contact);

        return [$this->notesEnvelope($notes, $defaults), 200];
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $defaults
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function createContactNote(Tenant $tenant, string $contactId, array $body, User $user, array $defaults = []): array
    {
        $contact = $this->contacts->findForTenant($tenant->id, $contactId);
        $noteBody = trim((string) ($body['body'] ?? ''));

        foreach (($body['urls'] ?? []) as $url) {
            $url = trim((string) $url);

            if ($url !== '') {
                $noteBody = trim($noteBody."\n\n".$url);
            }
        }

        $this->contacts->createNote($contact, $user, $noteBody);
        $notes = $this->contacts->listNotes($contact);

        return [$this->notesEnvelope($notes, $defaults), 200];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: array<string, mixed>, 1: int}
     */
    public function updateContactNote(Tenant $tenant, string $contactId, string $noteId, array $body, array $defaults = []): array
    {
        $contact = $this->contacts->findForTenant($tenant->id, $contactId);
        $note = $this->contacts->findNoteForContact($contact, $noteId);
        $noteBody = trim((string) ($body['body'] ?? ''));

        foreach (($body['urls'] ?? []) as $url) {
            $url = trim((string) $url);

            if ($url !== '') {
                $noteBody = trim($noteBody."\n\n".$url);
            }
        }

        $this->contacts->updateNote($note, $noteBody);
        $notes = $this->contacts->listNotes($contact);

        return [$this->notesEnvelope($notes, $defaults), 200];
    }

    /**
     * @return array{0: array{success: bool, status: bool}, 1: int}
     */
    public function deleteContactNote(Tenant $tenant, string $contactId, string $noteId): array
    {
        $contact = $this->contacts->findForTenant($tenant->id, $contactId);
        $note = $this->contacts->findNoteForContact($contact, $noteId);
        $this->contacts->deleteNote($note);

        return [['success' => true, 'status' => true], 200];
    }

    /**
     * @return array{0: array{tags: list<array{id: string, name: string}>, meta: array<string, mixed>}, 1: int}
     */
    public function listLocationTags(Tenant $tenant): array
    {
        $tags = $this->contacts->listDistinctTags($tenant->id);

        return [[
            'tags' => $tags,
            'meta' => ['total' => count($tags)],
        ], 200];
    }

    /**
     * @param  Collection<int, ContactNote>  $notes
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function notesEnvelope(Collection $notes, array $defaults = []): array
    {
        $mapped = [];

        foreach ($notes as $note) {
            $mapped[] = $this->mapNote($note, $defaults);
        }

        return [
            'success' => true,
            'status' => true,
            'notes' => $mapped,
            'meta' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function mapNote(ContactNote $note, array $defaults = []): array
    {
        $note->loadMissing('user');

        $userId = (string) ($defaults['userId'] ?? $note->user_id);
        $userName = trim((string) ($defaults['user_name'] ?? $note->user?->name ?? ''));

        if ($userName === '' && isset($defaults['userNamesById']) && is_array($defaults['userNamesById'])) {
            $userName = trim((string) ($defaults['userNamesById'][$userId] ?? ''));
        }

        return [
            'id' => (string) $note->id,
            'body' => (string) $note->body,
            'attachments' => [],
            'title' => (string) ($note->title ?? ''),
            'user_name' => $userName !== '' ? $userName : '(unknown)',
            'userId' => $userId,
            'contactId' => (string) ($defaults['contactId'] ?? $note->contact_id),
            'dateAdded' => $note->created_at?->toIso8601String() ?? '',
            'dateUpdated' => $note->updated_at?->toIso8601String() ?? '',
        ];
    }
}

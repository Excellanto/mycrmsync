<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Integrations\Connectors\MyCrmSync\MyCrmSyncContactMapper;
use App\Integrations\Connectors\MyCrmSync\MyCrmSyncCrmUsersConnector;
use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VoiceNote;
use App\Services\Contacts\ContactImportService;
use App\Services\Contacts\ContactService;
use App\Services\VoiceNote\VoiceNoteAttachmentSerializer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function __construct(
        private ContactService $contacts,
        private ContactImportService $contactImport,
        private VoiceNoteAttachmentSerializer $voiceNoteAttachments,
        private MyCrmSyncContactMapper $mapper = new MyCrmSyncContactMapper,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Contact::class);

        $user = Auth::user();
        $tenantId = $this->resolveTenantId($request, $user);

        $contacts = $this->contacts->paginateForTenant(
            $tenantId,
            $request->only(['search', 'assigned_to', 'tag']),
            20,
        );

        $usersQuery = User::query()
            ->select('id', 'name', 'email', 'tenant_id')
            ->orderBy('name');

        if (! $user->isMaster()) {
            $usersQuery->where('tenant_id', $user->tenant_id);
        } else {
            $usersQuery->where('tenant_id', $tenantId);
        }

        $tags = $this->contacts->listDistinctTags($tenantId);

        return Inertia::render('Admin/Contacts/Index', [
            'contacts' => $contacts->through(fn (Contact $contact) => $this->contactPayload($contact)),
            'filters' => $request->only(['tenant_id', 'search', 'assigned_to', 'tag']),
            'tenants' => $user->isMaster() ? $this->myCrmSyncTenants() : null,
            'users' => $usersQuery->get(),
            'tags' => $tags,
            'canCreate' => $user->can('create', Contact::class),
            'canUpdate' => $user->can('contacts.update'),
            'canDelete' => $user->can('contacts.delete'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        $user = Auth::user();
        $tenantId = $this->resolveTenantId($request, $user);
        $tenant = Tenant::query()->findOrFail($tenantId);
        $this->contacts->assertMyCrmSyncTenant($tenant);

        $data = $this->validatedContactData($request);
        $this->contacts->create($tenantId, $data);

        return redirect()
            ->route('admin.contacts.index', $this->indexQueryParams($request, $tenantId))
            ->with('success', 'Contact created successfully.');
    }

    public function previewImport(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
        ]);

        $rows = $this->contactImport->parseFile($request->file('file'));

        return response()->json([
            'rows' => $rows,
            'total' => count($rows),
            'importable' => collect($rows)->where('importable', true)->count(),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorize('create', Contact::class);

        $user = Auth::user();
        $tenantId = $this->resolveTenantId($request, $user);
        $tenant = Tenant::query()->findOrFail($tenantId);
        $this->contacts->assertMyCrmSyncTenant($tenant);

        $data = $request->validate([
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.index' => ['nullable', 'integer'],
            'contacts.*.first_name' => ['nullable', 'string', 'max:255'],
            'contacts.*.last_name' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'string', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.company_name' => ['nullable', 'string', 'max:255'],
            'contacts.*.source' => ['nullable', 'string', 'max:255'],
            'contacts.*.city' => ['nullable', 'string', 'max:255'],
            'contacts.*.state' => ['nullable', 'string', 'max:255'],
            'contacts.*.country' => ['nullable', 'string', 'max:255'],
            'contacts.*.address' => ['nullable', 'string', 'max:500'],
            'contacts.*.postal_code' => ['nullable', 'string', 'max:50'],
            'contacts.*.website' => ['nullable', 'string', 'max:255'],
            'contacts.*.tags' => ['nullable', 'array'],
            'contacts.*.tags.*' => ['string', 'max:255'],
        ]);

        $result = $this->contacts->importMany($tenantId, $data['contacts']);

        return response()->json([
            'success' => true,
            'created' => $result['created'],
            'skipped' => $result['skipped'],
            'errors' => $result['errors'],
            'message' => $result['created'] > 0
                ? "{$result['created']} contact(s) imported successfully."
                : 'No contacts were imported.',
        ]);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('update', $contact);
        $this->assertContactTenantAccess($request, $contact);

        $data = $this->validatedContactData($request);
        $this->contacts->update($contact, $data);

        return redirect()
            ->route('admin.contacts.index', $this->indexQueryParams($request, (int) $contact->tenant_id))
            ->with('success', 'Contact updated successfully.');
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        $this->authorize('delete', $contact);
        $this->assertContactTenantAccess($request, $contact);

        $tenantId = (int) $contact->tenant_id;
        $this->contacts->delete($contact);

        return redirect()
            ->route('admin.contacts.index', $this->indexQueryParams($request, $tenantId))
            ->with('success', 'Contact deleted successfully.');
    }

    public function notes(Contact $contact): JsonResponse
    {
        $this->authorize('view', $contact);

        $notes = $this->contacts->listNotes($contact);
        $attachmentsByNoteId = $this->voiceNotesGroupedByCrmNoteId(
            (int) $contact->tenant_id,
            $notes->pluck('id')->map(fn ($id) => (string) $id)->all()
        );
        $orphanMedia = $this->orphanVoiceNotesForContact($contact);

        return response()->json([
            'contact' => $this->contactPayload($contact),
            'notes' => $notes->map(fn (ContactNote $note) => $this->notePayload(
                $note,
                $attachmentsByNoteId->get((string) $note->id, collect())
            ))->values(),
            'media' => $this->voiceNoteAttachments->serializeMany($orphanMedia),
        ]);
    }

    public function storeNote(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $data = $request->validate([
            'body' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $note = $this->contacts->createNote($contact, $user, $data['body'], $data['title'] ?? '');

        return response()->json([
            'note' => $this->notePayload($note),
        ]);
    }

    public function updateNote(Request $request, Contact $contact, ContactNote $note): JsonResponse
    {
        $this->authorize('update', $contact);
        $this->authorize('manageNote', $note);

        if ((string) $note->contact_id !== (string) $contact->id) {
            abort(404);
        }

        $data = $request->validate([
            'body' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $updated = $this->contacts->updateNote($note, $data['body'], $data['title'] ?? '');

        return response()->json([
            'note' => $this->notePayload($updated),
        ]);
    }

    public function destroyNote(Contact $contact, ContactNote $note): JsonResponse
    {
        $this->authorize('update', $contact);
        $this->authorize('manageNote', $note);

        if ((string) $note->contact_id !== (string) $contact->id) {
            abort(404);
        }

        $this->contacts->deleteNote($note);

        return response()->json(['success' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(Contact $contact): array
    {
        return $this->mapper->mapContact($contact)->toArray();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, VoiceNote>|null  $attachments
     * @return array<string, mixed>
     */
    private function notePayload(ContactNote $note, $attachments = null): array
    {
        $note->loadMissing('user');
        $attachmentRows = $attachments ?? collect();

        return [
            'id' => (string) $note->id,
            'body' => (string) $note->body,
            'title' => (string) ($note->title ?? ''),
            'user_name' => (string) ($note->user?->name ?? ''),
            'user_id' => (int) $note->user_id,
            'contact_id' => (string) $note->contact_id,
            'dateAdded' => $note->created_at?->toIso8601String() ?? '',
            'dateUpdated' => $note->updated_at?->toIso8601String() ?? '',
            'attachments' => $this->voiceNoteAttachments->serializeMany($attachmentRows),
        ];
    }

    /**
     * @param  list<string>  $noteIds
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, VoiceNote>>
     */
    private function voiceNotesGroupedByCrmNoteId(int $tenantId, array $noteIds)
    {
        if ($noteIds === []) {
            return collect();
        }

        return VoiceNote::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('crm_note_id', $noteIds)
            ->orderBy('created_at')
            ->get()
            ->groupBy(fn (VoiceNote $row) => (string) $row->crm_note_id);
    }

    /**
     * @return \Illuminate\Support\Collection<int, VoiceNote>
     */
    private function orphanVoiceNotesForContact(Contact $contact)
    {
        return VoiceNote::query()
            ->where('tenant_id', $contact->tenant_id)
            ->where('contact_id', (string) $contact->id)
            ->where(function ($q) {
                $q->whereNull('crm_note_id')->orWhere('crm_note_id', '');
            })
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedContactData(Request $request): array
    {
        return $request->validate([
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:255'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'country' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'max:100'],
            'tags' => ['nullable'],
        ]);
    }

    private function resolveTenantId(Request $request, User $user): int
    {
        if ($user->isMaster()) {
            $tenantId = $request->integer('tenant_id');

            if ($tenantId <= 0) {
                $first = $this->myCrmSyncTenants()->first();

                if ($first === null) {
                    throw ValidationException::withMessages([
                        'tenant_id' => ['No MyCrmSync tenants are configured.'],
                    ]);
                }

                return (int) $first->id;
            }

            $this->assertMyCrmSyncTenantId($tenantId);

            return $tenantId;
        }

        if ($user->tenant_id === null) {
            abort(403, 'Your account is not linked to a tenant.');
        }

        $tenant = Tenant::query()->findOrFail($user->tenant_id);
        $this->contacts->assertMyCrmSyncTenant($tenant);

        return (int) $user->tenant_id;
    }

    private function assertMyCrmSyncTenantId(int $tenantId): void
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $this->contacts->assertMyCrmSyncTenant($tenant);
    }

    private function assertContactTenantAccess(Request $request, Contact $contact): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($user->isMaster()) {
            $this->assertMyCrmSyncTenantId((int) $contact->tenant_id);

            return;
        }

        if ((int) $contact->tenant_id !== (int) $user->tenant_id) {
            abort(403);
        }
    }

    /**
     * @return array<string, int|string|null>
     */
    private function indexQueryParams(Request $request, int $tenantId): array
    {
        $params = array_filter($request->only(['search', 'assigned_to', 'tag']));

        /** @var User $user */
        $user = Auth::user();

        if ($user->isMaster()) {
            $params['tenant_id'] = $tenantId;
        }

        return $params;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    private function myCrmSyncTenants()
    {
        return Tenant::query()
            ->where('integration->slug', MyCrmSyncCrmUsersConnector::integrationSlug())
            ->select('id', 'company_name')
            ->orderBy('company_name')
            ->get();
    }
}

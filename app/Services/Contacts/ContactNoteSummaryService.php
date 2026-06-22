<?php

namespace App\Services\Contacts;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\MyCrmSync\MyCrmSyncContactMapper;
use App\Integrations\Connectors\Zoho\ZohoCrmApiClient;
use App\Integrations\CrmApiClientResolver;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\VoiceNote;
use App\Support\PhoneNormalizer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ContactNoteSummaryService
{
    public function __construct(
        private ContactService $contacts = new ContactService,
        private GoHighLevelApiClient $ghl = new GoHighLevelApiClient,
        private ZohoCrmApiClient $zoho = new ZohoCrmApiClient,
        private MyCrmSyncContactMapper $mapper = new MyCrmSyncContactMapper,
    ) {}

    /**
     * @return array{contact: array<string, mixed>, summaries: list<array<string, mixed>>, meta: array{total: int}}
     */
    public function summariesByPhone(Tenant $tenant, string $phone): array
    {
        $phone = trim($phone);

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => ['The phone field is required.'],
            ]);
        }

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->summariesForMyCrmSync($tenant, $phone);
        }

        return $this->summariesForExternalCrm($tenant, $phone);
    }

    /**
     * @return array{contact: array<string, mixed>, summaries: list<array<string, mixed>>, meta: array{total: int}}
     */
    private function summariesForMyCrmSync(Tenant $tenant, string $phone): array
    {
        $contact = $this->contacts->findByPhone($tenant->id, $phone);

        if ($contact === null) {
            throw ValidationException::withMessages([
                'phone' => ['No contact was found for this phone number.'],
            ]);
        }

        $contactPayload = $this->mapper->mapContact($contact)->toArray();
        $summaries = $this->summariesForLocalContact($tenant, $contact);

        return [
            'contact' => $this->contactSummaryPayload($contactPayload),
            'summaries' => $summaries,
            'meta' => ['total' => count($summaries)],
        ];
    }

    /**
     * @return array{contact: array<string, mixed>, summaries: list<array<string, mixed>>, meta: array{total: int}}
     */
    private function summariesForExternalCrm(Tenant $tenant, string $phone): array
    {
        $contactPayload = $this->findExternalContactByPhone($tenant, $phone);

        if ($contactPayload === null) {
            throw ValidationException::withMessages([
                'phone' => ['No contact was found for this phone number.'],
            ]);
        }

        $contactId = trim((string) ($contactPayload['id'] ?? ''));

        if ($contactId === '') {
            throw ValidationException::withMessages([
                'phone' => ['No contact was found for this phone number.'],
            ]);
        }

        $noteDefaults = ['contactId' => $contactId];

        if (CrmApiClientResolver::isZohoTenant($tenant)) {
            [$json] = $this->zoho->listContactNotes($tenant, $contactId, [], $noteDefaults);
        } else {
            [$json] = $this->ghl->listContactNotes($tenant, $contactId, $noteDefaults);
        }

        $summaries = $this->summariesFromCrmNotes($tenant, $contactId, is_array($json) ? $json : []);

        return [
            'contact' => $this->contactSummaryPayload($contactPayload),
            'summaries' => $summaries,
            'meta' => ['total' => count($summaries)],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function summariesForLocalContact(Tenant $tenant, Contact $contact): array
    {
        $notes = $this->contacts->listNotes($contact);
        $noteIds = $notes->pluck('id')->map(fn ($id) => (string) $id)->all();

        $voiceByNoteId = $this->voiceNotesByCrmNoteId($tenant, $noteIds);
        $summaries = [];

        foreach ($notes as $note) {
            $summary = $this->resolveSummary(
                (string) ($voiceByNoteId[(string) $note->id]->summary ?? ''),
                (string) $note->body,
                (string) ($note->title ?? ''),
            );

            if ($summary === '') {
                continue;
            }

            $summaries[] = $this->formatSummaryRow(
                (string) $note->id,
                $summary,
                $note->created_at,
            );
        }

        foreach ($this->orphanVoiceNoteSummaries($tenant, (string) $contact->id) as $row) {
            $summaries[] = $row;
        }

        return $this->sortSummariesDesc($summaries);
    }

    /**
     * @param  array<string, mixed>  $notesJson
     * @return list<array<string, mixed>>
     */
    private function summariesFromCrmNotes(Tenant $tenant, string $contactId, array $notesJson): array
    {
        $notes = $notesJson['notes'] ?? [];

        if (! is_array($notes) || $notes === []) {
            return [];
        }

        $noteIds = collect($notes)
            ->map(fn (mixed $note): string => is_array($note) ? trim((string) ($note['id'] ?? '')) : '')
            ->filter()
            ->values()
            ->all();

        $voiceByNoteId = $this->voiceNotesByCrmNoteId($tenant, $noteIds);
        $summaries = [];

        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }

            $noteId = trim((string) ($note['id'] ?? ''));

            if ($noteId === '') {
                continue;
            }

            $summary = $this->resolveSummary(
                (string) ($voiceByNoteId[$noteId]->summary ?? ''),
                (string) ($note['body'] ?? ''),
                (string) ($note['title'] ?? ''),
            );

            if ($summary === '') {
                continue;
            }

            $createdAt = $this->parseNoteDate($note['dateAdded'] ?? $note['dateUpdated'] ?? null);

            $summaries[] = $this->formatSummaryRow($noteId, $summary, $createdAt);
        }

        foreach ($this->orphanVoiceNoteSummaries($tenant, $contactId) as $row) {
            $summaries[] = $row;
        }

        return $this->sortSummariesDesc($summaries);
    }

    /**
     * @param  list<string>  $crmNoteIds
     * @return Collection<string, VoiceNote>
     */
    private function voiceNotesByCrmNoteId(Tenant $tenant, array $crmNoteIds): Collection
    {
        if ($crmNoteIds === []) {
            return collect();
        }

        return VoiceNote::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('crm_note_id', $crmNoteIds)
            ->orderBy('created_at')
            ->get()
            ->keyBy('crm_note_id');
    }

    /**
     * Voice notes processed for a contact but not yet linked to a CRM note id.
     *
     * @return list<array<string, mixed>>
     */
    private function orphanVoiceNoteSummaries(Tenant $tenant, string $contactId): array
    {
        $rows = VoiceNote::query()
            ->where('tenant_id', $tenant->id)
            ->where('contact_id', $contactId)
            ->whereNull('crm_note_id')
            ->whereNotNull('summary')
            ->where('summary', '!=', '')
            ->orderByDesc('created_at')
            ->get();

        $summaries = [];

        foreach ($rows as $row) {
            $summary = trim((string) $row->summary);

            if ($summary === '') {
                continue;
            }

            $summaries[] = $this->formatSummaryRow(
                (string) $row->id,
                $summary,
                $row->created_at,
            );
        }

        return $summaries;
    }

    private function resolveSummary(string $voiceSummary, string $body, string $title): string
    {
        $voiceSummary = trim($voiceSummary);

        if ($voiceSummary !== '') {
            return $voiceSummary;
        }

        $fromBody = ContactNoteSummaryExtractor::fromBody($body);

        if ($fromBody !== '') {
            return $fromBody;
        }

        return trim($title);
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array{id: string, name: string, phone: string, email: string}
     */
    private function contactSummaryPayload(array $contact): array
    {
        $firstName = trim((string) ($contact['firstName'] ?? ''));
        $lastName = trim((string) ($contact['lastName'] ?? ''));
        $name = trim((string) ($contact['name'] ?? ''));

        if ($name === '') {
            $name = trim($firstName.' '.$lastName);
        }

        return [
            'id' => (string) ($contact['id'] ?? ''),
            'name' => $name,
            'phone' => trim((string) ($contact['phone'] ?? '')),
            'email' => trim((string) ($contact['email'] ?? '')),
        ];
    }

    /**
     * @return array{id: string, summary: string, date: string, time: string, datetime: string}
     */
    private function formatSummaryRow(string $id, string $summary, mixed $createdAt): array
    {
        $timestamp = $createdAt instanceof Carbon
            ? $createdAt
            : $this->parseNoteDate($createdAt);

        return [
            'id' => $id,
            'summary' => $summary,
            'date' => $timestamp->toDateString(),
            'time' => $timestamp->format('H:i:s'),
            'datetime' => $timestamp->toIso8601String(),
        ];
    }

    private function parseNoteDate(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        $raw = trim((string) $value);

        if ($raw === '') {
            return now();
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $summaries
     * @return list<array<string, mixed>>
     */
    private function sortSummariesDesc(array $summaries): array
    {
        usort($summaries, function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['datetime'] ?? ''));
            $rightTime = strtotime((string) ($right['datetime'] ?? ''));

            return ($rightTime ?: 0) <=> ($leftTime ?: 0);
        });

        return array_values($summaries);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findExternalContactByPhone(Tenant $tenant, string $phone): ?array
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

        return $this->bestPhoneMatch($contacts, $phone);
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

<?php

namespace App\Services\Contacts;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\Zoho\ZohoCrmApiClient;
use App\Integrations\CrmApiClientResolver;
use App\Models\CallLog;
use App\Models\ContactNote;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ContactCallStatsService
{
    public function __construct(
        private GoHighLevelApiClient $ghl = new GoHighLevelApiClient,
        private ZohoCrmApiClient $zoho = new ZohoCrmApiClient,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope  Contacts list/search JSON with a `contacts` key.
     * @return array<string, mixed>
     */
    public function enrichContactsEnvelope(Tenant $tenant, User $user, array $envelope): array
    {
        $contacts = $envelope['contacts'] ?? [];

        if (! is_array($contacts) || $contacts === []) {
            return $envelope;
        }

        $statsByContactId = $this->batchStatsForContacts($tenant, $user, $contacts);

        foreach ($contacts as $index => $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $contactId = trim((string) ($contact['id'] ?? ''));
            $contacts[$index]['calls'] = $statsByContactId[$contactId] ?? self::emptyStats();
        }

        $envelope['contacts'] = $contacts;

        return $envelope;
    }

    /**
     * @param  array<string, mixed>  $contact  Normalized contact payload with `id` and `phone`.
     * @return array{total_dialed: int, total_talk_time: int, total_received: int, total_notes: int}
     */
    public function statsForContact(Tenant $tenant, User $user, array $contact): array
    {
        $contactId = trim((string) ($contact['id'] ?? ''));

        if ($contactId === '') {
            return self::emptyStats();
        }

        $stats = $this->batchStatsForContacts($tenant, $user, [$contact])[$contactId] ?? self::emptyStats();

        if (! CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            $stats['total_notes'] = $this->externalNoteCount($tenant, $contactId);
        }

        return $stats;
    }

    /**
     * @return array{total_dialed: int, total_talk_time: int, total_received: int, total_notes: int}
     */
    public static function emptyStats(): array
    {
        return [
            'total_dialed' => 0,
            'total_talk_time' => 0,
            'total_received' => 0,
            'total_notes' => 0,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $contacts
     * @return array<string, array{total_dialed: int, total_talk_time: int, total_received: int, total_notes: int}>
     */
    private function batchStatsForContacts(Tenant $tenant, User $user, array $contacts): array
    {
        $stats = [];
        $contactIds = [];
        $phoneSuffixToContactId = [];

        foreach ($contacts as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $contactId = trim((string) ($contact['id'] ?? ''));

            if ($contactId === '') {
                continue;
            }

            $stats[$contactId] = self::emptyStats();
            $contactIds[] = $contactId;

            $suffix = $this->phoneSuffix(trim((string) ($contact['phone'] ?? '')));

            if ($suffix !== '' && ! isset($phoneSuffixToContactId[$suffix])) {
                $phoneSuffixToContactId[$suffix] = $contactId;
            }
        }

        if ($contactIds === []) {
            return $stats;
        }

        $contactIdSet = array_fill_keys($contactIds, true);
        $this->aggregateCallLogs($tenant, $user, $contactIds, $phoneSuffixToContactId, $contactIdSet, $stats);
        $this->applyNoteCounts($tenant, $contactIds, $stats);

        return $stats;
    }

    /**
     * @param  list<string>  $contactIds
     * @param  array<string, string>  $phoneSuffixToContactId
     * @param  array<string, true>  $contactIdSet
     * @param  array<string, array{total_dialed: int, total_talk_time: int, total_received: int, total_notes: int}>  $stats
     */
    private function aggregateCallLogs(
        Tenant $tenant,
        User $user,
        array $contactIds,
        array $phoneSuffixToContactId,
        array $contactIdSet,
        array &$stats,
    ): void {
        $phoneSuffixes = array_keys($phoneSuffixToContactId);

        $query = CallLog::query()
            ->forTenantId((int) $tenant->id)
            ->where('user_id', (string) $user->id)
            ->where(function (Builder $outer) use ($contactIds, $phoneSuffixes): void {
                $outer->whereIn('contact_id', $contactIds);

                if ($phoneSuffixes !== []) {
                    $outer->orWhere(function (Builder $phoneOuter) use ($phoneSuffixes): void {
                        $phoneOuter->where(function (Builder $unlinked): void {
                            $unlinked->whereNull('contact_id')
                                ->orWhere('contact_id', '');
                        });

                        $phoneOuter->where(function (Builder $numberOuter) use ($phoneSuffixes): void {
                            foreach ($phoneSuffixes as $suffix) {
                                $like = '%'.$suffix.'%';
                                $numberOuter->orWhere(function (Builder $numberQuery) use ($like): void {
                                    $numberQuery->where('phone_e164', 'ilike', $like)
                                        ->orWhere('phone_raw', 'ilike', $like);
                                });
                            }
                        });
                    });
                }
            });

        /** @var Collection<int, CallLog> $logs */
        $logs = $query->get(['contact_id', 'direction', 'duration_sec', 'phone_e164', 'phone_raw']);

        foreach ($logs as $log) {
            $matchedContactId = $this->resolveContactIdForLog($log, $contactIdSet, $phoneSuffixToContactId);

            if ($matchedContactId === null || ! isset($stats[$matchedContactId])) {
                continue;
            }

            $stats[$matchedContactId]['total_talk_time'] += (int) $log->duration_sec;

            if ($log->direction === 'OUTGOING') {
                $stats[$matchedContactId]['total_dialed']++;
            } elseif ($log->direction === 'INCOMING') {
                $stats[$matchedContactId]['total_received']++;
            }
        }
    }

    /**
     * @param  list<string>  $contactIds
     * @param  array<string, array{total_dialed: int, total_talk_time: int, total_received: int, total_notes: int}>  $stats
     */
    private function applyNoteCounts(Tenant $tenant, array $contactIds, array &$stats): void
    {
        if (! CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return;
        }

        $counts = ContactNote::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('contact_id', $contactIds)
            ->selectRaw('contact_id, COUNT(*) as aggregate')
            ->groupBy('contact_id')
            ->pluck('aggregate', 'contact_id');

        foreach ($counts as $contactId => $count) {
            $key = (string) $contactId;

            if (isset($stats[$key])) {
                $stats[$key]['total_notes'] = (int) $count;
            }
        }
    }

    private function externalNoteCount(Tenant $tenant, string $contactId): int
    {
        if (CrmApiClientResolver::isZohoTenant($tenant)) {
            [$json] = $this->zoho->listContactNotes($tenant, $contactId, [], ['contactId' => $contactId]);
        } else {
            [$json] = $this->ghl->listContactNotes($tenant, $contactId, ['contactId' => $contactId]);
        }

        $notes = $json['notes'] ?? [];

        return is_array($notes) ? count($notes) : 0;
    }

    /**
     * @param  array<string, true>  $contactIdSet
     * @param  array<string, string>  $phoneSuffixToContactId
     */
    private function resolveContactIdForLog(CallLog $log, array $contactIdSet, array $phoneSuffixToContactId): ?string
    {
        $contactId = trim((string) $log->contact_id);

        if ($contactId !== '' && isset($contactIdSet[$contactId])) {
            return $contactId;
        }

        if ($contactId !== '') {
            return null;
        }

        foreach ([(string) $log->phone_e164, (string) $log->phone_raw] as $phone) {
            $suffix = $this->phoneSuffix($phone);

            if ($suffix !== '' && isset($phoneSuffixToContactId[$suffix])) {
                return $phoneSuffixToContactId[$suffix];
            }
        }

        return null;
    }

    private function phoneSuffix(string $phone): string
    {
        $digits = PhoneNormalizer::digits($phone);

        if ($digits === '') {
            return '';
        }

        return strlen($digits) >= 10 ? substr($digits, -10) : $digits;
    }
}

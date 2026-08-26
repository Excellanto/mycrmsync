<?php

namespace App\Services\CallLog;

use App\Models\CallLog;
use App\Models\CallRecording;
use App\Models\ContactNote;
use App\Models\VoiceNote;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

final class CallLogRelatedMediaService
{
    /**
     * @param  Collection<int, CallLog>  $logs
     * @return array<string, array{playback_count: int, note: string|null, latest_recording: array<string, mixed>|null}>
     */
    public function summarizeForLogs(Collection $logs): array
    {
        if ($logs->isEmpty()) {
            return [];
        }

        $recordingsByLogId = $this->loadLinkedRecordings($logs);
        $orphanRecordings = $this->loadOrphanRecordings($logs);
        $voiceNotes = $this->loadVoiceNotes($logs);
        $contactNotes = $this->loadContactNotes($logs);

        $out = [];

        foreach ($logs as $log) {
            $logId = (string) $log->id;
            $linked = $recordingsByLogId->get($logId, collect());
            $orphans = $this->orphansForLog($log, $orphanRecordings);
            $notes = $this->voiceNotesForLog($log, $voiceNotes);
            $timelineNotes = $this->contactNotesForLog($log, $contactNotes);

            $playableRecordings = $linked->merge($orphans)->filter(
                fn (CallRecording $r) => $this->hasPlayableUrl($r->recording_url_long, $r->recording_url)
            );
            $playableVoiceNotes = $notes->filter(
                fn (VoiceNote $n) => $this->hasPlayableUrl($n->recording_url_long, $n->recording_url)
            );

            $noteText = $this->pickNoteText(
                $playableRecordings->merge($linked)->merge($orphans),
                $notes,
                $timelineNotes
            );
            $latestRecording = $linked->first() ?? $orphans->first();

            $out[$logId] = [
                'playback_count' => $playableRecordings->count() + $playableVoiceNotes->count(),
                'note' => $noteText,
                'latest_recording' => $latestRecording instanceof CallRecording
                    ? [
                        'id' => $latestRecording->id,
                        'status' => $latestRecording->status,
                        'has_transcription' => trim((string) ($latestRecording->transcription ?? '')) !== '',
                        'has_summary' => trim((string) ($latestRecording->summary ?? '')) !== '',
                        'transcription_backend' => $latestRecording->transcription_backend,
                        'created_at' => $latestRecording->created_at?->toIso8601String(),
                    ]
                    : null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mediaForLog(CallLog $log): array
    {
        $this->linkMatchingOrphanRecordings($log);

        $recordings = CallRecording::query()
            ->where('call_log_id', $log->id)
            ->orderByDesc('created_at')
            ->get();

        $voiceNotes = $this->loadVoiceNotes(collect([$log]));
        $matchedNotes = $this->voiceNotesForLog($log, $voiceNotes);

        $items = [];

        foreach ($recordings as $recording) {
            $items[] = [
                'type' => 'call_recording',
                'id' => (string) $recording->id,
                'created_at' => $recording->created_at?->toIso8601String(),
                'status' => $recording->status,
                'recording_url' => $recording->recording_url,
                'recording_url_long' => $recording->recording_url_long,
                'transcription' => $recording->transcription,
                'summary' => $recording->summary,
                'note' => $this->firstNonEmptyString($recording->summary, $recording->transcription),
                'sentiment' => $recording->sentiment,
                'transcription_backend' => $recording->transcription_backend,
                'file_name' => $recording->file_name,
                'filetype' => $recording->filetype,
                'duration_sec' => $recording->duration_sec,
            ];
        }

        foreach ($matchedNotes as $note) {
            $items[] = [
                'type' => 'voice_note',
                'id' => (string) $note->id,
                'created_at' => $note->created_at?->toIso8601String(),
                'status' => 'completed',
                'recording_url' => $note->recording_url,
                'recording_url_long' => $note->recording_url_long,
                'transcription' => $note->transcription,
                'summary' => $note->summary,
                'note' => $this->firstNonEmptyString($note->summary, $note->note_body, $note->transcription),
                'sentiment' => null,
                'transcription_backend' => $note->transcription_backend,
                'file_name' => $note->file_name,
                'filetype' => $note->filetype,
                'duration_sec' => $note->duration_sec,
            ];
        }

        usort($items, function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        return $items;
    }

    public function linkMatchingOrphanRecordings(CallLog $callLog): void
    {
        $window = $this->matchWindow($callLog);
        if ($window === null) {
            return;
        }

        [$from, $to] = $window;

        $query = CallRecording::query()
            ->whereNull('call_log_id')
            ->where('user_id', (int) $callLog->user_id)
            ->whereBetween('created_at', [$from, $to]);

        $contactId = trim((string) ($callLog->contact_id ?? ''));
        if ($contactId !== '') {
            $query->where('contact_id', $contactId);
        }

        $orphans = $query->orderBy('created_at')->get();

        if ($contactId === '' && $orphans->count() !== 1) {
            return;
        }

        foreach ($orphans as $orphan) {
            $orphan->update(['call_log_id' => $callLog->id]);
        }
    }

    /**
     * @param  Collection<int, CallLog>  $logs
     * @return Collection<string, Collection<int, CallRecording>>
     */
    private function loadLinkedRecordings(Collection $logs): Collection
    {
        $ids = $logs->pluck('id')->map(fn ($id) => (string) $id)->all();

        return CallRecording::query()
            ->whereIn('call_log_id', $ids)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(fn (CallRecording $r) => (string) $r->call_log_id);
    }

    /**
     * @param  Collection<int, CallLog>  $logs
     * @return Collection<int, CallRecording>
     */
    private function loadOrphanRecordings(Collection $logs): Collection
    {
        $userIds = $logs->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $bounds = $this->collectiveBounds($logs);
        if ($bounds === null || $userIds === []) {
            return collect();
        }

        [$from, $to] = $bounds;

        return CallRecording::query()
            ->whereNull('call_log_id')
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  Collection<int, CallLog>  $logs
     * @return Collection<int, VoiceNote>
     */
    private function loadVoiceNotes(Collection $logs): Collection
    {
        $contactIds = $logs->pluck('contact_id')
            ->map(fn ($id) => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($contactIds === []) {
            return collect();
        }

        $userIds = $logs->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $bounds = $this->collectiveBounds($logs, voiceNoteSlack: true);
        if ($bounds === null) {
            return collect();
        }

        [$from, $to] = $bounds;

        return VoiceNote::query()
            ->whereIn('contact_id', $contactIds)
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  Collection<int, CallLog>  $logs
     * @return Collection<int, ContactNote>
     */
    private function loadContactNotes(Collection $logs): Collection
    {
        // contact_notes.contact_id is uuid (MyCrmSync). Skip GHL/Zoho-style string ids.
        $contactIds = $logs->pluck('contact_id')
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn (string $id) => $id !== '' && Str::isUuid($id))
            ->unique()
            ->values()
            ->all();

        if ($contactIds === []) {
            return collect();
        }

        $bounds = $this->collectiveBounds($logs, voiceNoteSlack: true);
        if ($bounds === null) {
            return collect();
        }

        [$from, $to] = $bounds;

        return ContactNote::query()
            ->whereIn('contact_id', $contactIds)
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * @param  Collection<int, CallRecording>  $orphans
     * @return Collection<int, CallRecording>
     */
    private function orphansForLog(CallLog $log, Collection $orphans): Collection
    {
        $window = $this->matchWindow($log);
        if ($window === null) {
            return collect();
        }

        [$from, $to] = $window;
        $userId = (int) $log->user_id;
        $contactId = trim((string) ($log->contact_id ?? ''));

        $matched = $orphans->filter(function (CallRecording $r) use ($from, $to, $userId, $contactId) {
            if ((int) $r->user_id !== $userId || $r->created_at === null) {
                return false;
            }
            if ($r->created_at->lt($from) || $r->created_at->gt($to)) {
                return false;
            }
            if ($contactId !== '' && trim((string) $r->contact_id) !== $contactId) {
                return false;
            }

            return true;
        })->values();

        if ($contactId === '' && $matched->count() !== 1) {
            return collect();
        }

        return $matched;
    }

    /**
     * @param  Collection<int, VoiceNote>  $voiceNotes
     * @return Collection<int, VoiceNote>
     */
    private function voiceNotesForLog(CallLog $log, Collection $voiceNotes): Collection
    {
        $contactId = trim((string) ($log->contact_id ?? ''));
        if ($contactId === '') {
            return collect();
        }

        $window = $this->matchWindow($log, voiceNoteSlack: true);
        if ($window === null) {
            return collect();
        }

        [$from, $to] = $window;
        $userId = (int) $log->user_id;

        return $voiceNotes->filter(function (VoiceNote $note) use ($from, $to, $userId, $contactId) {
            if ((int) $note->user_id !== $userId) {
                return false;
            }
            if (trim((string) $note->contact_id) !== $contactId) {
                return false;
            }
            if ($note->created_at === null) {
                return false;
            }

            return ! $note->created_at->lt($from) && ! $note->created_at->gt($to);
        })->values();
    }

    /**
     * @param  Collection<int, ContactNote>  $contactNotes
     * @return Collection<int, ContactNote>
     */
    private function contactNotesForLog(CallLog $log, Collection $contactNotes): Collection
    {
        $contactId = trim((string) ($log->contact_id ?? ''));
        if ($contactId === '') {
            return collect();
        }

        $window = $this->matchWindow($log, voiceNoteSlack: true);
        if ($window === null) {
            return collect();
        }

        [$from, $to] = $window;

        return $contactNotes->filter(function (ContactNote $note) use ($from, $to, $contactId) {
            if (trim((string) $note->contact_id) !== $contactId) {
                return false;
            }
            if ($note->created_at === null) {
                return false;
            }

            return ! $note->created_at->lt($from) && ! $note->created_at->gt($to);
        })->values();
    }

    /**
     * @param  Collection<int, CallRecording>  $recordings
     * @param  Collection<int, VoiceNote>  $voiceNotes
     * @param  Collection<int, ContactNote>  $timelineNotes
     */
    private function pickNoteText(Collection $recordings, Collection $voiceNotes, ?Collection $timelineNotes = null): ?string
    {
        $timelineNotes ??= collect();

        foreach ($recordings as $recording) {
            $text = $this->firstNonEmptyString($recording->summary, $recording->transcription);
            if ($text !== null) {
                return $text;
            }
        }

        foreach ($voiceNotes as $note) {
            $text = $this->firstNonEmptyString($note->summary, $note->note_body, $note->transcription);
            if ($text !== null) {
                return $text;
            }
        }

        foreach ($timelineNotes as $note) {
            $text = $this->firstNonEmptyString($note->body);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, CallLog>  $logs
     * @return array{0: Carbon, 1: Carbon}|null
     */
    private function collectiveBounds(Collection $logs, bool $voiceNoteSlack = false): ?array
    {
        $from = null;
        $to = null;

        foreach ($logs as $log) {
            $window = $this->matchWindow($log, $voiceNoteSlack);
            if ($window === null) {
                continue;
            }
            [$wFrom, $wTo] = $window;
            $from = $from === null || $wFrom->lt($from) ? $wFrom->copy() : $from;
            $to = $to === null || $wTo->gt($to) ? $wTo->copy() : $to;
        }

        return $from !== null && $to !== null ? [$from, $to] : null;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}|null
     */
    public function matchWindow(CallLog $callLog, bool $voiceNoteSlack = false): ?array
    {
        $started = $callLog->started_at;
        if (! $started instanceof Carbon) {
            $started = $callLog->created_at;
        }
        if (! $started instanceof Carbon) {
            return null;
        }

        $duration = max(0, (int) ($callLog->duration_sec ?? 0));
        $from = $started->copy()->subMinutes(15);
        $to = $started->copy()->addSeconds($duration)->addMinutes($voiceNoteSlack ? 180 : 45);

        return [$from, $to];
    }

    private function hasPlayableUrl(?string $long, ?string $short): bool
    {
        return trim((string) $long) !== '' || trim((string) $short) !== '';
    }

    private function firstNonEmptyString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (! is_string($value)) {
                continue;
            }
            $trimmed = trim($value);
            if ($trimmed !== '') {
                return Str::limit($trimmed, 500);
            }
        }

        return null;
    }
}

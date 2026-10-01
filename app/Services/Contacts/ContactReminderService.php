<?php

namespace App\Services\Contacts;

use App\Models\Contact;
use App\Models\ContactReminder;
use App\Models\User;
use App\Services\Tasks\CrmTaskService;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ContactReminderService
{
    /**
     * Pending reminders for the dashboard and the mobile reminders list.
     * Tenant admins see every pending reminder in the tenant. Other users see their own.
     *
     * @return list<array<string, mixed>>
     */
    public function pendingForDashboard(User $user): array
    {
        if ($user->isMaster() || $user->tenant_id === null) {
            return [];
        }

        $query = ContactReminder::query()
            ->with('user:id,name')
            ->where('tenant_id', $user->tenant_id)
            ->where('status', ContactReminder::STATUS_PENDING)
            ->orderBy('remind_at')
            ->limit(100);

        if (! $this->managesTenantReminders($user)) {
            $query->where('user_id', $user->id);
        }

        $reminders = $query->get();
        $localIds = $this->localContactIdSet((int) $user->tenant_id, $reminders->pluck('contact_id')->all());

        return $reminders
            ->map(fn (ContactReminder $reminder) => $this->payload(
                $reminder,
                isset($localIds[(string) $reminder->contact_id])
            ))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pendingForContact(User $user, string $contactId): array
    {
        if ($user->tenant_id === null) {
            return [];
        }

        $query = ContactReminder::query()
            ->with('user:id,name')
            ->where('tenant_id', $user->tenant_id)
            ->where('contact_id', $contactId)
            ->where('status', ContactReminder::STATUS_PENDING)
            ->orderBy('remind_at');

        if (! $this->managesTenantReminders($user)) {
            $query->where('user_id', $user->id);
        }

        $reminders = $query->get();
        $local = $this->isLocalContact((int) $user->tenant_id, $contactId);

        return $reminders
            ->map(fn (ContactReminder $reminder) => $this->payload($reminder, $local))
            ->all();
    }

    /**
     * @param  list<string>  $noteIds
     * @return array<string, array<string, mixed>>
     */
    public function pendingPayloadsForNotes(User $user, array $noteIds): array
    {
        if ($user->tenant_id === null || $noteIds === []) {
            return [];
        }

        $reminders = ContactReminder::query()
            ->with('user:id,name')
            ->where('tenant_id', $user->tenant_id)
            ->where('user_id', $user->id)
            ->where('status', ContactReminder::STATUS_PENDING)
            ->whereIn('contact_note_id', $noteIds)
            ->orderBy('remind_at')
            ->get();

        $localIds = $this->localContactIdSet((int) $user->tenant_id, $reminders->pluck('contact_id')->all());
        $mapped = [];

        foreach ($reminders as $reminder) {
            $noteId = (string) $reminder->contact_note_id;
            if (isset($mapped[$noteId])) {
                continue;
            }

            $mapped[$noteId] = $this->payload(
                $reminder,
                isset($localIds[(string) $reminder->contact_id])
            );
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function decorateNotesPayload(User $user, array $payload): array
    {
        $notes = $payload['notes'] ?? null;
        if (! is_array($notes)) {
            return $payload;
        }

        $noteIds = [];
        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }
            $noteId = trim((string) ($note['id'] ?? ''));
            if ($noteId !== '') {
                $noteIds[] = $noteId;
            }
        }

        $byNote = $this->pendingPayloadsForNotes($user, $noteIds);

        foreach ($notes as $index => $note) {
            if (! is_array($note)) {
                continue;
            }
            $noteId = trim((string) ($note['id'] ?? ''));
            $notes[$index]['reminder'] = $byNote[$noteId] ?? null;
        }

        $payload['notes'] = $notes;

        return $payload;
    }

    public function create(
        User $user,
        string $contactId,
        mixed $remindAt,
        ?string $noteId = null,
        string $excerptSource = '',
        ?string $contactName = null,
    ): ContactReminder {
        if ($user->tenant_id === null) {
            throw ValidationException::withMessages([
                'user_id' => ['The selected user is not linked to a tenant.'],
            ]);
        }

        $noteId = $noteId !== null ? trim($noteId) : '';

        if ($noteId !== '') {
            $existing = $this->syncPendingForNote(
                (int) $user->tenant_id,
                $user,
                $contactId,
                $noteId,
                $remindAt,
                $excerptSource,
                $contactName,
            );

            if ($existing === null) {
                throw ValidationException::withMessages([
                    'reminder_at' => ['A reminder time is required.'],
                ]);
            }

            return $existing;
        }

        $created = ContactReminder::query()->create([
            'tenant_id' => $user->tenant_id,
            'contact_id' => $contactId,
            'contact_note_id' => null,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'remind_at' => $this->parseRemindAt($remindAt),
            'status' => ContactReminder::STATUS_PENDING,
            'contact_name' => $this->resolveContactName((int) $user->tenant_id, $contactId, $contactName),
            'note_excerpt' => $this->excerpt($excerptSource),
        ])->load('user:id,name');

        $this->tasks()->syncFromReminder($created);

        return $created;
    }

    /**
     * Create, move, or clear the current user's pending reminder for a note.
     * An empty reminder time dismisses the pending reminder.
     */
    public function syncPendingForNote(
        int $tenantId,
        User $user,
        string $contactId,
        string $noteId,
        mixed $remindAt,
        string $excerptSource = '',
        ?string $contactName = null,
    ): ?ContactReminder {
        $existing = ContactReminder::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('contact_note_id', $noteId)
            ->where('status', ContactReminder::STATUS_PENDING)
            ->first();

        if ($this->isEmptyRemindAt($remindAt)) {
            if ($existing !== null) {
                $existing->update([
                    'status' => ContactReminder::STATUS_DISMISSED,
                    'completed_at' => now(),
                ]);
                $this->tasks()->syncFromReminder($existing->fresh());
            }

            return null;
        }

        $attributes = [
            'contact_id' => $contactId,
            'remind_at' => $this->parseRemindAt($remindAt),
            'contact_name' => $this->resolveContactName($tenantId, $contactId, $contactName),
            'note_excerpt' => $this->excerpt($excerptSource),
        ];

        if ($existing !== null) {
            if ($attributes['contact_name'] === '') {
                unset($attributes['contact_name']);
            }
            if ($attributes['note_excerpt'] === '') {
                unset($attributes['note_excerpt']);
            }
            $existing->update($attributes);

            $saved = $existing->fresh(['user:id,name']);
            $this->tasks()->syncFromReminder($saved);

            return $saved;
        }

        $created = ContactReminder::query()->create([
            ...$attributes,
            'tenant_id' => $tenantId,
            'contact_note_id' => $noteId,
            'user_id' => $user->id,
            'created_by' => $user->id,
            'status' => ContactReminder::STATUS_PENDING,
            'contact_name' => $attributes['contact_name'],
            'note_excerpt' => $attributes['note_excerpt'],
        ])->load('user:id,name');

        $this->tasks()->syncFromReminder($created);

        return $created;
    }

    public function update(
        ContactReminder $reminder,
        mixed $remindAt,
        ?string $excerptSource = null,
        ?string $contactName = null,
        ?string $noteId = null,
    ): ContactReminder {
        $this->assertPending($reminder);

        $attributes = [
            'remind_at' => $this->parseRemindAt($remindAt),
        ];

        if ($excerptSource !== null) {
            $attributes['note_excerpt'] = $this->excerpt($excerptSource);
        }

        if ($contactName !== null) {
            $attributes['contact_name'] = $this->resolveContactName(
                (int) $reminder->tenant_id,
                (string) $reminder->contact_id,
                $contactName,
            );
        }

        if ($noteId !== null) {
            $noteId = trim($noteId);
            $attributes['contact_note_id'] = $noteId !== '' ? $noteId : null;
        }

        $reminder->update($attributes);

        $saved = $reminder->fresh(['user:id,name']);
        $this->tasks()->syncFromReminder($saved);

        return $saved;
    }

    public function complete(ContactReminder $reminder): ContactReminder
    {
        $this->assertPending($reminder);
        $reminder->update([
            'status' => ContactReminder::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $saved = $reminder->fresh(['user:id,name']);
        $this->tasks()->syncFromReminder($saved);

        return $saved;
    }

    public function dismiss(ContactReminder $reminder): ContactReminder
    {
        $this->assertPending($reminder);
        $reminder->update([
            'status' => ContactReminder::STATUS_DISMISSED,
            'completed_at' => now(),
        ]);

        $saved = $reminder->fresh(['user:id,name']);
        $this->tasks()->syncFromReminder($saved);

        return $saved;
    }

    public function snooze(ContactReminder $reminder, Carbon $remindAt): ContactReminder
    {
        $this->assertPending($reminder);
        $reminder->update([
            'remind_at' => $remindAt,
        ]);

        return $reminder->fresh(['user:id,name']);
    }

    public function findForUser(User $user, string $reminderId): ContactReminder
    {
        $query = ContactReminder::query()->whereKey($reminderId);

        if (! $user->isMaster()) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return $query->firstOrFail();
    }

    public function assertCanManage(User $user, ContactReminder $reminder): void
    {
        if (! $user->isMaster() && (int) $reminder->tenant_id !== (int) $user->tenant_id) {
            abort(404);
        }

        if ($user->isMaster() || $this->managesTenantReminders($user)) {
            return;
        }

        if ((int) $reminder->user_id !== (int) $user->id) {
            abort(403);
        }
    }

    public function deleteForNote(int $tenantId, string $noteId): void
    {
        if ($noteId === '') {
            return;
        }

        $reminders = ContactReminder::query()
            ->where('tenant_id', $tenantId)
            ->where('contact_note_id', $noteId)
            ->get();

        foreach ($reminders as $reminder) {
            $this->tasks()->deleteForReminder($reminder);
        }

        ContactReminder::query()
            ->where('tenant_id', $tenantId)
            ->where('contact_note_id', $noteId)
            ->delete();
    }

    public function deleteForContact(int $tenantId, string $contactId): void
    {
        $reminders = ContactReminder::query()
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contactId)
            ->get();

        foreach ($reminders as $reminder) {
            $this->tasks()->deleteForReminder($reminder);
        }

        ContactReminder::query()
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contactId)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ContactReminder $reminder, ?bool $localContact = null): array
    {
        $reminder->loadMissing('user:id,name');

        if ($localContact === null) {
            $localContact = $this->isLocalContact((int) $reminder->tenant_id, (string) $reminder->contact_id);
        }

        return [
            'id' => (string) $reminder->id,
            'contact_id' => (string) $reminder->contact_id,
            'contact_note_id' => $reminder->contact_note_id !== null ? (string) $reminder->contact_note_id : null,
            'contact_name' => (string) $reminder->contact_name,
            'note_excerpt' => (string) $reminder->note_excerpt,
            'remind_at' => $reminder->remind_at?->toIso8601String() ?? '',
            'status' => (string) $reminder->status,
            'user_id' => (int) $reminder->user_id,
            'user_name' => (string) ($reminder->user?->name ?? ''),
            'local_contact' => $localContact,
        ];
    }

    public function managesTenantReminders(User $user): bool
    {
        if ($user->isMaster()) {
            return true;
        }

        return $user->roles()
            ->where(function ($query) {
                $query->where('slug', 'tenant_admin')
                    ->orWhere('name', 'Tenant Admin');
            })
            ->exists();
    }

    private function tasks(): CrmTaskService
    {
        return app(CrmTaskService::class);
    }

    private function assertPending(ContactReminder $reminder): void
    {
        if ($reminder->status !== ContactReminder::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'reminderId' => ['This reminder is already closed.'],
            ]);
        }
    }

    private function parseRemindAt(mixed $remindAt): Carbon
    {
        if ($this->isEmptyRemindAt($remindAt)) {
            throw ValidationException::withMessages([
                'reminder_at' => ['A reminder time is required.'],
            ]);
        }

        return Carbon::parse($remindAt);
    }

    private function isEmptyRemindAt(mixed $remindAt): bool
    {
        return $remindAt === null || (is_string($remindAt) && trim($remindAt) === '');
    }

    private function excerpt(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $body) ?? '');

        if (mb_strlen($text) <= 180) {
            return $text;
        }

        return mb_substr($text, 0, 177).'...';
    }

    private function resolveContactName(int $tenantId, string $contactId, ?string $provided): string
    {
        $provided = trim((string) $provided);
        if ($provided !== '') {
            return mb_substr($provided, 0, 255);
        }

        if (! Str::isUuid($contactId)) {
            return '';
        }

        $contact = Contact::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($contactId)
            ->first(['id', 'first_name', 'last_name', 'company_name']);

        if ($contact === null) {
            return '';
        }

        $name = trim($contact->first_name.' '.$contact->last_name);
        if ($name === '') {
            $name = trim((string) $contact->company_name);
        }

        return mb_substr($name, 0, 255);
    }

    private function isLocalContact(int $tenantId, string $contactId): bool
    {
        return isset($this->localContactIdSet($tenantId, [$contactId])[$contactId]);
    }

    /**
     * @param  list<mixed>  $contactIds
     * @return array<string, true>
     */
    private function localContactIdSet(int $tenantId, array $contactIds): array
    {
        $uuids = array_values(array_filter(
            $contactIds,
            fn (mixed $id) => is_string($id) && Str::isUuid($id)
        ));

        if ($uuids === []) {
            return [];
        }

        return Contact::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $uuids)
            ->pluck('id')
            ->mapWithKeys(fn (mixed $id) => [(string) $id => true])
            ->all();
    }
}

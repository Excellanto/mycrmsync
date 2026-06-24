<?php

namespace App\Services\Contacts;

use App\Models\Contact;
use App\Models\ContactNote;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ApplicationCache;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class ContactService
{
    /**
     * @return LengthAwarePaginator<int, Contact>
     */
    public function paginateForTenant(
        int $tenantId,
        array $filters = [],
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = $this->filteredQuery($tenantId, $filters)
            ->with(['assignedUser:id,name,email'])
            ->orderByDesc('created_at');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @return Collection<int, Contact>
     */
    public function searchForTenant(Tenant $tenant, array $params = []): Collection
    {
        $limit = min(max((int) ($params['pageLimit'] ?? $params['limit'] ?? 20), 1), 100);
        $query = trim((string) ($params['query'] ?? ''));

        $builder = $this->filteredQuery($tenant->id, [
            'search' => $query,
            'assigned_to' => $params['assigned_to'] ?? null,
            'tag' => $params['tag'] ?? null,
        ])->with(['assignedUser:id,name,email'])
            ->orderByDesc('created_at')
            ->limit($limit);

        return $builder->get();
    }

    public function countForTenant(int $tenantId, array $filters = []): int
    {
        return $this->filteredQuery($tenantId, $filters)->count();
    }

    public function findForTenant(int $tenantId, string $contactId): Contact
    {
        return Contact::query()
            ->forTenantId($tenantId)
            ->with(['assignedUser:id,name,email'])
            ->whereKey($contactId)
            ->firstOrFail();
    }

    public function findByPhone(int $tenantId, string $phone): ?Contact
    {
        $phone = trim($phone);
        $digits = PhoneNormalizer::digits($phone);

        if ($digits === '') {
            return null;
        }

        $suffix = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        $candidates = Contact::query()
            ->forTenantId($tenantId)
            ->where('phone', '!=', '')
            ->where('phone', 'ilike', '%'.$suffix.'%')
            ->orderByDesc('updated_at')
            ->get();

        foreach ($candidates as $contact) {
            if (PhoneNormalizer::digitsMatch((string) $contact->phone, $phone)) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $tenantId, array $data): Contact
    {
        $attributes = $this->normalizeContactAttributes($data);
        $attributes['tenant_id'] = $tenantId;

        return Contact::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Contact $contact, array $data): Contact
    {
        $contact->update($this->normalizeContactAttributes($data));

        return $contact->fresh(['assignedUser:id,name,email']);
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }

    /**
     * @param  list<string>  $tags
     */
    public function syncTags(Contact $contact, array $tags): Contact
    {
        $normalized = array_values(array_unique(array_filter(array_map(
            fn ($tag) => trim((string) $tag),
            $tags
        ))));

        $contact->update(['tags' => $normalized]);

        return $contact->fresh();
    }

    /**
     * @return Collection<int, ContactNote>
     */
    public function listNotes(Contact $contact): Collection
    {
        return $contact->notes()
            ->with('user:id,name,email')
            ->orderByDesc('created_at')
            ->get();
    }

    public function createNote(Contact $contact, User $user, string $body, string $title = ''): ContactNote
    {
        return ContactNote::query()->create([
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->id,
            'user_id' => $user->id,
            'body' => trim($body),
            'title' => trim($title),
        ])->load('user:id,name,email');
    }

    public function updateNote(ContactNote $note, string $body, string $title = ''): ContactNote
    {
        $note->update([
            'body' => trim($body),
            'title' => trim($title),
        ]);

        return $note->fresh(['user:id,name,email']);
    }

    public function deleteNote(ContactNote $note): void
    {
        $note->delete();
    }

    public function findNoteForContact(Contact $contact, string $noteId): ContactNote
    {
        return ContactNote::query()
            ->where('contact_id', $contact->id)
            ->where('tenant_id', $contact->tenant_id)
            ->whereKey($noteId)
            ->firstOrFail();
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public function listDistinctTags(int $tenantId): array
    {
        return ApplicationCache::rememberContactTags($tenantId, function () use ($tenantId): array {
            $tags = Contact::query()
                ->forTenantId($tenantId)
                ->whereNotNull('tags')
                ->pluck('tags');

            $unique = [];

            foreach ($tags as $row) {
                if (! is_array($row)) {
                    continue;
                }

                foreach ($row as $tag) {
                    $label = trim((string) $tag);

                    if ($label !== '') {
                        $unique[$label] = true;
                    }
                }
            }

            $out = [];

            foreach (array_keys($unique) as $label) {
                $out[] = ['id' => $label, 'name' => $label];
            }

            usort($out, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

            return $out;
        });
    }

    public function assertMyCrmSyncTenant(Tenant $tenant): void
    {
        $slug = (string) data_get($tenant->integration, 'slug', '');

        if ($slug !== 'mycrmsync') {
            throw ValidationException::withMessages([
                'tenant' => ['Contact management is only available for MyCrmSync tenants.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(int $tenantId, array $filters = []): Builder
    {
        $query = Contact::query()->forTenantId($tenantId);

        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $query->where(function (Builder $q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('first_name', 'ilike', $like)
                    ->orWhere('last_name', 'ilike', $like)
                    ->orWhere('email', 'ilike', $like)
                    ->orWhere('phone', 'ilike', $like)
                    ->orWhere('company_name', 'ilike', $like);
            });
        }

        if (! empty($filters['assigned_to'])) {
            $query->where('assigned_to', (int) $filters['assigned_to']);
        }

        $tag = trim((string) ($filters['tag'] ?? ''));

        if ($tag !== '') {
            $query->whereJsonContains('tags', $tag);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeContactAttributes(array $data): array
    {
        $tags = $data['tags'] ?? null;

        if (is_string($tags)) {
            $tags = array_values(array_filter(array_map('trim', explode(',', $tags))));
        }

        if (! is_array($tags)) {
            $tags = null;
        }

        return [
            'first_name' => trim((string) ($data['first_name'] ?? $data['firstName'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? $data['lastName'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'company_name' => trim((string) ($data['company_name'] ?? $data['companyName'] ?? '')),
            'business_info' => trim((string) ($data['business_info'] ?? $data['businessInfo'] ?? '')),
            'source' => trim((string) ($data['source'] ?? '')),
            'type' => trim((string) ($data['type'] ?? '')),
            'assigned_to' => filled($data['assigned_to'] ?? $data['assignedTo'] ?? null)
                ? (int) ($data['assigned_to'] ?? $data['assignedTo'])
                : null,
            'city' => trim((string) ($data['city'] ?? '')),
            'state' => trim((string) ($data['state'] ?? '')),
            'postal_code' => trim((string) ($data['postal_code'] ?? $data['postalCode'] ?? '')),
            'address' => trim((string) ($data['address'] ?? '')),
            'country' => trim((string) ($data['country'] ?? '')),
            'website' => trim((string) ($data['website'] ?? '')),
            'timezone' => trim((string) ($data['timezone'] ?? '')),
            'profile_photo' => trim((string) ($data['profile_photo'] ?? $data['profilePhoto'] ?? '')),
            'date_of_birth' => filled($data['date_of_birth'] ?? $data['dateOfBirth'] ?? null)
                ? ($data['date_of_birth'] ?? $data['dateOfBirth'])
                : null,
            'tags' => $tags,
        ];
    }
}

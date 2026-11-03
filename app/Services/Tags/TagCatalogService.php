<?php

namespace App\Services\Tags;

use App\Integrations\Connectors\GoHighLevel\GoHighLevelApiClient;
use App\Integrations\Connectors\Zoho\ZohoCrmApiClient;
use App\Integrations\CrmApiClientResolver;
use App\Models\Contact;
use App\Models\Tenant;
use App\Models\TenantTag;
use App\Support\ApplicationCache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TagCatalogService
{
    public function __construct(
        private GoHighLevelApiClient $ghl,
        private ZohoCrmApiClient $zoho,
    ) {}

    public function assertSupportedTenant(Tenant $tenant): void
    {
        if (! CrmApiClientResolver::isSupportedTenant($tenant)) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Tag management is available for MyCrmSync, Zoho, and GoHighLevel.'],
            ]);
        }
    }

    public function labelFor(Tenant $tenant): string
    {
        return match (CrmApiClientResolver::slugForTenant($tenant)) {
            CrmApiClientResolver::SLUG_GOHIGHLEVEL => 'GoHighLevel',
            CrmApiClientResolver::SLUG_ZOHO => 'Zoho',
            CrmApiClientResolver::SLUG_MYCRMSYNC => 'MyCrmSync',
            default => 'CRM',
        };
    }

    /**
     * @return list<array{id: string, name: string, contacts: int|null}>
     */
    public function list(Tenant $tenant): array
    {
        $this->assertSupportedTenant($tenant);

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            return $this->listMyCrmSync($tenant);
        }

        [$payload] = CrmApiClientResolver::isZohoTenant($tenant)
            ? $this->zoho->listLocationTags($tenant)
            : $this->ghl->listLocationTags($tenant);

        $tags = [];

        foreach ($payload['tags'] ?? [] as $tag) {
            if (! is_array($tag)) {
                continue;
            }

            $name = trim((string) ($tag['name'] ?? ''));
            $id = trim((string) ($tag['id'] ?? ''));

            if ($name === '' || $id === '') {
                continue;
            }

            $tags[] = [
                'id' => $id,
                'name' => $name,
                'contacts' => null,
            ];
        }

        usort($tags, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $tags;
    }

    public function create(Tenant $tenant, string $name): void
    {
        $this->assertSupportedTenant($tenant);
        $name = $this->normalizeName($name);

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            $this->createMyCrmSync($tenant, $name);

            return;
        }

        if (CrmApiClientResolver::isZohoTenant($tenant)) {
            $this->zoho->createLocationTag($tenant, $name);

            return;
        }

        $this->ghl->createLocationTag($tenant, $name);
    }

    public function rename(Tenant $tenant, string $id, string $name): void
    {
        $this->assertSupportedTenant($tenant);
        $name = $this->normalizeName($name);
        $id = trim($id);

        if ($id === '') {
            throw ValidationException::withMessages([
                'id' => ['Choose a tag to rename.'],
            ]);
        }

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            $this->renameMyCrmSync($tenant, $id, $name);

            return;
        }

        if ($this->nameTaken($tenant, $name, $id)) {
            throw ValidationException::withMessages([
                'name' => ['This tag already exists.'],
            ]);
        }

        if (CrmApiClientResolver::isZohoTenant($tenant)) {
            $this->zoho->updateLocationTag($tenant, $id, $name);

            return;
        }

        $this->ghl->updateLocationTag($tenant, $id, $name);
    }

    public function delete(Tenant $tenant, string $id): void
    {
        $this->assertSupportedTenant($tenant);
        $id = trim($id);

        if ($id === '') {
            throw ValidationException::withMessages([
                'id' => ['Choose a tag to remove.'],
            ]);
        }

        if (CrmApiClientResolver::isMyCrmSyncTenant($tenant)) {
            $this->deleteMyCrmSync($tenant, $id);

            return;
        }

        if (CrmApiClientResolver::isZohoTenant($tenant)) {
            $this->zoho->deleteLocationTag($tenant, $id);

            return;
        }

        $this->ghl->deleteLocationTag($tenant, $id);
    }

    /**
     * @return list<array{id: string, name: string, contacts: int|null}>
     */
    private function listMyCrmSync(Tenant $tenant): array
    {
        $counts = $this->contactCounts($tenant->id);
        $names = $counts;

        foreach (TenantTag::query()->where('tenant_id', $tenant->id)->pluck('name') as $name) {
            $label = trim((string) $name);

            if ($label !== '' && ! array_key_exists($label, $names)) {
                $names[$label] = 0;
            }
        }

        $tags = [];

        foreach ($names as $name => $count) {
            $tags[] = [
                'id' => (string) $name,
                'name' => (string) $name,
                'contacts' => (int) $count,
            ];
        }

        usort($tags, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $tags;
    }

    private function createMyCrmSync(Tenant $tenant, string $name): void
    {
        if ($this->myCrmSyncNameExists($tenant->id, $name)) {
            throw ValidationException::withMessages([
                'name' => ['This tag already exists.'],
            ]);
        }

        TenantTag::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
        ]);

        ApplicationCache::forgetContactTags((int) $tenant->id);
    }

    private function renameMyCrmSync(Tenant $tenant, string $from, string $to): void
    {
        if (! $this->myCrmSyncNameExists($tenant->id, $from)) {
            throw ValidationException::withMessages([
                'id' => ['That tag was not found.'],
            ]);
        }

        if ($from === $to) {
            return;
        }

        if ($this->myCrmSyncNameExists($tenant->id, $to)) {
            throw ValidationException::withMessages([
                'name' => ['This tag already exists.'],
            ]);
        }

        DB::transaction(function () use ($tenant, $from, $to): void {
            $this->replaceOnContacts((int) $tenant->id, $from, $to);

            $current = TenantTag::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', $from)
                ->first();

            if ($current !== null) {
                $current->name = $to;
                $current->save();
            }
        });

        ApplicationCache::forgetContactTags((int) $tenant->id);
    }

    private function deleteMyCrmSync(Tenant $tenant, string $name): void
    {
        DB::transaction(function () use ($tenant, $name): void {
            TenantTag::query()
                ->where('tenant_id', $tenant->id)
                ->where('name', $name)
                ->delete();

            $this->replaceOnContacts((int) $tenant->id, $name, null);
        });

        ApplicationCache::forgetContactTags((int) $tenant->id);
    }

    private function myCrmSyncNameExists(int $tenantId, string $name): bool
    {
        if (TenantTag::query()->where('tenant_id', $tenantId)->where('name', $name)->exists()) {
            return true;
        }

        return array_key_exists($name, $this->contactCounts($tenantId));
    }

    /**
     * @return array<string, int>
     */
    private function contactCounts(int $tenantId): array
    {
        $counts = [];
        $rows = Contact::query()
            ->forTenantId($tenantId)
            ->whereNotNull('tags')
            ->pluck('tags');

        foreach ($rows as $tags) {
            if (! is_array($tags)) {
                continue;
            }

            foreach ($tags as $tag) {
                $label = trim((string) $tag);

                if ($label !== '') {
                    $counts[$label] = ($counts[$label] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    private function replaceOnContacts(int $tenantId, string $from, ?string $to): void
    {
        Contact::query()
            ->forTenantId($tenantId)
            ->whereNotNull('tags')
            ->orderBy('id')
            ->chunkById(200, function ($contacts) use ($from, $to): void {
                foreach ($contacts as $contact) {
                    $tags = is_array($contact->tags) ? $contact->tags : [];
                    $next = [];
                    $changed = false;

                    foreach ($tags as $tag) {
                        $label = trim((string) $tag);

                        if ($label === $from) {
                            $changed = true;
                            $label = $to ?? '';
                        }

                        if ($label !== '' && ! in_array($label, $next, true)) {
                            $next[] = $label;
                        }
                    }

                    if ($changed) {
                        $contact->tags = $next;
                        $contact->save();
                    }
                }
            });
    }

    private function nameTaken(Tenant $tenant, string $name, string $exceptId): bool
    {
        foreach ($this->list($tenant) as $tag) {
            if ($tag['id'] !== $exceptId && strcasecmp($tag['name'], $name) === 0) {
                return true;
            }
        }

        return false;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Enter a tag name.'],
            ]);
        }

        if (mb_strlen($name) > 100) {
            throw ValidationException::withMessages([
                'name' => ['Tag names must be 100 characters or fewer.'],
            ]);
        }

        return $name;
    }
}

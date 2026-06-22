<?php

namespace App\Integrations\MysimconnectApi;

/**
 * Outcome of listing CRM users mapped to MysimConnect resources.
 */
final readonly class MappedCrmUsersFetchResult
{
    /**
     * @param  list<CrmExternalUserResource>  $users
     */
    public function __construct(
        public array $users,
        public ?string $message,
        public int $httpStatus = 200,
    ) {}

    /**
     * @return list<array{id: string, name: string, email: string, phone: string, role: string}>
     */
    public function usersAsAdminApiJson(): array
    {
        return array_map(static fn (CrmExternalUserResource $u): array => $u->toAdminApiArray(), $this->users);
    }
}

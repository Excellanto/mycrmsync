<?php

namespace App\Integrations\MysimconnectApi;

/**
 * Canonical CRM user representation for MysimConnect admin / internal APIs (stable JSON shape).
 */
final readonly class CrmExternalUserResource
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public string $phone,
        public string $role,
    ) {}

    /**
     * @return array{id: string, name: string, email: string, phone: string, role: string}
     */
    public function toAdminApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role,
        ];
    }
}

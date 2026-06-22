<?php

namespace App\Support;

use App\Models\User;

/**
 * Human-readable bullets for the user create/edit form.
 *
 * How to extend assignment rules:
 * 1. UI list: adjust {@see \App\Models\Role::scopeAssignableByUser} so non-masters only see assignable roles.
 * 2. Server enforcement: extend {@see \App\Http\Controllers\UserController::validatedAssignableRoles} so the API
 *    rejects anything a tenant must not assign (never rely on the UI alone).
 * 3. Copy: add matching lines here — short hints in {@see self::descriptionsFor}, full sections in
 *    {@see self::catalogSections()} for the Assignment rules admin page.
 */
final class RoleAssignmentRules
{
    /**
     * Full read-only catalog for the admin reference page; keep in sync with code paths above.
     *
     * @return list<array{title: string, bullets: list<string>}>
     */
    public static function catalogSections(): array
    {
        return [
            [
                'title' => 'Create User',
                'bullets' => [
                    'Tenant accounts cannot see platform-wide roles (for example Super Admin) in the Role dropdown.',
                    'Tenant accounts only see tenant-scoped roles (for example Tenant Admin, Tenant User) in the Role dropdown.',
                    'Platform administrators see every web role in the Role dropdown.',
                ],
            ],
            [
                'title' => 'Edit User',
                'bullets' => [
                    'Same as Create User: tenant accounts do not see Super Admin or other platform-wide roles in the Role dropdown.',
                    'Editing users outside your tenant is blocked by access policy (tenant admins only manage their organization).',
                ],
            ],
            [
                'title' => 'Server (API)',
                'bullets' => [
                    'Assigning a platform-wide role is rejected for non–platform administrators, even if the request bypasses the UI.',
                    'The same checks apply to create, update, and assign-role endpoints.',
                ],
            ],
            [
                'title' => 'Role settings',
                'bullets' => [
                    'A role marked platform-wide in the database (`is_platform_scope`) is excluded from tenant Create/Edit User dropdowns and cannot be assigned by tenant accounts.',
                    'Super Admin is platform-wide by default in the application seed data.',
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function descriptionsFor(User $actor): array
    {
        if ($actor->isMaster()) {
            return [
                'You may assign any role, including platform-wide roles (for example Super Admin).',
                'Assign roles carefully: platform roles grant access across all tenants.',
            ];
        }

        return [
            'You may only assign tenant-scoped roles (for example Tenant Admin or Tenant User).',
            'Super Admin and other platform-wide roles cannot be assigned from a tenant account.',
        ];
    }
}

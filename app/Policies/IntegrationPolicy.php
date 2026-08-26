<?php

namespace App\Policies;

use App\Models\Integration;
use App\Models\User;

class IntegrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view')
            || $user->can('nav.settings.data-configuration.show');
    }

    public function update(User $user, Integration $integration): bool
    {
        return $user->can('settings.update')
            || $user->can('nav.settings.data-configuration.show');
    }
}

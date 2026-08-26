<?php

namespace App\Policies;

use App\Models\TenantSetting;
use App\Models\User;

class TenantSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view')
            || $user->can('nav.settings.integrations.show');
    }

    public function update(User $user, ?TenantSetting $setting = null): bool
    {
        return $user->can('settings.update')
            || $user->can('nav.settings.integrations.show');
    }
}

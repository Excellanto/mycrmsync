<?php

namespace App\Policies;

use App\Models\SiteSetting;
use App\Models\User;

class SettingPolicy
{
	public function viewAny(User $user): bool
	{
		return $user->can('settings.view')
			|| $user->can('nav.settings.system-settings.show');
	}

	public function update(User $user, SiteSetting $setting): bool
	{
		return $user->can('settings.update')
			|| $user->can('nav.settings.system-settings.show');
	}
}






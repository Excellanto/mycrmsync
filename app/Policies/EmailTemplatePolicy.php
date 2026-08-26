<?php

namespace App\Policies;

use App\Models\EmailTemplate;
use App\Models\User;

class EmailTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.view')
            || $user->can('nav.settings.email-templates.show');
    }

    public function update(User $user, EmailTemplate $emailTemplate): bool
    {
        return $user->can('settings.update')
            || $user->can('nav.settings.email-templates.show');
    }
}

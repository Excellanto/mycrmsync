<?php

namespace App\Policies;

use App\Models\ShortUrl;
use App\Models\User;

class ShortUrlPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('short-urls.view');
    }

    public function view(User $user, ShortUrl $shortUrl): bool
    {
        if (! $user->can('short-urls.view')) {
            return false;
        }

        if ($user->isMaster()) {
            return true;
        }

        return (int) $shortUrl->tenant_id === (int) $user->tenant_id;
    }
}

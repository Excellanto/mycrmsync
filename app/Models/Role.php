<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    protected $casts = [
        'is_platform_scope' => 'boolean',
    ];

    public function scopePlatform($query)
    {
        return $query->where('is_platform_scope', true);
    }

    /**
     * Roles that may appear in assign-role UI for the given user.
     */
    public function scopeAssignableByUser(Builder $query, User $user): Builder
    {
        if ($user->isMaster()) {
            return $query;
        }

        return $query->where('is_platform_scope', false);
    }
}

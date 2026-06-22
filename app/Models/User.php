<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'tenant_id',
        'intsysuser',
        'call_log_sync_token_hash',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'call_log_sync_token_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Platform-wide (cross-tenant) access — driven by {@see \App\Models\Role::$is_platform_scope} in the DB.
     */
    public function isMaster(): bool
    {
        if ($this->roles()->where('is_platform_scope', true)->exists()) {
            return true;
        }

        return $this->roles()->where('slug', 'super_admin')->exists();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'business_info',
        'source',
        'type',
        'assigned_to',
        'city',
        'state',
        'postal_code',
        'address',
        'country',
        'website',
        'timezone',
        'profile_photo',
        'date_of_birth',
        'tags',
    ];

    protected $casts = [
        'tags' => 'array',
        'date_of_birth' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ContactNote::class)->orderByDesc('created_at');
    }

    public function scopeForTenantId(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}

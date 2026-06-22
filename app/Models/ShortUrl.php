<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortUrl extends Model
{
    use HasUuids;

    protected $fillable = [
        'code',
        'long_url',
        'tenant_id',
        'user_id',
        'source_type',
        'source_id',
    ];

    protected $appends = [
        'short_url',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getShortUrlAttribute(): string
    {
        return rtrim((string) config('app.url'), '/').'/surl/'.$this->code;
    }

    public function scopeForTenantId(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CallLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'location_id',
        'user_id',
        'direction',
        'phone_raw',
        'phone_e164',
        'contact_id',
        'contact_name',
        'duration_sec',
        'started_at',
        'ended_at',
        'sim_account_id',
        'status',
        'sync_fingerprint',
        'created_at',
    ];

    protected $casts = [
        'duration_sec' => 'integer',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(CallRecording::class, 'call_log_id');
    }

    /**
     * user_id is stored as text; users.id is bigint — avoid whereHas joins on PostgreSQL.
     */
    public function scopeForTenantId(Builder $query, int $tenantId): Builder
    {
        return $query->whereIn('user_id', function ($sub) use ($tenantId) {
            $sub->selectRaw('id::text')
                ->from('users')
                ->where('tenant_id', $tenantId);
        });
    }
}

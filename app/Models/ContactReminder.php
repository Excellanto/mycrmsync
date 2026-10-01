<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactReminder extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'tenant_id',
        'contact_id',
        'contact_note_id',
        'user_id',
        'created_by',
        'remind_at',
        'status',
        'completed_at',
        'contact_name',
        'note_excerpt',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

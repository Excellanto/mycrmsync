<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CrmTask extends Model
{
    use HasUuids;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'created_by',
        'contact_id',
        'contact_name',
        'contact_note_id',
        'contact_reminder_id',
        'title',
        'body',
        'due_at',
        'is_reminder',
        'status',
        'completed_at',
        'external_provider',
        'external_id',
        'sync_error',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'is_reminder' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'crm_task_assignees')->orderBy('name');
    }

    public function reminder(): BelongsTo
    {
        return $this->belongsTo(ContactReminder::class, 'contact_reminder_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRecording extends Model
{
    use HasUuids;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'call_log_id',
        'contact_id',
        'file_name',
        'storage_path',
        'filetype',
        'mime_type',
        'recording_url',
        'recording_url_long',
        'short_code',
        'transcription_backend',
        'transcription',
        'summary',
        'sentiment',
        'duration_sec',
        'status',
    ];

    protected $casts = [
        'sentiment' => 'array',
        'duration_sec' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function callLog(): BelongsTo
    {
        return $this->belongsTo(CallLog::class, 'call_log_id');
    }
}

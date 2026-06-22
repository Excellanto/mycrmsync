<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'contact_id',
        'batch_id',
        'location_id',
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
        'note_body',
        'duration_sec',
        'crm_note_id',
    ];

    protected $casts = [
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
}

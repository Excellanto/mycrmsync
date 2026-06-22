<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiEndpointMapping extends Model
{
    protected $fillable = [
        'integration_id',
        'integration_slug',
        'system_endpoint_id',
        'system_method',
        'system_uri',
        'system_name',
        'crm_endpoint_key',
        'crm_method',
        'crm_uri',
        'crm_name',
        'field_mappings',
        'enabled',
    ];

    protected $casts = [
        'field_mappings' => 'array',
        'enabled' => 'boolean',
    ];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}

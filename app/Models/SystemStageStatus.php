<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemStageStatus extends Model
{
    protected $fillable = [
        'stage_slug',
        'status_slug',
        'label',
        'sort_order',
    ];
}

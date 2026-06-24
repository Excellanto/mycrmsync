<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    public const SLUG_LOGIN_OTP = 'login_otp';

    public const SLUG_PASSWORD_RECOVERY = 'password_recovery';

    protected $fillable = [
        'slug',
        'name',
        'subject',
        'html_body',
        'variables',
        'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];
}

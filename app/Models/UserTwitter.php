<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserTwitter extends Model
{
    protected $table = 'user_twitters';

    protected $fillable = [
        'username',
        'password',
        'tokens',
        'cookies',
        'user_agent',
        'is_active',
        'is_main',
    ];

    protected $hidden = [
        'password',
        'tokens',
        'cookies',
    ];

    protected $casts = [
        'tokens' => 'array',
        'cookies' => 'array',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
    ];
}

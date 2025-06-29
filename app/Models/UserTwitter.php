<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

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
        'tokens'    => 'array',
        'cookies'   => 'array',
        'is_active' => 'boolean',
        'is_main'   => 'boolean',
    ];

    public static function getExcludedUsernames(): Collection
    {
        return Cache::remember('excluded_usernames_lower', 300, function () {
            $usernamesFromDb     = self::pluck('username');
            $usernamesFromConfig = collect(Config::getValue('TWEET_REPLY_EXCLUDE_USERNAMES', []));

            return $usernamesFromDb
                ->merge($usernamesFromConfig)
                ->map(fn ($v) => strtolower($v))
                ->unique()
                ->flip();
        });
    }
}

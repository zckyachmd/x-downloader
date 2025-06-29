<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tweet extends Model
{
    protected $table = 'tweets';

    protected $fillable = [
        'user_id',
        'screen_name',
        'tweet_id',
        'tweet',
        'related_tweet_id',
        'urls',
        'media',
        'status',
    ];

    protected $casts = [
        'urls'  => 'array',
        'media' => 'array',
    ];
}

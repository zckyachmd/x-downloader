<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tweet extends Model
{
    protected $table = 'tweets';

    protected $fillable = [
        'user_id',
        'username',
        'tweet_id',
        'tweet',
        'related_tweet_id',
        'urls',
        'media',
        'status',
        'is_sensitive',
    ];

    protected $casts = [
        'urls'         => 'array',
        'media'        => 'array',
        'is_sensitive' => 'boolean',
    ];

    public function videoTrendings()
    {
        return $this->hasMany(VideoTrending::class, 'tweet_id', 'tweet_id');
    }
}

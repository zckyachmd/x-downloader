<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionTrack extends Model
{
    protected $fillable = [
        'user_twitter_id',
        'event',
        'user_agent',
        'extra',
    ];

    protected $casts = [
        'extra' => 'array',
    ];

    public function userTwitter(): BelongsTo
    {
        return $this->belongsTo(UserTwitter::class);
    }
}

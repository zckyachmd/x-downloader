<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoDownload extends Model
{
    use HasFactory;

    protected $fillable = [
        'tweet_id',
        'video_index',
        'total_count',
        'last_downloaded_at',
    ];

    protected $casts = [
        'last_downloaded_at' => 'datetime',
    ];

    public function tweet()
    {
        return $this->belongsTo(Tweet::class, 'tweet_id', 'tweet_id');
    }
}

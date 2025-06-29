<?php

namespace Database\Seeders;

use App\Models\Config;
use Illuminate\Database\Seeder;

class ConfigSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * This is the default configs for the application.
         *
         * Only modify the 'value' field to suit your environment.
         * Do NOT change the 'key', 'type', or 'description' unless you know what you're doing.
         */
        $configs = [
            [
                'key'         => 'API_X_DOWNLOADER',
                'name'        => 'API X Downloader',
                'description' => 'API X Downloader backend URL.',
                'value'       => 'http://localhost:3000',
                'type'        => 'string',
            ],
            [
                'key'         => 'TWEET_SEARCH_KEYWORDS',
                'name'        => 'Tweet Search Keywords',
                'description' => 'Kata kunci yang dipakai untuk mencari tweet (misalnya akun, hashtag, atau frasa), pisahkan dengan titik koma (;) untuk menggunakan beberapa kata kunci.',
                'value'       => '@StreamviDL; @TweetHelperBot; @xdownloaderbot; @xviddl',
                'type'        => 'string',
            ],
            [
                'key'         => 'TWEET_SEARCH_KEYWORDS_MAX_DEPTH',
                'name'        => 'Tweet Search Keywords Max Depth',
                'description' => 'Maksimal kedalaman pencarian kata kunci tweet (misalnya akun, hashtag, atau frasa), 1 untuk mencari kata kunci pertama saja.',
                'value'       => '3',
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWEET_REPLY_TEMPLATES',
                'name'        => 'Tweet Reply Templates',
                'description' => 'Template balasan untuk tweet yang diproses. Gunakan array dalam JSON format. Gunakan {{link}} sebagai placeholder untuk tautan download (contoh: ["Grab it here {{link}}"])',
                'value'       => json_encode([
                    "Grab the video here 🎬👇 {{link}}",
                    "Video? Got you 🎥👉 {{link}}",
                    "Save it before it’s gone 🔗💾 {{link}}",
                    "Your download link is served 🍽️ {{link}}",
                    "Fast, clean, no ads ⚡️ {{link}}",
                    "Tap here to get the video 📲 {{link}}",
                    "Done! Video’s here 👇 {{link}}",
                    "Here’s the good stuff 🍿 {{link}}",
                    "Get your clip in 3...2...1 🎞️ {{link}}",
                    "All yours 🚀 {{link}}",
                ]),
                'type' => 'json',
            ],
            [
                'key'         => 'TWEET_REPLY_EXCLUDE_USERNAMES',
                'name'        => 'Tweet Reply Exclude Usernames',
                'description' => 'Daftar username yang tidak diinginkan dalam balasan tweet. Pisahkan dengan titik koma (;).',
                'value'       => json_encode([
                    'StreamviDL',
                    'TweetHelperBot',
                    'xviddl',
                    'xdownloaderbot',
                ]),
                'type' => 'json',
            ],
        ];

        foreach ($configs as $config) {
            Config::updateOrCreate(
                ['key' => $config['key']],
                $config,
            );
        }
    }
}

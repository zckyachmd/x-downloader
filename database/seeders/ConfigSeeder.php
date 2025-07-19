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
                'key'         => 'AUTO_REFRESH_TOKEN',
                'name'        => 'Auto Refresh Token on Account',
                'description' => 'Memperbarui token secara otomatis pada akun X.',
                'value'       => 'true',
                'type'        => 'boolean',
            ],
            [
                'key'         => 'AUTO_SEARCH_TWEET',
                'name'        => 'Auto Search Tweet',
                'description' => 'Mengaktifkan pencarian tweet berdasarkan kata kunci yang akan diantri untuk dibalas.',
                'value'       => 'true',
                'type'        => 'boolean',
            ],
            [
                'key'         => 'AUTO_TWEET_REPLY',
                'name'        => 'Auto Tweet Reply',
                'description' => 'Mencari dan Mengirim balasan tweet secara otomatis berdasarkan kata kunci.',
                'value'       => 'true',
                'type'        => 'boolean',
            ],
            [
                'key'         => 'TWITTER_REFRESH_LIMIT',
                'name'        => 'Twitter Refresh Limit',
                'description' => 'Jumlah maksimum akun yang akan direfresh dalam sekali proses.',
                'value'       => '3',
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWITTER_REFRESH_MODE',
                'name'        => 'Twitter Refresh Mode',
                'description' => 'Mode default saat me-refresh akun: light (cepat) atau deep (lebih dalam).',
                'value'       => 'light',
                'type'        => 'string',
            ],
            [
                'key'         => 'TWEET_FETCH_MODE',
                'name'        => 'Tweet Fetch Mode',
                'description' => 'Mode pencarian tweet: all, fresh, atau historical.',
                'value'       => 'all',
                'type'        => 'string',
            ],
            [
                'key'         => 'TWEET_FETCH_ACCOUNT_LIMIT',
                'name'        => 'Tweet Fetch Account Limit',
                'description' => 'Jumlah maksimal akun yang dipakai untuk fetch tweet dalam 1x jalan.',
                'value'       => 3,
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWEET_FETCH_KEYWORD_LIMIT',
                'name'        => 'Tweet Fetch Keyword Limit',
                'description' => 'Jumlah maksimal kata kunci untuk pencarian tweet.',
                'value'       => 3,
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWEET_FETCH_REST_LIMIT',
                'name'        => 'Tweet Fetch Rest Limit',
                'description' => 'Jumlah istirahat pada setiap akun per jam saat fetch tweet.',
                'value'       => 1,
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWEET_SEARCH_KEYWORDS',
                'name'        => 'Tweet Search Keywords',
                'description' => 'Kata kunci yang dipakai untuk mencari tweet (misalnya akun, hashtag, atau frasa), pisahkan dengan titik koma (;) untuk menggunakan beberapa kata kunci.',
                'value'       => '@TweetHelperBot; @xdownloaderbot; @StreamviDL; @xviddl',
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
                    "Hey {{username}}, just wrapped it up for you. Let me know if you need anything else ✌️ {{link}}",
                    "Hi {{username}}, all sorted and good to go. Tap below whenever you're ready 👇 {{link}}",
                    "{{username}}, everything’s processed just like you wanted. Check it out here 📍 {{link}}",
                    "Alright {{username}}, mission accomplished. You know where to go 😏 {{link}}",
                    "It’s all handled on my end, {{username}}. Have a look and carry on 🚀 {{link}}",
                    "Clean, smooth, and just like that. Here you go {{username}} ⚙️✨ {{link}}",
                    "{{username}}, consider it done. Everything's where it should be now 📌 {{link}}",
                    "Took care of that for you {{username}}. No stress, just results 🔧🎯 {{link}}",
                    "We’re done here {{username}}. Hope it helps — holler if you need more 📫 {{link}}",
                    "Hi {{username}}, that was quick! Always happy to help 💡 {{link}}",
                    "Yo {{username}}, your request’s been sorted. Slide in anytime 🔄 {{link}}",
                    "Task complete {{username}}, and you're all set. Easy does it ✅ {{link}}",
                ]),
                'type' => 'json',
            ],
            [
                'key'         => 'TWEET_REPLY_EXCLUDE_USERNAMES',
                'name'        => 'Tweet Reply Exclude Usernames',
                'description' => 'Daftar username yang tidak diinginkan dalam balasan tweet. Pisahkan dengan titik koma (;).',
                'value'       => json_encode([
                    'TweetHelperBot',
                    'xdownloaderbot',
                    'xviddl',
                    'StreamviDL',
                ]),
                'type' => 'json',
            ],
            [
                'key'         => 'TWEET_REPLY_LIMIT',
                'name'        => 'Tweet Reply Limit',
                'description' => 'Maksimal jumlah balasan tweet dalam 1 proses.',
                'value'       => '5',
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWEET_REPLY_ACCOUNT_LIMIT',
                'name'        => 'Tweet Reply Account Limit',
                'description' => 'Maksimal jumlah akun yang digunakan untuk mengirim balasan tweet.',
                'value'       => '2',
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWEET_REPLY_USAGE_LIMIT',
                'name'        => 'Tweet Reply Usage Limit',
                'description' => 'Maksimal kuota tweet akun yang digunakan untuk mengirim balasan tweet.',
                'value'       => '75',
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWEET_REPLY_MODE',
                'name'        => 'Tweet Reply Mode',
                'description' => 'Mode penggunaan akun untuk mengirim balasan tweet (safe, balanced, aggressive).',
                'value'       => 'safe',
                'type'        => 'string',
            ],
            [
                'key'         => 'TWEET_REPLY_REST_LIMIT',
                'name'        => 'Tweet Reply Rest Account Hourly',
                'description' => 'Jumlah istirahat pada setiap akun per jam saat mengirim balasan tweet.',
                'value'       => '1',
                'type'        => 'integer',
            ],
            [
                'key'         => 'TWEET_REST_START_TIME',
                'name'        => 'Tweet Rest Start Time',
                'description' => 'Waktu mulai rest (jam:menit) untuk mencari tweet dan mengirim balasan tweet.',
                'value'       => '00:00',
                'type'        => 'string',
            ],
            [
                'key'         => 'TWEET_REST_END_TIME',
                'name'        => 'Tweet Rest End Time',
                'description' => 'Waktu akhir rest (jam:menit) untuk mencari tweet dan mengirim balasan tweet.',
                'value'       => '03:00',
                'type'        => 'string',
            ],
            [
                'key'         => 'STEALTH_ADS_ENABLED',
                'name'        => 'Stealth Ads Enabled',
                'description' => 'Aktifkan atau nonaktifkan iklan stealth berbasis overlay klik.',
                'value'       => 'true',
                'type'        => 'boolean',
            ],
            [
                'key'         => 'STEALTH_ADS_URLS',
                'name'        => 'Stealth Ads URLs',
                'description' => 'Daftar URL untuk stealth ads (yang dibuka saat pengguna klik overlay).',
                'value'       => json_encode([
                    'https://s.id/zckyachmd',
                    'https://s.id/zacky',
                ]),
                'type' => 'json',
            ],
            [
                'key'         => 'STEALTH_ADS_EXCLUDED_URLS',
                'name'        => 'Stealth Ads Exclude URLs',
                'description' => 'Daftar URL path (route) yang dikecualikan dari stealth overlay.',
                'value'       => json_encode([
                    '/privacy-policy',
                    '/terms',
                    '/about',
                ]),
                'type' => 'json',
            ],
            [
                'key'         => 'SHLINK_ENABLED',
                'name'        => 'Shlink Enabled',
                'description' => 'Aktifkan atau nonaktifkan shlink.',
                'value'       => 'true',
                'type'        => 'boolean',
            ],
            [
                'key'         => 'SHLINK_API_URL',
                'name'        => 'Shlink API URL',
                'description' => 'URL untuk shlink.',
                'value'       => '',
                'type'        => 'string',
            ],
            [
                'key'         => 'SHLINK_API_KEY',
                'name'        => 'Shlink API Key',
                'description' => 'API Key untuk shlink.',
                'value'       => '',
                'type'        => 'string',
            ],
        ];

        foreach ($configs as $config) {
            $created = Config::firstOrCreate(['key' => $config['key']], $config);
            if ($created->wasRecentlyCreated) {
                $this->command->info("✅ Created config: {$config['key']}");
            } else {
                $this->command->warn("⚠️ Skipped existing config: {$config['key']}");
            }
        }
    }
}

<?php

namespace App\Utils;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UserAgent
{
    protected array $agents = [
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.4.1 Safari/605.1.15",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.3.1 Safari/605.6.18",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Safari/605 Vienna/3.9.5",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6.1 Safari/605 Vienna/3.9.5",
        "Mozilla/5.0 (Macintosh; ARM Mac OS X 15_4_0) AppleWebKit/621.1.15.11.10 (KHTML, like Gecko) Version/18.4 Safari/621.1.15.11",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.204 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_2_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.265 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_1_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.205 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_1_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.6778.86 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_3_2) AppleWebKit/621.1.2.111.4 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/621.1.2.111",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_4_0) AppleWebKit/621.1.2.111.4 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/621.1.2.111.4",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_3_2) AppleWebKit/621.1.2.111.4 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/621.1.2.111.4",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_3_1) AppleWebKit/621.1.2.111.4 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/621.1.2.111",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0000.000 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_0_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.6723.191 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_2_0) AppleWebKit/621.1.2.111.4 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/621.1.2.111",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_3_1) AppleWebKit/621.1.2.111.4 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/621.1.2.111.4",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_1) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.15",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 15_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Safari/605.1.1",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 14.1) AppleWebKit/618.27 (KHTML, like Gecko) Version/17.4 Safari/618.27",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 14.3) AppleWebKit/617.17 (KHTML, like Gecko) Version/17.0.16 Safari/617.17",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.8220.52 Safari/537.36 Brave/4.0.2090.104",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.3718.101 Safari/537.36 Brave/48.0.7707.10",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.4348.33 Safari/537.36 Brave/70.0.4836.151",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.7045.45 Safari/537.36 Brave/23.0.2490.53",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.2649.110 Safari/537.36 Brave/57.0.4715.169",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.4226.125 Safari/537.36 Brave/0.0.8775.120",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.5194.41 Safari/537.36 Brave/7.0.8593.155",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.7111.81 Safari/537.36 Brave/41.0.7531.123",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.8183.187 Safari/537.36 Brave/15.0.6876.107",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.7",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0 OneOutlook/1.2025.516.400",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.3240.92",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0 Agency/93.8.3667.68",
        "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.7103.93 Safari/537.36 Edg/136.0.3240.64",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0 OneOutlook/1.2025.109.100",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.1849.39",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.1820.95",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.1833.10",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.1766.75",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.1921.61",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.1897.18",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.1822.51",
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36 Edg/135.0.1803.94",
    ];

    public function random(): string
    {
        return $this->agents[array_rand($this->agents)] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36 Edg/136.0.0.0';
    }

    protected array $socialBotKeywords = [
        'twitterbot',
        'facebookexternalhit',
        'facebot',
        'slackbot',
        'discordbot',
        'telegrambot',
        'linkedinbot',
        'whatsapp',
        'pinterest',
        'skypeuripreview',
    ];

    protected array $searchEngineBots = [
        'googlebot',
        'bingbot',
        'duckduckbot',
        'baiduspider',
        'yandexbot',
        'ahrefsbot',
        'semrushbot',
        'mj12bot',
        'dotbot',
        'sogou',
    ];

    public function isSocialMediaBot(?string $userAgent, ?Request $request = null): bool
    {
        if (!$userAgent) {
            return false;
        }

        $ua       = strtolower($userAgent);
        $accept   = strtolower($request?->header('accept', '') ?? '');
        $cacheKey = 'ua:bot:' . md5($ua . '|' . $accept);

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($ua, $accept) {
            foreach ($this->socialBotKeywords as $keyword) {
                if (str_contains($ua, $keyword)) {
                    return true;
                }
            }

            foreach ($this->searchEngineBots as $bot) {
                if (str_contains($ua, $bot)) {
                    return false;
                }
            }

            return str_contains($accept, 'text/html')
                && !str_contains($accept, 'application/json')
                && !str_contains($ua, 'chrome')
                && !str_contains($ua, 'safari')
                && !str_contains($ua, 'firefox');
        });
    }
}

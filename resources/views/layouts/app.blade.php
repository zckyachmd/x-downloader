<!DOCTYPE html>
<html lang="en">

<head>
    <script type='text/javascript' src='//hammerhintthesaurus.com/ca/e1/bf/cae1bfb87f3f6e5d34fe08a9ebe88bf8.js'>
    </script>

    <!-- Histats.com  START (hidden counter) -->
    <a href="/" alt="" target="_blank">
        <img src="//sstatic1.histats.com/0.gif?4962367&101" alt="" border="0">
        <!-- Histats.com  END  -->

        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="description" content="{{ $description ?? '' }}">
        <meta name="author" content="Zacky Achmad" />
        <meta name="keywords"
            content="{{ $keywords ?? 'twitter video download, X video, download X, save twitter video' }}">

        {{-- Robots Control --}}
        <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
        <meta name="googlebot" content="{{ $robots ?? 'index, follow' }}">
        <meta name="bingbot" content="{{ $robots ?? 'index, follow' }}">

        <link rel="icon" type="image/png" href="{{ url('assets/img/favicon.png') }}" />
        <link rel="canonical" href="{{ url()->current() }}" />

        <title>{{ trim(($title ?? '') . ' | ' . config('app.name', 'Laravel'), ' |') }}</title>

        {{-- OG Meta --}}
        <meta property="og:type" content="{{ !empty($videoUrl) ? 'video.other' : 'website' }}">
        <meta property="og:title" content="{{ $title ?? '' }}">
        <meta property="og:description" content="{{ $description ?? '' }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:image" content="{{ $thumbnail ?? url('assets/img/thumbnail.jpg') }}">
        <meta property="og:image:secure_url" content="{{ $thumbnail ?? url('assets/img/thumbnail.jpg') }}">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        @if (!empty($videoUrl))
            <meta property="og:video" content="{{ $videoUrl }}">
            <meta property="og:video:secure_url" content="{{ $videoUrl }}">
            <meta property="og:video:type" content="video/mp4">
            <meta property="og:video:width" content="{{ $media['resolution_width'] ?? 720 }}">
            <meta property="og:video:height" content="{{ $media['resolution_height'] ?? 1280 }}">
        @endif

        {{-- Twitter Card --}}
        <meta name="twitter:card" content="{{ 'summary_large_image' }}">
        <meta name="twitter:site" content="@zckyachmd">
        <meta name="twitter:creator" content="{{ '@' . ($username ?? '') }}">
        <meta name="twitter:title" content="{{ $title ?? '' }}">
        <meta name="twitter:description" content="{{ $description ?? '' }}">
        <meta name="twitter:image" content="{{ $thumbnail ?? url('assets/img/thumbnail.jpg') }}">
        @if (!empty($videoUrl))
            <meta name="twitter:player" content="{{ $videoUrl }}">
            <meta name="twitter:player:stream" content="{{ $videoUrl }}">
            <meta name="twitter:player:stream:content_type" content="video/mp4">
            <meta name="twitter:player:width" content="{{ $media['resolution_width'] ?? 720 }}">
            <meta name="twitter:player:height" content="{{ $media['resolution_height'] ?? 1280 }}">
        @endif

        {{-- Schema.org --}}
        <meta itemprop="name" content="{{ $title ?? '' }}">
        <meta itemprop="description" content="{{ $description ?? '' }}">
        <meta itemprop="image" content="{{ $thumbnail ?? url('assets/img/thumbnail.jpg') }}">
        @stack('meta')

        {{-- CSS --}}
        <link rel="stylesheet" href="{{ url('css/styles.css') }}" />
        @stack('styles')

        {{-- JS --}}
        <script src="{{ url('js/jquery-3.7.1.js') }}"></script>
        <script src="{{ url('js/font-awesome-6.4.0.js') }}"></script>
        <script src="{{ url('js/feather.js') }}"></script>
</head>

<body>
    <div id="layoutDefault">
        <div id="layoutDefault_content">
            <main>
                {{-- Navbar --}}
                @include('layouts._navbar')

                {{-- Header --}}
                @yield('header')

                {{-- Main Content --}}
                @yield('content')
            </main>
        </div>
        <div id="layoutDefault_footer">
            {{-- Footer --}}
            @include('layouts._footer')
        </div>
    </div>

    {{-- Ads --}}
    <a data-stealth target="_blank"
        style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 9999; background-color: transparent; pointer-events: auto;"></a>

    {{-- Modal --}}
    @include('layouts._modal')
    @stack('modals')

    <script src="{{ url('js/bootstrap.bundle-5.2.3.js') }}"></script>
    <script src="{{ url('js/sweetalert2.js') }}"></script>
    <script src="{{ url('js/scripts.js') }}"></script>
    <script>
        $(document).ajaxSend(function (event, xhr, settings) {
            xhr.setRequestHeader('X-CSRF-TOKEN', $('meta[name="csrf-token"]').attr('content'));
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
        });

        (function () {
            const STEALTH_DURATION = 3 * 60 * 1000;
            const urls = [
                "https://denotemylemonade.com/mk0g7xvz25?key=95729ea92a958e28a14d2717551cf133",
                "https://denotemylemonade.com/mk0g7xvz25?key=95729ea92a958e28a14d2717551cf133",
                "https://zacky.id",
            ];

            const getRandomUrl = () => urls[Math.floor(Math.random() * urls.length)];
            const randomId = () => "stealth-" + Math.random().toString(36).slice(2, 10);

            let timeout;

            function mountStealthLink() {
                const $existing = $("[data-stealth]");
                if ($existing.length) $existing.remove();

                const $link = $("<a>", {
                    id: randomId(),
                    href: getRandomUrl(),
                    target: "_blank",
                    "data-stealth": true,
                    css: {
                        position: "fixed",
                        top: 0,
                        left: 0,
                        width: "100%",
                        height: "100%",
                        "z-index": 9999,
                        "background-color": "transparent",
                        "pointer-events": "auto"
                    }
                });

                let clickCount = 0;
                let lastClickTime = 0;
                const maxClicks = 1 + Math.floor(Math.random() * 3);

                $link.on("click", function () {
                    const now = Date.now();
                    if (now - lastClickTime < 500) return;
                    lastClickTime = now;

                    clickCount++;
                    if (clickCount >= maxClicks) {
                        $link.remove();
                        $("body").removeClass("stealth-active");

                        clearTimeout(timeout);
                        timeout = setTimeout(mountStealthLink, STEALTH_DURATION);
                    }
                });

                $("body").append($link).addClass("stealth-active");

                try {
                    Object.defineProperty(window, "click_please", {
                        value: undefined,
                        writable: false,
                        configurable: false,
                    });
                } catch (_) {}
            }

            mountStealthLink();
        })();
    </script>
    @stack('scripts')
    <script type='text/javascript' src='//hammerhintthesaurus.com/ec/b8/56/ecb85690fbf2ed786726f35d884d4c93.js'>
    </script>
</body>

</html>

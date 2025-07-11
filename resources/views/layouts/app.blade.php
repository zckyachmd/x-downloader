<!DOCTYPE html>
<html lang="en">

@php
    $title = trim(($title ?? '') . ' | ' . config('app.name', 'Laravel'), ' |');
    $thumbnail = (isset($thumbnail) && trim($thumbnail)) ? $thumbnail : url('assets/img/favicon.png');
    $robots = (isset($robots) && trim($robots)) ? $robots : 'index, follow';
@endphp

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta name="description" content="{{ $description ?? '' }}">
    <meta name="author" content="Zacky Achmad" />
    <meta name="keywords"
        content="{{ $keywords ?? 'twitter video download, X video, download X, save twitter video' }}">

    {{-- Robots --}}
    <meta name="robots" content="{{ $robots }}">
    <meta name="googlebot" content="{{ $robots }}">
    <meta name="bingbot" content="{{ $robots }}">

    <link rel="icon" type="image/png" href="{{ url('assets/img/favicon.png') }}" />
    <link rel="canonical" href="{{ url()->current() }}" />
    <title>{{ $title }}</title>

    {{-- Open Graph --}}
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description ?? '' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:image" content="{{ $thumbnail }}">
    <meta property="og:image:secure_url" content="{{ $thumbnail }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary">
    <meta name="twitter:site" content="@zckyachmd">
    <meta name="twitter:creator" content="{{ '@' . ($username ?? '') }}">
    <meta name="twitter:title" content="{{ $title }}">
    <meta name="twitter:description" content="{{ $description ?? '' }}">
    <meta name="twitter:image" content="{{ $thumbnail }}">
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

    {{-- Modal --}}
    @include('layouts._modal')
    @stack('modals')

    <script>
        $(document).ajaxSend(function (event, xhr, settings) {
            xhr.setRequestHeader('X-CSRF-TOKEN', $('meta[name="csrf-token"]').attr('content'));
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
        });

        @if (!empty($stealthAdsUrls) && $stealthAdsEnabled)
            window.STEALTH_CONFIG = Object.freeze({
                enabled: true,
                urls: @json($stealthAdsUrls),
                excluded: @json($stealthAdsExcluded ?? []),
            });
        @endif
    </script>

    <script src="{{ url('js/bootstrap.bundle-5.2.3.js') }}"></script>
    <script src="{{ url('js/sweetalert2.js') }}"></script>
    <script src="{{ url('js/stealth-ads.js?v='. config('app.version')) }}"></script>
    <script src="{{ url('js/scripts.js?v='. config('app.version')) }}"></script>
    @stack('scripts')
</body>

</html>

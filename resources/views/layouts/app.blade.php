<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? '' }}">
    <meta name="author" content="Zacky Achmad" />
    <meta name="keywords" content="{{ $keywords ?? 'twitter video download, X video, download X, save twitter video' }}">

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
    <meta property="og:image" content="{{ $preview ?? url('assets/img/favicon.png') }}">
    <meta property="og:image:secure_url" content="{{ $preview ?? url('assets/img/favicon.png') }}">
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
    <meta name="twitter:creator" content="@{{ $username ?? '' }}">
    <meta name="twitter:title" content="{{ $title ?? '' }}">
    <meta name="twitter:description" content="{{ $description ?? '' }}">
    <meta name="twitter:image" content="{{ $preview ?? url('assets/img/favicon.png') }}">
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
    <meta itemprop="image" content="{{ $preview ?? url('assets/img/favicon.png') }}">
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

    <script src="{{ url('js/bootstrap.bundle-5.2.3.js') }}"></script>
    <script src="{{ url('js/sweetalert2.js') }}"></script>
    <script src="{{ url('js/scripts.js') }}"></script>
    <script>
        $(document).ajaxSend(function (event, xhr, settings) {
            xhr.setRequestHeader('X-CSRF-TOKEN', $('meta[name="csrf-token"]').attr('content'));
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');
        });
    </script>
    @stack('scripts')
</body>

</html>

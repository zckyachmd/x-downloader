<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? '' }}">
    <meta name="author" content="Zacky Achmad" />

    {{-- Robots Control --}}
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <meta name="googlebot" content="{{ $robots ?? 'index, follow' }}">
    <meta name="bingbot" content="{{ $robots ?? 'index, follow' }}">

    {{-- Title --}}
    <title>
        {{ trim(($title ?? '') . ' | ' . config('app.name', 'Laravel'), ' |') }}
    </title>

    {{-- Favicon --}}
    <link rel="icon" type="image/png" href="{{ url('assets/img/favicon.png') }}" />

    {{-- OG Meta --}}
    <meta property="og:title" content="{{ $title ?? '' }}">
    <meta property="og:description" content="{{ $description ?? '' }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:image" content="{{ $preview ?? url('assets/img/video-preview.jpg') }}">
    @if (!empty($videoUrl))
    <meta property="og:type" content="video.other">
    <meta property="og:video" content="{{ $videoUrl }}">
    <meta property="og:video:secure_url" content="{{ $videoUrl }}">
    <meta property="og:video:type" content="video/mp4">
    <meta property="og:video:width" content="{{ $media['resolution_width'] ?? 720 }}">
    <meta property="og:video:height" content="{{ $media['resolution_height'] ?? 1280 }}">
    @else
    <meta property="og:type" content="website">
    @endif

    {{-- Twitter Meta --}}
    <meta name="twitter:card" content="{{ !empty($videoUrl) ? 'player' : 'summary_large_image' }}">
    <meta name="twitter:site" content="@@zckyachmd">
    <meta name="twitter:creator" content="@@{{ $username ?? 'unknown' }}">
    <meta name="twitter:title" content="{{ $title ?? '' }}">
    <meta name="twitter:description" content="{{ $description ?? '' }}">
    <meta name="twitter:image" content="{{ $preview ?? url('assets/img/video-preview.jpg') }}">
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
    <meta itemprop="thumbnailUrl" content="{{ $preview ?? url('assets/img/video-preview.jpg') }}">

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

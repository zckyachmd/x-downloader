<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Download X Video from @{{ $username ?? 'unknown' }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1DA1F2">

    {{-- Prevent indexing by bots (including Google, Bing, etc) --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="googlebot" content="noindex, nofollow">
    <meta name="bingbot" content="noindex, nofollow">

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ url()->current() }}">

    {{-- Shared variables --}}
    @php
        $resolvedUsername = $username ?? 'unknown';
        $previewImage = $preview ?? asset('twitter-preview.jpg');
        $defaultDescription = "Watch and download X video (ID: {$tweetId})";
    @endphp

    {{-- Open Graph (Facebook, WhatsApp, Discord, etc) --}}
    <meta property="og:type" content="video.other">
    <meta property="og:site_name" content="Twitter Video Downloader">
    <meta property="og:title" content="Video from @{{ $resolvedUsername }}">
    <meta property="og:description" content="{{ $defaultDescription }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ $previewImage }}">
    <meta property="og:image:type" content="image/jpeg">
    <meta property="og:image:alt" content="Preview from @{{ $resolvedUsername }}">

    @if ($videoUrl)
        <meta property="og:video" content="{{ $videoUrl }}">
        <meta property="og:video:secure_url" content="{{ $videoUrl }}">
        <meta property="og:video:type" content="video/mp4">
        <meta property="og:video:width" content="{{ $media['resolution_width'] ?? 720 }}">
        <meta property="og:video:height" content="{{ $media['resolution_height'] ?? 1280 }}">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="{{ $videoUrl ? 'player' : 'summary_large_image' }}">
    <meta name="twitter:site" content="@zckyachmd">
    <meta name="twitter:creator" content="@{{ $resolvedUsername }}">
    <meta name="twitter:title" content="Video from @{{ $resolvedUsername }}">
    <meta name="twitter:description" content="{{ $defaultDescription }}">
    <meta name="twitter:image" content="{{ $previewImage }}">
    <meta name="twitter:image:alt" content="Preview from @{{ $resolvedUsername }}">

    @if ($videoUrl)
        <meta name="twitter:player" content="{{ $videoUrl }}">
        <meta name="twitter:player:stream" content="{{ $videoUrl }}">
        <meta name="twitter:player:stream:content_type" content="video/mp4">
        <meta name="twitter:player:width" content="{{ $media['resolution_width'] ?? 720 }}">
        <meta name="twitter:player:height" content="{{ $media['resolution_height'] ?? 1280 }}">
    @endif

    {{-- Schema.org (Google Rich Results) --}}
    <meta itemprop="name" content="Download X Video from @{{ $resolvedUsername }}">
    <meta itemprop="description" content="{{ $defaultDescription }}">
    <meta itemprop="thumbnailUrl" content="{{ $previewImage }}">
</head>
<body>
    <h1>Preparing Twitter Video...</h1>
</body>
</html>

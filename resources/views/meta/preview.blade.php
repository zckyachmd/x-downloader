@extends('layouts.app')

@php
$title = "Download X Video from @$username";
$description = "Watch and download X video (ID: {$tweetId})";
$robots = $isSocialBot ? 'index, follow' : 'noindex, nofollow';
@endphp

@section('meta')
    <meta property="og:video" content="{{ $videoUrl }}">
    <meta property="og:video:secure_url" content="{{ $videoUrl }}">
    <meta property="og:video:type" content="video/mp4">
    <meta property="og:video:width" content="{{ $media['resolution_width'] ?? 720 }}">
    <meta property="og:video:height" content="{{ $media['resolution_height'] ?? 1280 }}">

    <meta name="twitter:player" content="{{ $videoUrl }}">
    <meta name="twitter:player:stream" content="{{ $videoUrl }}">
    <meta name="twitter:player:stream:content_type" content="video/mp4">
    <meta name="twitter:player:width" content="{{ $media['resolution_width'] ?? 720 }}">
    <meta name="twitter:player:height" content="{{ $media['resolution_height'] ?? 1280 }}">
@endsection

@section('content')
<div class="container py-5 d-flex justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="card text-center shadow-sm border-0 px-4 py-5" style="max-width: 420px; width: 100%;">

        @if($isSocialBot)
            <div class="mb-4">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <h5 class="card-title fw-semibold mb-2">Your video is almost ready…</h5>

            <p class="text-muted mb-3 small">
                Please wait while we prepare the video.
            </p>
        @else
            <div class="mb-4">
                <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>

            <h5 class="card-title fw-semibold mb-2">Your video is almost ready…</h5>

            <p class="text-muted mb-3 small">
                You’ll be redirected in <span id="countdown" class="fw-bold text-dark">5</span> seconds.
            </p>

            <div id="manualRedirectBtn" class="d-none" data-url="{{ $redirectUrl }}"></div>

            <noscript>
                <div class="mt-4">
                    <a href="{{ $redirectUrl }}" class="btn btn-primary btn-sm px-4">
                        Continue to the video
                    </a>
                    <p class="text-muted small mt-2 mb-0">JavaScript is disabled, please click manually.</p>
                </div>
            </noscript>
        @endif

    </div>
</div>
@endsection

@push('scripts')
@unless($isSocialBot)
<script>
    $(function () {
        const $countdown = $('#countdown');
        const $manualBtn = $('#manualRedirectBtn');
        const redirectUrl = $manualBtn.data('url');

        const startTime = Date.now();
        const totalSeconds = Math.floor(Math.random() * (5 - 3 + 1)) + 3;

        const updateCountdown = () => {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const remaining = Math.max(totalSeconds - elapsed, 0);

            $countdown.text(remaining);

            if (remaining <= 0) {
                $manualBtn
                    .removeClass('d-none')
                    .html(`<a href="${redirectUrl}" class="btn btn-outline-primary btn-sm px-4">
                        Click here if it takes too long
                    </a>`);
                window.location.href = redirectUrl;
            } else {
                const jitter = 200 + Math.floor(Math.random() * 300);
                setTimeout(updateCountdown, jitter);
            }
        };

        setTimeout(updateCountdown, 200 + Math.floor(Math.random() * 300));
    });
</script>
@endunless
@endpush

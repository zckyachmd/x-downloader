@extends('layouts.app')

@php
    $title = "Download X Video from @$username";
    $description = "Watch and download X video (ID: {$tweetId})";
@endphp

@section('content')
<div class="container py-5 d-flex justify-content-center align-items-center" style="min-height: 70vh;">
    <div class="card text-center shadow-sm border-0 px-4 py-5" style="max-width: 420px; width: 100%;">
        <div class="mb-4">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>

        <h5 class="card-title fw-semibold mb-2">Your video is almost ready…</h5>

        <p class="text-muted mb-3 small">
            You’ll be redirected in <span id="countdown" class="fw-bold text-dark">5</span> seconds.
        </p>

        @unless($isBot)
            <div id="manualRedirectBtn" class="d-none" data-url="{{ $redirectUrl }}"></div>

            <noscript>
                <div class="mt-4">
                    <a href="{{ $redirectUrl }}" class="btn btn-primary btn-sm px-4">
                        Continue to the video
                    </a>
                    <p class="text-muted small mt-2 mb-0">JavaScript is disabled, please click manually.</p>
                </div>
            </noscript>
        @endunless
    </div>
</div>
@endsection

@push('scripts')
@unless($isBot)
<script>
    $(function () {
        const $countdown = $('#countdown');
        const $manualBtn = $('#manualRedirectBtn');
        const redirectUrl = $manualBtn.data('url');

        const startTime = Date.now();
        const totalSeconds = 5;

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

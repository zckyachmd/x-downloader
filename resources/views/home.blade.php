@extends('layouts.app')

@push('style')
<style>
    .video-container {
        width: 100%;
        background-color: #000;
        border-radius: 0.5rem;
        overflow: hidden;
        aspect-ratio: 9 / 16;
        max-width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .video-player {
        width: 100%;
        height: auto;
        object-fit: contain;
        display: block;
        border-radius: 0.375rem;
    }

    @media (min-width: 768px) {
        .video-container {
            aspect-ratio: 9 / 16;
            max-height: 420px;
        }
    }

    @media (max-width: 768px) {
        .video-wrapper {
            max-height: 360px;
        }
    }
</style>
@endpush

@section('header')
<header class="page-header-ui page-header-ui-dark bg-img-cover overlay overlay-primary overlay-90">
    <div class="page-header-ui-content py-5 position-relative">
        <div class="container px-5">
            <div class="row gx-5 justify-content-center">
                <div class="col-xl-8 col-lg-10 text-center">
                    <h1 class="page-header-ui-title">X Video Downloader</h1>
                    <p class="page-header-ui-text mb-5">
                        Download any video from X (formerly Twitter) in seconds. No signup, no watermark – just fast and
                        reliable downloads for free.
                    </p>
                </div>
            </div>
            <div class="row gx-5 justify-content-center">
                <div class="col-xl-6 col-lg-8 text-center">
                    <form action="{{ route('tweet.search') }}" method="POST" id="tweet-search-form"
                        class="row row-cols-1 row-cols-md-auto g-3 align-items-center">
                        <div class="col flex-grow-1">
                            <label class="sr-only" for="tweet-url">Enter the tweet URL</label>
                            <input class="form-control form-control-solid" id="tweet-url" name="tweet-url" type="text"
                                placeholder="Enter the tweet URL e.g: https://twitter.com/username/status/123456"
                                value="{{ old('tweet-url', $tweetUrl ?? '') }}" required />
                        </div>

                        <div class="col">
                            <button class="btn btn-teal fw-500 d-flex align-items-center justify-content-center gap-2"
                                type="submit">
                                Download
                                <i class="fa-solid fa-cloud-arrow-down"></i>
                            </button>
                        </div>
                    </form>
                    <div id="tweet-result" class="mt-5 d-none"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="svg-border-rounded text-dark">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 144.54 17.34" preserveAspectRatio="none"
            fill="currentColor">
            <path d="M144.54,17.34H0V0H144.54ZM0,0S32.36,17.34,72.27,17.34,144.54,0,144.54,0"></path>
        </svg>
    </div>
</header>
@endsection

@section('content')
<section class="bg-dark pt-10 pb-5" id="faq">
    <div class="container px-5">
        <div class="row gx-5 justify-content-center text-center">
            <div class="col-lg-8">
                <div class="badge bg-transparent-light rounded-pill badge-marketing mb-4">FAQ</div>
                <h2 class="text-white">Frequently Asked Questions.</h2>
            </div>
        </div>
        <div class="row gx-5 mt-5">
            <div class="col-lg-6 mb-5">
                <div class="d-flex h-100">
                    <div class="icon-stack flex-shrink-0 bg-teal text-white"><i class="fas fa-question"></i></div>
                    <div class="ms-4">
                        <h5 class="text-white">How do I download videos from X (formerly Twitter)?</h5>
                        <p class="text-white-50">Simply paste the tweet link into the input above, and we'll handle the
                            rest. Fast, easy, and watermark-free.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-5">
                <div class="d-flex h-100">
                    <div class="icon-stack flex-shrink-0 bg-teal text-white"><i class="fas fa-question"></i></div>
                    <div class="ms-4">
                        <h5 class="text-white">Can I repost videos I’ve downloaded?</h5>
                        <p class="text-white-50">Make sure to respect the original creator. Reposting content without
                            permission may violate copyright or platform rules.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-5">
                <div class="d-flex h-100">
                    <div class="icon-stack flex-shrink-0 bg-teal text-white"><i class="fas fa-question"></i></div>
                    <div class="ms-4">
                        <h5 class="text-white">Can I download videos from private or protected accounts?</h5>
                        <p class="text-white-50">No. X-Downloader only works with public tweets. We respect user privacy
                            and do not support bypassing account restrictions.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-5">
                <div class="d-flex h-100">
                    <div class="icon-stack flex-shrink-0 bg-teal text-white"><i class="fas fa-question"></i></div>
                    <div class="ms-4">
                        <h5 class="text-white">Is there a limit to how many videos I can download?</h5>
                        <p class="text-white-50">Nope! Download as much as you like. X-Downloader is completely free,
                            with no daily limits or paywalls.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-5">
                <div class="d-flex h-100">
                    <div class="icon-stack flex-shrink-0 bg-teal text-white"><i class="fas fa-question"></i></div>
                    <div class="ms-4">
                        <h5 class="text-white">Can I download videos directly on iOS?</h5>
                        <p class="text-white-50">Yes. Unlike many other platforms that force a “Save As” or fail on
                            Safari, X-Downloader supports auto-download on iOS and iPadOS — no extra apps needed.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-5 mb-lg-0">
                <div class="d-flex h-100">
                    <div class="icon-stack flex-shrink-0 bg-teal text-white"><i class="fas fa-question"></i></div>
                    <div class="ms-4">
                        <h5 class="text-white">Do I get to choose video quality?</h5>
                        <p class="text-white-50">Absolutely. We provide multiple video variants (bitrate/resolution) so
                            you can pick what fits your device or data needs — from lightweight clips to full HD.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ url('js/home.js?v=' . config('app.version')) }}"></script>
<script>
    window.routes = {
        tweetDownload: @json(route('tweet.download', ['videoKey' => ':videoKey'])),
    };

    @if ($autoSearch && filter_var($tweetUrl, FILTER_VALIDATE_URL))
        $(function () {
            const $form = $('#tweet-search-form');
            const $input = $('#tweet-url');

            if ($form.length && $input.length) {
                $input.val(@json($tweetUrl));
                $form.trigger('submit');
            }
        });
    @endif
</script>
@endpush

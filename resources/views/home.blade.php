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
</header>
@endsection

@push('scripts')
<script src="{{ url('js/home.js') }}"></script>
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

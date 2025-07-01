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
<script>
    $(document).ready(function () {
        $('#tweet-search-form').on('submit', function (e) {
            e.preventDefault();

            const form = $(this);
            const btn = form.find('button[type="submit"]');

            if (!btn.data('original-html')) {
                btn.data('original-html', btn.html());
            }

            $.ajax({
                url: form.attr('action'),
                method: form.attr('method'),
                data: form.serialize(),
                beforeSend() {
                    btn.prop('disabled', true).html(`<span class="spinner-border spinner-border-sm"></span> Loading`);
                },
                success(res) {
                    const data = res.data;
                    if (!data) return;

                    const tweetId = data.tweet_id;
                    const cardHtml = renderTweetCard(data, tweetId);

                    $('#tweet-result').html(cardHtml).removeClass('d-none');
                    $('html, body').animate({ scrollTop: $('#tweet-result').offset().top - 80 }, 600);
                },
                error(err) {
                    const message = err?.responseJSON?.message || 'Oops, something went wrong.';

                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: message,
                    });
                },
                complete() {
                    btn.prop('disabled', false).html(btn.data('original-html'));
                }
            });
        });

        $(document).on('click tap touchstart', '.btn-download', function (e) {
            e.preventDefault();

            const btn = $(this);
            const tweetId = btn.data('tweet-id');
            const bitrate = btn.data('bitrate');
            const downloadUrl = @json(route('tweet.download', ['tweetId' => ':tweetId', 'bitrate' => ':bitrate']))
                .replace(':tweetId', tweetId)
                .replace(':bitrate', bitrate);

            if (!btn.data('original-html')) {
                btn.data('original-html', btn.html());
            }

            $.ajax({
                url: downloadUrl,
                method: 'POST',
                xhrFields: {
                    responseType: 'blob'
                },
                beforeSend: function () {
                    btn.prop('disabled', true).html(`<span class="spinner-border spinner-border-sm"></span> Downloading...`);
                },
                success: function (blob, status, xhr) {
                    const disposition = xhr.getResponseHeader('Content-Disposition');
                    const match = /filename="(.+)"/.exec(disposition);
                    const filename = match?.[1] || 'video.mp4';

                    const fileURL = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = fileURL;
                    link.download = filename;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    URL.revokeObjectURL(fileURL);
                },
                error: function (xhr) {
                    let message = 'Failed to download video.';

                    try {
                        const resText = xhr.responseText;
                        if (resText && resText.startsWith('{')) {
                            const response = JSON.parse(resText);
                            message = response.message || message;
                        }
                    } catch (_) {
                        // Silent fail
                    }

                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: message,
                    });
                },
                complete: function () {
                    btn.prop('disabled', false).html(btn.data('original-html'));
                }
            });
        });
    });

    function renderTweetCard(data, tweetId) {
        return `
            <div class="card border-0 shadow-sm p-4">
                <div class="row g-4">
                    <div class="col-12 col-md-5">
                        <div class="bg-black rounded overflow-hidden position-relative w-100" style="aspect-ratio: 9 / 16; height: 100%">
                            <video
                                class="w-100 h-100 rounded-md"
                                style="object-fit: contain"
                                controls
                                playsinline
                                poster="${data.preview.image}"
                            >
                                <source src="${data.preview.video}" type="video/mp4" />
                                Your browser does not support the video tag.
                            </video>
                        </div>
                    </div>

                    <div class="col-md-7 d-flex flex-column justify-content-between">
                        <div class="mb-4">
                            <p class="text-muted tweet-text text-start mb-3 fst-italic border-start ps-3">
                                ${data.text ?? "No text found."}
                            </p>
                            <div class="d-flex align-items-center mb-2">
                                <h6 class="fw-semibold mb-0">
                                    <a
                                        href="https://x.com/${data.author.username}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-decoration-none text-dark"
                                    >
                                        &#8212; @${data.author.username}
                                    </a>
                                </h6>
                            </div>
                            <hr class="my-3" />
                        </div>

                        <div class="mt-auto">
                            <h6 class="fw-bold mb-3 text-dark">Available Videos <small class="text-muted">(${data.media.duration})</small></h6>
                            <div class="d-grid gap-2">
                                ${data.media.variants.map((m) => `
                                    <button
                                        type="button"
                                        class="btn btn-outline-primary btn-sm d-flex justify-content-between align-items-center btn-download"
                                        data-bitrate="${m.bitrate}"
                                        data-tweet-id="${tweetId}"
                                    >
                                        <span class="text-truncate">
                                            ${m.resolution ?? "Unknown"} • ${m.size ?? "Unknown"}
                                        </span>
                                        <i class="fa-solid fa-download ms-2"></i>
                                    </button>
                                `).join("")}
                            </div>

                            <div class="mt-2 text-start text-muted fst-italic" style="font-size: 0.75rem;">
                                Estimated size — actual may vary.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
</script>
@endpush

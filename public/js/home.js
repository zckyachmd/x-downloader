$(document).ready(function () {
    $("#tweet-search-form").on("submit", function (e) {
        e.preventDefault();

        const form = $(this);
        const btn = form.find('button[type="submit"]');

        if (!btn.data("original-html")) {
            btn.data("original-html", btn.html());
        }

        $.ajax({
            url: form.attr("action"),
            method: form.attr("method"),
            data: form.serialize(),
            beforeSend() {
                btn.prop("disabled", true).html(
                    `<span class="spinner-border spinner-border-sm"></span> Loading`
                );
            },
            success(res) {
                const data = res.data;
                if (!data) return;

                const cardHtml = renderTweetCard(data);
                $("#tweet-result").html(cardHtml).removeClass("d-none");

                bindCustomPlayButtons();

                $("html, body").animate(
                    { scrollTop: $("#tweet-result").offset().top - 100 },
                    600
                );
            },
            error(err) {
                const message =
                    err?.responseJSON?.message || "Oops, something went wrong.";

                Swal.fire({
                    icon: "error",
                    title: "Oops...",
                    text: message,
                });
            },
            complete() {
                btn.prop("disabled", false).html(btn.data("original-html"));
            },
        });
    });

    $(document).on("click tap touchstart", ".btn-download", function (e) {
        e.preventDefault();

        const btn = $(this);
        const videoKey = btn.data("video-key");
        const bitrate = btn.data("bitrate");

        let downloadUrl = window.routes.tweetDownload.replace(
            ":videoKey",
            encodeURIComponent(videoKey)
        );
        if (bitrate) {
            downloadUrl += `?bitrate=${encodeURIComponent(bitrate)}`;
        }

        if (!btn.data("original-html")) {
            btn.data("original-html", btn.html());
        }

        btn.prop("disabled", true).html(
            `<span class="spinner-border spinner-border-sm"></span> Preparing...`
        );

        const a = document.createElement("a");
        a.href = downloadUrl;
        a.download = "";
        a.style.display = "none";
        document.body.appendChild(a);
        a.click();
        a.remove();

        setTimeout(() => {
            btn.prop("disabled", false).html(btn.data("original-html"));
        }, 2000);
    });
});

function renderTweetCard(data) {
    const mediaList = data.media || [];

    const gridItemsHtml = mediaList
        .map((media) => {
            const variantCount = media.variants.length;

            const variantsHtml = media.variants
                .sort((a, b) => (b.bitrate ?? 0) - (a.bitrate ?? 0))
                .map((v, i) => {
                    const isLastOdd =
                        variantCount % 2 === 1 && i === variantCount - 1;
                    const colClass =
                        variantCount === 1
                            ? "col-12"
                            : isLastOdd
                            ? "col-12"
                            : "col-6";

                    return `
                        <div class="${colClass} mb-2">
                            <button
                                type="button"
                                class="btn btn-outline-primary btn-sm w-100 btn-download"
                                data-video-key="${media.key}"
                                data-bitrate="${v.bitrate}"
                            >
                                ${v.resolution ?? "?"} • ${v.size ?? "?"}
                            </button>
                        </div>`;
                })
                .join("");

            return `
                <div class="${
                    mediaList.length === 1
                        ? "col-md-10 offset-md-1 mb-4 mt-4"
                        : "col-md-6 mb-3 mt-3"
                }">
                    <div class="media-card position-relative bg-black rounded overflow-hidden" style="aspect-ratio: 10 / 13; border: 1px solid rgba(0,0,0,0.1);">
                       <video
                            id="video-${media.key}"
                            class="w-100 h-100 rounded tweet-video"
                            style="object-fit: contain"
                            playsinline
                            poster="${media.preview}"
                            preload="none"
                            loading="lazy"
                            data-video-key="${media.key}"
                        >
                            <source src="${media.video}" type="video/mp4" />
                            Your browser does not support the video tag.
                        </video>

                        <button
                            type="button"
                            class="btn custom-play-btn position-absolute top-50 start-50 translate-middle"
                            data-target="video-${media.key}"
                            aria-label="Play"
                            style="
                                width: 64px;
                                height: 64px;
                                border-radius: 50%;
                                background-color: rgba(255, 255, 255, 0.85);
                                border: none;
                                box-shadow: 0 0 10px rgba(0,0,0,0.2);
                                font-size: 1.5rem;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                            "
                        >
                            <i class="fas fa-play"></i>
                        </button>

                        <span class="position-absolute top-0 end-0 m-2 px-2 py-1 bg-dark bg-opacity-75 text-white small rounded-pill" style="font-size: 0.65rem;">
                            ${media.duration}
                        </span>
                    </div>

                    <div class="row gx-2 mt-3">
                        ${variantsHtml}
                    </div>
                </div>`;
        })
        .join("");

    return `
        <div class="card border-0 shadow-sm p-4">
            <div class="mb-4">
                <p class="text-muted tweet-text text-start mb-2 border-start ps-3">
                    <span>${data.text?.trim() || "No text."}</span>
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
            </div>

            <div class="row gx-2 gy-2">
                ${gridItemsHtml}
            </div>

            <div class="mt-1 text-start text-muted fst-italic" style="font-size: 0.75rem;">
                * Estimated size — actual may vary.
            </div>
        </div>
    `;
}

function bindCustomPlayButtons() {
    $(".custom-play-btn").each(function () {
        const $btn = $(this);
        const videoId = $btn.data("target");
        const $video = $("#" + videoId);

        if (!$video.length) return;

        $btn.on("click", function () {
            const video = $video[0];

            video.play().catch(console.warn);
        });

        $video.on("play", function () {
            $btn.fadeOut(200);
            $video.attr("controls", true);
        });
    });
}

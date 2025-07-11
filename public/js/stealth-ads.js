(function () {
    const MAX_WAIT = 3000;
    const INTERVAL = 200;
    const START = Date.now();

    const DEFAULT_DURATION = 2 * 60 * 1000;
    const MAX_TRIGGER_COUNT = 3;
    let currentTriggerCount = 0;
    let active = false;

    function normalizePath(path) {
        return "/" + path.replace(/^\/+|\/+$/g, "");
    }

    function getQueryParam(key) {
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get(key);
    }

    function isExcluded(path, excluded) {
        const clean = normalizePath(path);
        return excluded.includes(clean);
    }

    function waitUntilReady() {
        const config = window.STEALTH_CONFIG;

        if (!config || typeof config !== "object") return;

        const path = window.location.pathname;
        const excluded = Array.isArray(config.excluded)
            ? config.excluded.map(normalizePath)
            : [];

        const force = getQueryParam("stealth") === "1";

        if (!force && isExcluded(path, excluded)) return;

        if (
            (config.enabled || force) &&
            Array.isArray(config.urls) &&
            config.urls.length > 0
        ) {
            window.__stealthConfig = config;
            setupStealthTrigger();
            window.triggerStealthOverlay();
        }

        if (Date.now() - START < MAX_WAIT) {
            setTimeout(waitUntilReady, INTERVAL);
        }
    }

    function setupStealthTrigger() {
        window.triggerStealthOverlay = (options = {}) => {
            if (active || currentTriggerCount >= MAX_TRIGGER_COUNT) return;

            const config = window.__stealthConfig;
            const urls =
                Array.isArray(options.urls) && options.urls.length > 0
                    ? options.urls
                    : config.urls;

            if (!urls || urls.length === 0) return;

            const duration = options.duration || DEFAULT_DURATION;
            const maxClicks =
                options.maxClicks || 1 + Math.floor(Math.random() * 2);

            active = true;
            currentTriggerCount++;

            const getRandomUrl = () =>
                urls[Math.floor(Math.random() * urls.length)];

            const $overlay = $("<a>", {
                href: "#",
                target: "_blank",
                "data-stealth": true,
                css: {
                    position: "fixed",
                    top: 0,
                    left: 0,
                    width: "100%",
                    height: "100%",
                    "z-index": 9999,
                    backgroundColor: "transparent",
                    pointerEvents: "auto",
                },
            });

            let clickCount = 0;
            let lastClick = 0;

            $overlay.on("click", function () {
                const now = Date.now();
                if (now - lastClick < 500) return;
                lastClick = now;

                this.href = getRandomUrl();
                clickCount++;

                if (clickCount >= maxClicks) {
                    destroyOverlay();
                }
            });

            function destroyOverlay() {
                $overlay.remove();
                $("body").removeClass("stealth-active");
                active = false;

                if (currentTriggerCount < MAX_TRIGGER_COUNT) {
                    setTimeout(() => {
                        window.triggerStealthOverlay();
                    }, duration);
                }
            }

            $("body").append($overlay).addClass("stealth-active");

            try {
                Object.defineProperty(window, "click_please", {
                    value: undefined,
                    writable: false,
                    configurable: false,
                });
            } catch (_) {}

            setTimeout(() => {
                if (active) {
                    destroyOverlay();
                }
            }, duration);
        };
    }

    waitUntilReady();
})();

<nav class="navbar navbar-marketing navbar-expand-lg bg-white navbar-light">
    <div class="container px-5">
        <a class="navbar-brand text-dark" href="{{ route('home') }}">{{ config('app.name', 'Laravel') }}</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
            aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation"><i
                data-feather="menu"></i></button>
        <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav ms-auto me-lg-5">
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}">Home</a></li>
                <li class="nav-item">
                    <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#aboutModal">About</a>
                </li>
                <li class="nav-item"><a class="nav-link" href="{{ route('home') }}#faq">FAQ</a></li>
            </ul>
            <a class="btn fw-500 ms-lg-4 btn-primary" href="https://x.com/x_downloader" target="_blank" rel="noopener">
                Find us on X
            </a>
        </div>
    </div>
</nav>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Senggani Kemasan') — Packaging Berkualitas</title>

    {{-- Bootstrap CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Font Awesome --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    {{-- Google Fonts --}}
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #4c0404;
            --primary-dark: #4e2424;
            --primary-light: #8a4444;
            --accent: #d4a853;
        }

        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: #ffffff;
            color: #333;
        }

        /* ── Navbar ── */
        .navbar-brand img {
            height: 28px;
        }

        .navbar-senggani {
            background: var(--primary) !important;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .navbar-senggani .nav-link {
            color: rgba(255, 255, 255, 0.85) !important;
            font-weight: 500;
            transition: color 0.2s;
        }

        .navbar-senggani .nav-link:hover,
        .navbar-senggani .nav-link.active {
            color: #fff !important;
        }

        .btn-wa {
            background: #25d366;
            color: #fff !important;
            border-radius: 25px;
            padding: 6px 18px !important;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-wa:hover {
            background: #1ebe5d;
        }

        section {
            scroll-margin-top: 56px;
        }

        /* ── Buttons ── */
        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-danger {
            background-color: #7A0505;
            border-color: #7A0505;
        }

        .btn-danger:hover {
            background-color: #6a3131;
            border-color: #6a3131;
        }

        /* ── Section Title ── */
        .section-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary);
            position: relative;
            display: inline-block;
            margin-bottom: 0.5rem;
        }

        .section-title::after {
            content: '';
            display: block;
            width: 60px;
            height: 4px;
            background: var(--accent);
            border-radius: 2px;
            margin: 8px auto 0;
        }

        /* ── Cards ── */
        .card {
            border: none;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s, box-shadow 0.2s;
            border-radius: 12px;
            overflow: hidden;
        }

        .card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(106, 49, 49, 0.15);
        }

        .card-img-top {
            height: 200px;
            object-fit: cover;
        }

        /* ── Kategori Card ── */
        .kategori-card {
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .kategori-card .card-body {
            padding: 1.2rem;
        }

        .kategori-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .kategori-icon i {
            color: #fff;
            font-size: 1.5rem;
        }

        /* ── Keunggulan ── */
        .keunggulan-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.2rem;
        }

        .keunggulan-icon i {
            color: #fff;
            font-size: 1.8rem;
        }

        /* ── Alur Pemesanan ── */
        .alur-step {
            position: relative;
            text-align: center;
        }

        .alur-number {
            width: 55px;
            height: 55px;
            background: var(--primary);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            font-weight: 700;
            margin: 0 auto 1rem;
        }

        .alur-step::after {
            content: '';
            position: absolute;
            top: 27px;
            right: -50%;
            width: 100%;
            height: 2px;
            background: #ddd;
            z-index: -1;
        }

        .alur-step:last-child::after {
            display: none;
        }

        /* ── CTA Section ── */
        .cta-section {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 80px 0;
        }

        /* ── Footer ── */
        .footer-main {
            background: #2d2d2d;
            color: rgba(255, 255, 255, 0.8);
            padding: 50px 0 20px;
        }

        .footer-main h5 {
            color: var(--accent);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-main a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-main a:hover {
            color: #fff;
        }

        .footer-bottom {
            background: #1a1a1a;
            color: rgba(255, 255, 255, 0.5);
            padding: 15px 0;
            font-size: 0.85rem;
        }

        /* ── Carousel ── */

        /* Fix: target picture + img agar tidak collapse di mobile */
        .carousel-item picture {
            display: block;
            width: 100%;
            line-height: 0;
        }

        .carousel-item img {
            width: 100%;
            height: 550px;
            object-fit: cover;
            display: block;
        }

        .carousel-caption {
            left: 8%;
            top: 50%;
            transform: translateY(-50%);
            right: auto;
            bottom: auto;
            text-align: left;
            max-width: 550px;
        }

        .banner-content {
            margin-bottom: 1rem;
        }

        .carousel-caption h2 {
        
            font-size: 2.8rem;
            font-weight: 700;
            color: #6e0c0c;
            margin-bottom: 1rem;
        }

        .carousel-caption p {
            font-size: 1.1rem;
            font-weight: 500;
            color: #6e0c0c;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .btn-banner-wa {
            display: inline-block;
            background: #7A0505;
            color: #fff;
            padding: 12px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 1.5rem;
        }

        .btn-banner-wa:hover {
            color: #fff;
            background: #600404;
        }

        /* ── Mobile Carousel ── */
        @media (max-width: 767.98px) {

            .carousel-item img {
                height: 420px;
                /* tinggi lebih besar agar foto mobile terlihat */
                object-fit: cover;
                object-position: center;
            }

            .carousel-caption {
                left: 0;
                right: 0;
                top: auto;
                bottom: 0;
                transform: none;
                max-width: 100%;
                padding: 1rem 1.2rem 1.5rem;
                background: linear-gradient(to top, rgba(0, 0, 0, 0.65) 0%, transparent 100%);
                text-align: left;
            }

            .banner-content {
                display: none;
            }

            .carousel-caption h2 {
                font-size: 1.2rem;
                margin-bottom: 0.25rem;
                color: #fff;
            }

            .carousel-caption p {
                font-size: 0.78rem;
                -webkit-line-clamp: 2;
                color: rgba(255, 255, 255, 0.85);
                margin-bottom: 0;
            }

            .btn-banner-wa {
                padding: 8px 16px;
                font-size: 0.8rem;
                margin-top: 0.75rem;
            }
        }

        /* ── Scroll Animation ── */
        .fade-up {
            opacity: 0;
            transform: translateY(30px);
            transition: opacity 0.6s ease, transform 0.6s ease;
        }

        .fade-up.visible {
            opacity: 1;
            transform: translateY(0);
        }
    </style>

    @stack('styles')
</head>

<body>

    {{-- ═══════════════════════════════════════
     NAVBAR
═══════════════════════════════════════ --}}
    <nav class="navbar navbar-expand-lg navbar-senggani sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="{{ route('home') }}">
                <img src="{{ asset('images/logo.png') }}" class="logo-navbar">
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon" style="filter: invert(1);"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav ms-auto gap-3">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}"
                            href="{{ route('home') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="{{ request()->routeIs('home') ? '#kategori' : route('home') . '#kategori' }}">Kategori</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="{{ request()->routeIs('home') ? '#produk' : route('home') . '#produk' }}">Produk</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="{{ request()->routeIs('home') ? '#tentang' : route('home') . '#tentang' }}">Tentang
                            Kami</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link"
                            href="{{ request()->routeIs('home') ? '#kontak' : route('home') . '#kontak' }}">Kontak</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    {{-- ═══════════════════════════════════════
     CONTENT
═══════════════════════════════════════ --}}
    @yield('content')

    {{-- ═══════════════════════════════════════
     FOOTER
═══════════════════════════════════════ --}}
    <footer class="footer-main">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h5><i class="fas fa-box-open me-2"></i>Senggani Kemasan</h5>
                    <p style="font-size:0.9rem;">
                        Spesialis packaging berkualitas untuk bisnis Anda.
                        Paperbag, dus kemasan, box, dan berbagai produk kemasan lainnya.
                    </p>
                    <div class="d-flex gap-3 mt-3">
                        <a href="https://www.instagram.com/sengganipaperbag?igsh=MXNqMmVyMjJ2N2h4ZQ="><i
                                class="fab fa-instagram fa-lg"></i></a>
                        <a href="#"><i class="fab fa-facebook fa-lg"></i></a>
                        <a href="#"><i class="fab fa-whatsapp fa-lg"></i></a>
                        <a href="#"><i class="fab fa-tiktok fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-3">
                    <h5>Menu</h5>
                    <ul class="list-unstyled" style="font-size:0.9rem;">
                        <li class="mb-1"><a href="{{ route('home') }}">Home</a></li>
                        <li class="mb-1"><a href="#produk">Produk</a></li>
                        <li class="mb-1"><a href="#tentang">Tentang Kami</a></li>
                        <li class="mb-1"><a href="#kontak">Kontak</a></li>
                    </ul>
                </div>
                <div class="col-md-5">
                    <h5>Hubungi Kami</h5>
                    <ul class="list-unstyled" style="font-size:0.9rem;">
                        <li class="mb-2">
                            <i class="fab fa-whatsapp me-2" style="color:#25d366;"></i>
                            <a href="https://wa.me/6281234567890">+62 812-3456-7890</a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2" style="color:var(--accent);"></i>
                            <a href="mailto:info@senggani.com">info@senggani.com</a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-map-marker-alt me-2" style="color:var(--accent);"></i>
                            Jl. Contoh No. 123, Kota Anda
                        </li>
                    </ul>
                </div>
            </div>
            <hr style="border-color: rgba(255,255,255,0.1); margin-top: 2rem;">
        </div>
    </footer>
    <div class="footer-bottom text-center">
        <div class="container">
            <p class="mb-0">&copy; {{ date('Y') }} Senggani Kemasan. All rights reserved.</p>
        </div>
    </div>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Scroll animation
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, {
            threshold: 0.1
        });

        document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
    </script>

    @stack('scripts')
</body>

</html>

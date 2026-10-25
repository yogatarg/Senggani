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

        /* ── Kartu Produk (partial kartu-produk + kartu di detail kategori) ──
           ≥768px: sama seperti sebelumnya (gambar 200px / 220px di detail kategori).
           <768px: grid 2 kolom → kartu versi ringkas di bawah. */
        .kartu-produk-media {
            height: 200px;
            width: 100%;
            object-fit: cover;
        }

        .kartu-produk-media-tinggi {
            height: 220px;
        }

        .kartu-produk-placeholder {
            background: linear-gradient(135deg, #f5e6e6, #edd);
        }

        .kartu-produk-badge {
            background: #f5e6e6;
            color: #6a3131;
            font-size: 0.8rem;
        }

        /* HP: kartu ringkas — foto 4:3 (lebih pendek dari persegi), padding rapat,
           huruf lebih kecil; tombol tetap ≥36px agar mudah disentuh. */
        @media (max-width: 767.98px) {
            .kartu-produk .kartu-produk-media {
                height: auto;
                aspect-ratio: 4 / 3;
            }

            .kartu-produk .kartu-produk-placeholder .fa-3x {
                font-size: 1.75rem;
            }

            .kartu-produk .card-body {
                padding: 0.6rem;
            }

            .kartu-produk .kartu-produk-judul {
                font-size: 0.88rem;
                line-height: 1.3;
                margin-bottom: 0.25rem !important;
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                overflow: hidden;
                overflow-wrap: anywhere;
            }

            .kartu-produk .kartu-produk-desk {
                font-size: 0.72rem;
                line-height: 1.4;
                margin-bottom: 0.4rem !important;
                display: -webkit-box;
                -webkit-box-orient: vertical;
                -webkit-line-clamp: 2;
                line-clamp: 2;
                overflow: hidden;
            }

            .kartu-produk .kartu-produk-badges {
                margin-bottom: 0.4rem !important;
            }

            .kartu-produk .kartu-produk-badge {
                font-size: 0.68rem;
                line-height: 1.3;
                padding: 0.25rem 0.45rem !important;
                white-space: normal;
                text-align: left;
            }

            .kartu-produk .kartu-produk-meta {
                font-size: 0.72rem;
                line-height: 1.35;
                margin-bottom: 0.5rem !important;
            }

            .kartu-produk .kartu-produk-btn {
                --bs-btn-padding-y: 0.3rem;
                --bs-btn-padding-x: 0.5rem;
                --bs-btn-font-size: 0.75rem;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 36px;
                white-space: nowrap;
            }

            /* Kartu kategori: foto lebih pendek (inline 120px ditimpa) & isi rapat */
            .kartu-kategori .kartu-kategori-media {
                height: auto !important;
                aspect-ratio: 16 / 10;
            }

            .kartu-kategori .kartu-kategori-icon {
                width: 44px;
                height: 44px;
            }

            .kartu-kategori .kartu-kategori-icon i {
                font-size: 1.1rem;
            }

            .kategori-card.kartu-kategori .kartu-kategori-body {
                padding: 0.6rem !important;
            }

            .kartu-kategori .kartu-kategori-judul {
                font-size: 0.88rem;
                line-height: 1.3;
                margin-bottom: 0.15rem !important;
                overflow-wrap: anywhere;
            }

            .kartu-kategori .kartu-kategori-meta {
                font-size: 0.72rem;
            }
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

        /* Desktop: area tombol prev/next dipersempit (Bootstrap default 15%)
           agar ikon panah tidak menimpa awal teks caption (left 8%) */
        @media (min-width: 768px) {
            .hero-banner .carousel-control-prev,
            .hero-banner .carousel-control-next {
                width: 3rem;
            }
        }

        /* Tablet (768–991.98px): gambar banner 550px di-crop (object-fit: cover) sehingga
           foto produk mulai ±52% lebar layar → caption dipersempit & huruf diperkecil
           agar teks tidak menimpa foto. HP & desktop ≥992px tidak terpengaruh. */
        @media (min-width: 768px) and (max-width: 991.98px) {
            .hero-banner .carousel-caption {
                max-width: 42%;
            }

            .hero-banner .carousel-caption h2 {
                font-size: 2rem;
            }

            .hero-banner .carousel-caption p {
                font-size: 1rem;
            }
        }

        /* ── Mobile Carousel: banner setinggi layar pertama (dikurangi navbar sticky).
           Foto mengisi sisa tinggi di atas panel putih bersudut bulat atas yang "naik"
           menutupi bagian bawah foto (titik indikator, judul, deskripsi, tombol penuh
           lebar). Panah prev/next disembunyikan; geser jari tetap aktif.
           Efek foto diam saat scroll: lihat skrip "Parallax banner HP" di bawah. ── */
        @media (max-width: 767.98px) {

            .hero-banner {
                --panel-overlap: 26px;
                /* tinggi panel tetap → indikator & tinggi foto stabil antar slide */
                --panel-h: 16.5rem;
                /* tinggi navbar sticky; diperbarui JS dari ukuran sebenarnya */
                --nav-h: 56px;
                --hero-vh: 100vh;
                /* tinggi banner = layar - navbar, minimal panel + foto 50vw
                   (mis. HP landscape yang sangat pendek) */
                --hero-h: max(calc(var(--hero-vh) - var(--nav-h)), calc(var(--panel-h) + 50vw));
                overflow: hidden;
            }

            /* svh = tinggi layar saat address bar tampil → tidak "melompat" di HP */
            @supports (height: 100svh) {
                .hero-banner {
                    --hero-vh: 100svh;
                }
            }

            /* Area foto = tinggi banner - panel + bagian yang tertutup panel */
            .hero-banner .carousel-item picture {
                height: calc(var(--hero-h) - var(--panel-h) + var(--panel-overlap));
                overflow: hidden;
            }

            /* Fallback banner lama (hanya gambar desktop ±16:9, area kosong di kiri,
               produk di kanan ±52–95% lebar) → rapat kanan agar produk utuh terlihat. */
            .hero-banner .carousel-item img {
                height: 100%;
                object-fit: cover;
                object-position: right center;
            }

            /* Gambar khusus mobile → cukup di tengah */
            .hero-banner .carousel-item img.banner-img-has-mobile {
                object-position: center;
            }

            /* Panel putih: naik var(--panel-overlap) menutupi bawah foto.
               Tinggi tetap + judul/deskripsi line-clamp 2 → tinggi slide stabil;
               tombol selalu di dasar panel. Garis tipis #edd memisahkan banner
               dari section #kategori yang juga putih. */
            .hero-banner .carousel-caption {
                position: relative;
                z-index: 1;
                transform: none;
                left: auto;
                top: auto;
                display: flex;
                flex-direction: column;
                max-width: 100%;
                height: var(--panel-h);
                margin-top: calc(-1 * var(--panel-overlap));
                padding: 2.75rem 1.25rem 1.5rem;
                background: #fff;
                border-radius: 24px 24px 0 0;
                box-shadow: 0 -6px 16px rgba(0, 0, 0, 0.06);
                border-bottom: 1px solid #edd;
                text-align: center;
            }

            .hero-banner .banner-content {
                flex: 1 1 auto;
                margin-bottom: 0;
            }

            .hero-banner .carousel-caption h2 {
                font-size: 1.65rem;
                line-height: 1.25;
                margin-bottom: 0.5rem;
                color: #6e0c0c;
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            .hero-banner .carousel-caption p {
                font-size: 0.9rem;
                line-height: 1.5;
                font-weight: 400;
                -webkit-line-clamp: 2;
                color: #6c757d;
                margin-bottom: 0;
            }

            /* Tombol penuh lebar (rounded-pill dari markup tetap berlaku) */
            .hero-banner .btn-banner-wa {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                padding: 14px 20px;
                font-size: 1rem;
                margin-top: 1.25rem;
            }

            .hero-banner .btn-banner-wa:focus-visible {
                outline: 3px solid var(--accent);
                outline-offset: 2px;
            }

            /* Titik indikator: tampil di dalam panel, di atas judul.
               Satu set indikator bersama untuk semua slide (diposisikan relatif
               terhadap .hero-banner), jadi tetap diam saat slide berganti. */
            .hero-banner .carousel-indicators {
                z-index: 2;
                /* tepi atas panel + 0.75rem (tombol titik setinggi 24px) */
                top: auto;
                bottom: calc(var(--panel-h) - 0.75rem - 24px);
                margin: 0;
                gap: 2px;
            }

            /* Titik 8px; border transparan 8px memperbesar area sentuh jadi 24px */
            .hero-banner .carousel-indicators [data-bs-target] {
                box-sizing: content-box;
                width: 8px;
                height: 8px;
                margin: 0;
                padding: 0;
                border: 8px solid transparent;
                border-radius: 50%;
                background-color: #6e0c0c;
                background-clip: content-box;
                opacity: 0.2;
            }

            .hero-banner .carousel-indicators .active {
                opacity: 1;
            }

            .hero-banner .carousel-indicators [data-bs-target]:focus-visible {
                outline: 2px solid #6e0c0c;
                outline-offset: -4px;
            }

            .hero-banner .carousel-control-prev,
            .hero-banner .carousel-control-next {
                display: none;
            }

            /* Ruang samping konten lebih lega di HP (20px, Bootstrap default 12px).
               Berlaku untuk semua .container (navbar, section, footer) agar tepi
               konten tetap sejajar di seluruh halaman. Tablet/desktop tidak berubah. */
            .container {
                --bs-gutter-x: 2.5rem;
            }
        }

        /* ── Header halaman detail kategori ── */
        .kategori-header {
            background: #4c0404;
            padding: 48px 0;
        }

        .kategori-header .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: 0.5rem;
        }

        .kategori-header h1 {
            margin-bottom: 0.5rem;
        }

        @media (max-width: 767.98px) {
            .kategori-header {
                padding: 28px 0;
            }

            .kategori-header .breadcrumb {
                margin-bottom: 0.25rem;
                font-size: 0.875rem;
                line-height: 1.4;
            }

            .kategori-header h1 {
                font-size: 1.6rem;
                line-height: 1.2;
                margin-bottom: 0.25rem;
            }

            .kategori-header p {
                font-size: 0.9375rem;
                line-height: 1.45;
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

        @media (prefers-reduced-motion: reduce) {
            .fade-up {
                opacity: 1;
                transform: none;
                transition: none;
            }
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
                        <a class="nav-link {{ request()->routeIs('kategori.*') ? 'active' : '' }}"
                            href="{{ route('kategori.index') }}">Kategori</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('produk.*') ? 'active' : '' }}"
                            href="{{ route('produk.index') }}">Produk</a>
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
                        <a href="https://www.instagram.com/sengganipaperbag/" target="_blank" rel="noopener"
                            aria-label="Instagram"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="https://wa.me/6283829208316" target="_blank" rel="noopener" aria-label="WhatsApp"><i
                                class="fab fa-whatsapp fa-lg"></i></a>
                    </div>
                </div>
                <div class="col-md-3">
                    <h5>Menu</h5>
                    <ul class="list-unstyled" style="font-size:0.9rem;">
                        <li class="mb-1"><a href="{{ route('home') }}">Home</a></li>
                        <li class="mb-1"><a href="{{ route('kategori.index') }}">Kategori</a></li>
                        <li class="mb-1"><a href="{{ route('produk.index') }}">Produk</a></li>
                        <li class="mb-1"><a
                                href="{{ request()->routeIs('home') ? '#tentang' : route('home') . '#tentang' }}">Tentang
                                Kami</a></li>
                        <li class="mb-1"><a
                                href="{{ request()->routeIs('home') ? '#kontak' : route('home') . '#kontak' }}">Kontak</a>
                        </li>
                    </ul>
                </div>
                <div class="col-md-5">
                    <h5>Hubungi Kami</h5>
                    <ul class="list-unstyled" style="font-size:0.9rem;">
                        <li class="mb-2">
                            <i class="fab fa-whatsapp me-2" style="color:#25d366;"></i>
                            <a href="https://wa.me/6283829208316">+62 838-2920-8316</a>
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-envelope me-2" style="color:var(--accent);"></i>
                            <a href="mailto:sengganipaperbag@gmail.com">sengganipaperbag@gmail.com</a>
                        </li>
                        <li class="mb-2">
                            @php($alamat = 'Pilahan, RT 40, RW 12, Rejowinangun, Kotagede, Yogyakarta 55171')
                            <i class="fas fa-map-marker-alt me-2" style="color:var(--accent);"></i>
                            <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($alamat) }}"
                                target="_blank" rel="noopener">{{ $alamat }}</a>
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

        // Parallax banner HP (<768px): foto banner tetap diam saat halaman di-scroll,
        // panel putih & konten di bawahnya naik menutupinya. Foto (picture) digeser
        // translateY sebesar jarak scroll selama banner masih terlihat; bagian yang
        // keluar dipotong overflow:hidden .carousel-inner. Hanya transform (tanpa
        // ubah layout), listener passive + requestAnimationFrame. Tidak memakai
        // position:fixed (rusak di dalam transform slide carousel) maupun
        // background-attachment:fixed (tidak jalan di iOS). Geser slide & indikator
        // tidak terpengaruh karena hanya lapisan foto yang digeser.
        (() => {
            const hero = document.getElementById('heroBanner');
            const navbar = document.querySelector('.navbar-senggani');
            if (!hero) return;

            const mqMobile = window.matchMedia('(max-width: 767.98px)');
            const mqReduce = window.matchMedia('(prefers-reduced-motion: reduce)');
            const fotos = hero.querySelectorAll('.carousel-item picture');
            let heroStart = 0; // posisi scroll saat banner tepat di bawah navbar
            let heroHeight = 0;
            let ticking = false;
            let aktif = false;

            const ukur = () => {
                // Tinggi navbar diukur saat menu tertutup (menu terbuka menambah tinggi)
                if (navbar && !navbar.querySelector('.navbar-collapse.show, .navbar-collapse.collapsing')) {
                    hero.style.setProperty('--nav-h', navbar.offsetHeight + 'px');
                }
                const navH = navbar ? navbar.offsetHeight : 0;
                heroStart = hero.getBoundingClientRect().top + window.scrollY - navH;
                heroHeight = hero.offsetHeight;
            };

            const geser = () => {
                ticking = false;
                if (!aktif) return;
                const y = Math.min(Math.max(window.scrollY - heroStart, 0), heroHeight);
                fotos.forEach(foto => {
                    foto.style.transform = `translate3d(0, ${y}px, 0)`;
                });
            };

            const onScroll = () => {
                if (!ticking) {
                    ticking = true;
                    requestAnimationFrame(geser);
                }
            };

            const atur = () => {
                const boleh = mqMobile.matches && !mqReduce.matches;
                if (mqMobile.matches) ukur();
                else hero.style.removeProperty('--nav-h');

                if (boleh && !aktif) {
                    aktif = true;
                    fotos.forEach(foto => foto.style.willChange = 'transform');
                    window.addEventListener('scroll', onScroll, { passive: true });
                } else if (!boleh && aktif) {
                    aktif = false;
                    window.removeEventListener('scroll', onScroll);
                    fotos.forEach(foto => {
                        foto.style.transform = '';
                        foto.style.willChange = '';
                    });
                }
                geser();
            };

            atur();
            window.addEventListener('resize', atur, { passive: true });
            window.addEventListener('load', atur);
            mqMobile.addEventListener('change', atur);
            mqReduce.addEventListener('change', atur);
        })();
    </script>

    @stack('scripts')
</body>

</html>

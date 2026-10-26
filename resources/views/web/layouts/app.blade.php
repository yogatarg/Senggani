@php
    /* ── Data usaha (NAP) dari config/usaha.php; default aman bila config belum ada ── */
    $namaUsaha = config('usaha.nama') ?: 'Senggani Paperbag & Packaging';
    $deskripsiUsaha = config('usaha.deskripsi') ?: 'Paperbag, dus, tas furing, dan undangan custom dari Kotagede, Yogyakarta. Ukuran, bahan, dan finishing sesuai pesanan. Konsultasi harga via WhatsApp.';
    $teleponUsaha = config('usaha.telepon') ?: '+62 838-2920-8316';
    $waUsaha = preg_replace('/\D+/', '', (string) (config('usaha.whatsapp') ?: '6283829208316'));
    $emailUsaha = config('usaha.email') ?: 'sengganipaperbag@gmail.com';
    $alamatUsaha = array_merge([
        'jalan' => 'Pilahan, RT 40, RW 12',
        'kelurahan' => 'Rejowinangun',
        'kecamatan' => 'Kotagede',
        'kota' => 'Yogyakarta',
        'provinsi' => 'DI Yogyakarta',
        'kode_pos' => '55171',
        'negara' => 'ID',
    ], array_filter((array) config('usaha.alamat', []), fn ($v) => filled($v)));
    // Teks alamat yang tampil = sumber yang sama dengan JSON-LD (NAP konsisten)
    $alamatTeks = trim(implode(', ', array_filter([
        $alamatUsaha['jalan'], $alamatUsaha['kelurahan'], $alamatUsaha['kecamatan'], $alamatUsaha['kota'],
    ])) . ' ' . $alamatUsaha['kode_pos']);
    $geoUsaha = (array) config('usaha.geo', []);
    $sosialUsaha = array_values(array_filter((array) config('usaha.sosial', ['https://www.instagram.com/sengganipaperbag/']), fn ($u) => is_string($u) && filter_var($u, FILTER_VALIDATE_URL)));
    $areaLayanan = array_values(array_filter((array) config('usaha.area_layanan', []), 'filled'));

    /* Jam buka: format schema.org openingHours dari config, mis. "Mo-Sa 08:00-17:00".
       Item yang cocok pola → masuk JSON-LD + tampil versi Indonesia ("Senin–Sabtu 08.00–17.00").
       Item lain hanya ditampilkan apa adanya. */
    $petaHari = ['Mo' => 'Senin', 'Tu' => 'Selasa', 'We' => 'Rabu', 'Th' => 'Kamis', 'Fr' => 'Jumat', 'Sa' => 'Sabtu', 'Su' => 'Minggu'];
    $polaJamBuka = '/^(Mo|Tu|We|Th|Fr|Sa|Su)([-,](Mo|Tu|We|Th|Fr|Sa|Su))*\s+\d{2}:\d{2}-\d{2}:\d{2}$/';
    $urutanHari = array_keys($petaHari);
    $hariSchema = ['Mo' => 'Monday', 'Tu' => 'Tuesday', 'We' => 'Wednesday', 'Th' => 'Thursday', 'Fr' => 'Friday', 'Sa' => 'Saturday', 'Su' => 'Sunday'];
    $jamBukaTampil = [];
    $jamBukaSpesifikasi = []; // openingHoursSpecification (schema.org)
    $hariBuka = [];
    $semuaJamValid = true;
    foreach ((array) config('usaha.jam_buka', []) as $jb) {
        if (! is_string($jb) || trim($jb) === '') {
            continue;
        }
        $jb = trim($jb);
        if (preg_match($polaJamBuka, $jb)) {
            [$bagianHari, $bagianJam] = preg_split('/\s+/', $jb, 2);
            [$jamMulai, $jamTutup] = explode('-', $bagianJam);
            // Uraikan "Mo-Fr" / "Mo,We" menjadi daftar hari (rentang boleh melewati Minggu, mis. "Sa-Mo")
            $hariItem = [];
            foreach (explode(',', $bagianHari) as $potong) {
                $ujung = explode('-', $potong);
                $awal = array_search($ujung[0], $urutanHari, true);
                $akhir = array_search(end($ujung), $urutanHari, true);
                for ($i = $awal; $i <= ($akhir >= $awal ? $akhir : $akhir + 7); $i++) {
                    $hariItem[] = $urutanHari[$i % 7];
                }
            }
            $hariItem = array_values(array_unique($hariItem));
            $hariBuka = array_merge($hariBuka, $hariItem);
            $jamBukaSpesifikasi[] = [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => array_map(fn ($h) => $hariSchema[$h], $hariItem),
                'opens' => $jamMulai,
                'closes' => $jamTutup,
            ];
            $jb = preg_replace_callback('/\b(Mo|Tu|We|Th|Fr|Sa|Su)\b/', fn ($m) => $petaHari[$m[1]], $jb);
            $jb = preg_replace('/(\d{2}):(\d{2})/', '$1.$2', $jb);
            $jb = str_replace(['-', ','], ['–', ', '], $jb);
        } else {
            $semuaJamValid = false;
        }
        $jamBukaTampil[] = $jb;
    }
    // Hari yang tidak disebut tampil "Libur" (hanya bila semua baris jam buka terbaca polanya)
    if ($jamBukaSpesifikasi && $semuaJamValid) {
        foreach (array_diff($urutanHari, $hariBuka) as $hariLibur) {
            $jamBukaTampil[] = $petaHari[$hariLibur] . ' Libur';
        }
    }
    // Listing Google Maps (Google Business Profile) dari config
    $petaUsaha = filter_var(config('usaha.peta'), FILTER_VALIDATE_URL) ?: null;

    // Telepon tampil: dari config, dikelompokkan agar mudah dibaca (+62 838-2920-8316)
    $teleponTampil = preg_match('/^\+62(\d{3})(\d{4})(\d{3,5})$/', preg_replace('/[^\d+]/', '', $teleponUsaha), $m)
        ? "+62 {$m[1]}-{$m[2]}-{$m[3]}"
        : $teleponUsaha;

    /* ── SEO per halaman ── */
    // URL absolut mengikuti APP_URL (sama dengan sitemap/llms.txt), bukan host permintaan
    $keAbsolut = fn (string $u) => rtrim(\Illuminate\Support\Str::replaceStart(request()->root(), rtrim(config('app.url'), '/'), $u), '/') === rtrim(config('app.url'), '/')
        ? rtrim(config('app.url'), '/') . '/'
        : \Illuminate\Support\Str::replaceStart(request()->root(), rtrim(config('app.url'), '/'), $u);
    $teksSection = fn (string $nama, string $default = '') => trim(html_entity_decode($__env->yieldContent($nama, $default), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $judulSeo = $teksSection('title') ?: $namaUsaha;
    $deskripsiSeo = $teksSection('meta_description') ?: $deskripsiUsaha;
    $robotsSeo = $teksSection('robots');
    $ogType = $teksSection('og_type') ?: 'website';
    $ogImage = $teksSection('og_image');
    $ogImageDefault = $ogImage === '';
    $ogImageJpg = $keAbsolut(asset('og-image.jpg'));
    $ogImage = $ogImageDefault ? $ogImageJpg : $ogImage;
    // Gambar halaman berformat WebP (foto produk) belum tentu dipratinjau WhatsApp/sebagian aplikasi chat,
    // yang umumnya memakai og:image pertama. Maka JPG default ditaruh pertama, foto WebP sebagai og:image kedua.
    // twitter:image tetap foto halaman (X mendukung WebP).
    $ogImageWebp = ! $ogImageDefault && preg_match('/\.webp(\?.*)?$/i', $ogImage);

    // Canonical: URL tanpa query, kecuali ?page (>1) dan, di /produk, ?kategori (halaman berbeda)
    $izinQuery = request()->routeIs('produk.index') ? ['kategori', 'page'] : ['page'];
    $queryCanonical = collect(request()->query())
        ->only($izinQuery)
        ->filter(fn ($v, $k) => is_string($v) && $v !== '' && ! ($k === 'page' && (int) $v <= 1))
        ->all();
    $canonical = $teksSection('canonical') ?: $keAbsolut(url()->current() . ($queryCanonical ? '?' . http_build_query($queryCanonical) : ''));

    /* ── JSON-LD: LocalBusiness (Store) + WebSite di semua halaman ── */
    $urlBeranda = $keAbsolut(route('home'));
    $usahaSchema = array_filter([
        '@type' => 'Store',
        '@id' => $urlBeranda . '#usaha',
        'name' => $namaUsaha,
        'description' => $deskripsiUsaha,
        'url' => $urlBeranda,
        'logo' => $keAbsolut(asset('icon-512.png')),
        'image' => $keAbsolut(asset('og-image.jpg')),
        'telephone' => $teleponUsaha,
        'email' => $emailUsaha,
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => implode(', ', array_filter([$alamatUsaha['jalan'], $alamatUsaha['kelurahan'], $alamatUsaha['kecamatan']])),
            'addressLocality' => $alamatUsaha['kota'],
            'addressRegion' => $alamatUsaha['provinsi'],
            'postalCode' => $alamatUsaha['kode_pos'],
            'addressCountry' => $alamatUsaha['negara'],
        ]),
        'geo' => is_numeric($geoUsaha['lat'] ?? null) && is_numeric($geoUsaha['lng'] ?? null)
            ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $geoUsaha['lat'], 'longitude' => (float) $geoUsaha['lng']]
            : null,
        'openingHoursSpecification' => $jamBukaSpesifikasi ?: null,
        'hasMap' => $petaUsaha,
        'sameAs' => array_values(array_unique(array_filter([...$sosialUsaha, $petaUsaha]))) ?: null,
        'areaServed' => $areaLayanan ? array_map(fn ($a) => ['@type' => 'Place', 'name' => $a], $areaLayanan) : null,
    ], fn ($v) => $v !== null);
    $jsonLdGlobal = [
        '@context' => 'https://schema.org',
        '@graph' => [
            $usahaSchema,
            [
                '@type' => 'WebSite',
                '@id' => $urlBeranda . '#website',
                'name' => $namaUsaha,
                'url' => $urlBeranda,
                'inLanguage' => 'id-ID',
                'publisher' => ['@id' => $urlBeranda . '#usaha'],
            ],
        ],
    ];
    // Flag aman: </script> dari data admin di-escape jadi </script>
    $jsonLdFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;
@endphp
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $judulSeo }}</title>
    <meta name="description" content="{{ $deskripsiSeo }}">
    @if ($robotsSeo)
        <meta name="robots" content="{{ $robotsSeo }}">
    @else
        <link rel="canonical" href="{{ $canonical }}">
    @endif
    <meta name="theme-color" content="#4c0404">

    {{-- Ikon --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">

    {{-- Open Graph & Twitter --}}
    <meta property="og:site_name" content="{{ $namaUsaha }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $judulSeo }}">
    <meta property="og:description" content="{{ $deskripsiSeo }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if ($ogImageDefault || $ogImageWebp)
        <meta property="og:image" content="{{ $ogImageJpg }}">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $namaUsaha }}: spesialis packaging custom untuk bisnis Anda">
    @endif
    @if (! $ogImageDefault)
        <meta property="og:image" content="{{ $ogImage }}">
        @if ($ogImageWebp)
            <meta property="og:image:type" content="image/webp">
        @endif
        <meta property="og:image:alt" content="{{ $judulSeo }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $judulSeo }}">
    <meta name="twitter:description" content="{{ $deskripsiSeo }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    {{-- Data terstruktur --}}
    <script type="application/ld+json">{!! json_encode($jsonLdGlobal, $jsonLdFlags) !!}</script>
    @stack('jsonld')

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
            width: auto;
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

        .carousel-caption h1,
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

            .hero-banner .carousel-caption h1,
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
           Efek foto diam saat scroll: CSS scroll-driven animation di bawah. ── */
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

            /* Area foto = tinggi banner - panel + bagian yang tertutup tepi atas panel */
            .hero-banner .carousel-item picture {
                height: calc(var(--hero-h) - var(--panel-h) + var(--panel-overlap));
                overflow: hidden;
            }

            /* Panel putih statis (tidak ikut bergeser saat slide berganti): latar
               panel, titik indikator, dan tombol bersama .btn-banner-wa-tetap diam;
               hanya foto, judul, dan deskripsi tiap slide yang bergeser (dipotong
               overflow:hidden .carousel-inner).
               ::before = latar panel, dilukis di bawah konten slide.
               ::after  = tepi atas panel (bersudut 24px + bayangan tipis) di ATAS
               slide, menutupi 26px bawah area foto; area ini hanya berisi padding
               atas caption & indikator (z-index 2), bukan teks.
               Garis tipis #edd memisahkan banner dari section #kategori. */
            .hero-banner::before,
            .hero-banner::after {
                content: "";
                position: absolute;
                left: 0;
                right: 0;
                background: #fff;
                pointer-events: none;
            }

            .hero-banner::before {
                bottom: 0;
                height: var(--panel-h);
                border-bottom: 1px solid #edd;
            }

            /* Turun 2px melewati dasar foto: tepi bawah foto dan tepi bawah ::after
               berada di koordinat yang sama, dan pada skala pecahan (DPR 2.75, pratinjau
               HP yang diskalakan) keduanya di-anti-alias → foto merembes jadi garis 1px
               setinggi titik indikator. Lebih 2px (putih di atas latar putih ::before
               dan padding atas caption) menutup sambungan itu. */
            .hero-banner::after {
                z-index: 1;
                bottom: calc(var(--panel-h) - var(--panel-overlap) - 2px);
                height: calc(var(--panel-overlap) + 2px);
                border-radius: 24px 24px 0 0;
                box-shadow: 0 -6px 16px rgba(0, 0, 0, 0.06);
                /* bayangan hanya ke atas (tidak menggaris di atas isi panel) */
                clip-path: inset(-24px 0 0 0);
            }

            /* Efek foto diam saat scroll (CSS scroll-driven animation, berjalan di
               compositor sehingga sinkron dengan scroll; tanpa JS per frame).
               Timeline = posisi .hero-banner terhadap viewport yang dipotong setinggi
               navbar sticky (inset atas). Rentang exit-crossing: 0% saat tepi atas
               banner tepat di bawah navbar, 100% saat tepi bawahnya lewat → panjang
               rentang = tinggi banner, jadi translateY 0 → tinggi banner = 1:1 dengan
               scroll. Yang dianimasikan gambar (img) di dalam picture, bukan
               .carousel-item, jadi transform geser slide carousel tidak bentrok.
               Bagian foto yang turun dipotong overflow:hidden picture dan
               ditutup tepi atas panel (::after). Browser tanpa dukungan: foto ikut scroll biasa.
               Mati bila prefers-reduced-motion: reduce. */
            @supports (animation-timeline: view()) {
                @media (prefers-reduced-motion: no-preference) {
                    .hero-banner {
                        view-timeline-name: --hero-banner;
                        view-timeline-axis: block;
                        view-timeline-inset: var(--nav-h) 0px;
                    }

                    .hero-banner .carousel-item img {
                        animation: hero-foto-diam linear both;
                        animation-duration: auto;
                        animation-timeline: --hero-banner;
                        animation-range: exit-crossing 0% exit-crossing 100%;
                    }
                }
            }

            @keyframes hero-foto-diam {
                from {
                    transform: translate3d(0, 0, 0);
                }

                to {
                    transform: translate3d(0, var(--hero-h), 0);
                }
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

            /* Area teks per slide (transparan, di atas panel statis): naik
               var(--panel-overlap) seperti panel. Tinggi tetap + judul/deskripsi
               line-clamp 2 → tinggi slide stabil. Padding bawah menyisakan ruang
               tombol bersama (1.5rem + garis 1px + tombol 52px + jarak 1.25rem). */
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
                padding: 2.75rem 1.25rem calc(1.5rem + 1px + 52px + 1.25rem);
                text-align: center;
            }

            .hero-banner .banner-content {
                flex: 1 1 auto;
                margin-bottom: 0;
            }

            .hero-banner .carousel-caption h1,
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

            /* Tombol per slide disembunyikan di HP (tidak bisa difokus);
               gantinya satu tombol bersama yang diam di dasar panel statis. */
            .hero-banner .carousel-item .btn-banner-wa {
                display: none;
            }

            .hero-banner .btn-banner-wa-tetap {
                position: absolute;
                z-index: 2;
                left: 1.25rem;
                right: 1.25rem;
                bottom: calc(1.5rem + 1px);
                width: auto;
                margin-top: 0;
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
                <img src="{{ asset('images/logo.png') }}" class="logo-navbar" alt="{{ $namaUsaha }}" width="144" height="28">
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
                    <h5><i class="fas fa-box-open me-2" aria-hidden="true"></i>{{ $namaUsaha }}</h5>
                    <p style="font-size:0.9rem;">
                        Spesialis packaging berkualitas untuk bisnis Anda.
                        Paperbag, dus kemasan, box, dan berbagai produk kemasan lainnya.
                    </p>
                    <div class="d-flex gap-3 mt-3">
                        @foreach ($sosialUsaha as $urlSosial)
                            @php
                                $hostSosial = strtolower((string) parse_url($urlSosial, PHP_URL_HOST));
                                [$ikonSosial, $labelSosial] = match (true) {
                                    str_contains($hostSosial, 'instagram') => ['fab fa-instagram', 'Instagram'],
                                    str_contains($hostSosial, 'facebook') => ['fab fa-facebook', 'Facebook'],
                                    str_contains($hostSosial, 'tiktok') => ['fab fa-tiktok', 'TikTok'],
                                    str_contains($hostSosial, 'youtube') => ['fab fa-youtube', 'YouTube'],
                                    str_contains($hostSosial, 'threads') => ['fab fa-threads', 'Threads'],
                                    str_contains($hostSosial, 'shopee') => ['fas fa-shopping-bag', 'Shopee'],
                                    str_contains($hostSosial, 'tokopedia') => ['fas fa-store', 'Tokopedia'],
                                    default => ['fas fa-link', $hostSosial],
                                };
                            @endphp
                            <a href="{{ $urlSosial }}" target="_blank" rel="noopener" aria-label="{{ $labelSosial }}"><i
                                    class="{{ $ikonSosial }} fa-lg" aria-hidden="true"></i></a>
                        @endforeach
                        <a href="https://wa.me/{{ $waUsaha }}" target="_blank" rel="noopener" aria-label="WhatsApp"><i
                                class="fab fa-whatsapp fa-lg" aria-hidden="true"></i></a>
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
                    {{-- NAP dirender dari config/usaha.php (sama dengan JSON-LD) --}}
                    <address class="mb-0">
                        <ul class="list-unstyled footer-kontak" style="font-size:0.9rem;">
                            <li class="mb-2">
                                <i class="fab fa-whatsapp me-2" style="color:#25d366;" aria-hidden="true"></i>
                                <a href="https://wa.me/{{ $waUsaha }}">{{ $teleponTampil }}</a>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-envelope me-2" style="color:var(--accent);" aria-hidden="true"></i>
                                <a href="mailto:{{ $emailUsaha }}">{{ $emailUsaha }}</a>
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-map-marker-alt me-2" style="color:var(--accent);" aria-hidden="true"></i>
                                <a href="{{ $petaUsaha ?: 'https://www.google.com/maps/search/?api=1&query=' . urlencode($namaUsaha . ', ' . $alamatTeks) }}"
                                    target="_blank" rel="noopener">{{ $alamatTeks }}</a>
                            </li>
                            @if ($jamBukaTampil)
                                <li class="mb-2 d-flex">
                                    <i class="fas fa-clock me-2 mt-1" style="color:var(--accent);" aria-hidden="true"></i>
                                    <div>
                                        <span class="visually-hidden">Jam buka:</span>
                                        @foreach ($jamBukaTampil as $baris)
                                            <span class="d-block">{{ $baris }}</span>
                                        @endforeach
                                    </div>
                                </li>
                            @endif
                        </ul>
                    </address>
                </div>
            </div>
            <hr style="border-color: rgba(255,255,255,0.1); margin-top: 2rem;">
        </div>
    </footer>
    <div class="footer-bottom text-center">
        <div class="container">
            <p class="mb-0">&copy; {{ date('Y') }} {{ $namaUsaha }}. All rights reserved.</p>
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

        // Banner HP (<768px): efek foto diam saat scroll murni CSS (scroll-driven
        // animation, lihat CSS .hero-banner). Skrip ini hanya menyetel --nav-h dari
        // tinggi navbar sticky sebenarnya (dipakai tinggi banner & inset timeline),
        // saat load/resize/menu ditutup. Tidak ada kerja per frame scroll.
        (() => {
            const hero = document.getElementById('heroBanner');
            const navbar = document.querySelector('.navbar-senggani');
            if (!hero || !navbar) return;

            const mqMobile = window.matchMedia('(max-width: 767.98px)');

            const ukur = () => {
                if (!mqMobile.matches) {
                    hero.style.removeProperty('--nav-h');
                    return;
                }
                // Diukur saat menu tertutup (menu terbuka menambah tinggi navbar)
                if (!navbar.querySelector('.navbar-collapse.show, .navbar-collapse.collapsing')) {
                    hero.style.setProperty('--nav-h', navbar.offsetHeight + 'px');
                }
            };

            ukur();
            window.addEventListener('resize', ukur, { passive: true });
            window.addEventListener('load', ukur);
            mqMobile.addEventListener('change', ukur);
            navbar.addEventListener('hidden.bs.collapse', ukur);
        })();
    </script>

    @stack('scripts')
</body>

</html>

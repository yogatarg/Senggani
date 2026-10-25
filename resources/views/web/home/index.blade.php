@extends('web.layouts.app')

@section('title', 'Home')

@section('content')

    {{-- ═══════════════════════════════════════
     1. HERO CAROUSEL BANNER
═══════════════════════════════════════ --}}
    @if ($banner->count() > 0)
        <div id="heroBanner" class="carousel slide hero-banner" data-bs-ride="carousel">
            <div class="carousel-indicators">
                @foreach ($banner as $i => $item)
                    <button type="button" data-bs-target="#heroBanner" data-bs-slide-to="{{ $i }}" aria-label="Banner {{ $i + 1 }}"
                        class="{{ $i === 0 ? 'active' : '' }}"></button>
                @endforeach
            </div>
            <div class="carousel-inner">
                @foreach ($banner as $i => $item)
                    <div class="carousel-item {{ $i === 0 ? 'active' : '' }}">
                        <picture>
                            @if ($item->gambar_mobile)
                                {{-- Fix: pakai max-width 767.98px sesuai Bootstrap breakpoint md --}}
                                <source media="(max-width: 767.98px)"
                                    srcset="{{ asset('storage/' . $item->gambar_mobile) }}">
                            @endif
                            <img src="{{ asset('storage/' . $item->gambar) }}"
                                class="d-block w-100 banner-img {{ $item->gambar_mobile ? 'banner-img-has-mobile' : '' }}"
                                alt="{{ $item->judul }}" width="1920" height="550" loading="eager">
                        </picture>
                        <div class="carousel-caption custom-caption">
                            <div class="banner-content">
                                <h2>{{ $item->judul }}</h2>

                                @if ($item->deskripsi)
                                    <p>
                                        {{ $item->deskripsi }}
                                    </p>
                                @endif
                            </div>

                            <a href="https://wa.me/6283829208316" target="_blank" rel="noopener" class="btn-banner-wa rounded-pill px-4">

                                <i class="fab fa-whatsapp me-2" aria-hidden="true"></i>
                                Konsultasi Sekarang

                            </a>


                        </div>
                    </div>
                @endforeach
            </div>
            {{-- HP (<768px): satu tombol bersama di panel statis, tidak ikut bergeser
                 saat slide berganti. Tombol per slide di atas disembunyikan di HP. --}}
            <a href="https://wa.me/6283829208316" target="_blank" rel="noopener"
                class="btn-banner-wa btn-banner-wa-tetap rounded-pill px-4 d-md-none">
                <i class="fab fa-whatsapp me-2" aria-hidden="true"></i>
                Konsultasi Sekarang
            </a>
            <button class="carousel-control-prev" type="button" data-bs-target="#heroBanner" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Sebelumnya</span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#heroBanner" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Berikutnya</span>
            </button>
        </div>
    @else
        {{-- Fallback jika belum ada banner --}}
        <div style="background: linear-gradient(135deg, #6a3131, #4e2424); padding: 120px 0;">
            <div class="container text-center text-white">
                <h1 class="display-4 fw-bold mb-3">Senggani Kemasan</h1>
                <p class="fs-5 mb-4 opacity-75">Spesialis Packaging Berkualitas untuk Bisnis Anda</p>
                <a href="https://wa.me/6283829208316" target="_blank" rel="noopener" class="btn btn-success btn-lg rounded-pill px-5">
                    <i class="fab fa-whatsapp me-2"></i>Konsultasi Gratis
                </a>
            </div>
        </div>
    @endif

    <!--2. KATEGORI PRODUK-->
    <section class="py-5" style="background: #ffffff" id="kategori">
        <div class="container">
            <div class="text-center mb-5 fade-up">
                <h2 class="section-title">Kategori Produk</h2>
                <p class="text-muted mt-3">Temukan berbagai jenis kemasan sesuai kebutuhan bisnis Anda</p>
            </div>

            <div class="row g-4 justify-content-center">
                @forelse($kategori as $item)
                    @include('web.partials.kartu-kategori', ['item' => $item])
                @empty
                    <div class="col-12 text-center text-muted">
                        <p>Belum ada kategori tersedia.</p>
                    </div>
                @endforelse
            </div>

            <div class="text-center mt-5 fade-up">
                <a href="{{ route('kategori.index') }}" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-600">
                    Lihat Semua Kategori <i class="fas fa-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
    </section>


    <!--3. PRODUK TERBARU-->

    <section class="py-5" id="produk">
        <div class="container">
            <div class="text-center mb-5 fade-up">
                <h2 class="section-title">Produk Kami</h2>
                <p class="text-muted mt-3">Kualitas terbaik dengan harga terjangkau</p>
            </div>

            <div class="row g-3 g-md-4">
                @forelse($produk as $item)
                    @include('web.partials.kartu-produk', ['item' => $item])
                @empty
                    <div class="col-12 text-center text-muted">
                        <p>Belum ada produk tersedia.</p>
                    </div>
                @endforelse
            </div>

            @if ($produk->count() > 0)
                <div class="text-center mt-5 fade-up">
                    <a href="{{ route('produk.index') }}" class="btn btn-outline-primary rounded-pill px-4 py-2 fw-600">
                        Lihat Semua Produk <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            @endif
        </div>
    </section>

    <!--4. KEUNGGULAN-->

    <section class="py-5" style="background: #ffffff" id="tentang">
        <div class="container">
            <div class="text-center mb-5 fade-up">
                <h2 class="section-title">Mengapa Senggani Kemasan?</h2>
                <p class="text-muted mt-3">Kami hadir untuk memenuhi kebutuhan packaging bisnis Anda</p>
            </div>

            <div class="row g-4 text-center">
                <div class="col-md-3 fade-up">
                    <div class="keunggulan-icon">
                        <i class="fas fa-pencil-ruler"></i>
                    </div>
                    <h5 class="fw-600">Konsultasi Desain Gratis</h5>
                    <p class="text-muted small">Tim kami siap membantu mewujudkan desain kemasan impian Anda tanpa biaya
                        tambahan.</p>
                </div>
                <div class="col-md-3 fade-up">
                    <div class="keunggulan-icon">
                        <i class="fas fa-boxes"></i>
                    </div>
                    <h5 class="fw-600">Minimal Order Rendah</h5>
                    <p class="text-muted small">Cocok untuk UMKM dan bisnis skala kecil, minimal order sangat terjangkau.
                    </p>
                </div>
                <div class="col-md-3 fade-up">
                    <div class="keunggulan-icon">
                        <i class="fas fa-award"></i>
                    </div>
                    <h5 class="fw-600">Bahan Berkualitas</h5>
                    <p class="text-muted small">Menggunakan bahan pilihan yang ramah lingkungan dan tahan lama.</p>
                </div>
                <div class="col-md-3 fade-up">
                    <div class="keunggulan-icon">
                        <i class="fas fa-shipping-fast"></i>
                    </div>
                    <h5 class="fw-600">Produksi Cepat</h5>
                    <p class="text-muted small">Estimasi produksi cepat dengan kualitas yang tetap terjaga.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════
     5. ALUR PEMESANAN
═══════════════════════════════════════ --}}
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5 fade-up">
                <h2 class="section-title">Alur Pemesanan</h2>
                <p class="text-muted mt-3">Proses mudah dan transparan dari konsultasi hingga produk jadi</p>
            </div>

            <div class="row g-3 text-center position-relative">
                @php
                    $alur = [
                        [
                            'icon' => 'fa-comments',
                            'step' => '1',
                            'judul' => 'Konsultasi',
                            'desc' => 'Hubungi kami via WhatsApp untuk diskusi kebutuhan',
                        ],
                        [
                            'icon' => 'fa-paint-brush',
                            'step' => '2',
                            'judul' => 'Desain',
                            'desc' => 'Tim desainer kami menyiapkan mockup kemasan',
                        ],
                        [
                            'icon' => 'fa-money-bill',
                            'step' => '3',
                            'judul' => 'DP',
                            'desc' => 'Pembayaran DP sebagai tanda konfirmasi order',
                        ],
                        [
                            'icon' => 'fa-industry',
                            'step' => '4',
                            'judul' => 'Produksi',
                            'desc' => 'Proses produksi dimulai sesuai spesifikasi',
                        ],
                        [
                            'icon' => 'fa-check-circle',
                            'step' => '5',
                            'judul' => 'Pelunasan',
                            'desc' => 'Pelunasan dan pengiriman produk ke tangan Anda',
                        ],
                    ];
                @endphp

                @foreach ($alur as $item)
                    <div class="col fade-up">
                        <div class="alur-step">
                            <div class="alur-number">{{ $item['step'] }}</div>
                            <div class="mb-2">
                                <i class="fas {{ $item['icon'] }} fa-lg" style="color: var(--primary);"></i>
                            </div>
                            <h6 class="fw-600">{{ $item['judul'] }}</h6>
                            <p class="text-muted" style="font-size:0.8rem;">{{ $item['desc'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ═══════════════════════════════════════
     6. CTA WHATSAPP
═══════════════════════════════════════ --}}
    <section class="cta-section text-center text-white fade-up" id="kontak">
        <div class="container">
            <i class="fab fa-whatsapp fa-4x mb-4" style="color: #25d366;"></i>
            <h2 class="fw-700 mb-3">Siap Memesan Kemasan Impian Anda?</h2>
            <p class="fs-5 opacity-75 mb-5">
                Konsultasikan kebutuhan packaging bisnis Anda sekarang.<br>
                Gratis konsultasi, tanpa biaya desain!
            </p>
            <a href="https://wa.me/6283829208316?text=Halo%20Senggani%20Kemasan%2C%20saya%20ingin%20konsultasi%20mengenai%20packaging."
                target="_blank" rel="noopener" class="btn btn-success btn-lg rounded-pill px-5 py-3 fs-5 fw-600">
                <i class="fab fa-whatsapp me-2"></i>Konsultasi Sekarang via WhatsApp
            </a>
        </div>
    </section>

@endsection

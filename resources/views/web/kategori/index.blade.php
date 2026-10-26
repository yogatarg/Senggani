@extends('web.layouts.app')

@php
    $namaUsahaSeo = config('usaha.nama') ?: 'Senggani Paperbag & Packaging';
    $namaKat = trim($kategori->nama_kategori);
    $adaCustom = str_contains(mb_strtolower($namaKat), 'custom');
    // Judul: "Kategori {Nama} | Senggani Paperbag & Packaging Yogyakarta" (maks. 60 karakter), berbeda dari judul produk.
    // Huruf awal tiap kata dibesarkan hanya di judul (kata tugas tetap kecil, singkatan seperti PVC tetap).
    $kataKecil = ['dan', 'atau', 'untuk', 'dari', 'di', 'ke', 'yang', 'dengan', '&'];
    $namaJudulKat = collect(preg_split('/\s+/u', $namaKat))
        ->map(fn ($kata, $i) => $i > 0 && in_array(mb_strtolower($kata), $kataKecil, true)
            ? $kata
            : mb_strtoupper(mb_substr($kata, 0, 1)) . mb_substr($kata, 1))
        ->implode(' ');
    $judulKat = 'Kategori ' . $namaJudulKat . ' | ' . $namaUsahaSeo . ' Yogyakarta';
    if (mb_strlen($judulKat) > 60) {
        $sisa = 60 - mb_strlen('Kategori  | ' . $namaUsahaSeo);
        $judulKat = 'Kategori ' . (mb_strlen($namaJudulKat) > $sisa ? rtrim(mb_substr($namaJudulKat, 0, $sisa - 1)) . '…' : $namaJudulKat) . ' | ' . $namaUsahaSeo;
    }
    $metaKat = $namaKat . (mb_strlen($namaKat) <= 24 && ! $adaCustom ? ' custom' : '')
        . ' dari ' . $namaUsahaSeo . ', Kotagede, Yogyakarta. Ukuran, bahan, dan finishing sesuai kebutuhan. Konsultasi harga via WhatsApp.';
@endphp

@section('title', $judulKat)
@section('meta_description', $metaKat)

@php
    $keAbsolut = fn (string $u) => rtrim(\Illuminate\Support\Str::replaceStart(request()->root(), rtrim(config('app.url'), '/'), $u), '/') === rtrim(config('app.url'), '/')
        ? rtrim(config('app.url'), '/') . '/'
        : \Illuminate\Support\Str::replaceStart(request()->root(), rtrim(config('app.url'), '/'), $u);
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $keAbsolut(route('home'))],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Kategori', 'item' => $keAbsolut(route('kategori.index'))],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $kategori->nama_kategori, 'item' => $keAbsolut(route('kategori.show', $kategori->slug))],
        ],
    ];
@endphp
@push('jsonld')
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')

{{-- ── Header Kategori ── --}}
<div class="kategori-header">
    <div class="container text-white">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}" class="text-white opacity-75">Home</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('kategori.index') }}" class="text-white opacity-75">Kategori</a>
                </li>
                <li class="breadcrumb-item active text-white">
                    {{ $kategori->nama_kategori }}
                </li>
            </ol>
        </nav>
        <h1 class="fw-700">{{ $kategori->nama_kategori }}</h1>
        @if($kategori->deskripsi)
            <p class="opacity-75 mb-0">{{ $kategori->deskripsi }}</p>
        @endif
    </div>
</div>

{{-- ── Grid Produk ── --}}
<section class="py-5">
    <div class="container">

        {{-- Info jumlah produk --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <p class="text-muted mb-0">
                Menampilkan <strong>{{ $produk->count() }}</strong> produk
                dalam kategori <strong>{{ $kategori->nama_kategori }}</strong>
            </p>
            <a href="{{ route('kategori.index') }}" class="btn btn-outline-primary btn-sm rounded-pill">
                <i class="fas fa-arrow-left me-1"></i> Semua Kategori
            </a>
        </div>

        <div class="row g-3 g-md-4">
            @forelse($produk as $item)
            <div class="col-6 col-md-6 col-lg-4 fade-up">
                <div class="card h-100 kartu-produk">

                    {{-- Gambar Produk --}}
                    @if($item->gambar)
                        <img src="{{ asset('storage/' . $item->gambar) }}"
                             class="card-img-top kartu-produk-media kartu-produk-media-tinggi"
                             alt="{{ $item->nama_produk }}" width="400" height="300" loading="lazy" decoding="async">
                    @else
                        <div class="kartu-produk-media kartu-produk-media-tinggi kartu-produk-placeholder d-flex align-items-center justify-content-center">
                            <i class="fas fa-box fa-3x" style="color:#6a3131; opacity:0.3;" aria-hidden="true"></i>
                        </div>
                    @endif

                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title fw-600 mb-2 kartu-produk-judul">{{ $item->nama_produk }}</h5>

                        @if($item->deskripsi)
                            <p class="text-muted small mb-3 kartu-produk-desk">
                                {{ Str::limit($item->deskripsi, 80, '...') }}
                            </p>
                        @endif

                        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 kartu-produk-badges">
                            <span class="badge py-2 px-3 kartu-produk-badge">
                                <i class="fas fa-tag me-1" aria-hidden="true"></i> Harga Menyesuaikan
                            </span>
                        </div>

                        <p class="text-muted small mb-3 kartu-produk-meta">
                            <i class="fas fa-box me-1" aria-hidden="true"></i>
                            @if ((int) $item->minimal_pesan > 1)
                                Min. pesan {{ number_format((int) $item->minimal_pesan, 0, ',', '.') }} pcs
                            @else
                                Tanpa minimum order
                            @endif
                        </p>

                        <div class="mt-auto">
                            <a href="{{ route('produk.show', $item->slug) }}"
                               class="btn btn-primary w-100 rounded-pill kartu-produk-btn">
                                <i class="fas fa-eye me-1" aria-hidden="true"></i> Lihat Detail
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            @empty
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-4x mb-3" style="color:#6a3131; opacity:0.3;"></i>
                    <h5 class="text-muted">Belum ada produk di kategori ini</h5>
                    <p class="text-muted small">Silakan cek kategori lainnya</p>
                    <a href="{{ route('home') }}" class="btn btn-primary rounded-pill px-4">
                        <i class="fas fa-arrow-left me-1"></i> Kembali ke Home
                    </a>
                </div>
            </div>
            @endforelse
        </div>

    </div>
</section>

{{-- ── CTA WhatsApp ── --}}
<section class="py-5 text-center text-white"
         style="background: linear-gradient(135deg, #6a3131, #4e2424);">
    <div class="container">
        <i class="fab fa-whatsapp fa-3x mb-3" style="color:#25d366;"></i>
        <h4 class="fw-700 mb-2">Tidak menemukan yang Anda cari?</h4>
        <p class="opacity-75 mb-4">Konsultasikan kebutuhan packaging Anda langsung via WhatsApp</p>
        <a href="https://wa.me/{{ config('usaha.whatsapp') }}?text=Halo%20Senggani%20Paperbag%20%26%20Packaging%2C%20saya%20ingin%20bertanya%20mengenai%20produk%20kategori%20{{ urlencode($kategori->nama_kategori) }}."
           target="_blank" rel="noopener"
           class="btn btn-success btn-lg rounded-pill px-5">
            <i class="fab fa-whatsapp me-2"></i> Konsultasi Sekarang
        </a>
    </div>
</section>

@endsection
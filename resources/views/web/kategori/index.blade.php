@extends('web.layouts.app')

@section('title', $kategori->nama_kategori)

@section('content')

{{-- ── Header Kategori ── --}}
<div style="background: linear-gradient(135deg, #6a3131, #4e2424); padding: 60px 0;">
    <div class="container text-white">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2" style="background:transparent; padding:0;">
                <li class="breadcrumb-item">
                    <a href="{{ route('home') }}" class="text-white opacity-75">Home</a>
                </li>
                <li class="breadcrumb-item active text-white">
                    {{ $kategori->nama_kategori }}
                </li>
            </ol>
        </nav>
        <h1 class="fw-700 mb-2">{{ $kategori->nama_kategori }}</h1>
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
            <a href="{{ request()->routeIs('home') ? '#kategori' : route('home') . '#kategori' }}" class="btn btn-outline-primary btn-sm rounded-pill">
                <i class="fas fa-arrow-left me-1"></i> Semua Kategori
            </a>
        </div>

        <div class="row g-4">
            @forelse($produk as $item)
            <div class="col-md-6 col-lg-4 fade-up">
                <div class="card h-100">

                    {{-- Gambar Produk --}}
                    @if($item->gambar)
                        <img src="{{ asset('storage/' . $item->gambar) }}"
                             class="card-img-top"
                             style="height:220px; object-fit:cover;"
                             alt="{{ $item->nama_produk }}">
                    @else
                        <div class="d-flex align-items-center justify-content-center"
                             style="height:220px; background: linear-gradient(135deg, #f5e6e6, #edd);">
                            <i class="fas fa-box fa-3x" style="color:#6a3131; opacity:0.3;"></i>
                        </div>
                    @endif

                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title fw-600 mb-2">{{ $item->nama_produk }}</h5>

                        @if($item->deskripsi)
                            <p class="text-muted small mb-3">
                                {{ Str::limit($item->deskripsi, 80, '...') }}
                            </p>
                        @endif

                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge py-2 px-3"
                                  style="background:#f5e6e6; color:#6a3131; font-size:0.8rem;">
                                <i class="fas fa-tag me-1"></i> Harga Menyesuaikan
                            </span>
                        </div>

                        <p class="text-muted small mb-3">
                            <i class="fas fa-box me-1"></i>
                            Min. pesan {{ $item->minimal_pesan }} pcs
                        </p>

                        <div class="mt-auto">
                            <a href="{{ route('produk.show', $item->slug) }}"
                               class="btn btn-primary w-100 rounded-pill">
                                <i class="fas fa-eye me-1"></i> Lihat Detail
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
        <a href="https://wa.me/6285236987231?text=Halo%20Senggani%20Kemasan%2C%20saya%20ingin%20bertanya%20mengenai%20produk%20kategori%20{{ urlencode($kategori->nama_kategori) }}."
           target="_blank"
           class="btn btn-success btn-lg rounded-pill px-5">
            <i class="fab fa-whatsapp me-2"></i> Konsultasi Sekarang
        </a>
    </div>
</section>

@endsection
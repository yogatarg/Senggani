{{-- Kartu kategori — dipakai di home (#kategori) dan halaman daftar kategori. Variabel: $item (KategoriProduk + produk_count) --}}
<div class="col-6 col-md-4 col-lg-2 fade-up">
    <a href="{{ route('kategori.show', $item->slug) }}" class="kategori-card kartu-kategori">
        <div class="card text-center h-100">

            {{-- Gambar kategori --}}
            @if ($item->gambar)
                <img src="{{ asset('storage/' . $item->gambar) }}" class="card-img-top kartu-kategori-media"
                    style="height:120px; object-fit:cover;" alt="{{ $item->nama_kategori }}" width="320" height="200"
                    loading="lazy" decoding="async">
            @else
                {{-- Default jika tidak ada gambar --}}
                <div class="d-flex align-items-center justify-content-center kartu-kategori-media"
                    style="height:120px; background: linear-gradient(135deg, #f5e6e6, #edd);">
                    <div class="kategori-icon kartu-kategori-icon" style="margin:0;">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            @endif

            <div class="card-body py-3 kartu-kategori-body">
                <h6 class="fw-600 mb-1 kartu-kategori-judul">{{ $item->nama_kategori }}</h6>
                <small class="text-muted kartu-kategori-meta">{{ $item->produk_count }} produk</small>
            </div>
        </div>
    </a>
</div>

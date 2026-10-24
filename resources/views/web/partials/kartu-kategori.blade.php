{{-- Kartu kategori — dipakai di home (#kategori) dan halaman daftar kategori. Variabel: $item (KategoriProduk + produk_count) --}}
<div class="col-6 col-md-4 col-lg-2 fade-up">
    <a href="{{ route('kategori.show', $item->slug) }}" class="kategori-card">
        <div class="card text-center h-100">

            {{-- Gambar kategori --}}
            @if ($item->gambar)
                <img src="{{ asset('storage/' . $item->gambar) }}" class="card-img-top"
                    style="height:120px; object-fit:cover;" alt="{{ $item->nama_kategori }}">
            @else
                {{-- Default jika tidak ada gambar --}}
                <div class="d-flex align-items-center justify-content-center"
                    style="height:120px; background: linear-gradient(135deg, #f5e6e6, #edd);">
                    <div class="kategori-icon" style="margin:0;">
                        <i class="fas fa-box"></i>
                    </div>
                </div>
            @endif

            <div class="card-body py-3">
                <h6 class="fw-600 mb-1">{{ $item->nama_kategori }}</h6>
                <small class="text-muted">{{ $item->produk_count }} produk</small>
            </div>
        </div>
    </a>
</div>

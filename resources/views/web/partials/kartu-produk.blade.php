{{-- Kartu produk — dipakai di home (#produk) dan halaman daftar produk. Variabel: $item (Produk) --}}
<div class="col-md-6 col-lg-4 fade-up">
    <div class="card h-100">
        @if ($item->gambar)
            <img src="{{ asset('storage/' . $item->gambar) }}" class="card-img-top"
                alt="{{ $item->nama_produk }}">
        @else
            <div class="card-img-top d-flex align-items-center justify-content-center"
                style="height:200px; background: linear-gradient(135deg, #f5e6e6, #edd);">
                <i class="fas fa-box fa-3x" style="color:#6a3131; opacity:0.4;"></i>
            </div>
        @endif
        <div class="card-body d-flex flex-column">
            <h5 class="card-title fw-600">{{ $item->nama_produk }}</h5>
            <p class="text-muted small mb-3">
                Min. pesan {{ $item->minimal_pesan }} pcs
            </p>
            <div class="mt-auto">
                <a href="{{ route('produk.show', $item->slug) }}" class="btn btn-primary w-100">
                    <i class="fas fa-eye me-1"></i> Lihat Detail
                </a>
            </div>
        </div>
    </div>
</div>

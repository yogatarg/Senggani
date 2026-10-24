{{-- Kartu produk — dipakai di home (#produk) dan halaman daftar produk. Variabel: $item (Produk)
     Kelas .kartu-produk* (CSS di layouts/app) dipakai bersama kartu di halaman detail kategori:
     2 kolom & versi ringkas di HP (<768px), tampilan ≥768px tidak berubah. --}}
<div class="col-6 col-md-6 col-lg-4 fade-up">
    <div class="card h-100 kartu-produk">
        @if ($item->gambar)
            <img src="{{ asset('storage/' . $item->gambar) }}" class="card-img-top kartu-produk-media"
                alt="{{ $item->nama_produk }}">
        @else
            <div class="card-img-top kartu-produk-media kartu-produk-placeholder d-flex align-items-center justify-content-center">
                <i class="fas fa-box fa-3x" style="color:#6a3131; opacity:0.4;" aria-hidden="true"></i>
            </div>
        @endif
        <div class="card-body d-flex flex-column">
            <h5 class="card-title fw-600 kartu-produk-judul">{{ $item->nama_produk }}</h5>
            <p class="text-muted small mb-3 kartu-produk-meta">
                Min. pesan {{ $item->minimal_pesan }} pcs
            </p>
            <div class="mt-auto">
                <a href="{{ route('produk.show', $item->slug) }}" class="btn btn-primary w-100 kartu-produk-btn">
                    <i class="fas fa-eye me-1" aria-hidden="true"></i> Lihat Detail
                </a>
            </div>
        </div>
    </div>
</div>

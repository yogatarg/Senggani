@extends('web.layouts.app')

@section('title', $kategoriAktif ? 'Produk ' . $kategoriAktif->nama_kategori : 'Produk')

@push('styles')
    <style>
        /* ── Filter Kategori ── */
        .filter-kategori {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding: 0.25rem 0.25rem 0.75rem;
            margin: 0 -0.25rem;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
        }

        .filter-kategori .btn {
            flex: 0 0 auto;
            white-space: nowrap;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .filter-kategori .btn .jumlah {
            font-size: 0.75rem;
            opacity: 0.75;
            margin-left: 0.25rem;
        }

        @media (min-width: 768px) {
            .filter-kategori {
                flex-wrap: wrap;
                justify-content: center;
                overflow-x: visible;
            }
        }

        /* ── Paginasi warna tema ── */
        .pagination {
            --bs-pagination-color: var(--primary);
            --bs-pagination-hover-color: var(--primary-dark);
            --bs-pagination-focus-color: var(--primary-dark);
            --bs-pagination-focus-box-shadow: 0 0 0 0.25rem rgba(76, 4, 4, 0.2);
            --bs-pagination-active-bg: var(--primary);
            --bs-pagination-active-border-color: var(--primary);
        }
    </style>
@endpush

@section('content')

    <section class="py-5">
        <div class="container">
            <div class="text-center mb-4 fade-up">
                <h1 class="section-title">
                    {{ $kategoriAktif ? $kategoriAktif->nama_kategori : 'Semua Produk' }}
                </h1>
                <p class="text-muted mt-3 mb-0">
                    @if ($kategoriAktif)
                        Menampilkan {{ $produk->total() }} produk di kategori {{ $kategoriAktif->nama_kategori }}
                    @else
                        Kualitas terbaik dengan harga terjangkau
                    @endif
                </p>
            </div>

            {{-- Filter kategori --}}
            @if ($kategori->count() > 0)
                <nav class="filter-kategori mb-4" aria-label="Filter kategori produk">
                    <a href="{{ route('produk.index') }}"
                        class="btn rounded-pill px-3 {{ $kategoriAktif ? 'btn-outline-primary' : 'btn-primary' }}"
                        @if (!$kategoriAktif) aria-current="page" @endif>
                        Semua
                    </a>
                    @foreach ($kategori as $kat)
                        @php($aktif = $kategoriAktif && $kategoriAktif->id === $kat->id)
                        <a href="{{ route('produk.index', ['kategori' => $kat->slug]) }}"
                            class="btn rounded-pill px-3 {{ $aktif ? 'btn-primary' : 'btn-outline-primary' }}"
                            @if ($aktif) aria-current="page" @endif>
                            {{ $kat->nama_kategori }}<span class="jumlah">({{ $kat->produk_count }})</span>
                        </a>
                    @endforeach
                </nav>
            @endif

            {{-- Grid produk --}}
            <div class="row g-3 g-md-4">
                @forelse ($produk as $item)
                    @include('web.partials.kartu-produk', ['item' => $item])
                @empty
                    <div class="col-12 text-center text-muted py-5">
                        <i class="fas fa-box-open fa-3x mb-3" style="color:#6a3131; opacity:0.4;"></i>
                        <p class="mb-3">
                            {{ $kategoriAktif ? 'Belum ada produk di kategori ini.' : 'Belum ada produk tersedia.' }}
                        </p>
                        @if ($kategoriAktif)
                            <a href="{{ route('produk.index') }}" class="btn btn-outline-primary rounded-pill px-4">
                                Lihat Semua Produk
                            </a>
                        @endif
                    </div>
                @endforelse
            </div>

            @if ($produk->hasPages())
                <div class="d-flex justify-content-center mt-5">
                    {{ $produk->links('pagination::bootstrap-5') }}
                </div>
            @endif
        </div>
    </section>

@endsection

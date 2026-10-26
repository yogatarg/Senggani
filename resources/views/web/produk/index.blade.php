@extends('web.layouts.app')

@if ($kategoriAktif)
@php
    $namaUsahaSeo = config('usaha.nama') ?: 'Senggani Paperbag & Packaging';
    $namaKat = trim($kategoriAktif->nama_kategori);
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
    {{-- /produk?kategori=x (semua halaman) isinya bagian dari /kategori/x yang memuat semua produk kategori itu:
         halaman kategori adalah versi utama, jadi canonical menunjuk ke sana (judul dibiarkan sama agar sinyal konsisten). --}}
    @section('canonical', \Illuminate\Support\Str::replaceStart(request()->root(), rtrim(config('app.url'), '/'), route('kategori.show', $kategoriAktif->slug)))
@else
    @php $judulHalaman = 'Produk Kemasan Custom | ' . (config('usaha.nama') ?: 'Senggani Paperbag & Packaging'); @endphp
    @section('title', mb_strlen($judulHalaman . ' Yogyakarta') <= 60 ? $judulHalaman . ' Yogyakarta' : $judulHalaman)
    @section('meta_description', 'Lihat produk kemasan custom Senggani Paperbag & Packaging: paperbag, dus, tas furing, undangan, dan lainnya. Pilih bahan dan finishing, lalu pesan via WhatsApp.')
@endif

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

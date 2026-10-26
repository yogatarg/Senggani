@extends('web.layouts.app')

@php
    $namaUsahaSeo = config('usaha.nama') ?: 'Senggani Paperbag & Packaging';
    $keAbsolut = fn (string $u) => rtrim(\Illuminate\Support\Str::replaceStart(request()->root(), rtrim(config('app.url'), '/'), $u), '/') === rtrim(config('app.url'), '/')
        ? rtrim(config('app.url'), '/') . '/'
        : \Illuminate\Support\Str::replaceStart(request()->root(), rtrim(config('app.url'), '/'), $u);
    $namaProdukSeo = trim($produk->nama_produk);
    // Judul maks. 60 karakter: "{Produk} | {Usaha} Yogyakarta" bila muat, lalu tanpa " Yogyakarta",
    // terakhir nama produk dipotong (…) agar tetap ≤60.
    $judulProduk = $namaProdukSeo . ' | ' . $namaUsahaSeo . ' Yogyakarta';
    if (mb_strlen($judulProduk) > 60) {
        $sisaJudul = 60 - mb_strlen(' | ' . $namaUsahaSeo);
        $judulProduk = (mb_strlen($namaProdukSeo) > $sisaJudul
            ? rtrim(mb_substr($namaProdukSeo, 0, $sisaJudul - 1)) . '…'
            : $namaProdukSeo) . ' | ' . $namaUsahaSeo;
    }
    // minimal_pesan ≤ 1 atau kosong = tanpa minimum order
    $minPesan = max(1, (int) $produk->minimal_pesan);
    $adaMinimum = $minPesan > 1;
    $minPesanTeks = number_format($minPesan, 0, ',', '.');
    $adaPilihan = $produk->bahan->count() > 0 || $produk->finishing->count() > 0 || $produk->cetak->count() > 0;
    $metaEkor = '. ' . ($adaMinimum ? 'Minimal ' . $minPesanTeks . ' pcs, ' : 'Tanpa minimal order, ')
        . ($adaPilihan ? 'pilih bahan dan finishing, ' : 'ukuran custom, ')
        . 'harga sesuai spesifikasi. Pesan via WhatsApp.';
    $metaProduk = $namaProdukSeo . ' dari ' . $namaUsahaSeo . ' Yogyakarta' . $metaEkor;
    if (mb_strlen($metaProduk) > 160) {
        $metaProduk = $namaProdukSeo . ' dari ' . $namaUsahaSeo . $metaEkor;
    }

    // Semua foto produk (absolut) untuk og:image & JSON-LD
    $fotoSeo = collect([$produk->gambar])
        ->merge($produk->galeri->pluck('nama_file'))
        ->filter()
        ->unique()
        ->map(fn ($f) => $keAbsolut(asset('storage/' . $f)))
        ->values();

    // Deskripsi polos (tanpa **tebal** dan baris baru) untuk data terstruktur
    $deskripsiPolos = trim(preg_replace('/\s+/u', ' ', str_replace('**', '', (string) $produk->deskripsi)));

    $produkSchema = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $namaProdukSeo,
        'description' => $deskripsiPolos !== '' ? $deskripsiPolos : $metaProduk,
        'image' => $fotoSeo->all() ?: null,
        'url' => $keAbsolut(route('produk.show', $produk->slug)),
        'brand' => ['@type' => 'Brand', 'name' => $namaUsahaSeo],
        'category' => $produk->kategori?->nama_kategori,
        // Tanpa offers: harga custom sesuai spesifikasi
    ], fn ($v) => $v !== null);

    $remah = [['name' => 'Home', 'item' => $keAbsolut(route('home'))]];
    if ($produk->kategori) {
        $remah[] = ['name' => $produk->kategori->nama_kategori, 'item' => $keAbsolut(route('kategori.show', $produk->kategori->slug))];
    }
    $remah[] = ['name' => $namaProdukSeo, 'item' => $keAbsolut(route('produk.show', $produk->slug))];
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($remah)->map(fn ($r, $i) => ['@type' => 'ListItem', 'position' => $i + 1] + $r)->all(),
    ];
@endphp

@section('title', $judulProduk)
@section('meta_description', $metaProduk)
@section('og_type', 'product')
@if ($fotoSeo->isNotEmpty())
    @section('og_image', $fotoSeo->first())
@endif

@push('jsonld')
    <script type="application/ld+json">{!! json_encode($produkSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')

    <div class="container py-5">
        <div class="row gx-4 gx-sm-5 gy-5">

            {{-- ── Kolom Kiri: Foto Utama + Thumbnail ── --}}
            <div class="col-lg-6">

                {{-- Foto Utama --}}
                <div class="rounded-3 overflow-hidden shadow mb-3" style="height:400px; background:#f5e6e6;">
                    @php
                        // Prioritas: gambar utama produk dulu, baru galeri
                        $fotoUtama = $produk->gambar
                            ? asset('storage/' . $produk->gambar)
                            : ($produk->galeri->count() > 0
                                ? asset('storage/' . $produk->galeri->first()->nama_file)
                                : null);
                    @endphp

                    @if ($fotoUtama)
                        <img id="fotoUtama" src="{{ $fotoUtama }}" class="w-100 h-100"
                            style="object-fit:cover; cursor:zoom-in; transition: opacity 0.2s;"
                            alt="{{ $produk->nama_produk }}" fetchpriority="high" decoding="async"
                            onclick="bukaLightbox(this.src)">
                    @else
                        <div class="w-100 h-100 d-flex align-items-center justify-content-center">
                            <i class="fas fa-box fa-5x" style="color:#6a3131; opacity:0.3;"></i>
                        </div>
                    @endif
                </div>

                {{-- Thumbnail Strip --}}
                @php
                    // Gabungkan gambar utama + galeri untuk thumbnail
                    $semuaFoto = collect();
                    if ($produk->gambar) {
                        $semuaFoto->push(['src' => asset('storage/' . $produk->gambar), 'alt' => 'Gambar Utama']);
                    }
                    foreach ($produk->galeri as $foto) {
                        $semuaFoto->push([
                            'src' => asset('storage/' . $foto->nama_file),
                            'alt' => $foto->keterangan ?? '',
                        ]);
                    }
                @endphp

                @if ($semuaFoto->count() > 1)
                    <div class="d-flex gap-2 flex-wrap">
                        @foreach ($semuaFoto as $i => $foto)
                            <div onclick="gantifoto('{{ $foto['src'] }}', this)"
                                class="thumbnail-item rounded-2 overflow-hidden"
                                style="width:70px; height:70px; cursor:pointer;
                border: 2px solid {{ $i === 0 ? '#6a3131' : '#ddd' }};
                transition: border 0.2s; flex-shrink:0;">
                                <img src="{{ $foto['src'] }}" class="w-100 h-100" style="object-fit:cover;"
                                    alt="{{ $foto['alt'] }}" width="70" height="70" loading="lazy" decoding="async">
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>

            {{-- ── Kolom Kanan: Info + Form ── --}}
            <div class="col-lg-6">

                {{-- Breadcrumb --}}
                <nav aria-label="breadcrumb" class="mb-2">
                    <ol class="breadcrumb" style="font-size:0.85rem;">
                        <li class="breadcrumb-item">
                            <a href="{{ route('home') }}" style="color:#6a3131;">Home</a>
                        </li>
                        @if ($produk->kategori)
                            <li class="breadcrumb-item">
                                <a href="{{ route('kategori.show', $produk->kategori?->slug) }}" style="color:#6a3131;">
                                    {{ $produk->kategori?->nama_kategori ?? '-' }}
                                </a>
                            </li>
                        @endif
                        <li class="breadcrumb-item active text-muted">
                            {{ $produk->nama_produk }}
                        </li>
                    </ol>
                </nav>



                {{-- Nama Produk --}}
                <h1 class="fw-bold mb-2" style="color:#6a3131; font-size:1.8rem;">
                    {{ $produk->nama_produk }}
                </h1>

                {{-- Min. Pesan --}}
                <div class="d-flex align-items-center gap-3 mb-3">
                    <span class="badge py-2 px-3" style="background:#f5e6e6; color:#6a3131; font-size:0.85rem;">
                        <i class="fas fa-tag me-1"></i> Harga sesuai spesifikasi
                    </span>
                    <span class="text-muted small">
                        <i class="fas fa-box me-1" aria-hidden="true"></i>{{ $adaMinimum ? 'Min. ' . $minPesanTeks . ' pcs' : 'Tanpa minimum order' }}
                    </span>
                </div>

                {{-- Deskripsi --}}
                @if ($produk->deskripsi)
                    <div class="text-muted mb-3" style="font-size:0.95rem; line-height:1.7;">
                        {!! nl2br(preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', e($produk->deskripsi))) !!}
                    </div>
                @endif

                <hr>

                {{-- Bahan & Finishing Info --}}
                <div class="row g-3 mb-4">

                    @if ($produk->bahan->count() > 0)
                        <div class="col-12">
                            <div class="p-3 rounded-3" style="background:#f9f3f3; border-left:4px solid #6a3131;">
                                <h6 class="fw-bold mb-2" style="color:#6a3131; font-size:0.9rem;">
                                    <i class="fas fa-layer-group me-2"></i>Pilihan Bahan
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($produk->bahan as $item)
                                        <span class="badge py-2 px-3"
                                            style="background:#fff; color:#6a3131;
                                         border:1px solid #6a3131;
                                         font-size:0.8rem; font-weight:500;">
                                            {{ $item->nama_bahan }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($produk->cetak->count() > 0)
                        <div class="col-12">
                            <div class="p-3 rounded-3" style="background:#f9f3f3; border-left:4px solid #6a3131;">
                                <h6 class="fw-bold mb-2" style="color:#6a3131; font-size:0.9rem;">
                                    <i class="fas fa-print me-2"></i>Pilihan Cetak
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($produk->cetak as $item)
                                        <span class="badge py-2 px-3"
                                            style="background:#fff; color:#6a3131;
                                         border:1px solid #6a3131;
                                         font-size:0.8rem; font-weight:500;">
                                            {{ $item->nama_cetak }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    @if ($produk->finishing->count() > 0)
                        <div class="col-12">
                            <div class="p-3 rounded-3" style="background:#f9f3f3; border-left:4px solid #d4a853;">
                                <h6 class="fw-bold mb-2" style="color:#6a3131; font-size:0.9rem;">
                                    <i class="fas fa-paint-brush me-2"></i>Pilihan Finishing
                                </h6>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($produk->finishing as $item)
                                        <span class="badge py-2 px-3"
                                            style="background:#fff; color:#6a3131;
                                         border:1px solid #d4a853;
                                         font-size:0.8rem; font-weight:500;">
                                            {{ $item->nama_finishing }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                </div>

                {{-- ── FORM PEMESANAN ── --}}
                <div id="formPesan">

                    {{-- Bahan Dropdown --}}
                    @if ($produk->bahan->count() > 0)
                        <div class="mb-3">
                            <label class="form-label fw-bold" style="font-size:0.9rem;">
                                Pilih Bahan
                            </label>
                            <select id="pilihBahan" class="form-select">
                                <option value="">-- Pilih Bahan --</option>
                                @foreach ($produk->bahan as $item)
                                    <option value="{{ $item->nama_bahan }}">
                                        {{ $item->nama_bahan }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Cetak Dropdown (satu pilihan) --}}
                    @if ($produk->cetak->count() > 0)
                        <div class="mb-3">
                            <label class="form-label fw-bold" for="pilihCetak" style="font-size:0.9rem;">
                                Pilih Cetak
                            </label>
                            <select id="pilihCetak" class="form-select">
                                <option value="">-- Pilih Cetak --</option>
                                @foreach ($produk->cetak as $item)
                                    <option value="{{ $item->nama_cetak }}">
                                        {{ $item->nama_cetak }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    {{-- Finishing Checkbox (boleh lebih dari satu) --}}
                    @if ($produk->finishing->count() > 0)
                        <fieldset class="mb-3">
                            <legend class="form-label fw-bold mb-1" style="font-size:0.9rem;">
                                Pilih Finishing
                                <span class="fw-normal text-muted small">(boleh lebih dari satu)</span>
                            </legend>
                            <div class="row g-1" id="pilihFinishing">
                                @foreach ($produk->finishing as $item)
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                id="finishing{{ $item->id }}" value="{{ $item->nama_finishing }}">
                                            <label class="form-check-label small" for="finishing{{ $item->id }}">
                                                {{ $item->nama_finishing }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endif

                    {{-- Jumlah --}}
                    <div class="mb-4">
                        <label class="form-label fw-bold" style="font-size:0.9rem;">
                            Jumlah (pcs)
                        </label>
                        <input type="number" id="inputJumlah" class="form-control" min="{{ $minPesan }}"
                            value="{{ $minPesan }}">
                        <div class="form-text">
                            {{ $adaMinimum ? 'Minimal pemesanan ' . $minPesanTeks . ' pcs' : 'Tanpa minimum order' }}
                        </div>
                    </div>

                    {{-- Tombol WhatsApp --}}
                    <button onclick="pesanWhatsApp()" type="button"
                        class="btn btn-success btn-lg w-100 py-3 fw-bold rounded-pill">
                        <i class="fab fa-whatsapp me-2 fs-5"></i>
                        Pesan via WhatsApp
                    </button>

                    <p class="text-muted text-center small mt-2">
                        <i class="fas fa-info-circle me-1"></i>
                        Anda akan diarahkan ke WhatsApp untuk konfirmasi pesanan
                    </p>

                </div>

            </div>
        </div>
    </div>

    {{-- ── LIGHTBOX ── --}}
    <div id="lightbox" onclick="tutupLightbox()"
        style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.85);
            z-index:9999; align-items:center; justify-content:center; cursor:zoom-out;">
        {{-- Tanpa atribut src sampai dibuka (src kosong memicu permintaan ke halaman ini sendiri) --}}
        <img id="lightboxImg" alt="Foto {{ $produk->nama_produk }} diperbesar"
            style="max-width:90vw; max-height:90vh;
                object-fit:contain; border-radius:8px; box-shadow:0 0 40px rgba(0,0,0,0.5);">
        <button onclick="tutupLightbox()"
            style="position:fixed; top:20px; right:25px; background:none;
                   border:none; color:#fff; font-size:2rem; cursor:pointer;">
            &times;
        </button>
    </div>

    {{-- Scripts --}}
    <script>
        const NOMOR_WA = @json((string) config('usaha.whatsapp'));
        const NAMA_PRODUK = @json($produk->nama_produk);
        const URL_PRODUK = @json(url()->current());

        // ── Ganti foto utama saat thumbnail diklik ──
        function gantifoto(src, el) {
            const fotoUtama = document.getElementById('fotoUtama');

            // Fade out
            fotoUtama.style.opacity = '0';
            setTimeout(() => {
                fotoUtama.src = src;
                fotoUtama.style.opacity = '1';
            }, 150);

            // Update border thumbnail
            document.querySelectorAll('.thumbnail-item').forEach(t => {
                t.style.borderColor = '#ddd';
            });
            el.style.borderColor = '#6a3131';
        }

        // ── Lightbox ──
        function bukaLightbox(src) {
            const lb = document.getElementById('lightbox');
            document.getElementById('lightboxImg').src = src;
            lb.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function tutupLightbox() {
            document.getElementById('lightbox').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Tutup lightbox dengan tombol Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') tutupLightbox();
        });

        // ── Pesan WhatsApp ──
        function pesanWhatsApp() {
            const bahan = document.getElementById('pilihBahan')?.value || '-';
            const cetak = document.getElementById('pilihCetak')?.value || '-';
            const finishing = Array.from(document.querySelectorAll('#pilihFinishing input:checked'))
                .map(cb => cb.value).join(', ') || '-';
            const jumlah = document.getElementById('inputJumlah').value || '-';

            const minPesan = {{ $minPesan }};
            if (parseInt(jumlah) < minPesan) {
                alert(`Minimal pemesanan adalah ${minPesan} pcs.`);
                document.getElementById('inputJumlah').focus();
                return;
            }

            const pesan =
                `Halo Senggani Paperbag & Packaging, saya tertarik dan ingin menanyakan produk:\n\n` +
                `Nama Produk : ${NAMA_PRODUK}\n` +
                `Bahan       : ${bahan}\n` +
                `Cetak       : ${cetak}\n` +
                `Finishing   : ${finishing}\n` +
                `Jumlah      : ${jumlah} pcs\n\n` +
                `Mohon info estimasi harga dan proses pemesanannya. Terima kasih.`;

            const url = `https://wa.me/${NOMOR_WA}?text=${encodeURIComponent(pesan)}`;
            window.open(url, '_blank');
        }
    </script>

@endsection

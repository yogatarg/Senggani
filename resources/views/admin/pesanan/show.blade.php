@extends('admin.layouts.pesanan')

@section('title', $pesanan->kode_tampil)
@section('kembali', route('admin.pesanan.index'))
@section('tanpa_bottomnav', '1')

@php
    use App\Enums\StatusProduksi;
    use Illuminate\Support\Facades\Gate;

    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $angka = fn ($n) => number_format((float) $n, 0, ',', '.');
    $status = $pesanan->status_produksi;
    $dibatalkan = $status === StatusProduksi::Dibatalkan;
    $tidakJadi = $status === StatusProduksi::TidakJadi;
    $diArsipBatal = $dibatalkan || $tidakJadi;
    $hitungHarga = $status === StatusProduksi::HitungHarga;
    $menungguDp = $status === StatusProduksi::MenungguDp;
    $sudahProduksi = $status->sudahProduksi();
    $urutanKini = $status->urutan();
    $isAdmin = Gate::allows('update', $pesanan);

    // 6281234567890 → +62 812-3456-7890
    $wa = (string) $pesanan->nomor_whatsapp;
    if (str_starts_with($wa, '62') && strlen($wa) > 5) {
        $sisaNo = substr($wa, 2);
        $waTampil = '+62 ' . substr($sisaNo, 0, 3) . '-' . implode('-', str_split(substr($sisaNo, 3), 4));
    } else {
        $waTampil = $wa;
    }

    $file = $pesanan->file;
    $riwayat = $pesanan->riwayat;
    $item = $pesanan->detailPesanan;
    $jumlahItem = $item->count();
    $tanpaHarga = $pesanan->jumlah_item_tanpa_harga;

    $bisaUbahStatus = Gate::allows('ubahStatus', $pesanan);
    $bisaBatal = ! $dibatalkan && $status->bisaDibatalkan() && Gate::allows('batalkan', $pesanan);
    $bisaHapus = Gate::allows('delete', $pesanan);
    $bisaPulihkan = $diArsipBatal && Gate::allows('pulihkan', $pesanan);
    $bisaCatatLunas = $sudahProduksi && ! $pesanan->tanggal_lunas && $pesanan->total_tagihan > 0 && Gate::allows('catatLunas', $pesanan);
    $tampilHitungHarga = $hitungHarga && $bisaIsiHarga;

    // Ke Pengiriman dikunci sampai lunas (backend menolak; tombol ditampilkan nonaktif dengan keterangan)
    $majuTerkunci = $menungguPelunasan && $statusBerikutnya === StatusProduksi::Pengiriman;
    $teksKunci = 'Belum lunas — minta admin mencatat pelunasan dulu.';

    // Alasan "tidak jadi" = catatan pada riwayat terakhir yang memindahkan ke tidak_jadi
    $riwayatTidakJadi = $tidakJadi ? $riwayat->first(fn ($r) => $r->status_ke === StatusProduksi::TidakJadi) : null;

    // Teks total: harga bisa belum lengkap sebelum produksi
    // Total tagihan = subtotal produk + ongkir − diskon
    $teksTotal = $sudahProduksi || $pesanan->harga_lengkap
        ? $rp($pesanan->total_tagihan)
        : ((float) $pesanan->total > 0 ? $rp($pesanan->total_tagihan) . ' (belum lengkap)' : 'Belum dihitung');

    // Modal yang dibuka ulang otomatis bila ada galat validasi dari form di dalamnya
    $galatDp = $errors->hasAny(['dp', 'tanggal_dp', 'nomor_spp', 'konfirmasi_nomor', 'status', 'metode_dp']);
    $galatLunas = $errors->hasAny(['tanggal_lunas', 'metode_lunas']);
    $saranNomorBaru = \App\Models\Pesanan::saranNomorSpp();

    $gid = fn (string $kunci) => 'g-' . str_replace('.', '-', $kunci);

    // Ongkir & diskon: baris rincian hanya bila salah satunya > 0 (rancangan invoice 5.4)
    $ongkir = (float) $pesanan->ongkir;
    $diskon = (float) $pesanan->diskon;
    $adaBiaya = $ongkir > 0 || $diskon > 0;
    $teksTermasuk = $adaBiaya ? 'termasuk ' . collect([
        $ongkir > 0 ? 'ongkir ' . $rp($ongkir) : null,
        $diskon > 0 ? 'diskon ' . $rp($diskon) : null,
    ])->filter()->implode(' · ') : null;
    // "Metode · tanggal" di bawah nominal DP/pelunasan (metode kosong untuk data lama)
    $ketBayar = fn ($metode, $tanggal) => collect([$metode?->label(), $tanggal?->translatedFormat('j M Y')])->filter()->implode(' · ');

    // Invoice tetap dibuat tanpa rekening & QRIS; admin diberi tahu lewat teks bantu (rancangan 5.1)
    $infoBayarKosong = false;
    if ($bisaUnduhInvoice) {
        $pengaturanInvoice = app(\App\Services\PengaturanInvoice::class);
        $infoBayarKosong = empty($pengaturanInvoice->semua()['rekening']) && ! $pengaturanInvoice->pathGambar('qris');
    }
@endphp

@section('content')
<a href="{{ route('admin.pesanan.index') }}" class="sg-remah d-none d-lg-inline-flex"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Pesanan</a>

<header class="sg-detail-kepala">
    <div>
        <h1 class="sg-no-order">{{ $pesanan->kode_tampil }}</h1>
        <p class="sg-nama-cust">{{ $pesanan->nama_customer }}</p>
        @if ($pesanan->nama_brand)
            <p class="sg-brand">{{ $pesanan->nama_brand }}</p>
        @endif
        <p class="sg-kontak sg-angka">
            @if ($pesanan->customer && Gate::allows('kelola-customer'))
                <a href="{{ route('admin.customer.show', $pesanan->customer) }}" class="sg-kode-customer" aria-label="Lihat customer {{ $pesanan->customer->kode }}"><i class="fa-solid fa-address-book" aria-hidden="true"></i>{{ $pesanan->customer->kode }}</a>
                <span aria-hidden="true">·</span>
            @endif
            {{ $waTampil }}@if ($pesanan->kota) · {{ $pesanan->kota }}@endif
        </p>
        <div class="sg-lencana">
            @include('admin.pesanan._badge-status', ['status' => $status])
            @if ($sudahProduksi)
                @include('admin.pesanan._badge-bayar', ['p' => $pesanan])
            @endif
            @if ($menungguPelunasan)
                <span class="sg-bayar sg-by-dp"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i>Menunggu pelunasan</span>
            @endif
            @include('admin.pesanan._deadline', ['p' => $pesanan])
        </div>
    </div>
    @if ($isAdmin || $teksSpp)
        {{--
            Urutan (rancangan invoice 5.1): HP grid 2 kolom [WhatsApp][Invoice PDF | Edit][Salin teks SPP];
            576–1199 satu baris membungkus WhatsApp, Invoice PDF, Edit, Salin; ≥1200 WhatsApp, Edit, Invoice PDF.
            Diatur lewat kelas sg-ak-* (CSS order), bukan urutan markup.
        --}}
        <div class="sg-lencana-aksi sg-aksi-kepala {{ $isAdmin ? '' : 'd-xl-none' }}">
            @if ($isAdmin)
                <a href="{{ $pesanan->link_whatsapp }}" target="_blank" rel="noopener" class="sg-btn sg-btn-wa sg-ak-wa"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp Customer<span class="visually-hidden"> (tab baru)</span></a>
                <a href="{{ route('admin.pesanan.edit', $pesanan) }}" class="sg-btn sg-btn-sekunder sg-ak-edit"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
                @if ($bisaUnduhInvoice)
                    {{-- Tanpa atribut download: bila gagal, server mengalihkan kembali ke halaman ini dengan pesan galat --}}
                    <a href="{{ route('admin.pesanan.invoice', $pesanan) }}" class="sg-btn sg-btn-sekunder sg-ak-invoice" data-unduh-invoice
                       aria-label="Unduh invoice {{ $pesanan->nomor_invoice }} (PDF)" @if ($infoBayarKosong) aria-describedby="ket-invoice" @endif>
                        <i class="fa-solid fa-file-invoice" aria-hidden="true" data-unduh-ikon></i> <span data-unduh-label>Invoice PDF</span>
                    </a>
                    @if ($infoBayarKosong)
                        <p class="sg-bantu sg-ak-ket" id="ket-invoice">
                            Info pembayaran belum diatur.
                            @can('kelola-invoice')<a href="{{ route('admin.pengaturan.invoice.edit') }}" class="fw-semibold">Atur invoice</a>@endcan
                        </p>
                    @endif
                @elseif ($hitungHarga)
                    <button type="button" class="sg-btn sg-btn-sekunder sg-ak-invoice" aria-disabled="true" aria-describedby="ket-invoice"><i class="fa-solid fa-lock" aria-hidden="true"></i> Invoice PDF</button>
                    <p class="sg-kunci-ket sg-ak-ket" id="ket-invoice"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>Invoice tersedia setelah semua harga terisi.</p>
                @endif
            @endif
            @if ($teksSpp)
                <button type="button" class="sg-btn sg-btn-sekunder sg-ak-salin d-xl-none" data-salin-teks="#teks-spp"><i class="fa-regular fa-copy" aria-hidden="true"></i> <span data-salin-label>Salin teks SPP</span></button>
            @endif
        </div>
    @endif
</header>

<div class="sg-detail">
    <div class="sg-detail-utama">
        {{-- Hitung Harga: tugas utama selama status hitung_harga (produksi & admin) --}}
        @if ($tampilHitungHarga)
            <section class="sg-kartu sg-kartu-harga sg-o-harga" aria-labelledby="judul-hitung">
                <h2 class="sg-kartu-judul" id="judul-hitung"><i class="fa-solid fa-calculator" aria-hidden="true"></i> Hitung Harga</h2>
                <p class="sg-bantu mt-0 mb-3">Isi harga satuan tiap item. Setelah semua item berharga, pesanan otomatis pindah ke <strong>Menunggu DP</strong>. Boleh disimpan sebagian dulu. Harga yang diisi minimal Rp 1.</p>
                <form method="POST" action="{{ route('admin.pesanan.harga', $pesanan) }}" id="form-harga" data-kirim data-form-harga novalidate>
                    @csrf
                    <div id="f-harga">
                        @error('harga')<p class="sg-galat mb-3" id="g-harga"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                    </div>
                    @foreach ($item as $d)
                        @php
                            $kunciH = 'harga.' . $d->id;
                            $nilaiH = old($kunciH, $d->harga_satuan === null ? '' : number_format((int) $d->harga_satuan, 0, ',', '.'));
                            $spek = collect([
                                trim($d->jenis_bahan . ' ' . $d->gramasi),
                                trim($d->jenis_cetak . ' ' . $d->warna_cetak),
                                $d->daftar_finishing ? implode(', ', $d->daftar_finishing) : null,
                                $d->aksesori,
                            ])->filter()->implode(' · ');
                        @endphp
                        <div class="sg-harga-item" data-harga-item data-jumlah="{{ (int) $d->jumlah }}">
                            <div class="sg-harga-item-kepala">
                                <h3><span class="sg-muted">{{ $loop->iteration }}.</span> {{ $d->jenis_packaging }}@if ($d->ukuran_teks) · <span class="sg-angka">{{ $d->ukuran_teks }}</span>@endif</h3>
                                <span class="sg-jumlah-besar" style="font-size:18px">{{ $angka($d->jumlah) }} pcs</span>
                            </div>
                            @if ($spek)<p class="sg-harga-item-spek">{{ $spek }}</p>@else<div class="mb-2"></div>@endif
                            <div class="sg-harga-isian">
                                <div>
                                    <label class="sg-label" for="f-harga-{{ $d->id }}">Harga satuan<span class="visually-hidden"> item {{ $loop->iteration }}, {{ $d->jenis_packaging }}</span></label>
                                    <div class="sg-imbuh sg-imbuh-depan">
                                        <span class="sg-imbuh-teks" aria-hidden="true">Rp</span>
                                        <input type="text" inputmode="numeric" id="f-harga-{{ $d->id }}" name="harga[{{ $d->id }}]" value="{{ $nilaiH }}" autocomplete="off" placeholder="Belum diisi"
                                               pattern=".*[1-9].*" data-min-rupiah="Harga satuan minimal Rp 1."
                                               class="form-control sg-angka @error($kunciH) is-invalid @enderror" data-rupiah data-harga-input
                                               aria-describedby="sub-harga-{{ $d->id }}{{ $errors->has($kunciH) ? ' ' . $gid($kunciH) : '' }}" @error($kunciH) aria-invalid="true" @enderror>
                                    </div>
                                    @error($kunciH)<p class="sg-galat" id="{{ $gid($kunciH) }}"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                                </div>
                                <p class="sg-harga-sub m-0" id="sub-harga-{{ $d->id }}"><span>Subtotal</span> <strong data-harga-sub>{{ $d->harga_satuan === null ? 'Belum dihitung' : $rp($d->subtotal) }}</strong></p>
                            </div>
                        </div>
                    @endforeach

                    <div class="sg-harga-total">
                        <div>
                            <span>Total</span>
                            <small data-harga-ket>{{ $tanpaHarga ? 'Belum lengkap: ' . $tanpaHarga . ' dari ' . $jumlahItem . ' item belum berharga' : 'Semua item sudah berharga' }}</small>
                        </div>
                        <strong data-harga-total aria-live="polite">{{ (float) $pesanan->total > 0 ? $rp($pesanan->total) : 'Belum dihitung' }}</strong>
                    </div>
                    <p class="sg-bantu mt-1"><i class="fa-solid fa-circle-info me-1" aria-hidden="true"></i>Ongkir &amp; diskon diatur admin di form SPP.</p>

                    <div class="sg-field mt-3">
                        <label class="sg-label" for="f-catatan_harga">Catatan perhitungan <span class="fw-normal sg-muted">(opsional)</span></label>
                        <textarea id="f-catatan_harga" name="catatan_harga" rows="2" maxlength="2000" style="min-height:72px"
                                  class="form-control @error('catatan_harga') is-invalid @enderror" placeholder="Mis. harga sudah termasuk laminasi, ongkir terpisah"
                                  @error('catatan_harga') aria-invalid="true" aria-describedby="g-catatan_harga" @enderror>{{ old('catatan_harga', $pesanan->catatan_harga) }}</textarea>
                        @error('catatan_harga')<p class="sg-galat" id="g-catatan_harga"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                    </div>
                    {{-- Di HP tombol ini ada di bilah bawah (selalu terjangkau jempol) --}}
                    <button type="submit" class="sg-btn sg-btn-utama sg-btn-penuh d-none d-lg-flex" style="min-height:56px" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan harga</button>
                </form>
            </section>
        @endif

        {{-- Item --}}
        <section class="sg-kartu sg-o-item" aria-labelledby="judul-item">
            <h2 class="sg-kartu-judul" id="judul-item">Item SPP ({{ $jumlahItem }})</h2>
            @forelse ($item as $d)
                <article class="sg-item-detail">
                    <div class="sg-item-detail-kepala">
                        <h3><span class="sg-muted">{{ $loop->iteration }}.</span> {{ $d->jenis_packaging }}@if ($d->ukuran_teks) · <span class="sg-angka">{{ $d->ukuran_teks }}</span>@endif</h3>
                        <span class="sg-jumlah-besar">{{ $angka($d->jumlah) }} pcs</span>
                    </div>
                    <dl class="sg-dl">
                        @if ($d->jenis_bahan || $d->gramasi)<dt>Bahan</dt><dd>{{ trim($d->jenis_bahan . ' ' . $d->gramasi) }}</dd>@endif
                        @if ($d->jenis_cetak || $d->warna_cetak)<dt>Cetak</dt><dd>{{ trim($d->jenis_cetak . ' ' . $d->warna_cetak) }}</dd>@endif
                        @if ($d->daftar_finishing)<dt>Finishing</dt><dd>{{ implode(', ', $d->daftar_finishing) }}</dd>@endif
                        @if ($d->aksesori)<dt>Tali/aksesori</dt><dd>{{ $d->aksesori }}</dd>@endif
                        @if (filled($d->catatan))<dt>Catatan</dt><dd class="sg-teks-catatan">{{ $d->catatan }}</dd>@endif
                        <dt>Harga</dt>
                        @if ($d->harga_satuan === null)
                            <dd class="sg-belum-harga">Belum dihitung</dd>
                        @else
                            <dd class="sg-angka">{{ $rp($d->harga_satuan) }} × {{ $angka($d->jumlah) }} = <strong>{{ $rp($d->subtotal) }}</strong></dd>
                        @endif
                    </dl>
                </article>
            @empty
                <p class="sg-muted m-0">Belum ada item.</p>
            @endforelse
        </section>

        {{-- Desain --}}
        <section class="sg-kartu sg-o-desain" aria-labelledby="judul-desain">
            <h2 class="sg-kartu-judul" id="judul-desain">Desain</h2>
            <p class="d-flex align-items-center gap-2 flex-wrap"><span class="sg-muted sg-kecil">Approval</span> <strong>{{ $pesanan->status_desain->label() }}</strong></p>

            @if ($file->isNotEmpty())
                <ul class="sg-galeri" data-galeri>
                    @foreach ($file as $f)
                        <li>
                            <button type="button" data-lb-indeks="{{ $loop->index }}"
                                    data-lb-src="{{ route('admin.pesanan.file.lihat', [$pesanan, $f]) }}"
                                    data-lb-unduh="{{ route('admin.pesanan.file.lihat', [$pesanan, $f, 'unduh' => 1]) }}"
                                    data-lb-nama="{{ $f->jenis->label() }} · {{ $f->nama_asli }}"
                                    @can('hapusFile', $pesanan) data-lb-hapus="{{ route('admin.pesanan.file.hapus', [$pesanan, $f]) }}" @endcan
                                    aria-label="Lihat {{ strtolower($f->jenis->label()) }} {{ $loop->iteration }} dari {{ $file->count() }}">
                                <img src="{{ route('admin.pesanan.file.lihat', [$pesanan, $f]) }}" alt="" loading="lazy">
                                <span class="sg-pratinjau-label" aria-hidden="true">{{ $f->jenis->label() }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="sg-muted">Belum ada mockup.</p>
            @endif

            @if ($pesanan->link_desain)
                <p><a href="{{ $pesanan->link_desain }}" target="_blank" rel="noopener" class="sg-btn sg-btn-sekunder"><i class="fa-brands fa-google-drive" aria-hidden="true"></i> Buka folder Google Drive <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true" style="font-size:12px"></i><span class="visually-hidden"> (tab baru)</span></a></p>
            @else
                <p class="sg-muted">Link Drive belum diisi.</p>
            @endif

            @if ($pesanan->catatan_desain)
                <h3 class="sg-label mt-3">Catatan desain</h3>
                <p class="sg-teks-catatan">{{ $pesanan->catatan_desain }}</p>
            @endif
            @if ($pesanan->catatan_admin)
                <h3 class="sg-label mt-3">Catatan SPP</h3>
                <p class="sg-teks-catatan mb-0">{{ $pesanan->catatan_admin }}</p>
            @endif
            @if ($pesanan->alamat)
                <h3 class="sg-label mt-3">Alamat pengiriman</h3>
                <p class="sg-teks-catatan mb-0">{{ $pesanan->alamat }}@if ($pesanan->kota), {{ $pesanan->kota }}@endif</p>
            @endif
        </section>

        {{-- Catatan progres --}}
        <section class="sg-kartu sg-o-catatan" aria-labelledby="judul-catatan">
            <h2 class="sg-kartu-judul" id="judul-catatan">Catatan progres</h2>
            <form method="POST" action="{{ route('admin.pesanan.catatan', $pesanan) }}" data-form-catatan data-kirim>
                @csrf
                <div class="sg-chips mb-3" role="group" aria-label="Saran catatan">
                    @foreach ($saranCatatan as $saran)
                        <button type="button" class="sg-chip-btn sg-saran" data-saran="{{ $saran }}">{{ $saran }}</button>
                    @endforeach
                </div>
                <label for="f-catatan" class="sg-label">Catatan</label>
                <textarea id="f-catatan" name="catatan" rows="3" maxlength="500" class="form-control @error('catatan') is-invalid @enderror"
                          placeholder="Tulis catatan progres…" @error('catatan') aria-invalid="true" aria-describedby="g-catatan" @enderror>{{ old('catatan') }}</textarea>
                @error('catatan')<p class="sg-galat" id="g-catatan"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                <p class="sg-hitung-sisa" data-hitung hidden aria-live="polite"></p>
                <div class="d-flex justify-content-end mt-3">
                    <button type="submit" class="sg-btn sg-btn-utama sg-btn-penuh-hp" data-teks-kirim="Menyimpan…" data-butuh-isi>Simpan catatan</button>
                </div>
            </form>
        </section>

        {{-- Riwayat --}}
        <section class="sg-kartu sg-o-riwayat" aria-labelledby="judul-riwayat">
            <h2 class="sg-kartu-judul" id="judul-riwayat">Riwayat</h2>
            @if ($riwayat->isEmpty())
                <p class="sg-muted m-0">Belum ada riwayat.</p>
            @else
                <ol class="sg-timeline">
                    @foreach ($riwayat as $r)
                        @php
                            $dari = $r->status_dari;
                            $ke = $r->status_ke;
                            // Langkah khusus alur baru: harga dihitung (→ Menunggu DP) & DP dicatat (→ Dalam Proses)
                            $hargaDihitung = $r->jenis === 'maju' && $dari === StatusProduksi::HitungHarga && $ke === StatusProduksi::MenungguDp;
                            $dpDicatat = $r->jenis === 'maju' && $dari === StatusProduksi::MenungguDp && $ke === StatusProduksi::Proses;
                            [$ikonR, $kelasR] = match (true) {
                                $hargaDihitung                => ['fa-calculator', 'sg-st-menunggu_dp'],
                                $dpDicatat                    => ['fa-coins', 'sg-st-proses'],
                                $r->jenis === 'dibuat'        => ['fa-plus', ''],
                                $r->jenis === 'maju'          => ['fa-arrow-right', 'sg-st-' . $ke->value],
                                $r->jenis === 'mundur'        => ['fa-rotate-left', 'sg-st-' . $ke->value],
                                $r->jenis === 'batal'         => ['fa-ban', 'sg-st-dibatalkan'],
                                $r->jenis === 'tidak_jadi'    => ['fa-circle-xmark', 'sg-st-tidak_jadi'],
                                $r->jenis === 'pulihkan'      => ['fa-arrow-rotate-left', 'sg-st-' . $ke->value],
                                str_starts_with((string) $r->catatan, 'Harga ')       => ['fa-calculator', ''],
                                str_starts_with((string) $r->catatan, 'Pelunasan')    => ['fa-hand-holding-dollar', ''],
                                str_starts_with((string) $r->catatan, 'Nomor SPP')    => ['fa-hashtag', ''],
                                default                       => ['fa-note-sticky', ''],
                            };
                        @endphp
                        <li @if ($loop->index >= 10) data-riwayat-lain hidden @endif>
                            <span class="sg-tl-ikon {{ $kelasR }}" aria-hidden="true"><i class="fa-solid {{ $ikonR }}"></i></span>
                            <div style="min-width:0">
                                <div class="sg-tl-meta">{{ $r->created_at->translatedFormat('j M Y, H.i') }} · {{ $r->nama_pengguna }}</div>
                                @if ($hargaDihitung)
                                    <p class="sg-tl-isi">Harga dihitung · pindah ke <strong>{{ $ke->label() }}</strong></p>
                                @elseif ($dpDicatat)
                                    <p class="sg-tl-isi">DP dicatat · masuk <strong>{{ $ke->label() }}</strong></p>
                                @else
                                    @switch($r->jenis)
                                        @case('dibuat') <p class="sg-tl-isi">SPP dibuat</p> @break
                                        @case('maju') <p class="sg-tl-isi">Status: <strong>{{ $dari->label() }}</strong> → <strong>{{ $ke->label() }}</strong></p> @break
                                        @case('mundur') <p class="sg-tl-isi">Dikembalikan ke <strong>{{ $ke->label() }}</strong></p> @break
                                        @case('batal') <p class="sg-tl-isi">SPP dibatalkan</p> @break
                                        @case('tidak_jadi') <p class="sg-tl-isi">Ditandai <strong>tidak jadi</strong></p> @break
                                        @case('pulihkan') <p class="sg-tl-isi">SPP dipulihkan ke <strong>{{ $ke->label() }}</strong></p> @break
                                        @default <p class="sg-tl-isi sg-teks-catatan">{{ $r->catatan }}</p>
                                    @endswitch
                                @endif
                                @if ($r->catatan && ! in_array($r->jenis, ['dibuat', 'catatan'], true))
                                    <p class="sg-tl-catatan">{{ $r->catatan }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if ($riwayat->count() > 10)
                    <button type="button" class="sg-btn sg-btn-sekunder sg-btn-penuh mt-3" data-tampil-riwayat>Lihat semua riwayat ({{ $riwayat->count() }})</button>
                @endif
            @endif
        </section>
    </div>

    <div class="sg-detail-samping">
        {{-- Teks SPP siap salin (admin & produksi). Hanya setelah bernomor (DP dicatat). --}}
        @if ($teksSpp)
            <section class="sg-kartu sg-o-teks" aria-labelledby="judul-teks-spp">
                <h2 class="sg-kartu-judul" id="judul-teks-spp">Teks SPP</h2>
                <p class="sg-bantu mt-0 mb-3">Salin lalu kirim sendiri ke kontak atau grup produksi.</p>
                <div class="sg-teks-spp-aksi">
                    <button type="button" class="sg-btn sg-btn-utama" data-salin-teks="#teks-spp"><i class="fa-regular fa-copy" aria-hidden="true"></i> <span data-salin-label>Salin teks</span></button>
                    <a href="{{ $linkWhatsappSpp }}" target="_blank" rel="noopener" class="sg-btn sg-btn-wa"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Buka di WhatsApp<span class="visually-hidden"> (tab baru)</span></a>
                </div>
                <div class="sg-teks-spp" id="teks-spp" tabindex="0" role="region" aria-label="Pratinjau teks SPP">{{ $teksSpp }}</div>
            </section>
        @elseif (! $diArsipBatal)
            <section class="sg-kartu sg-o-teks" aria-labelledby="judul-teks-spp">
                <h2 class="sg-kartu-judul" id="judul-teks-spp">Teks SPP</h2>
                <p class="sg-status-info m-0"><i class="fa-solid fa-lock" aria-hidden="true"></i><span>Teks SPP tersedia setelah DP dicatat dan pesanan mendapat nomor SPP.</span></p>
            </section>
        @endif

        {{-- Status produksi --}}
        <section class="sg-kartu sg-o-status" aria-labelledby="judul-status">
            <h2 class="sg-kartu-judul" id="judul-status">Status produksi</h2>
            @if ($dibatalkan)
                <div class="sg-alert sg-alert-peringatan mb-3"><i class="fa-solid fa-ban" aria-hidden="true"></i><p>SPP ini dibatalkan{{ isset($waktuStatus['dibatalkan']) ? ' pada ' . $waktuStatus['dibatalkan']->translatedFormat('j M Y') : '' }}.</p></div>
            @elseif ($tidakJadi)
                <div class="sg-alert sg-alert-peringatan mb-3">
                    <i class="fa-solid fa-circle-xmark" aria-hidden="true"></i>
                    <div>
                        <p>Customer tidak jadi memesan{{ isset($waktuStatus['tidak_jadi']) ? ' (' . $waktuStatus['tidak_jadi']->translatedFormat('j M Y') . ')' : '' }}.</p>
                        <p class="mt-1 sg-teks-catatan" style="color:var(--sg-text)"><strong>Alasan:</strong> {{ $riwayatTidakJadi?->catatan ?: 'tidak dicatat' }}</p>
                    </div>
                </div>
            @elseif ($hitungHarga)
                <p class="sg-status-info"><i class="fa-solid fa-calculator" aria-hidden="true"></i>
                    <span>Menunggu harga dihitung produksi. {{ $jumlahItem - $tanpaHarga }} dari {{ $jumlahItem }} item sudah berharga.</span></p>
            @elseif ($menungguDp)
                <p class="sg-status-info"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                    <span>{{ $bisaCatatDp ? 'Harga sudah dihitung. Catat DP saat customer membayar; SPP lalu masuk produksi dan mendapat nomor.' : 'Menunggu DP dari customer. Admin memasukkan SPP ke produksi setelah DP dicatat.' }}</span></p>
                <div class="sg-total-dp"><span>Total tagihan</span><strong>{{ $teksTotal }}</strong></div>
                @if ($teksTermasuk)<p class="sg-total-dp-ket">{{ $teksTermasuk }}</p>@endif
            @endif

            <ol class="sg-stepper">
                @foreach ($alurStatus as $s)
                    @php
                        $keStep = $loop->iteration;
                        $kelas = $urutanKini === null ? (isset($waktuStatus[$s->value]) ? 'sg-lewat' : '') : ($keStep < $urutanKini ? 'sg-lewat' : ($keStep === $urutanKini ? 'sg-kini' : ''));
                        if ($s === StatusProduksi::Selesai && $status === StatusProduksi::Selesai) { $kelas = 'sg-lewat'; }
                    @endphp
                    <li class="{{ $kelas }}" @if ($kelas === 'sg-kini') aria-current="step" @endif>
                        <span class="sg-step-titik" aria-hidden="true">@if ($kelas === 'sg-lewat')<i class="fa-solid fa-check"></i>@endif</span>
                        <span class="sg-step-label">
                            {{ $s->label() }}
                            @if ($kelas === 'sg-kini')<span class="visually-hidden"> (status sekarang)</span>@endif
                            @isset($waktuStatus[$s->value])
                                @if ($kelas !== '')
                                    <small>{{ $kelas === 'sg-kini' ? 'sejak ' : '' }}{{ $waktuStatus[$s->value]->translatedFormat('j M, H.i') }}</small>
                                @endif
                            @endisset
                        </span>
                    </li>
                @endforeach
            </ol>

            {{-- Tombol status (desktop). Di HP ada di bilah bawah. --}}
            <div class="d-none d-lg-grid gap-2">
                @if ($diArsipBatal)
                    @if ($bisaPulihkan)
                        <button type="button" class="sg-btn sg-btn-utama sg-btn-penuh" data-bs-toggle="modal" data-bs-target="#modalPulihkan"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Pulihkan SPP</button>
                    @endif
                @elseif ($menungguDp)
                    @if ($bisaCatatDp)
                        <button type="button" class="sg-btn sg-btn-utama sg-btn-penuh" data-bs-toggle="modal" data-bs-target="#modalCatatDp"><i class="fa-solid fa-coins" aria-hidden="true"></i> Catat DP &amp; masukkan ke produksi</button>
                    @endif
                @elseif ($bisaUbahStatus && ! $hitungHarga)
                    @if ($majuTerkunci)
                        <button type="button" class="sg-btn sg-btn-utama sg-btn-penuh" aria-disabled="true" aria-describedby="ket-kunci-desktop">Pindahkan ke {{ $statusBerikutnya->label() }} <i class="fa-solid fa-lock" aria-hidden="true"></i></button>
                        <p class="sg-kunci-ket" id="ket-kunci-desktop"><i class="fa-solid fa-circle-info" aria-hidden="true"></i>{{ $teksKunci }}</p>
                        @if ($bisaCatatLunas)
                            <button type="button" class="sg-btn sg-btn-sekunder sg-btn-penuh" data-bs-toggle="modal" data-bs-target="#modalLunas"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> Catat lunas</button>
                        @endif
                    @elseif ($statusBerikutnya)
                        <button type="button" class="sg-btn sg-btn-utama sg-btn-penuh" data-bs-toggle="modal" data-bs-target="#modalMaju">Pindahkan ke {{ $statusBerikutnya->label() }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    @elseif ($status === StatusProduksi::Selesai)
                        <p class="sg-muted m-0"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> SPP sudah selesai.</p>
                    @endif
                @endif
                @if (! $diArsipBatal && $bisaUbahStatus && $statusSebelumnya)
                    <button type="button" class="sg-btn sg-btn-teks" data-bs-toggle="modal" data-bs-target="#modalMundur"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembalikan ke {{ $statusSebelumnya->label() }}</button>
                @endif
            </div>

            {{-- HP: keterangan kunci pengiriman juga tampil di kartu --}}
            @if ($majuTerkunci && $bisaUbahStatus)
                <p class="sg-kunci-ket d-lg-none"><i class="fa-solid fa-lock" aria-hidden="true"></i>Ke Pengiriman terkunci. {{ $teksKunci }}</p>
            @endif
        </section>

        {{-- Pembayaran --}}
        <section class="sg-kartu sg-o-bayar sg-ringkas" aria-labelledby="judul-bayar">
            <h2 class="sg-kartu-judul" id="judul-bayar">Pembayaran</h2>
            @php $hargaBelumLengkap = ! $sudahProduksi && ! $pesanan->harga_lengkap; @endphp
            <dl>
                @if ($adaBiaya)
                    {{-- Harga belum dihitung sama sekali: jangan tampilkan "Subtotal produk Rp 0" di samping "Belum dihitung" --}}
                    @unless ($hargaBelumLengkap && (float) $pesanan->total <= 0)
                    <div><dt>Subtotal produk</dt><dd @if ($hargaBelumLengkap) class="sg-belum-harga" @endif>{{ $rp($pesanan->total) }}</dd></div>
                    @endunless
                    @if ($ongkir > 0)<div><dt>Ongkir</dt><dd>{{ $rp($ongkir) }}</dd></div>@endif
                    @if ($diskon > 0)<div><dt>Diskon</dt><dd>− {{ $rp($diskon) }}</dd></div>@endif
                @endif
                <div class="{{ $sudahProduksi ? '' : 'sg-sisa' }} {{ $adaBiaya ? 'sg-baris-total' : '' }}"><dt>{{ $adaBiaya ? 'Total tagihan' : 'Total' }}</dt><dd @if ($hargaBelumLengkap) class="sg-belum-harga" @endif>{{ $teksTotal }}</dd></div>
                @if ($sudahProduksi)
                    @php $ketDp = $ketBayar($pesanan->metode_dp, $pesanan->tanggal_dp); @endphp
                    <div><dt>DP</dt><dd>{{ $rp($pesanan->dp) }}@if ($ketDp)<small class="sg-ket-bayar">{{ $ketDp }}</small>@endif</dd></div>
                    @if ($pesanan->tanggal_lunas)
                        @php $ketLunas = $ketBayar($pesanan->metode_lunas, $pesanan->tanggal_lunas); @endphp
                        <div><dt>Pelunasan</dt><dd>{{ $rp($pesanan->nilai_pelunasan) }}@if ($ketLunas)<small class="sg-ket-bayar">{{ $ketLunas }}</small>@endif</dd></div>
                    @endif
                    <div class="sg-sisa"><dt>Sisa</dt><dd>{{ $rp($pesanan->sisa) }}</dd></div>
                @endif
            </dl>
            @if ($sudahProduksi && $pesanan->kelebihan_bayar > 0)
                <p class="sg-kunci-ket mt-1"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Kelebihan bayar {{ $rp($pesanan->kelebihan_bayar) }}.</p>
            @endif
            @if ($sudahProduksi)
                <p class="mt-2 mb-0 d-flex align-items-center gap-2 flex-wrap">
                    @include('admin.pesanan._badge-bayar', ['p' => $pesanan])
                </p>
            @elseif (! $diArsipBatal)
                <p class="sg-bantu mb-0">DP dicatat saat pesanan masuk produksi.</p>
            @endif
            @if ($pesanan->catatan_harga && ! $tampilHitungHarga)
                <h3 class="sg-label mt-3">Catatan perhitungan</h3>
                <p class="sg-teks-catatan mb-0">{{ $pesanan->catatan_harga }}</p>
            @endif
            <hr class="sg-pemisah my-3">
            <dl>
                <div><dt>Tanggal SPP</dt><dd>{{ $pesanan->tanggal_order?->translatedFormat('j M Y') }}</dd></div>
                <div><dt>Deadline</dt><dd>{{ $pesanan->deadline?->translatedFormat('j M Y') }}@if ($pesanan->label_jenis_deadline) <span class="sg-chip-kecil">{{ $pesanan->label_jenis_deadline }}</span>@endif</dd></div>
            </dl>

            @if ($bisaCatatLunas)
                <button type="button" class="sg-btn sg-btn-sekunder sg-btn-penuh mt-3" data-bs-toggle="modal" data-bs-target="#modalLunas"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> Catat lunas</button>
            @endif
            @if ($isAdmin && $sudahProduksi)
                <p class="mt-2 mb-0"><a href="{{ route('admin.pesanan.edit', $pesanan) }}#bagian-harga" class="sg-tautan-aksi"><i class="fa-solid fa-pen" aria-hidden="true" style="font-size:14px"></i> Ubah pembayaran</a></p>
            @endif
        </section>

        @if ($bisaTidakJadi || $bisaBatal || $bisaHapus)
            <section class="sg-kartu sg-o-admin" aria-labelledby="judul-admin">
                <h2 class="sg-kartu-judul" id="judul-admin">Tindakan admin</h2>
                <div class="d-grid gap-2">
                    @if ($bisaTidakJadi)
                        <button type="button" class="sg-btn sg-btn-bahaya" data-bs-toggle="modal" data-bs-target="#modalTidakJadi"><i class="fa-solid fa-circle-xmark" aria-hidden="true"></i> Tidak Jadi</button>
                    @endif
                    @if ($bisaBatal)
                        <button type="button" class="sg-btn sg-btn-bahaya" data-bs-toggle="modal" data-bs-target="#modalBatal"><i class="fa-solid fa-ban" aria-hidden="true"></i> Batalkan SPP</button>
                    @endif
                    @if ($bisaHapus)
                        <button type="button" class="sg-btn sg-btn-teks sg-bahaya" data-bs-toggle="modal" data-bs-target="#modalHapus"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Hapus SPP (salah input)</button>
                    @endif
                </div>
            </section>
        @endif
    </div>
</div>

{{-- ── Bilah aksi sticky HP & tablet ─────────────── --}}
@php
    $mundurBilah = ! $diArsipBatal && $bisaUbahStatus && $statusSebelumnya;
    $adaBilah = ($diArsipBatal && $bisaPulihkan) || $tampilHitungHarga || $menungguDp
        || (! $diArsipBatal && ! $hitungHarga && $bisaUbahStatus);
@endphp
@if ($adaBilah)
    <div class="sg-bilah sg-bilah-detail">
        @if ($diArsipBatal)
            <button type="button" class="sg-btn sg-btn-utama flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalPulihkan"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Pulihkan SPP</button>
        @elseif ($tampilHitungHarga)
            <button type="submit" form="form-harga" class="sg-btn sg-btn-utama flex-grow-1" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Simpan harga</button>
        @else
            @if ($mundurBilah)
                <button type="button" class="sg-btn sg-btn-sekunder sg-btn-ikon" style="width:56px" data-bs-toggle="modal" data-bs-target="#modalMundur" aria-label="Kembalikan ke {{ $statusSebelumnya->label() }}"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
            @endif
            @if ($menungguDp)
                @if ($bisaCatatDp)
                    <button type="button" class="sg-btn sg-btn-utama flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalCatatDp" aria-label="Catat DP dan masukkan ke produksi">
                        <i class="fa-solid fa-coins" aria-hidden="true"></i>
                        <span class="d-sm-none">Catat DP</span><span class="d-none d-sm-inline">Catat DP &amp; masukkan ke produksi</span>
                    </button>
                @else
                    <p class="sg-bilah-teks"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i> Menunggu DP dari customer</p>
                @endif
            @elseif ($majuTerkunci)
                <div class="sg-bilah-kolom">
                    <p class="sg-kunci-ket" id="ket-kunci-hp"><i class="fa-solid fa-lock" aria-hidden="true"></i>{{ $teksKunci }}</p>
                    <div class="sg-bilah-baris">
                        @if ($bisaCatatLunas)
                            <button type="button" class="sg-btn sg-btn-utama" data-bs-toggle="modal" data-bs-target="#modalLunas"><i class="fa-solid fa-hand-holding-dollar" aria-hidden="true"></i> Catat lunas</button>
                        @endif
                        <button type="button" class="sg-btn sg-btn-sekunder" aria-disabled="true" aria-describedby="ket-kunci-hp" aria-label="Ke {{ $statusBerikutnya->label() }} (terkunci)"><span class="d-sm-none">Kirim</span><span class="d-none d-sm-inline">Ke {{ $statusBerikutnya->label() }}</span> <i class="fa-solid fa-lock" aria-hidden="true"></i></button>
                    </div>
                </div>
            @elseif ($statusBerikutnya)
                {{-- Label pendek di HP kecil supaya tetap satu baris; nama aksesibel tetap lengkap --}}
                <button type="button" class="sg-btn sg-btn-utama flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalMaju" aria-label="Pindahkan ke {{ $statusBerikutnya->label() }}">
                    <span class="d-sm-none">Ke {{ $statusBerikutnya->label() }}</span><span class="d-none d-sm-inline">Pindahkan ke {{ $statusBerikutnya->label() }}</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            @else
                <p class="m-0 flex-grow-1 text-center sg-muted"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> SPP sudah selesai</p>
            @endif
        @endif
    </div>
@endif

{{-- ── Modal konfirmasi ─────────────────────────── --}}
@if (! $diArsipBatal && $bisaUbahStatus && $statusBerikutnya && ! $majuTerkunci)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalMaju', 'judul' => 'Pindahkan ke ' . $statusBerikutnya->label() . '?',
        'aksi' => route('admin.pesanan.status', $pesanan), 'ke' => $statusBerikutnya,
        'placeholder' => 'Tambahkan catatan (opsional)', 'tombol' => 'Ya, pindahkan', 'gaya' => 'sg-btn-utama',
    ])
@endif
@if ($mundurBilah)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalMundur', 'judul' => 'Kembalikan ke ' . $statusSebelumnya->label() . '?',
        'aksi' => route('admin.pesanan.status', $pesanan), 'ke' => $statusSebelumnya,
        'placeholder' => 'Kenapa dikembalikan? (dianjurkan)', 'tombol' => 'Ya, kembalikan', 'gaya' => 'sg-btn-utama',
        'keterangan' => $statusSebelumnya === StatusProduksi::HitungHarga ? 'Harga bisa direvisi lagi oleh produksi atau admin.' : null,
    ])
@endif
@if ($bisaTidakJadi)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalTidakJadi', 'judul' => 'Tandai tidak jadi?',
        'aksi' => route('admin.pesanan.tidak_jadi', $pesanan), 'ke' => StatusProduksi::TidakJadi, 'kirimStatusBaru' => false,
        'labelCatatan' => 'Alasan tidak jadi', 'placeholder' => 'Mis. customer batal karena harga', 'tombol' => 'Ya, tidak jadi', 'gaya' => 'sg-btn-bahaya-isi',
        'keterangan' => 'Pesanan pindah ke History dan bisa dipulihkan lagi oleh admin.',
    ])
@endif
@if ($bisaBatal)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalBatal', 'judul' => 'Batalkan SPP ini?',
        'aksi' => route('admin.pesanan.batal', $pesanan), 'ke' => StatusProduksi::Dibatalkan, 'kirimStatusBaru' => false,
        'labelCatatan' => 'Alasan pembatalan', 'placeholder' => 'Alasan pembatalan (opsional)', 'tombol' => 'Ya, batalkan', 'gaya' => 'sg-btn-bahaya-isi',
        'keterangan' => 'SPP pindah ke History dan bisa dipulihkan lagi oleh admin.',
    ])
@endif
@if ($bisaPulihkan)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalPulihkan', 'judul' => 'Pulihkan SPP ini?',
        'aksi' => route('admin.pesanan.pulihkan', $pesanan), 'ke' => null,
        'placeholder' => 'Catatan (opsional)', 'tombol' => 'Ya, pulihkan', 'gaya' => 'sg-btn-utama',
        'keterangan' => $tidakJadi ? 'Pesanan kembali ke status sebelum ditandai tidak jadi.' : 'SPP kembali ke status terakhir sebelum dibatalkan.',
    ])
@endif

{{-- Catat DP & masukkan ke produksi (admin, status Menunggu DP). Nomor SPP diberikan di sini. --}}
@if ($bisaCatatDp)
    @php
        $nomorLama = $pesanan->nomor_spp;
        $tampilKonfNomor = $errors->has('konfirmasi_nomor') || old('konfirmasi_nomor');
        $dpLama = old('dp', '');
    @endphp
    <div class="modal fade modal-sheet" id="modalCatatDp" tabindex="-1" aria-labelledby="modalCatatDpJudul" aria-hidden="true" @if ($galatDp) data-buka-otomatis @endif>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.pesanan.dp', $pesanan) }}" data-kirim data-form-dp data-total="{{ (int) $pesanan->total_tagihan }}" novalidate>
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title" id="modalCatatDpJudul">Catat DP &amp; masukkan ke produksi</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="m-0 sg-muted">{{ $pesanan->kode_tampil }} · {{ $pesanan->nama_customer }}</p>
                    <div class="sg-total-dp"><span>Total tagihan</span><strong>{{ $rp($pesanan->total_tagihan) }}</strong></div>
                    @if ($teksTermasuk)<p class="sg-total-dp-ket">{{ $teksTermasuk }}</p>@endif
                    @error('status')<p class="sg-galat mb-3"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror

                    <div class="sg-field">
                        <label class="sg-label" for="f-dp">DP diterima <span class="sg-wajib" aria-hidden="true">*</span></label>
                        <div class="sg-imbuh sg-imbuh-depan">
                            <span class="sg-imbuh-teks" aria-hidden="true">Rp</span>
                            <input type="text" inputmode="numeric" id="f-dp" name="dp" value="{{ $dpLama }}" autocomplete="off" required placeholder="0"
                                   class="form-control sg-angka @error('dp') is-invalid @enderror" data-rupiah data-dp-input
                                   aria-describedby="peringatan-dp{{ $errors->has('dp') ? ' g-dp' : '' }}" @error('dp') aria-invalid="true" @enderror>
                        </div>
                        @error('dp')<p class="sg-galat" id="g-dp"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                        <p class="sg-galat" id="peringatan-dp" data-peringatan-dp hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span></span></p>
                    </div>

                    @include('admin.pesanan._metode-bayar', ['nama' => 'metode_dp', 'legend' => 'Metode pembayaran', 'wajib' => true])

                    <div class="sg-field">
                        <label class="sg-label" for="f-tanggal_dp">Tanggal DP</label>
                        <input type="date" id="f-tanggal_dp" name="tanggal_dp" value="{{ old('tanggal_dp', today()->toDateString()) }}"
                               min="{{ $pesanan->tanggal_order?->toDateString() }}" max="{{ today()->toDateString() }}"
                               class="form-control @error('tanggal_dp') is-invalid @enderror" @error('tanggal_dp') aria-invalid="true" aria-describedby="g-tanggal_dp" @enderror>
                        @error('tanggal_dp')<p class="sg-galat" id="g-tanggal_dp"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                    </div>

                    <div class="sg-field">
                        <label class="sg-label" for="f-nomor_spp">Nomor SPP <span class="sg-wajib" aria-hidden="true">*</span></label>
                        <div class="sg-imbuh sg-imbuh-depan sg-nomor-spp-isian">
                            <span class="sg-imbuh-teks" aria-hidden="true">#</span>
                            <input type="text" inputmode="numeric" id="f-nomor_spp" name="nomor_spp" value="{{ old('nomor_spp', $saranNomor) }}" maxlength="9" required autocomplete="off"
                                   class="form-control sg-angka @error('nomor_spp') is-invalid @enderror" data-nomor-dp data-saran-nomor="{{ $saranNomorBaru }}" data-nomor-lama="{{ $nomorLama }}"
                                   aria-describedby="bantu-nomor-dp{{ $errors->has('nomor_spp') ? ' g-nomor_spp' : '' }}" @error('nomor_spp') aria-invalid="true" @enderror>
                        </div>
                        @error('nomor_spp')<p class="sg-galat" id="g-nomor_spp"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                        <p class="sg-bantu" id="bantu-nomor-dp">
                            @if ($nomorLama)
                                Nomor lama SPP ini <strong class="sg-angka">#{{ $nomorLama }}</strong>. Saran nomor baru: #{{ $saranNomorBaru }}.
                            @else
                                Saran nomor berikutnya: <strong class="sg-angka">#{{ $saranNomorBaru }}</strong>. Boleh diganti bila melanjutkan nomor buku.
                            @endif
                        </p>
                        <div class="sg-alert sg-alert-peringatan sg-konfirmasi-nomor" data-konfirmasi-nomor="konfirmasi_nomor" @unless ($tampilKonfNomor) hidden @endunless>
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                            <div>
                                <p class="mb-2" id="pesan-konfirmasi_nomor" data-pesan-konfirmasi>{{ $errors->first('konfirmasi_nomor') }}</p>
                                <label class="sg-centang" for="f-konfirmasi_nomor">
                                    <input type="checkbox" id="f-konfirmasi_nomor" name="konfirmasi_nomor" value="1" class="form-check-input" @checked(old('konfirmasi_nomor'))
                                           aria-describedby="pesan-konfirmasi_nomor" @error('konfirmasi_nomor') aria-invalid="true" @enderror>
                                    <span>Ya, saya yakin pakai nomor ini</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sg-btn sg-btn-sekunder" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…">Catat DP &amp; masukkan</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Catat lunas (admin, SPP yang sudah produksi) --}}
@if ($bisaCatatLunas)
    <div class="modal fade modal-sheet" id="modalLunas" tabindex="-1" aria-labelledby="modalLunasJudul" aria-hidden="true" @if ($galatLunas) data-buka-otomatis @endif>
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.pesanan.lunas', $pesanan) }}" data-kirim novalidate>
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title" id="modalLunasJudul">Catat pelunasan</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="m-0 sg-muted">{{ $pesanan->kode_tampil }} · {{ $pesanan->nama_customer }}</p>
                    <div class="sg-total-dp"><span>Sisa tagihan</span><strong>{{ $rp($pesanan->sisa) }}</strong></div>
                    @include('admin.pesanan._metode-bayar', ['nama' => 'metode_lunas', 'legend' => 'Metode pembayaran', 'wajib' => true])
                    <div class="sg-field">
                        <label for="f-tanggal_lunas" class="sg-label">Tanggal lunas</label>
                        <input type="date" id="f-tanggal_lunas" name="tanggal_lunas" value="{{ old('tanggal_lunas', today()->toDateString()) }}"
                               min="{{ $pesanan->tanggal_order?->toDateString() }}" max="{{ today()->toDateString() }}"
                               class="form-control @error('tanggal_lunas') is-invalid @enderror" @error('tanggal_lunas') aria-invalid="true" aria-describedby="g-tanggal_lunas" @enderror>
                        @error('tanggal_lunas')<p class="sg-galat" id="g-tanggal_lunas"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                    </div>
                    @if ($menungguPelunasan)
                        <p class="sg-bantu mb-2">Setelah lunas, SPP bisa dipindahkan ke Pengiriman.</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="sg-btn sg-btn-sekunder" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="sg-btn sg-btn-utama" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Catat lunas</button>
                </div>
            </form>
        </div>
    </div>
@endif

@if ($bisaHapus)
    <div class="modal fade modal-sheet" id="modalHapus" tabindex="-1" aria-labelledby="modalHapusJudul" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.pesanan.destroy', $pesanan) }}" data-kirim>
                @csrf @method('DELETE')
                <div class="modal-header">
                    <h2 class="modal-title" id="modalHapusJudul">Hapus {{ $pesanan->kode_tampil }}?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="m-0">Gunakan hanya untuk SPP <strong>salah input</strong>. Pesanan yang batal sebaiknya ditandai {{ ($status->sebelumProduksi() || $tidakJadi) ? 'tidak jadi' : 'dibatalkan' }}, bukan dihapus, supaya riwayatnya tetap ada.@if ($pesanan->nomor_spp) Nomornya tidak dipakai ulang.@endif</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="sg-btn sg-btn-sekunder" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="sg-btn sg-btn-bahaya-isi" data-teks-kirim="Menghapus…">Ya, hapus</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Lightbox galeri --}}
@if ($file->isNotEmpty())
    <div class="modal fade sg-lightbox" id="lightbox" tabindex="-1" aria-labelledby="lightboxJudul" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content">
                <div class="sg-lb-atas">
                    <h2 class="m-0 fs-6 text-white text-truncate" id="lightboxJudul" data-lb-judul>Mockup</h2>
                    <button type="button" class="sg-lb-btn" data-bs-dismiss="modal" aria-label="Tutup"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                </div>
                <div class="modal-body" data-lb-tubuh>
                    <img src="" alt="" data-lb-gambar>
                    <button type="button" class="sg-lb-btn sg-lb-nav sg-kiri" data-lb-geser="-1" aria-label="Gambar sebelumnya"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <button type="button" class="sg-lb-btn sg-lb-nav sg-kanan" data-lb-geser="1" aria-label="Gambar berikutnya"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                </div>
                <div class="sg-lb-bawah">
                    <span class="sg-lb-hitung" data-lb-hitung aria-live="polite">1 / 1</span>
                    <a href="#" class="sg-lb-btn" target="_blank" rel="noopener" data-lb-buka><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> Buka asli</a>
                    <a href="#" class="sg-lb-btn" data-lb-unduh-link><i class="fa-solid fa-download" aria-hidden="true"></i> Unduh</a>
                    @can('hapusFile', $pesanan)
                        <form method="POST" action="" data-lb-form-hapus>
                            @csrf @method('DELETE')
                            <button type="button" class="sg-lb-btn" data-lb-hapus-tombol><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Hapus gambar</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

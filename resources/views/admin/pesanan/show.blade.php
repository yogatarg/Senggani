@extends('admin.layouts.pesanan')

@section('title', $pesanan->nomor_spp_teks)
@section('kembali', route('admin.pesanan.index'))
@section('tanpa_bottomnav', '1')

@php
    use App\Enums\StatusProduksi;

    $rp = fn ($n) => 'Rp ' . number_format((float) $n, 0, ',', '.');
    $angka = fn ($n) => number_format((float) $n, 0, ',', '.');
    $status = $pesanan->status_produksi;
    $dibatalkan = $status === \App\Enums\StatusProduksi::Dibatalkan;
    $urutanKini = $status->urutan();

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
    $bisaUbahStatus = \Illuminate\Support\Facades\Gate::allows('ubahStatus', $pesanan);
    $bisaBatal = ! $dibatalkan && $status->bisaDibatalkan() && \Illuminate\Support\Facades\Gate::allows('batalkan', $pesanan);
    $bisaHapus = \Illuminate\Support\Facades\Gate::allows('delete', $pesanan);
    $bisaPulihkan = $dibatalkan && \Illuminate\Support\Facades\Gate::allows('pulihkan', $pesanan);
    $judulOrder = $pesanan->nomor_spp_teks . ' · ' . $pesanan->nama_customer;
@endphp

@section('content')
<a href="{{ route('admin.pesanan.index') }}" class="sg-remah d-none d-lg-inline-flex"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Pesanan</a>

<header class="sg-detail-kepala">
    <div>
        <h1 class="sg-no-order">{{ $pesanan->nomor_spp_teks }}</h1>
        <p class="sg-nama-cust">{{ $pesanan->nama_customer }}</p>
        @if ($pesanan->nama_brand)
            <p class="sg-brand">{{ $pesanan->nama_brand }}</p>
        @endif
        <p class="sg-kontak sg-angka">
            @if ($pesanan->customer && \Illuminate\Support\Facades\Gate::allows('kelola-customer'))
                <a href="{{ route('admin.customer.show', $pesanan->customer) }}" class="sg-kode-customer" aria-label="Lihat customer {{ $pesanan->customer->kode }}"><i class="fa-solid fa-address-book" aria-hidden="true"></i>{{ $pesanan->customer->kode }}</a>
                <span aria-hidden="true">·</span>
            @endif
            {{ $waTampil }}@if ($pesanan->kota) · {{ $pesanan->kota }}@endif
        </p>
        <div class="sg-lencana">
            @include('admin.pesanan._badge-status', ['status' => $status])
            @include('admin.pesanan._badge-bayar', ['p' => $pesanan])
            @include('admin.pesanan._deadline', ['p' => $pesanan])
        </div>
    </div>
    @can('update', $pesanan)
        <div class="sg-lencana-aksi">
            <a href="{{ $pesanan->link_whatsapp }}" target="_blank" rel="noopener" class="sg-btn sg-btn-wa"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp Customer</a>
            <a href="{{ route('admin.pesanan.edit', $pesanan) }}" class="sg-btn sg-btn-sekunder" style="min-height:44px"><i class="fa-solid fa-pen" aria-hidden="true"></i> Edit</a>
            <button type="button" class="sg-btn sg-btn-sekunder d-xl-none" style="min-height:44px" data-salin-teks="#teks-spp"><i class="fa-regular fa-copy" aria-hidden="true"></i> <span data-salin-label>Salin teks SPP</span></button>
        </div>
    @else
        <div class="sg-lencana-aksi d-xl-none">
            <button type="button" class="sg-btn sg-btn-sekunder" style="min-height:44px" data-salin-teks="#teks-spp"><i class="fa-regular fa-copy" aria-hidden="true"></i> <span data-salin-label>Salin teks SPP</span></button>
        </div>
    @endcan
</header>

<div class="sg-detail">
    <div class="sg-detail-utama">
        {{-- Item --}}
        <section class="sg-kartu sg-o-item" aria-labelledby="judul-item">
            <h2 class="sg-kartu-judul" id="judul-item">Item SPP ({{ $pesanan->detailPesanan->count() }})</h2>
            @forelse ($pesanan->detailPesanan as $d)
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
                        <dt>Harga</dt><dd class="sg-angka">{{ $rp($d->harga_satuan) }} × {{ $angka($d->jumlah) }} = <strong>{{ $rp($d->subtotal) }}</strong></dd>
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
                            [$ikonR, $kelasR] = match ($r->jenis) {
                                'dibuat'   => ['fa-plus', ''],
                                'maju'     => ['fa-arrow-right', 'sg-st-' . $r->status_ke->value],
                                'mundur'   => ['fa-rotate-left', 'sg-st-' . $r->status_ke->value],
                                'batal'    => ['fa-ban', 'sg-st-dibatalkan'],
                                'pulihkan' => ['fa-arrow-rotate-left', 'sg-st-' . $r->status_ke->value],
                                default    => ['fa-note-sticky', ''],
                            };
                        @endphp
                        <li @if ($loop->index >= 10) data-riwayat-lain hidden @endif>
                            <span class="sg-tl-ikon {{ $kelasR }}" aria-hidden="true"><i class="fa-solid {{ $ikonR }}"></i></span>
                            <div style="min-width:0">
                                <div class="sg-tl-meta">{{ $r->created_at->translatedFormat('j M Y, H.i') }} · {{ $r->nama_pengguna }}</div>
                                @switch($r->jenis)
                                    @case('dibuat') <p class="sg-tl-isi">SPP dibuat</p> @break
                                    @case('maju') <p class="sg-tl-isi">Status: <strong>{{ $r->status_dari->label() }}</strong> → <strong>{{ $r->status_ke->label() }}</strong></p> @break
                                    @case('mundur') <p class="sg-tl-isi">Dikembalikan ke <strong>{{ $r->status_ke->label() }}</strong></p> @break
                                    @case('batal') <p class="sg-tl-isi">SPP dibatalkan</p> @break
                                    @case('pulihkan') <p class="sg-tl-isi">SPP dipulihkan ke <strong>{{ $r->status_ke->label() }}</strong></p> @break
                                    @default <p class="sg-tl-isi sg-teks-catatan">{{ $r->catatan }}</p>
                                @endswitch
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
        {{-- Teks SPP siap salin (admin & produksi). Sistem tidak mengirim apa pun; admin memilih sendiri kontak/grup WA. --}}
        <section class="sg-kartu sg-o-teks" aria-labelledby="judul-teks-spp">
            <h2 class="sg-kartu-judul" id="judul-teks-spp">Teks SPP</h2>
            <p class="sg-bantu mt-0 mb-3">Salin lalu kirim sendiri ke kontak atau grup produksi.</p>
            <div class="sg-teks-spp-aksi">
                <button type="button" class="sg-btn sg-btn-utama" data-salin-teks="#teks-spp"><i class="fa-regular fa-copy" aria-hidden="true"></i> <span data-salin-label>Salin teks</span></button>
                <a href="{{ $linkWhatsappSpp }}" target="_blank" rel="noopener" class="sg-btn sg-btn-wa"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> Buka di WhatsApp<span class="visually-hidden"> (tab baru)</span></a>
            </div>
            <div class="sg-teks-spp" id="teks-spp" tabindex="0" role="region" aria-label="Pratinjau teks SPP">{{ $teksSpp }}</div>
        </section>

        {{-- Status produksi --}}
        <section class="sg-kartu sg-o-status" aria-labelledby="judul-status">
            <h2 class="sg-kartu-judul" id="judul-status">Status produksi</h2>
            @if ($dibatalkan)
                <div class="sg-alert sg-alert-peringatan mb-3"><i class="fa-solid fa-ban" aria-hidden="true"></i><p>SPP ini dibatalkan{{ isset($waktuStatus['dibatalkan']) ? ' pada ' . $waktuStatus['dibatalkan']->translatedFormat('j M Y') : '' }}.</p></div>
            @endif
            <ol class="sg-stepper">
                @foreach ($alurStatus as $s)
                    @php
                        $ke = $loop->iteration;
                        $kelas = $urutanKini === null ? (isset($waktuStatus[$s->value]) ? 'sg-lewat' : '') : ($ke < $urutanKini ? 'sg-lewat' : ($ke === $urutanKini ? 'sg-kini' : ''));
                        if ($s === \App\Enums\StatusProduksi::Selesai && $status === \App\Enums\StatusProduksi::Selesai) { $kelas = 'sg-lewat'; }
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

            <div class="d-none d-lg-grid gap-2">
                @if ($dibatalkan)
                    @if ($bisaPulihkan)
                        <button type="button" class="sg-btn sg-btn-utama sg-btn-penuh" data-bs-toggle="modal" data-bs-target="#modalPulihkan"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Pulihkan SPP</button>
                    @endif
                @elseif ($bisaUbahStatus)
                    @if ($statusBerikutnya)
                        <button type="button" class="sg-btn sg-btn-utama sg-btn-penuh" data-bs-toggle="modal" data-bs-target="#modalMaju">Pindahkan ke {{ $statusBerikutnya->label() }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></button>
                    @else
                        <p class="sg-muted m-0"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> SPP sudah selesai.</p>
                    @endif
                    @if ($statusSebelumnya)
                        <button type="button" class="sg-btn sg-btn-teks" data-bs-toggle="modal" data-bs-target="#modalMundur"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Kembalikan ke {{ $statusSebelumnya->label() }}</button>
                    @endif
                @endif
            </div>
        </section>

        {{-- Pembayaran --}}
        <section class="sg-kartu sg-o-bayar sg-ringkas" aria-labelledby="judul-bayar">
            <h2 class="sg-kartu-judul" id="judul-bayar">Pembayaran</h2>
            <dl>
                <div><dt>Total</dt><dd>{{ $rp($pesanan->total) }}</dd></div>
                <div><dt>DP</dt><dd>{{ $rp($pesanan->dp) }}</dd></div>
                <div class="sg-sisa"><dt>Sisa</dt><dd>{{ $rp($pesanan->sisa) }}</dd></div>
            </dl>
            <p class="mt-2 mb-0 d-flex align-items-center gap-2 flex-wrap">
                @include('admin.pesanan._badge-bayar', ['p' => $pesanan])
                @if ($pesanan->tanggal_lunas)<span class="sg-muted sg-kecil">lunas {{ $pesanan->tanggal_lunas->translatedFormat('j M Y') }}</span>@endif
            </p>
            <hr class="sg-pemisah my-3">
            <dl>
                <div><dt>Tanggal SPP</dt><dd>{{ $pesanan->tanggal_order?->translatedFormat('j M Y') }}</dd></div>
                <div><dt>Deadline</dt><dd>{{ $pesanan->deadline?->translatedFormat('j M Y') }}@if ($pesanan->label_jenis_deadline) <span class="sg-chip-kecil">{{ $pesanan->label_jenis_deadline }}</span>@endif</dd></div>
            </dl>

            @if (! $pesanan->tanggal_lunas && (float) $pesanan->total > 0)
                @can('catatLunas', $pesanan)
                    <form method="POST" action="{{ route('admin.pesanan.lunas', $pesanan) }}" class="mt-3" data-kirim>
                        @csrf
                        <label for="f-tanggal_lunas" class="sg-label">Tanggal lunas</label>
                        <div class="d-flex gap-2 flex-wrap">
                            <input type="date" id="f-tanggal_lunas" name="tanggal_lunas" value="{{ old('tanggal_lunas', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="form-control flex-grow-1 @error('tanggal_lunas') is-invalid @enderror" style="width:auto">
                            <button type="submit" class="sg-btn sg-btn-sekunder" data-teks-kirim="Menyimpan…"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Catat lunas</button>
                        </div>
                        @error('tanggal_lunas')<p class="sg-galat"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>{{ $message }}</p>@enderror
                    </form>
                @endcan
            @endif
            @can('update', $pesanan)
                <p class="mt-3 mb-0"><a href="{{ route('admin.pesanan.edit', $pesanan) }}#bagian-harga">Ubah pembayaran</a></p>
            @endcan
        </section>

        @if ($bisaBatal || $bisaHapus)
            <section class="sg-kartu sg-o-admin" aria-labelledby="judul-admin">
                <h2 class="sg-kartu-judul" id="judul-admin">Tindakan admin</h2>
                <div class="d-grid gap-2">
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

{{-- Bilah aksi sticky HP & tablet --}}
@if (($dibatalkan && $bisaPulihkan) || (! $dibatalkan && $bisaUbahStatus))
    <div class="sg-bilah sg-bilah-detail">
        @if ($dibatalkan)
            <button type="button" class="sg-btn sg-btn-utama flex-grow-1" data-bs-toggle="modal" data-bs-target="#modalPulihkan"><i class="fa-solid fa-arrow-rotate-left" aria-hidden="true"></i> Pulihkan SPP</button>
        @else
            @if ($statusSebelumnya)
                <button type="button" class="sg-btn sg-btn-sekunder sg-btn-ikon" style="width:56px" data-bs-toggle="modal" data-bs-target="#modalMundur" aria-label="Kembalikan ke {{ $statusSebelumnya->label() }}"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
            @endif
            @if ($statusBerikutnya)
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
@if (! $dibatalkan && $bisaUbahStatus && $statusBerikutnya)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalMaju', 'judul' => 'Pindahkan ke ' . $statusBerikutnya->label() . '?',
        'aksi' => route('admin.pesanan.status', $pesanan), 'ke' => $statusBerikutnya,
        'placeholder' => 'Tambahkan catatan (opsional)', 'tombol' => 'Ya, pindahkan', 'gaya' => 'sg-btn-utama',
    ])
@endif
@if (! $dibatalkan && $bisaUbahStatus && $statusSebelumnya)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalMundur', 'judul' => 'Kembalikan ke ' . $statusSebelumnya->label() . '?',
        'aksi' => route('admin.pesanan.status', $pesanan), 'ke' => $statusSebelumnya,
        'placeholder' => 'Kenapa dikembalikan? (dianjurkan)', 'tombol' => 'Ya, kembalikan', 'gaya' => 'sg-btn-utama',
    ])
@endif
@if ($bisaBatal)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalBatal', 'judul' => 'Batalkan SPP ini?',
        'aksi' => route('admin.pesanan.batal', $pesanan), 'ke' => \App\Enums\StatusProduksi::Dibatalkan,
        'placeholder' => 'Alasan pembatalan (opsional)', 'tombol' => 'Ya, batalkan', 'gaya' => 'sg-btn-bahaya-isi',
        'keterangan' => 'SPP pindah ke History dan bisa dipulihkan lagi oleh admin.',
    ])
@endif
@if ($bisaPulihkan)
    @include('admin.pesanan._modal-status', [
        'idModal' => 'modalPulihkan', 'judul' => 'Pulihkan SPP ini?',
        'aksi' => route('admin.pesanan.pulihkan', $pesanan), 'ke' => null,
        'placeholder' => 'Catatan (opsional)', 'tombol' => 'Ya, pulihkan', 'gaya' => 'sg-btn-utama',
        'keterangan' => 'SPP kembali ke status terakhir sebelum dibatalkan.',
    ])
@endif
@if ($bisaHapus)
    <div class="modal fade modal-sheet" id="modalHapus" tabindex="-1" aria-labelledby="modalHapusJudul" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('admin.pesanan.destroy', $pesanan) }}" data-kirim>
                @csrf @method('DELETE')
                <div class="modal-header">
                    <h2 class="modal-title" id="modalHapusJudul">Hapus {{ $pesanan->nomor_spp_teks }}?</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <p class="m-0">Gunakan hanya untuk SPP <strong>salah input</strong>. SPP yang batal sebaiknya dibatalkan, bukan dihapus, supaya riwayatnya tetap ada. Nomornya tidak dipakai ulang.</p>
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

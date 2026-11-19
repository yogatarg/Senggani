@extends('admin.layouts.pesanan')

@section('title', 'Pesanan')
{{-- Banner "pesanan lewat deadline" dirender layout (butuh \$notifDeadline) --}}
@section('banner_lewat', '1')

@php
    $infoTab = [
        'semua'            => ['ikon' => 'fa-layer-group',       'pendek' => 'Semua'],
        'hitung_harga'     => ['ikon' => 'fa-calculator',        'pendek' => 'Harga'],
        'menunggu_dp'      => ['ikon' => 'fa-hourglass-half',    'pendek' => 'DP'],
        'proses'           => ['ikon' => 'fa-print',             'pendek' => 'Proses'],
        'selesai_produksi' => ['ikon' => 'fa-circle-check',      'pendek' => 'Pesanan Selesai'],
        'pengiriman'       => ['ikon' => 'fa-truck',             'pendek' => 'Kirim'],
        'history'          => ['ikon' => 'fa-clock-rotate-left', 'pendek' => 'History'],
    ];
    // Label tab tampil (istilah SPP); kunci tetap dari backend
    $labelTab = [
        'semua'            => 'Semua',
        'hitung_harga'     => 'Hitung Harga',
        'menunggu_dp'      => 'Menunggu DP',
        'proses'           => 'Dalam Proses',
        'selesai_produksi' => 'Pesanan Selesai',
        'pengiriman'       => 'Pengiriman',
        'history'          => 'History',
    ];
    $labelUrut = ['terbaru' => 'SPP terbaru'];
    $kosong = [
        'semua'            => ['fa-box-open', 'Belum ada SPP', 'SPP baru yang ditambahkan admin akan muncul di sini.'],
        'hitung_harga'     => ['fa-calculator', 'Tidak ada pesanan yang menunggu dihitung harganya', 'Pesanan baru yang belum berharga muncul di sini untuk dihitung produksi.'],
        'menunggu_dp'      => ['fa-hourglass-half', 'Tidak ada pesanan yang menunggu DP', 'Pesanan yang harganya sudah dihitung menunggu DP customer di sini.'],
        'proses'           => ['fa-print', 'Tidak ada yang sedang dikerjakan', 'SPP masuk ke sini setelah DP dicatat admin.'],
        'selesai_produksi' => ['fa-circle-check', 'Belum ada pesanan selesai', 'SPP yang selesai diproduksi menunggu dikirim di sini.'],
        'pengiriman'       => ['fa-truck', 'Tidak ada SPP dalam pengiriman', 'SPP yang sedang diantar atau dikirim muncul di sini.'],
        'history'          => ['fa-clock-rotate-left', 'Riwayat masih kosong', 'SPP yang selesai, tidak jadi, atau dibatalkan tersimpan di sini.'],
    ];
    $angka = fn ($n) => number_format((int) $n, 0, ',', '.');
    $mencari = $q !== '';
@endphp

@section('content')
<div class="sg-kepala">
    <div>
        <h1 class="d-none d-lg-block">Pesanan</h1>
        <h1 class="visually-hidden d-lg-none">Pesanan</h1>
        <p class="sg-kepala-sub" id="status-segar">
            <span data-teks-segar>Diperbarui <time datetime="{{ now()->toIso8601String() }}">{{ now()->format('H.i') }}</time></span>
            <a href="{{ request()->fullUrl() }}" class="sg-segar" aria-label="Muat ulang"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></a>
        </p>
    </div>
    @can('create', \App\Models\Pesanan::class)
        <div class="sg-kepala-aksi d-none d-lg-flex">
            <a href="{{ route('admin.pesanan.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah SPP</a>
        </div>
    @endcan
</div>

<div class="sg-alert sg-alert-info" id="ada-perubahan" role="status" hidden>
    <i class="fa-solid fa-rotate" aria-hidden="true"></i>
    <p>Ada perubahan SPP. <a href="{{ request()->fullUrl() }}" class="sg-alert-aksi">Muat ulang</a></p>
</div>

<nav class="sg-tab-wadah" aria-label="Tab status pesanan">
    <ul class="sg-tab" data-tab-gulir>
        @foreach ($daftarTab as $kunci => $label)
            @php $aktif = $tab === $kunci && ! $mencari; $jml = $ringkasan['jumlah'][$kunci] ?? 0; @endphp
            <li>
                <a href="{{ route('admin.pesanan.index', ['tab' => $kunci, 'urut' => $urut]) }}" data-tab-link
                   @if ($aktif) aria-current="page" @endif>
                    <i class="fa-solid {{ $infoTab[$kunci]['ikon'] }}" aria-hidden="true"></i>
                    <span class="d-md-none">{{ $infoTab[$kunci]['pendek'] }}</span>
                    <span class="d-none d-md-inline">{{ $labelTab[$kunci] ?? $label }}</span>
                    @if ($kunci !== 'history')
                        <span class="sg-tab-angka {{ $jml === 0 ? 'sg-nol' : '' }}" data-jumlah-tab="{{ $kunci }}">{{ $jml }}<span class="visually-hidden"> SPP</span></span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>

<form method="GET" action="{{ route('admin.pesanan.index') }}" class="sg-alat" role="search" data-form-cari>
    <input type="hidden" name="tab" value="{{ $tab }}">
    <div class="sg-cari">
        <label for="cari" class="visually-hidden">Cari SPP</label>
        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
        <input type="search" id="cari" name="q" value="{{ $q }}" class="form-control" maxlength="100"
               placeholder="Cari customer, brand, atau nomor SPP" enterkeyhint="search" autocomplete="off">
        <button type="button" class="sg-cari-hapus" data-hapus-cari aria-label="Hapus isi pencarian" @if (! $mencari) hidden @endif>
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
        </button>
    </div>
    <div class="sg-urut">
        <label for="urut">Urut</label>
        <select id="urut" name="urut" class="form-select" data-kirim-otomatis>
            @foreach ($daftarUrut as $kunci => $label)
                <option value="{{ $kunci }}" @selected($urut === $kunci)>{{ $labelUrut[$kunci] ?? $label }}</option>
            @endforeach
        </select>
    </div>
</form>

@if ($mencari)
    <p class="sg-info-baris" role="status">
        <span><strong>{{ $angka($pesanan->total()) }}</strong> SPP cocok dengan “{{ $q }}” <span class="sg-muted">(semua status, termasuk History)</span></span>
        <span aria-hidden="true">·</span>
        <a href="{{ route('admin.pesanan.index', ['tab' => $tab, 'urut' => $urut]) }}">Hapus pencarian</a>
    </p>
@endif

@if ($pesanan->isEmpty())
    @php [$ikonK, $judulK, $teksK] = $mencari
        ? ['fa-magnifying-glass', 'Tidak ada SPP untuk “' . $q . '”', 'Periksa ejaan, atau cari dengan nomor SPP (misalnya 176).']
        : $kosong[$tab]; @endphp
    <div class="sg-kosong">
        <span class="sg-kosong-ikon"><i class="fa-solid {{ $ikonK }}" aria-hidden="true"></i></span>
        <h2>{{ $judulK }}</h2>
        <p>{{ $teksK }}</p>
        @if ($mencari)
            <a href="{{ route('admin.pesanan.index', ['tab' => $tab, 'urut' => $urut]) }}" class="sg-btn sg-btn-sekunder">Hapus pencarian</a>
        @elseif ($tab === 'semua')
            @can('create', \App\Models\Pesanan::class)
                <a href="{{ route('admin.pesanan.create') }}" class="sg-btn sg-btn-utama"><i class="fa-solid fa-plus" aria-hidden="true"></i> Tambah SPP</a>
            @endcan
        @elseif ($tab === 'proses')
            <a href="{{ route('admin.pesanan.index', ['tab' => 'menunggu_dp']) }}" class="sg-btn sg-btn-sekunder">Lihat Menunggu DP</a>
        @endif
    </div>
@else
    <div class="sg-daftar" id="daftar-pesanan" data-daftar>
        @foreach ($pesanan as $p)
            @include('admin.pesanan._kartu', ['p' => $p])
        @endforeach
    </div>

    @if ($pesanan->hasPages())
        <div class="sg-muat" data-muat>
            @unless ($pesanan->onFirstPage())
                <a href="{{ $pesanan->previousPageUrl() }}" class="sg-btn sg-btn-sekunder"><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> SPP sebelumnya</a>
            @endunless
            @if ($pesanan->hasMorePages())
                <a href="{{ $pesanan->nextPageUrl() }}" class="sg-btn sg-btn-sekunder" data-muat-lagi>Muat lebih banyak</a>
            @endif
        </div>
        <p class="sg-muted sg-kecil text-center mt-2 mb-0" data-muat-info>
            Menampilkan {{ $angka($pesanan->firstItem()) }}–<span data-muat-akhir>{{ $angka($pesanan->lastItem()) }}</span> dari {{ $angka($pesanan->total()) }} SPP
        </p>
    @endif
@endif

<div hidden
     data-segar-otomatis
     data-url="{{ route('admin.pesanan.jumlah') }}"
     data-penanda="{{ $ringkasan['penanda'] }}"></div>
@endsection

{{--
    Layout modul Pesanan (admin & produksi). Bootstrap 5.3 + Font Awesome 6.4 + Poppins, tanpa AdminLTE/jQuery.
    Acuan: catatan desain 2026-11-18-desain-pesanan-senggani.md.

    Section opsional dari halaman:
    - title        : judul tab browser & top bar HP
    - judul_bar    : judul top bar HP (bila berbeda dari title)
    - kembali      : URL tombol kembali di top bar HP (sub-halaman)
    - tanpa_bottomnav : isi apa saja untuk menyembunyikan bottom nav (halaman dengan bilah aksi sticky)
--}}
@php
    $namaPengguna = (string) session('admin_nama', 'Pengguna');
    $peranProduksi = session('admin_peran') === 'produksi';
    $inisial = collect(preg_split('/\s+/', trim($namaPengguna)) ?: [])
        ->filter()->take(2)->map(fn ($k) => mb_strtoupper(mb_substr($k, 0, 1)))->implode('') ?: 'S';

    $diPesanan  = request()->routeIs('admin.pesanan.*') && ! request()->routeIs('admin.pesanan.create');
    $diTambah   = request()->routeIs('admin.pesanan.create');
    $diPengguna = request()->routeIs('admin.pengguna.*');
    $diCustomer = request()->routeIs('admin.customer.*');
    $diPassword = request()->routeIs('admin.password.*');
    $diInvoice  = request()->routeIs('admin.pengaturan.invoice.*');
    $diProduk    = request()->routeIs('admin.produk.*');
    $diKategori  = request()->routeIs('admin.kategori-produk.*');
    $diBanner    = request()->routeIs('admin.banner.*');
    $diBahan     = request()->routeIs('admin.bahan.*');
    $diFinishing = request()->routeIs('admin.finishing.*');
    $diCetak     = request()->routeIs('admin.cetak.*');
    $diKatalog   = $diProduk || $diKategori || $diBanner || $diBahan || $diFinishing || $diCetak;
    $menuKatalog = [
        ['admin.produk.index', 'fa-cube', 'Produk', $diProduk],
        ['admin.kategori-produk.index', 'fa-tags', 'Kategori', $diKategori],
        ['admin.banner.index', 'fa-images', 'Banner', $diBanner],
        ['admin.bahan.index', 'fa-scroll', 'Master Bahan', $diBahan],
        ['admin.finishing.index', 'fa-wand-magic-sparkles', 'Master Finishing', $diFinishing],
        ['admin.cetak.index', 'fa-fill-drip', 'Master Cetak', $diCetak],
    ];
    $adaBottomNav = ! $__env->hasSection('tanpa_bottomnav');

    // Notifikasi deadline (view composer). Chip: "Lewat 2 hari" / "Hari ini" / "H-2"
    $notifDeadline = $notifDeadline ?? ['total' => 0, 'lewat' => 0, 'mendekat' => 0, 'item' => []];
    $chipNotif = function (array $n) {
        $hari = $n['hari_menuju_deadline'];
        if ($hari === null) return [null, 'netral'];
        if ($hari < 0) return ['Lewat ' . abs($hari) . ' hari', 'lewat'];
        return [$hari === 0 ? 'Hari ini' : 'H-' . $hari, 'mendekat'];
    };

    // id field untuk link di ringkasan error: item.0.jumlah → f-item-0-jumlah
    // Galat array file/centang mengarah ke field induknya: galeri.0 → f-galeri, bahan.0 → f-bahan.
    // gambar.0 (unggah galeri di Edit Produk) → f-galeri-tambah, karena f-gambar dipakai gambar utama di halaman yang sama.
    $idField = function (string $kunci) {
        $kunci = preg_replace('/^(mockup|referensi|galeri|bahan|cetak|finishing)\..*$/', '$1', $kunci);
        $kunci = preg_replace('/^gambar\.\d+$/', 'galeri-tambah', $kunci);
        $kunci = preg_replace('/^(item\.\d+\.finishing)\..*$/', '$1', $kunci);

        return 'f-' . str_replace('.', '-', $kunci);
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#FFFFFF">
    <title>@yield('title', 'Pesanan') — Senggani</title>
    <link rel="icon" href="{{ asset('favicon-32.png') }}" sizes="32x32">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="{{ asset('css/pesanan.css') }}?v={{ filemtime(public_path('css/pesanan.css')) }}" rel="stylesheet">
</head>
<body class="sg {{ $adaBottomNav ? 'sg-ada-bottomnav' : 'sg-bilah-ada' }}">
<a class="sg-lewati" href="#konten">Lewati ke konten</a>

{{-- Top bar HP & tablet --}}
<header class="sg-topbar d-lg-none">
    @hasSection('kembali')
        <a href="@yield('kembali')" class="sg-topbar-kembali" aria-label="Kembali"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
    @else
        <img src="{{ asset('icon-192.png') }}" alt="" class="sg-topbar-ikon" width="32" height="32">
    @endif
    <p class="sg-topbar-judul">{!! $__env->hasSection('judul_bar') ? $__env->yieldContent('judul_bar') : $__env->yieldContent('title', 'Pesanan') !!}</p>
    @include('admin.layouts._lonceng')
    <button type="button" class="sg-avatar" data-bs-toggle="offcanvas" data-bs-target="#sheetAkun" aria-controls="sheetAkun" aria-label="Menu akun {{ $namaPengguna }}">
        <span aria-hidden="true">{{ $inisial }}</span>
    </button>
</header>

<div class="sg-kerangka">
    {{-- Sidebar desktop --}}
    <aside class="sg-sidebar d-none d-lg-flex" aria-label="Navigasi utama">
        <a href="{{ route('admin.pesanan.index') }}" class="sg-sidebar-logo" aria-label="Senggani — ke daftar pesanan">
            <img src="{{ asset('images/logo.png') }}" alt="Senggani" width="123" height="24">
        </a>
        <div class="sg-profil">
            <span class="sg-avatar sg-avatar-besar" aria-hidden="true">{{ $inisial }}</span>
            <div style="min-width:0">
                <div class="sg-profil-nama" title="{{ $namaPengguna }}">{{ $namaPengguna }}</div>
                <span class="sg-chip-peran">{{ $peranProduksi ? 'Produksi' : 'Admin' }}</span>
            </div>
            @include('admin.layouts._lonceng')
        </div>
        <hr class="sg-pemisah">
        <nav aria-labelledby="menu-pesanan">
            <h2 class="sg-menu-kelompok" id="menu-pesanan">Pesanan</h2>
            <ul class="sg-menu">
                <li><a class="sg-menu-item" href="{{ route('admin.pesanan.index') }}" @if ($diPesanan) aria-current="page" @endif><i class="fa-solid fa-box-open" aria-hidden="true"></i> Pesanan</a></li>
                @can('create', \App\Models\Pesanan::class)
                    <li><a class="sg-menu-item" href="{{ route('admin.pesanan.create') }}" @if ($diTambah) aria-current="page" @endif><i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Tambah SPP</a></li>
                @endcan
                @can('kelola-customer')
                    <li><a class="sg-menu-item" href="{{ route('admin.customer.index') }}" @if ($diCustomer) aria-current="page" @endif><i class="fa-solid fa-address-book" aria-hidden="true"></i> Customer</a></li>
                @endcan
            </ul>
        </nav>
        @can('kelola-katalog')
            <hr class="sg-pemisah">
            <nav aria-labelledby="menu-katalog">
                <h2 class="sg-menu-kelompok" id="menu-katalog">Katalog Situs</h2>
                <ul class="sg-menu">
                    @foreach ($menuKatalog as [$rute, $ikon, $label, $aktif])
                        <li><a class="sg-menu-item" href="{{ route($rute) }}" @if ($aktif) aria-current="page" @endif><i class="fa-solid {{ $ikon }}" aria-hidden="true"></i> {{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endcan
        <hr class="sg-pemisah">
        <nav aria-label="Akun">
            <ul class="sg-menu">
                @can('kelola-pengguna')
                    <li><a class="sg-menu-item" href="{{ route('admin.pengguna.index') }}" @if ($diPengguna) aria-current="page" @endif><i class="fa-solid fa-users" aria-hidden="true"></i> Kelola Pengguna</a></li>
                @endcan
                @can('kelola-invoice')
                    <li><a class="sg-menu-item" href="{{ route('admin.pengaturan.invoice.edit') }}" @if ($diInvoice) aria-current="page" @endif><i class="fa-solid fa-file-signature" aria-hidden="true"></i> Pengaturan Invoice</a></li>
                @endcan
                <li><a class="sg-menu-item" href="{{ route('admin.password.edit') }}" @if ($diPassword) aria-current="page" @endif><i class="fa-solid fa-key" aria-hidden="true"></i> Ubah Password</a></li>
            </ul>
        </nav>
        <hr class="sg-pemisah">
        <form action="{{ route('admin.logout') }}" method="POST">
            @csrf
            <button type="submit" class="sg-menu-item sg-menu-keluar"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Keluar</button>
        </form>
    </aside>

    <main id="konten" class="sg-utama" tabindex="-1">
        <div class="sg-panel">
            {{-- Indikator koneksi pembaruan otomatis (lonceng & jumlah tab). Diisi JS setelah 2 kali gagal berturut-turut. --}}
            <div class="sg-koneksi-wadah" role="status" aria-live="polite" data-koneksi>
                <div class="sg-koneksi" data-koneksi-isi hidden>
                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                    <p><span>Tidak tersambung</span><span class="visually-hidden">. Data pesanan dan notifikasi deadline mungkin belum terbaru.</span></p>
                    <button type="button" class="sg-koneksi-ulang" data-koneksi-ulang>Coba lagi</button>
                </div>
            </div>

            @if (session('error'))
                @if (session('bentrok'))
                    <div class="sg-alert sg-alert-info" role="alert">
                        <i class="fa-solid fa-rotate" aria-hidden="true"></i>
                        <p>{{ session('error') }}</p>
                    </div>
                @else
                    <div class="sg-alert sg-alert-galat" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <p>{{ session('error') }}</p>
                    </div>
                @endif
            @endif

            @if (session('info_customer'))
                <div class="sg-alert sg-alert-info" role="status">
                    <i class="fa-solid fa-address-book" aria-hidden="true"></i>
                    <p>{{ session('info_customer') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="sg-alert sg-alert-galat" role="alert" tabindex="-1" id="ringkasan-galat">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <div>
                        <p><strong>{{ $errors->count() === 1 ? 'Ada 1 isian yang perlu diperbaiki' : 'Ada ' . $errors->count() . ' isian yang perlu diperbaiki' }}</strong></p>
                        <ul>
                            @foreach ($errors->getMessages() as $kunci => $daftarPesan)
                                @foreach ($daftarPesan as $pesan)
                                    <li><a href="#{{ $idField($kunci) }}">{{ $pesan }}</a></li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @hasSection('banner_lewat')
                @php $jmlLewat = (int) ($notifDeadline['lewat']); @endphp
                <div class="sg-alert sg-alert-galat sg-alert-lewat" role="status" data-banner-lewat @if ($jmlLewat === 0) hidden @endif>
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <p><strong data-banner-lewat-teks>{{ $jmlLewat }} pesanan lewat deadline</strong></p>
                    <button type="button" class="sg-btn sg-btn-sekunder" data-bs-toggle="modal" data-bs-target="#panelNotif" aria-label="Lihat pesanan yang lewat deadline">Lihat</button>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>

{{-- Bottom nav HP & tablet (halaman tingkat atas) --}}
@if ($adaBottomNav)
    <nav class="sg-bottomnav d-lg-none" aria-label="Navigasi bawah">
        <a href="{{ route('admin.pesanan.index') }}" @if ($diPesanan) aria-current="page" @endif>
            <span class="sg-bn-ikon"><i class="fa-solid fa-box-open" aria-hidden="true"></i></span> Pesanan
        </a>
        @can('create', \App\Models\Pesanan::class)
            <a href="{{ route('admin.pesanan.create') }}" @if ($diTambah) aria-current="page" @endif>
                <span class="sg-bn-ikon"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i></span> Tambah
            </a>
        @endcan
        <button type="button" data-bs-toggle="offcanvas" data-bs-target="#sheetAkun" aria-controls="sheetAkun" @if ($diPengguna || $diPassword || $diCustomer || $diKatalog || $diInvoice) aria-current="page" @endif>
            <span class="sg-bn-ikon"><i class="fa-solid fa-circle-user" aria-hidden="true"></i></span> Akun
        </button>
    </nav>
@endif

{{-- Sheet Akun --}}
<div class="offcanvas offcanvas-bottom sg-sheet" tabindex="-1" id="sheetAkun" aria-labelledby="sheetAkunJudul">
    <div class="sg-sheet-pegangan" aria-hidden="true"></div>
    <div class="offcanvas-header pb-0">
        <div class="sg-profil p-0">
            <span class="sg-avatar sg-avatar-besar" aria-hidden="true">{{ $inisial }}</span>
            <div style="min-width:0">
                <h2 class="sg-profil-nama fs-6 m-0" id="sheetAkunJudul">{{ $namaPengguna }}</h2>
                <span class="sg-chip-peran">{{ $peranProduksi ? 'Produksi' : 'Admin' }}</span>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Tutup"></button>
    </div>
    <div class="offcanvas-body pt-2">
        @can('kelola-customer')
            <nav aria-labelledby="sheet-menu-pesanan">
                <h2 class="sg-menu-kelompok" id="sheet-menu-pesanan">Pesanan</h2>
                <ul class="sg-menu">
                    <li><a class="sg-menu-item" href="{{ route('admin.customer.index') }}" @if ($diCustomer) aria-current="page" @endif><i class="fa-solid fa-address-book" aria-hidden="true"></i> Customer</a></li>
                </ul>
            </nav>
        @endcan
        @can('kelola-katalog')
            <nav aria-labelledby="sheet-menu-katalog">
                <h2 class="sg-menu-kelompok" id="sheet-menu-katalog">Katalog Situs</h2>
                <ul class="sg-menu">
                    @foreach ($menuKatalog as [$rute, $ikon, $label, $aktif])
                        <li><a class="sg-menu-item" href="{{ route($rute) }}" @if ($aktif) aria-current="page" @endif><i class="fa-solid {{ $ikon }}" aria-hidden="true"></i> {{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endcan
        <hr class="sg-pemisah">
        <nav aria-label="Akun">
            <ul class="sg-menu">
                @can('kelola-pengguna')
                    <li><a class="sg-menu-item" href="{{ route('admin.pengguna.index') }}" @if ($diPengguna) aria-current="page" @endif><i class="fa-solid fa-users" aria-hidden="true"></i> Kelola Pengguna</a></li>
                @endcan
                @can('kelola-invoice')
                    <li><a class="sg-menu-item" href="{{ route('admin.pengaturan.invoice.edit') }}" @if ($diInvoice) aria-current="page" @endif><i class="fa-solid fa-file-signature" aria-hidden="true"></i> Pengaturan Invoice</a></li>
                @endcan
                <li><a class="sg-menu-item" href="{{ route('admin.password.edit') }}" @if ($diPassword) aria-current="page" @endif><i class="fa-solid fa-key" aria-hidden="true"></i> Ubah Password</a></li>
                <li>
                    <form action="{{ route('admin.logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="sg-menu-item sg-menu-keluar"><i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i> Keluar</button>
                    </form>
                </li>
            </ul>
        </nav>
    </div>
</div>

{{-- Panel notifikasi deadline (sheet di HP, panel di samping sidebar di desktop). Isi diperbarui JS tiap 60 detik. --}}
<div class="modal fade sg-notif" id="panelNotif" tabindex="-1" aria-labelledby="panelNotifJudul" aria-hidden="true"
     data-panel-notif data-url="{{ route('admin.pesanan.jumlah') }}" data-hari-mendekat="{{ \App\Models\Pesanan::HARI_MENDEKAT }}" data-url-daftar="{{ route('admin.pesanan.index', ['urut' => 'deadline']) }}">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="sg-sheet-pegangan d-lg-none" aria-hidden="true"></div>
            <div class="modal-header">
                <div>
                    <h2 class="modal-title" id="panelNotifJudul">Deadline mendesak</h2>
                    <p class="sg-notif-sub" data-notif-sub>
                        @if ($notifDeadline['total'])
                            {{ $notifDeadline['lewat'] }} lewat · {{ $notifDeadline['mendekat'] }} mendekati (≤ {{ \App\Models\Pesanan::HARI_MENDEKAT }} hari)
                        @else
                            Pesanan aktif sebelum pengiriman dengan deadline lewat atau ≤ {{ \App\Models\Pesanan::HARI_MENDEKAT }} hari
                        @endif
                    </p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" data-notif-isi>
                @if ($notifDeadline['item'])
                    <ul class="sg-notif-daftar">
                        @foreach ($notifDeadline['item'] as $n)
                            @php [$teksChip, $jenisChip] = $chipNotif($n); @endphp
                            <li>
                                <a class="sg-notif-tautan" href="{{ $n['url'] }}">
                                    <span>
                                        <span class="sg-notif-kode">{{ $n['kode_tampil'] }}</span>
                                        <span class="sg-notif-nama">{{ $n['nama_customer'] }}@if ($n['nama_brand']) / {{ $n['nama_brand'] }}@endif</span>
                                        <span class="sg-notif-meta">{{ $n['label_status'] }} · deadline {{ $n['deadline_teks'] }}</span>
                                    </span>
                                    @if ($teksChip)
                                        <span class="sg-deadline sg-deadline-{{ $jenisChip }} sg-notif-chip"><i class="fa-solid {{ $jenisChip === 'lewat' ? 'fa-circle-exclamation' : 'fa-clock' }}" aria-hidden="true"></i>{{ $teksChip }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="sg-notif-kosong">
                        <i class="fa-regular fa-bell-slash" aria-hidden="true"></i>
                        <p>Tidak ada deadline mendesak</p>
                    </div>
                @endif
            </div>
            <div class="modal-footer" data-notif-lain @if ($notifDeadline['total'] <= count($notifDeadline['item'])) hidden @endif>
                <a class="sg-tautan-aksi" href="{{ route('admin.pesanan.index', ['urut' => 'deadline']) }}" data-notif-lain-link>
                    Lihat {{ max(0, $notifDeadline['total'] - count($notifDeadline['item'])) }} lainnya di daftar pesanan
                </a>
            </div>
        </div>
    </div>
</div>

{{-- Toast --}}
<div class="sg-toast-wadah" id="wadah-toast" aria-live="polite">
    @if (session('success'))
        <div class="sg-toast sg-masuk" role="status" data-toast-otomatis>
            <i class="fa-solid fa-circle-check" aria-hidden="true" style="color:#86EFAC"></i>
            {{-- Info customer sudah tampil sebagai alert di halaman, jadi tidak diulang di toast --}}
            <p>{{ session('info_customer') ? trim(str_replace(session('info_customer'), '', session('success'))) : session('success') }}</p>
            <button type="button" data-tutup-toast aria-label="Tutup pemberitahuan"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
    @endif
</div>

{{-- Modal konfirmasi umum (dipakai JS: hapus gambar, nonaktifkan akun, keluar tanpa simpan) --}}
<div class="modal fade modal-sheet sg-modal-konfirmasi" id="modalKonfirmasi" tabindex="-1" aria-labelledby="modalKonfirmasiJudul" aria-describedby="modalKonfirmasiIsi" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalKonfirmasiJudul" data-konfirmasi-judul>Lanjutkan?</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <p class="m-0" id="modalKonfirmasiIsi" data-konfirmasi-isi></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="sg-btn sg-btn-sekunder" data-bs-dismiss="modal" data-konfirmasi-batal>Batal</button>
                <button type="button" class="sg-btn sg-btn-utama" data-konfirmasi-ya>Ya, lanjutkan</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/pesanan.js') }}?v={{ filemtime(public_path('js/pesanan.js')) }}"></script>
@stack('scripts')
</body>
</html>

<?php

use App\Http\Controllers\Admin\BahanController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\CetakController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\FinishingController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\KategoriProdukController;
use App\Http\Controllers\Admin\PasswordController;
use App\Http\Controllers\Admin\PengaturanInvoiceController;
use App\Http\Controllers\Admin\PenggunaController;
use App\Http\Controllers\Admin\PesananController;
use App\Http\Controllers\Admin\PesananFileController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\KategoriController;
use App\Http\Controllers\Web\ProdukController;
use App\Http\Controllers\Web\SeoController;
use Illuminate\Support\Facades\Route;

// ── Public Routes ──────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/kategori', [KategoriController::class, 'index'])->name('kategori.index');
Route::get('/kategori/{slug}', [KategoriController::class, 'show'])->name('kategori.show');
Route::get('/produk', [ProdukController::class, 'index'])->name('produk.index');
Route::get('/produk/{slug}', [ProdukController::class, 'show'])->name('produk.show');

// ── SEO & AI (dinamis agar URL absolut mengikuti APP_URL) ──
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [SeoController::class, 'llms'])->name('llms');

// ── Admin Auth Routes (tanpa middleware) ───────────────
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])
        ->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])
        ->name('login.post');
    Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])
        ->name('logout');
});

// ── Panel (wajib login): admin & produksi ───────────
// Hak akses per aksi diperiksa lagi di Policy/Form Request (App\Policies\PesananPolicy).
Route::prefix('admin')->name('admin.')->middleware('auth.admin')->group(function () {

    // Pesanan
    Route::get('/pesanan', [PesananController::class, 'index'])->name('pesanan.index');
    Route::get('/pesanan/jumlah', [PesananController::class, 'jumlah'])->name('pesanan.jumlah');
    Route::get('/pesanan/tambah', [PesananController::class, 'create'])->name('pesanan.create');
    Route::post('/pesanan', [PesananController::class, 'store'])->name('pesanan.store');
    Route::get('/pesanan/{pesanan}', [PesananController::class, 'show'])->name('pesanan.show');
    Route::get('/pesanan/{pesanan}/edit', [PesananController::class, 'edit'])->name('pesanan.edit');
    Route::put('/pesanan/{pesanan}', [PesananController::class, 'update'])->name('pesanan.update');
    Route::delete('/pesanan/{pesanan}', [PesananController::class, 'destroy'])->name('pesanan.destroy');

    Route::post('/pesanan/{pesanan}/harga', [PesananController::class, 'isiHarga'])->name('pesanan.harga');
    Route::post('/pesanan/{pesanan}/dp', [PesananController::class, 'catatDp'])->name('pesanan.dp');
    Route::post('/pesanan/{pesanan}/tidak-jadi', [PesananController::class, 'tidakJadi'])->name('pesanan.tidak_jadi');
    Route::post('/pesanan/{pesanan}/status', [PesananController::class, 'ubahStatus'])->name('pesanan.status');
    Route::post('/pesanan/{pesanan}/batal', [PesananController::class, 'batalkan'])->name('pesanan.batal');
    Route::post('/pesanan/{pesanan}/pulihkan', [PesananController::class, 'pulihkan'])->name('pesanan.pulihkan');
    Route::post('/pesanan/{pesanan}/catatan', [PesananController::class, 'tambahCatatan'])->name('pesanan.catatan');
    Route::post('/pesanan/{pesanan}/lunas', [PesananController::class, 'catatLunas'])->name('pesanan.lunas');

    // Invoice PDF & Pengaturan Invoice: khusus admin lewat Gate/Policy di controller (Produksi 403, bukan redirect)
    Route::get('/pesanan/{pesanan}/invoice', [InvoiceController::class, 'unduh'])->name('pesanan.invoice');
    Route::get('/pengaturan-invoice', [PengaturanInvoiceController::class, 'edit'])->name('pengaturan.invoice.edit');
    Route::put('/pengaturan-invoice', [PengaturanInvoiceController::class, 'update'])->name('pengaturan.invoice.update');
    Route::get('/pengaturan-invoice/contoh', [PengaturanInvoiceController::class, 'contoh'])->name('pengaturan.invoice.contoh');
    Route::get('/pengaturan-invoice/gambar/{jenis}', [PengaturanInvoiceController::class, 'gambar'])
        ->whereIn('jenis', ['ttd', 'stempel', 'qris'])->name('pengaturan.invoice.gambar');
    Route::post('/pengaturan-invoice/gambar/{jenis}', [PengaturanInvoiceController::class, 'unggah'])
        ->whereIn('jenis', ['ttd', 'stempel', 'qris'])->name('pengaturan.invoice.unggah');
    Route::delete('/pengaturan-invoice/gambar/{jenis}', [PengaturanInvoiceController::class, 'hapus'])
        ->whereIn('jenis', ['ttd', 'stempel', 'qris'])->name('pengaturan.invoice.hapus');

    Route::get('/pesanan/{pesanan}/file/{file}', [PesananFileController::class, 'lihat'])->name('pesanan.file.lihat');
    Route::delete('/pesanan/{pesanan}/file/{file}', [PesananFileController::class, 'hapus'])->name('pesanan.file.hapus');

    // Ubah password sendiri
    Route::get('/password', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/password', [PasswordController::class, 'update'])->name('password.update');
});

// ── Admin Routes (wajib login, khusus peran admin) ─────
Route::prefix('admin')->name('admin.')->middleware(['auth.admin', 'peran:admin'])->group(function () {

    // Kelola pengguna
    Route::get('/pengguna', [PenggunaController::class, 'index'])->name('pengguna.index');
    Route::get('/pengguna/tambah', [PenggunaController::class, 'create'])->name('pengguna.create');
    Route::post('/pengguna', [PenggunaController::class, 'store'])->name('pengguna.store');
    Route::get('/pengguna/{pengguna}/edit', [PenggunaController::class, 'edit'])->name('pengguna.edit');
    Route::put('/pengguna/{pengguna}', [PenggunaController::class, 'update'])->name('pengguna.update');
    Route::put('/pengguna/{pengguna}/password', [PenggunaController::class, 'resetPassword'])->name('pengguna.password');
    Route::patch('/pengguna/{pengguna}/aktif', [PenggunaController::class, 'ubahAktif'])->name('pengguna.aktif');

    // Customer (dibuat otomatis dari form SPP; produksi tidak perlu)
    Route::get('/customer', [CustomerController::class, 'index'])->name('customer.index');
    Route::get('/customer/cari', [CustomerController::class, 'cari'])->name('customer.cari');
    Route::get('/customer/{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('customer.show');
    Route::get('/customer/{customer}/edit', [CustomerController::class, 'edit'])->whereNumber('customer')->name('customer.edit');
    Route::put('/customer/{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('customer.update');
    Route::delete('/customer/{customer}', [CustomerController::class, 'destroy'])->whereNumber('customer')->name('customer.destroy');

    // Dashboard lama dialihkan ke daftar produk; nama rute dipertahankan agar link/bookmark lama tetap jalan
    Route::get('/dashboard', fn () => redirect()->route('admin.produk.index'))
        ->name('dashboard');

    // ── Katalog situs (khusus admin; menu memakai Gate 'kelola-katalog' dengan aturan yang sama) ──

    Route::resource('banner', BannerController::class)->except(['show']);
    Route::patch('banner/{banner}/aktif', [BannerController::class, 'toggleAktif'])
        ->name('banner.aktif');

    Route::resource('kategori-produk', KategoriProdukController::class)->except(['show']);

    Route::resource('produk', \App\Http\Controllers\Admin\ProdukController::class)->except(['show']);

    // Galeri
    Route::post('produk/{produk}/galeri', [\App\Http\Controllers\Admin\ProdukController::class, 'tambahGaleri'])
        ->name('produk.galeri.tambah');
    Route::delete('galeri/{id}', [\App\Http\Controllers\Admin\ProdukController::class, 'hapusGaleri'])
        ->name('galeri.hapus');

    // Ukuran
    Route::post('produk/{produk}/ukuran', [\App\Http\Controllers\Admin\ProdukController::class, 'tambahUkuran'])
        ->name('produk.ukuran.tambah');
    Route::delete('ukuran/{id}', [\App\Http\Controllers\Admin\ProdukController::class, 'hapusUkuran'])
        ->name('produk.ukuran.hapus');

    // Kelola bahan & finishing produk (centang dari master)
    Route::get('/produk/{produk}/kelola', [\App\Http\Controllers\Admin\ProdukController::class, 'atribut'])
        ->name('produk.atribut');
    Route::put('produk/{produk}/atribut', [\App\Http\Controllers\Admin\ProdukController::class, 'simpanAtribut'])
        ->name('produk.atribut.simpan');

    // Master bahan & finishing
    Route::resource('bahan', BahanController::class)
        ->only(['index', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['bahan' => 'bahan']);
    Route::resource('finishing', FinishingController::class)
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
    Route::resource('cetak', CetakController::class)
        ->only(['index', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['cetak' => 'cetak']);
});

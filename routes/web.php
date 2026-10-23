<?php

use App\Http\Controllers\Admin\BahanController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\FinishingController;
use App\Http\Controllers\Admin\KategoriProdukController;
use App\Http\Controllers\Web\HomeController;
use App\Http\Controllers\Web\KategoriController;
use App\Http\Controllers\Web\ProdukController;
use Illuminate\Support\Facades\Route;

// ── Public Routes ──────────────────────────────────────
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/kategori/{slug}', [KategoriController::class, 'show'])->name('kategori.show');
Route::get('/produk/{slug}', [ProdukController::class, 'show'])->name('produk.show');

// ── Admin Auth Routes (tanpa middleware) ───────────────
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [\App\Http\Controllers\Admin\AuthController::class, 'showLogin'])
        ->name('login');
    Route::post('/login', [\App\Http\Controllers\Admin\AuthController::class, 'login'])
        ->name('login.post');
    Route::post('/logout', [\App\Http\Controllers\Admin\AuthController::class, 'logout'])
        ->name('logout');
});

// ── Admin Routes (wajib login) ─────────────────────────
Route::prefix('admin')->name('admin.')->middleware('auth.admin')->group(function () {

    Route::get('/dashboard', [\App\Http\Controllers\Admin\DashboardController::class, 'index'])
        ->name('dashboard');

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
    Route::put('produk/{produk}/bahan', [\App\Http\Controllers\Admin\ProdukController::class, 'simpanBahan'])
        ->name('produk.bahan.simpan');
    Route::put('produk/{produk}/finishing', [\App\Http\Controllers\Admin\ProdukController::class, 'simpanFinishing'])
        ->name('produk.finishing.simpan');

    // Master bahan & finishing
    Route::resource('bahan', BahanController::class)
        ->only(['index', 'store', 'edit', 'update', 'destroy'])
        ->parameters(['bahan' => 'bahan']);
    Route::resource('finishing', FinishingController::class)
        ->only(['index', 'store', 'edit', 'update', 'destroy']);
});

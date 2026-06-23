<?php

use App\Http\Controllers\Admin\BannerController;
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

    Route::resource('banner', BannerController::class);

    Route::resource('kategori-produk', KategoriProdukController::class);

    Route::resource('produk', \App\Http\Controllers\Admin\ProdukController::class);

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

    // Bahan
    Route::post('produk/{produk}/bahan', [\App\Http\Controllers\Admin\ProdukController::class, 'tambahBahan'])
        ->name('produk.bahan.tambah');
    Route::delete('bahan/{id}', [\App\Http\Controllers\Admin\ProdukController::class, 'hapusBahan'])
        ->name('produk.bahan.hapus');

    // Finishing
    Route::post('produk/{produk}/finishing', [\App\Http\Controllers\Admin\ProdukController::class, 'tambahFinishing'])
        ->name('produk.finishing.tambah');
    Route::delete('finishing/{id}', [\App\Http\Controllers\Admin\ProdukController::class, 'hapusFinishing'])
        ->name('produk.finishing.hapus');

    Route::get('/produk/{produk}/kelola', [\App\Http\Controllers\Admin\ProdukController::class, 'atribut'])
    ->name('produk.atribut');
});

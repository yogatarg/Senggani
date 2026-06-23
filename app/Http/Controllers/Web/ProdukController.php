<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Produk;

class ProdukController extends Controller
{
    public function show($slug)
    {
        // Cari produk berdasarkan slug
        // with() memuat semua relasi sekaligus (eager loading)
        $produk = Produk::with([
            'kategori',
            'galeri',
            'ukuran',
            'bahan',
            'finishing',
        ])->where('slug', $slug)->firstOrFail();

        return view('web.produk.detail', compact('produk'));
    }
}

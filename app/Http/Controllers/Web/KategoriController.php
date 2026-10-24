<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KategoriProduk;

class KategoriController extends Controller
{
    public function index()
    {
        // Semua kategori, urutan sama seperti di beranda
        $kategori = KategoriProduk::withCount('produk')->orderBy('id')->get();

        return view('web.kategori.semua', compact('kategori'));
    }

    public function show($slug)
    {
        // Cari kategori berdasarkan slug, jika tidak ada tampilkan 404
        $kategori = KategoriProduk::where('slug', $slug)->firstOrFail();

        // Ambil semua produk milik kategori ini
        $produk = $kategori->produk()->get();

        return view('web.kategori.index', compact('kategori', 'produk'));
    }
}
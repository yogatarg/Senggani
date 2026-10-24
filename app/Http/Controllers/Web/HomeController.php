<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use App\Models\KategoriProduk;
use App\Models\Produk;

class HomeController extends Controller
{
    public function index()
    {
        $banner   = Banner::where('aktif', true)->get();
        // Beranda hanya menampilkan 6 kategori pertama; sisanya di /kategori
        $kategori      = KategoriProduk::withCount('produk')->orderBy('id')->take(6)->get();
        $totalKategori = KategoriProduk::count();
        $produk   = Produk::with('kategori')->latest()->take(6)->get();

        return view('web.home.index', compact('banner', 'kategori', 'produk', 'totalKategori'));
    }
}
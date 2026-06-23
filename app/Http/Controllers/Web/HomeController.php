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
        $kategori = KategoriProduk::withCount('produk')->get();
        $produk   = Produk::with('kategori')->latest()->take(6)->get();

        return view('web.home.index', compact('banner', 'kategori', 'produk'));
    }
}
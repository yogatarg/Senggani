<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KategoriProduk;
use App\Models\Produk;

class DashboardController extends Controller
{
    public function index()
    {
        $jumlahKategori = KategoriProduk::count();
        $jumlahProduk   = Produk::count();

        return view('admin.dashboard', compact('jumlahKategori', 'jumlahProduk'));
    }
}
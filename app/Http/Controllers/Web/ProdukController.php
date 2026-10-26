<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\KategoriProduk;
use App\Models\Produk;
use Illuminate\Http\Request;

class ProdukController extends Controller
{
    public function index(Request $request)
    {
        // Semua kategori + jumlah produknya (untuk filter)
        $kategori = KategoriProduk::withCount('produk')
            ->orderBy('nama_kategori')
            ->get();

        // Filter opsional ?kategori=<slug>. Bila parameter ada tetapi tidak cocok dengan slug mana pun
        // (slug asing, kosong, atau array), arahkan permanen ke /produk agar tidak ada URL duplikat.
        $kategoriAktif = null;
        if ($request->query->has('kategori')) {
            $slugKategori = $request->query('kategori');
            $kategoriAktif = is_string($slugKategori) && $slugKategori !== ''
                ? $kategori->firstWhere('slug', $slugKategori)
                : null;

            if (! $kategoriAktif) {
                return redirect()->route('produk.index', status: 301);
            }
        }

        $produk = Produk::with('kategori')
            ->when($kategoriAktif, fn ($query) => $query->where('kategori_id', $kategoriAktif->id))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // Halaman di luar rentang: arahkan ke halaman terakhir (query string seperti ?kategori ikut terbawa)
        if ($produk->total() > 0 && $produk->currentPage() > $produk->lastPage()) {
            return redirect()->to($produk->url($produk->lastPage()));
        }

        return view('web.produk.index', compact('produk', 'kategori', 'kategoriAktif'));
    }

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
            'cetak',
        ])->where('slug', $slug)->firstOrFail();

        return view('web.produk.detail', compact('produk'));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KategoriProduk;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class KategoriProdukController extends Controller
{
    public function index()
    {
        $kategori = KategoriProduk::withCount('produk')->latest()->get();
        return view('admin.kategori_produk.index', compact('kategori'));
    }

    public function create()
    {
        return view('admin.kategori_produk.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_kategori' => 'required|string|max:255',
            'deskripsi'     => 'nullable|string',
            'gambar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $data = [
            'nama_kategori' => $request->nama_kategori,
            'slug'          => Str::slug($request->nama_kategori),
            'deskripsi'     => $request->deskripsi,
        ];

        // Upload gambar jika ada
        if ($request->hasFile('gambar')) {
            $data['gambar'] = $request->file('gambar')
                                      ->store('uploads/kategori', 'public');
        }

        KategoriProduk::create($data);

        return redirect()->route('admin.kategori-produk.index')
                         ->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(KategoriProduk $kategoriProduk)
    {
        return view('admin.kategori_produk.edit', compact('kategoriProduk'));
    }

    public function update(Request $request, KategoriProduk $kategoriProduk)
    {
        $request->validate([
            'nama_kategori' => 'required|string|max:255',
            'deskripsi'     => 'nullable|string',
            'gambar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $kategoriProduk->nama_kategori = $request->nama_kategori;
        $kategoriProduk->slug          = Str::slug($request->nama_kategori);
        $kategoriProduk->deskripsi     = $request->deskripsi;

        // Ganti gambar jika ada upload baru
        if ($request->hasFile('gambar')) {
            // Hapus gambar lama
            if ($kategoriProduk->gambar) {
                Storage::disk('public')->delete($kategoriProduk->gambar);
            }
            $kategoriProduk->gambar = $request->file('gambar')
                                               ->store('uploads/kategori', 'public');
        }

        $kategoriProduk->save();

        return redirect()->route('admin.kategori-produk.index')
                         ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(KategoriProduk $kategoriProduk)
    {
        // Hapus gambar saat kategori dihapus
        if ($kategoriProduk->gambar) {
            Storage::disk('public')->delete($kategoriProduk->gambar);
        }

        $kategoriProduk->delete();

        return redirect()->route('admin.kategori-produk.index')
                         ->with('success', 'Kategori berhasil dihapus.');
    }
}
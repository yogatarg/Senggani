<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KategoriProduk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

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
            'slug'          => KategoriProduk::slugUnik($request->nama_kategori),
            'deskripsi'     => $request->deskripsi,
        ];

        // Upload gambar jika ada
        if ($request->hasFile('gambar')) {
            $data['gambar'] = $request->file('gambar')
                                      ->store('uploads/kategori', 'public');
        }

        try {
            KategoriProduk::create($data);
        } catch (Throwable $e) {
            // Simpan gagal: buang gambar yang sempat terunggah
            if (! empty($data['gambar'])) {
                Storage::disk('public')->delete($data['gambar']);
            }
            throw $e;
        }

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
        $kategoriProduk->slug          = KategoriProduk::slugUnik($request->nama_kategori, $kategoriProduk->id);
        $kategoriProduk->deskripsi     = $request->deskripsi;

        $gambarLama = null;
        $gambarBaru = null;

        // Ganti gambar jika ada upload baru
        if ($request->hasFile('gambar')) {
            $gambarLama = $kategoriProduk->gambar;
            $gambarBaru = $request->file('gambar')
                                  ->store('uploads/kategori', 'public');
            $kategoriProduk->gambar = $gambarBaru;
        }

        try {
            $kategoriProduk->save();
        } catch (Throwable $e) {
            if ($gambarBaru) {
                Storage::disk('public')->delete($gambarBaru);
            }
            throw $e;
        }

        // Hapus gambar lama hanya setelah data baru tersimpan
        if ($gambarLama) {
            Storage::disk('public')->delete($gambarLama);
        }

        return redirect()->route('admin.kategori-produk.index')
                         ->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(KategoriProduk $kategoriProduk)
    {
        // Catat dulu semua file: gambar kategori + file produk di kategori ini
        // (produk ikut terhapus lewat cascade, aturan ini tidak diubah)
        $file = array_filter([$kategoriProduk->gambar]);

        foreach ($kategoriProduk->produk as $produk) {
            $file = array_merge($file, $produk->daftarFile());
        }

        DB::transaction(fn () => $kategoriProduk->delete());

        // File dihapus setelah datanya berhasil dihapus
        Storage::disk('public')->delete($file);

        return redirect()->route('admin.kategori-produk.index')
                         ->with('success', 'Kategori berhasil dihapus.');
    }
}
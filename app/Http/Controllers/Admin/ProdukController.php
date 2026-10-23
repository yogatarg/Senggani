<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bahan;
use App\Models\Finishing;
use App\Models\GaleriProduk;
use App\Models\KategoriProduk;
use App\Models\Produk;
use App\Models\UkuranProduk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProdukController extends Controller
{
    public function index()
    {
        $produk = Produk::with('kategori')
            ->withCount(['bahan', 'finishing'])
            ->latest()
            ->get();
        return view('admin.produk.index', compact('produk'));
    }

    public function create()
    {
        $kategori = KategoriProduk::all();
        return view('admin.produk.create', compact('kategori'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_produk'   => 'required|string|max:255',
            'kategori_id'   => 'required|exists:kategori_produk,id',
            'deskripsi'     => 'nullable|string',
            'minimal_pesan' => 'required|integer|min:1',
            'gambar'        => 'nullable|image|max:2048',
            'galeri.*'      => 'nullable|image|max:2048',
        ]);

        $data = [
            'nama_produk'   => $request->nama_produk,
            'slug'          => Str::slug($request->nama_produk),
            'kategori_id'   => $request->kategori_id,
            'deskripsi'     => $request->deskripsi,
            'minimal_pesan' => $request->minimal_pesan,
            'produk_kustom' => $request->has('produk_kustom'),
        ];

        if ($request->hasFile('gambar')) {
            $data['gambar'] = $request->file('gambar')->store('produk', 'public');
        }

        $produk = Produk::create($data);

        // ── Simpan galeri ── (INI YANG KURANG)
        if ($request->hasFile('galeri')) {
            foreach ($request->file('galeri') as $file) {
                GaleriProduk::create([
                    'produk_id' => $produk->id,
                    'nama_file' => $file->store('galeri', 'public'),
                ]);
            }
        }

        // Bahan & finishing dipilih di halaman Kelola
        return redirect()->route('admin.produk.atribut', $produk)
            ->with('success', 'Produk berhasil dibuat. Silakan pilih bahan dan finishing produk.');
    }

    public function edit(Produk $produk)
    {
        $produk->load(['kategori', 'galeri', 'ukuran', 'bahan', 'finishing']);
        $kategori = KategoriProduk::all();
        return view('admin.produk.edit', compact('produk', 'kategori'));
    }

    public function update(Request $request, Produk $produk)
    {
        $request->validate([
            'nama_produk'   => 'required|string|max:255',
            'kategori_id'   => 'required|exists:kategori_produk,id',
            'deskripsi'     => 'nullable|string',
            'minimal_pesan' => 'required|integer|min:1',
            'gambar'        => 'nullable|image|max:2048',
        ]);

        $produk->nama_produk   = $request->nama_produk;
        $produk->slug          = Str::slug($request->nama_produk);
        $produk->kategori_id   = $request->kategori_id;
        $produk->deskripsi     = $request->deskripsi;
        $produk->minimal_pesan = $request->minimal_pesan;
        $produk->produk_kustom = $request->has('produk_kustom');

        if ($request->hasFile('gambar')) {
            // Hapus gambar lama jika ada
            if ($produk->gambar) {
                Storage::disk('public')->delete($produk->gambar);
            }
            $produk->gambar = $request->file('gambar')->store('produk', 'public');
        }

        $produk->save();

        return redirect()->route('admin.produk.edit', $produk)
            ->with('success', 'Produk berhasil diperbarui.');

    }

    public function destroy(Produk $produk)
    {
        if ($produk->gambar && Storage::disk('public')->exists($produk->gambar)) {
            Storage::disk('public')->delete($produk->gambar);
        }
        $produk->delete();

        return redirect()->route('admin.produk.index')
            ->with('success', 'Produk berhasil dihapus.');
    }

    // ── Galeri ────────────────────────────────────────────
    public function tambahGaleri(Request $request, Produk $produk)
    {
        // Cek dulu sebelum validasi
        if (! $request->hasFile('gambar')) {
            return back()->with('error', 'Pilih foto terlebih dahulu.');
        }

        $request->validate([
            'gambar.*' => 'image|max:2048',
        ]);

        foreach ($request->file('gambar') as $file) {
            GaleriProduk::create([
                'produk_id' => $produk->id,
                'nama_file' => $file->store('galeri', 'public'),
            ]);
        }

        return back()->with('success', 'Galeri berhasil ditambahkan.');
    }

    public function hapusGaleri($id)
    {
        $galeri = GaleriProduk::findOrFail($id);
        Storage::disk('public')->delete($galeri->nama_file);
        $galeri->delete();

        return back()->with('success', 'Foto berhasil dihapus.');
    }

    // ── Ukuran ────────────────────────────────────────────
    public function tambahUkuran(Request $request, Produk $produk)
    {
        $request->validate(['nama_ukuran' => 'required|string|max:255']);

        UkuranProduk::create([
            'produk_id'   => $produk->id,
            'nama_ukuran' => $request->nama_ukuran,
            'panjang'     => $request->panjang,
            'lebar'       => $request->lebar,
            'tinggi'      => $request->tinggi,
            'keterangan'  => $request->keterangan,
        ]);

        return back()->with('success', 'Ukuran berhasil ditambahkan.');
    }

    public function hapusUkuran($id)
    {
        UkuranProduk::findOrFail($id)->delete();
        return back()->with('success', 'Ukuran berhasil dihapus.');
    }

    // ── Kelola Bahan & Finishing (centang dari master) ────
    public function atribut(Produk $produk)
    {
        $produk->load(['bahan', 'finishing']);
        $semuaBahan     = Bahan::orderBy('nama_bahan')->get();
        $semuaFinishing = Finishing::orderBy('nama_finishing')->get();

        return view('admin.produk.atribut', compact('produk', 'semuaBahan', 'semuaFinishing'));
    }

    public function simpanBahan(Request $request, Produk $produk)
    {
        $request->validate([
            'bahan'   => 'nullable|array',
            'bahan.*' => 'exists:bahan,id',
        ]);

        // sync(): yang dicentang ditambahkan, yang tidak dicentang dilepas
        $produk->bahan()->sync($request->input('bahan', []));

        return back()->with('success', 'Bahan produk berhasil disimpan.');
    }

    public function simpanFinishing(Request $request, Produk $produk)
    {
        $request->validate([
            'finishing'   => 'nullable|array',
            'finishing.*' => 'exists:finishing,id',
        ]);

        $produk->finishing()->sync($request->input('finishing', []));

        return back()->with('success', 'Finishing produk berhasil disimpan.');
    }
}

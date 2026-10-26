<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\UnggahGambar;
use App\Http\Controllers\Controller;
use App\Models\Bahan;
use App\Models\Cetak;
use App\Models\Finishing;
use App\Models\GaleriProduk;
use App\Models\KategoriProduk;
use App\Models\Produk;
use App\Models\UkuranProduk;
use App\Services\OlahGambar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProdukController extends Controller
{
    use UnggahGambar;

    public function __construct(private OlahGambar $olahGambar)
    {
    }

    public function index()
    {
        $produk = Produk::with('kategori')
            ->withCount(['bahan', 'finishing', 'cetak'])
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
            'gambar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|' . self::ATURAN_DIMENSI,
            'galeri'        => 'nullable|array',
            'galeri.*'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|' . self::ATURAN_DIMENSI,
        ], $this->pesanDimensi(['gambar' => 'Gambar produk', 'galeri.*' => 'Foto galeri']));

        $data = [
            'nama_produk'   => $request->nama_produk,
            'slug'          => Produk::slugUnik($request->nama_produk),
            'kategori_id'   => $request->kategori_id,
            'deskripsi'     => $request->deskripsi,
            'minimal_pesan' => $request->minimal_pesan,
            'produk_kustom' => $request->has('produk_kustom'),
        ];

        // File yang sudah terunggah dicatat agar bisa dihapus lagi bila penyimpanan gagal
        $fileBaru = [];

        try {
            if ($request->hasFile('gambar')) {
                $data['gambar'] = $fileBaru[] = $this->simpanGambar($request->file('gambar'), 'produk', OlahGambar::UMUM, 'gambar');
            }

            $galeri = [];
            if ($request->hasFile('galeri')) {
                foreach ($request->file('galeri') as $i => $file) {
                    $galeri[] = $fileBaru[] = $this->simpanGambar($file, 'galeri', OlahGambar::UMUM, "galeri.$i");
                }
            }

            // Produk & galeri disimpan sekaligus: gagal satu, batal semua
            $produk = DB::transaction(function () use ($data, $galeri) {
                $produk = Produk::create($data);

                foreach ($galeri as $namaFile) {
                    GaleriProduk::create([
                        'produk_id' => $produk->id,
                        'nama_file' => $namaFile,
                    ]);
                }

                return $produk;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($fileBaru);
            throw $e;
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
            'gambar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048|' . self::ATURAN_DIMENSI,
        ], $this->pesanDimensi(['gambar' => 'Gambar produk']));

        $produk->nama_produk   = $request->nama_produk;
        $produk->slug          = Produk::slugUnik($request->nama_produk, $produk->id);
        $produk->kategori_id   = $request->kategori_id;
        $produk->deskripsi     = $request->deskripsi;
        $produk->minimal_pesan = $request->minimal_pesan;
        $produk->produk_kustom = $request->has('produk_kustom');

        $gambarLama = null;
        $gambarBaru = null;

        if ($request->hasFile('gambar')) {
            $gambarLama     = $produk->gambar;
            $gambarBaru     = $this->simpanGambar($request->file('gambar'), 'produk', OlahGambar::UMUM, 'gambar');
            $produk->gambar = $gambarBaru;
        }

        try {
            $produk->save();
        } catch (Throwable $e) {
            // Simpan gagal: buang file baru, gambar lama tetap utuh
            if ($gambarBaru) {
                Storage::disk('public')->delete($gambarBaru);
            }
            throw $e;
        }

        // Hapus gambar lama hanya setelah data baru tersimpan
        if ($gambarLama) {
            Storage::disk('public')->delete($gambarLama);
        }

        return redirect()->route('admin.produk.edit', $produk)
            ->with('success', 'Produk berhasil diperbarui.');

    }

    public function destroy(Produk $produk)
    {
        // Catat file dulu, hapus data (galeri ikut terhapus lewat cascade), baru hapus file
        $file = $produk->daftarFile();

        DB::transaction(fn () => $produk->delete());

        Storage::disk('public')->delete($file);

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
            'gambar'   => 'array',
            'gambar.*' => 'image|mimes:jpg,jpeg,png,webp|max:2048|' . self::ATURAN_DIMENSI,
        ], $this->pesanDimensi(['gambar.*' => 'Foto galeri']));

        $fileBaru = [];

        try {
            foreach ($request->file('gambar') as $i => $file) {
                $fileBaru[] = $this->simpanGambar($file, 'galeri', OlahGambar::UMUM, "gambar.$i");
            }

            DB::transaction(function () use ($produk, $fileBaru) {
                foreach ($fileBaru as $namaFile) {
                    GaleriProduk::create([
                        'produk_id' => $produk->id,
                        'nama_file' => $namaFile,
                    ]);
                }
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($fileBaru);
            throw $e;
        }

        return back()->with('success', 'Galeri berhasil ditambahkan.');
    }

    public function hapusGaleri($id)
    {
        $galeri = GaleriProduk::findOrFail($id);
        $galeri->delete();

        // File dihapus setelah datanya berhasil dihapus
        Storage::disk('public')->delete($galeri->nama_file);

        return back()->with('success', 'Foto berhasil dihapus.');
    }

    // ── Ukuran ────────────────────────────────────────────
    public function tambahUkuran(Request $request, Produk $produk)
    {
        $request->validate([
            'nama_ukuran' => 'required|string|max:255',
            'panjang'     => 'nullable|numeric|min:0|max:999999',
            'lebar'       => 'nullable|numeric|min:0|max:999999',
            'tinggi'      => 'nullable|numeric|min:0|max:999999',
            'keterangan'  => 'nullable|string|max:255',
        ]);

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
        $produk->load(['bahan', 'finishing', 'cetak']);
        $semuaBahan     = Bahan::orderBy('nama_bahan')->get();
        $semuaFinishing = Finishing::orderBy('nama_finishing')->get();
        $semuaCetak     = Cetak::orderBy('nama_cetak')->get();

        return view('admin.produk.atribut', compact('produk', 'semuaBahan', 'semuaFinishing', 'semuaCetak'));
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

    public function simpanCetak(Request $request, Produk $produk)
    {
        $request->validate([
            'cetak'   => 'nullable|array',
            'cetak.*' => 'exists:cetak,id',
        ]);

        $produk->cetak()->sync($request->input('cetak', []));

        return back()->with('success', 'Jenis cetak produk berhasil disimpan.');
    }
}

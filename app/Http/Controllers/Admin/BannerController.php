<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BannerController extends Controller
{
    public function index()
    {
        $banner = Banner::latest()->get();
        return view('admin.banner.index', compact('banner'));
    }

    public function create()
    {
        return view('admin.banner.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'judul'         => 'required|string|max:255',
            'deskripsi'     => 'nullable|string|max:1000',
            'gambar'        => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'gambar_mobile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'tautan'        => 'nullable|url',
        ]);

        $gambar       = null;
        $gambarMobile = null;

        try {
            $gambar = $request->file('gambar')->store('banner', 'public');

            if ($request->hasFile('gambar_mobile')) {
                $gambarMobile = $request
                    ->file('gambar_mobile')
                    ->store('banner', 'public');
            }

            Banner::create([
                'judul'         => $request->judul,
                'deskripsi'     => $request->deskripsi,
                'gambar'        => $gambar,
                'gambar_mobile' => $gambarMobile,
                'tautan'        => $request->tautan,
                'aktif'         => $request->has('aktif'),
            ]);
        } catch (Throwable $e) {
            // Simpan gagal: buang file yang sempat terunggah
            Storage::disk('public')->delete(array_filter([$gambar, $gambarMobile]));
            throw $e;
        }

        return redirect()->route('admin.banner.index')
            ->with('success', 'Banner berhasil ditambahkan.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banner.edit', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $request->validate([
            'judul'         => 'required|string|max:255',
            'deskripsi'     => 'nullable|string|max:1000',
            'gambar'        => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'gambar_mobile' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
            'tautan'        => 'nullable|url',
        ]);

        // File lama baru dihapus setelah data baru tersimpan;
        // file baru dibuang lagi bila penyimpanan gagal
        $fileLama = [];
        $fileBaru = [];

        try {
            if ($request->hasFile('gambar')) {
                $fileLama[]     = $banner->gambar;
                $banner->gambar = $fileBaru[] = $request->file('gambar')->store('banner', 'public');
            }

            if ($request->hasFile('gambar_mobile')) {
                $fileLama[]            = $banner->gambar_mobile;
                $banner->gambar_mobile = $fileBaru[] = $request
                    ->file('gambar_mobile')
                    ->store('banner', 'public');
            }

            $banner->judul     = $request->judul;
            $banner->deskripsi = $request->deskripsi;
            $banner->tautan    = $request->tautan;
            $banner->aktif     = $request->has('aktif');
            $banner->save();
        } catch (Throwable $e) {
            Storage::disk('public')->delete($fileBaru);
            throw $e;
        }

        Storage::disk('public')->delete(array_filter($fileLama));

        return redirect()->route('admin.banner.index')
            ->with('success', 'Banner berhasil diperbarui.');
    }

    // Aktif / nonaktifkan banner langsung dari daftar
    public function toggleAktif(Banner $banner)
    {
        $banner->aktif = ! $banner->aktif;
        $banner->save();

        return back()->with('success', $banner->aktif
            ? 'Banner ditampilkan di website.'
            : 'Banner disembunyikan dari website.');
    }

    public function destroy(Banner $banner)
    {
        $file = array_filter([$banner->gambar, $banner->gambar_mobile]);

        DB::transaction(fn () => $banner->delete());

        // File dihapus setelah datanya berhasil dihapus
        Storage::disk('public')->delete($file);

        return redirect()->route('admin.banner.index')
            ->with('success', 'Banner berhasil dihapus.');
    }
}

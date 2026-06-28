<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

        $gambar       = $request->file('gambar')->store('banner', 'public');
        $gambarMobile = null;

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
            'aktif'         => true,
        ]);

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

        if ($request->hasFile('gambar')) {
            Storage::disk('public')->delete($banner->gambar);
            $banner->gambar = $request->file('gambar')->store('banner', 'public');
        }

        if ($request->hasFile('gambar_mobile')) {

            if ($banner->gambar_mobile) {
                Storage::disk('public')
                    ->delete($banner->gambar_mobile);
            }

            $banner->gambar_mobile = $request
                ->file('gambar_mobile')
                ->store('banner', 'public');
        }

        $banner->judul     = $request->judul;
        $banner->deskripsi = $request->deskripsi;
        $banner->tautan    = $request->tautan;
        $banner->save();

        return redirect()->route('admin.banner.index')
            ->with('success', 'Banner berhasil diperbarui.');
    }

    public function destroy(Banner $banner)
    {
        Storage::disk('public')->delete($banner->gambar);
        $banner->delete();

        return redirect()->route('admin.banner.index')
            ->with('success', 'Banner berhasil dihapus.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bahan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BahanController extends Controller
{
    public function index()
    {
        $bahan = Bahan::withCount('produk')->orderBy('nama_bahan')->get();
        return view('admin.bahan.index', compact('bahan'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_bahan' => 'required|string|max:255|unique:bahan,nama_bahan',
            'deskripsi'  => 'nullable|string',
        ], [
            'nama_bahan.unique' => 'Bahan ini sudah ada di daftar.',
        ]);

        Bahan::create($request->only('nama_bahan', 'deskripsi'));

        return redirect()->route('admin.bahan.index')
                         ->with('success', 'Bahan berhasil ditambahkan.');
    }

    public function edit(Bahan $bahan)
    {
        return view('admin.bahan.edit', compact('bahan'));
    }

    public function update(Request $request, Bahan $bahan)
    {
        $request->validate([
            'nama_bahan' => ['required', 'string', 'max:255', Rule::unique('bahan', 'nama_bahan')->ignore($bahan->id)],
            'deskripsi'  => 'nullable|string',
        ], [
            'nama_bahan.unique' => 'Bahan ini sudah ada di daftar.',
        ]);

        $bahan->update($request->only('nama_bahan', 'deskripsi'));

        return redirect()->route('admin.bahan.index')
                         ->with('success', 'Bahan berhasil diperbarui.');
    }

    public function destroy(Bahan $bahan)
    {
        // Relasi ke produk ikut terhapus (cascade di tabel produk_bahan)
        $bahan->delete();

        return redirect()->route('admin.bahan.index')
                         ->with('success', 'Bahan berhasil dihapus.');
    }
}

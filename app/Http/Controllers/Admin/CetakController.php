<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cetak;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CetakController extends Controller
{
    public function index()
    {
        $cetak = Cetak::withCount('produk')->orderBy('nama_cetak')->get();
        return view('admin.cetak.index', compact('cetak'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_cetak' => 'required|string|max:255|unique:cetak,nama_cetak',
            'deskripsi'  => 'nullable|string',
        ], [
            'nama_cetak.unique' => 'Jenis cetak ini sudah ada di daftar.',
        ]);

        Cetak::create($request->only('nama_cetak', 'deskripsi'));

        return redirect()->route('admin.cetak.index')
                         ->with('success', 'Jenis cetak berhasil ditambahkan.');
    }

    public function edit(Cetak $cetak)
    {
        return view('admin.cetak.edit', compact('cetak'));
    }

    public function update(Request $request, Cetak $cetak)
    {
        $request->validate([
            'nama_cetak' => ['required', 'string', 'max:255', Rule::unique('cetak', 'nama_cetak')->ignore($cetak->id)],
            'deskripsi'  => 'nullable|string',
        ], [
            'nama_cetak.unique' => 'Jenis cetak ini sudah ada di daftar.',
        ]);

        $cetak->update($request->only('nama_cetak', 'deskripsi'));

        return redirect()->route('admin.cetak.index')
                         ->with('success', 'Jenis cetak berhasil diperbarui.');
    }

    public function destroy(Cetak $cetak)
    {
        // Relasi ke produk ikut terhapus (cascade di tabel produk_cetak)
        $cetak->delete();

        return redirect()->route('admin.cetak.index')
                         ->with('success', 'Jenis cetak berhasil dihapus.');
    }
}

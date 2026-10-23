<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Finishing;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FinishingController extends Controller
{
    public function index()
    {
        $finishing = Finishing::withCount('produk')->orderBy('nama_finishing')->get();
        return view('admin.finishing.index', compact('finishing'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_finishing' => 'required|string|max:255|unique:finishing,nama_finishing',
            'deskripsi'      => 'nullable|string',
        ], [
            'nama_finishing.unique' => 'Finishing ini sudah ada di daftar.',
        ]);

        Finishing::create($request->only('nama_finishing', 'deskripsi'));

        return redirect()->route('admin.finishing.index')
                         ->with('success', 'Finishing berhasil ditambahkan.');
    }

    public function edit(Finishing $finishing)
    {
        return view('admin.finishing.edit', compact('finishing'));
    }

    public function update(Request $request, Finishing $finishing)
    {
        $request->validate([
            'nama_finishing' => ['required', 'string', 'max:255', Rule::unique('finishing', 'nama_finishing')->ignore($finishing->id)],
            'deskripsi'      => 'nullable|string',
        ], [
            'nama_finishing.unique' => 'Finishing ini sudah ada di daftar.',
        ]);

        $finishing->update($request->only('nama_finishing', 'deskripsi'));

        return redirect()->route('admin.finishing.index')
                         ->with('success', 'Finishing berhasil diperbarui.');
    }

    public function destroy(Finishing $finishing)
    {
        // Relasi ke produk ikut terhapus (cascade di tabel produk_finishing)
        $finishing->delete();

        return redirect()->route('admin.finishing.index')
                         ->with('success', 'Finishing berhasil dihapus.');
    }
}

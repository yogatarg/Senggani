<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Peran;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordPenggunaRequest;
use App\Http\Requests\SimpanPenggunaRequest;
use App\Models\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * Kelola pengguna panel (khusus admin). Pengguna dinonaktifkan, bukan dihapus,
 * supaya riwayat order tetap punya nama pelaku.
 */
class PenggunaController extends Controller
{
    public function index()
    {
        Gate::authorize('kelola-pengguna');

        $pengguna = Admin::orderByDesc('aktif')->orderBy('nama')->get();

        return view('admin.pengguna.index', [
            'pengguna'   => $pengguna,
            'daftarPeran' => Peran::pilihan(),
            'idSaya'     => Auth::id(),
        ]);
    }

    public function create()
    {
        Gate::authorize('kelola-pengguna');

        return view('admin.pengguna.create', [
            'pengguna'    => new Admin(['peran' => Peran::Produksi->value]),
            'daftarPeran' => Peran::pilihan(),
        ]);
    }

    public function store(SimpanPenggunaRequest $request)
    {
        $data = $request->validated();

        $pengguna = Admin::create([
            'nama'     => $data['nama'],
            'username' => $data['username'] ?? null,
            'email'    => $data['email'] ?? null,
            'peran'    => $data['peran'],
            'aktif'    => true,
            'password' => $data['password'],
        ]);

        return redirect()->route('admin.pengguna.index')
            ->with('success', "Pengguna {$pengguna->nama} berhasil ditambahkan.");
    }

    public function edit(Admin $pengguna)
    {
        Gate::authorize('kelola-pengguna');

        return view('admin.pengguna.edit', [
            'pengguna'    => $pengguna,
            'daftarPeran' => Peran::pilihan(),
            'akunSendiri' => $pengguna->is(Auth::user()),
        ]);
    }

    public function update(SimpanPenggunaRequest $request, Admin $pengguna)
    {
        $data = $request->validated();

        $pengguna->update([
            'nama'     => $data['nama'],
            'username' => $data['username'] ?? null,
            'email'    => $data['email'] ?? null,
            'peran'    => $data['peran'],
        ]);

        return redirect()->route('admin.pengguna.index')
            ->with('success', "Data {$pengguna->nama} berhasil diperbarui.");
    }

    public function resetPassword(ResetPasswordPenggunaRequest $request, Admin $pengguna)
    {
        $pengguna->update(['password' => $request->validated('password')]);

        return redirect()->route('admin.pengguna.index')
            ->with('success', "Password {$pengguna->nama} berhasil diganti. Sampaikan password baru ke penggunanya.");
    }

    /** Aktifkan/nonaktifkan. Admin tidak bisa menonaktifkan dirinya sendiri. */
    public function ubahAktif(Admin $pengguna)
    {
        Gate::authorize('kelola-pengguna');

        if ($pengguna->is(Auth::user())) {
            return redirect()->route('admin.pengguna.index')
                ->with('error', 'Anda tidak bisa menonaktifkan akun Anda sendiri.');
        }

        $pengguna->update(['aktif' => ! $pengguna->aktif]);

        $pesan = $pengguna->aktif
            ? "Akun {$pengguna->nama} diaktifkan kembali."
            : "Akun {$pengguna->nama} dinonaktifkan. Ia tidak bisa masuk lagi sampai diaktifkan.";

        return redirect()->route('admin.pengguna.index')->with('success', $pesan);
    }
}

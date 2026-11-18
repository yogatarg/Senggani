<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UbahPasswordRequest;
use Illuminate\Support\Facades\Auth;

/** Ubah password sendiri (admin & produksi). */
class PasswordController extends Controller
{
    public function edit()
    {
        return view('admin.password.edit', ['pengguna' => Auth::user()]);
    }

    public function update(UbahPasswordRequest $request)
    {
        Auth::user()->update(['password' => $request->validated('password')]);

        // ID session baru setelah ganti password
        $request->session()->regenerate();

        return redirect()->route('admin.pesanan.index')
            ->with('success', 'Password berhasil diubah.');
    }
}

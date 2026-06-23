<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // Tampilkan halaman login
    public function showLogin()
    {
        // Jika sudah login, langsung ke dashboard
        if (session('admin_logged_in')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    // Proses login
    public function login(Request $request)
    {
        $request->validate([
            'email'      => 'required|email',
            'password' => 'required|string',
        ]);

        // Cari admin berdasarkan email
        $admin = Admin::where('email', $request->email)->first();

        // Cek apakah admin ada dan password cocok
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Email atau kata sandi salah.');
        }

        // Simpan data login ke session
        session([
            'admin_logged_in' => true,
            'admin_id'        => $admin->id,
            'admin_nama'      => $admin->nama,
            'admin_email'     => $admin->email,
        ]);

        return redirect()->route('admin.dashboard')
                         ->with('success', 'Selamat datang, ' . $admin->nama . '!');
    }

    // Logout
    public function logout(Request $request)
    {
        $request->session()->forget([
            'admin_logged_in',
            'admin_id',
            'admin_nama',
            'admin_email',
        ]);

        return redirect()->route('admin.login')
                         ->with('success', 'Anda berhasil logout.');
    }
}
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

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

        // Batasi percobaan login: maks. 5x gagal per menit per email + IP
        $kunciLimit = 'login-admin:' . Str::lower($request->email) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($kunciLimit, 5)) {
            $detik = RateLimiter::availableIn($kunciLimit);

            return back()
                ->withInput($request->only('email'))
                ->with('error', "Terlalu banyak percobaan login. Coba lagi dalam {$detik} detik.");
        }

        // Cari admin berdasarkan email
        $admin = Admin::where('email', $request->email)->first();

        // Cek apakah admin ada dan password cocok
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            RateLimiter::hit($kunciLimit, 60);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Email atau kata sandi salah.');
        }

        RateLimiter::clear($kunciLimit);

        // Buat ID session baru agar session lama tidak bisa dipakai (session fixation)
        $request->session()->regenerate();

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
        // Hapus seluruh session & buat token CSRF baru
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')
                         ->with('success', 'Anda berhasil logout.');
    }
}
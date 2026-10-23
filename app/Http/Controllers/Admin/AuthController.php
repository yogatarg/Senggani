<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Batasi percobaan login:
        // - maks. 5x gagal per menit per email + IP
        // - maks. 20x gagal per jam per email (dari IP mana pun)
        $kunciLimit      = 'login-admin:' . Str::lower($request->email) . '|' . $request->ip();
        $kunciLimitEmail = 'login-admin-email:' . Str::lower($request->email);

        foreach ([$kunciLimit => 5, $kunciLimitEmail => 20] as $kunci => $maks) {
            if (RateLimiter::tooManyAttempts($kunci, $maks)) {
                $detik = RateLimiter::availableIn($kunci);

                return back()
                    ->withInput($request->only('email'))
                    ->with('error', "Terlalu banyak percobaan login. Coba lagi dalam {$detik} detik.");
            }
        }

        // Cari admin berdasarkan email
        $admin = Admin::where('email', $request->email)->first();

        // Cek password. Bila email tidak ditemukan, tetap jalankan Hash::check
        // terhadap hash dummy agar waktu respons sama (email tidak bisa ditebak dari lamanya respons).
        $passwordCocok = Hash::check($request->password, $admin ? $admin->password : $this->hashDummy());

        if (!$admin || !$passwordCocok) {
            RateLimiter::hit($kunciLimit, 60);
            RateLimiter::hit($kunciLimitEmail, 3600);

            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Email atau kata sandi salah.');
        }

        RateLimiter::clear($kunciLimit);
        RateLimiter::clear($kunciLimitEmail);

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

    // Hash acak dengan biaya (rounds) yang sama dengan password admin asli
    private function hashDummy(): string
    {
        $hash = Cache::rememberForever('login-admin:hash-dummy', fn () => Hash::make(Str::random(40)));

        // Bila pengaturan rounds berubah, buat ulang agar waktunya tetap setara
        if (Hash::needsRehash($hash)) {
            $hash = Hash::make(Str::random(40));
            Cache::forever('login-admin:hash-dummy', $hash);
        }

        return $hash;
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
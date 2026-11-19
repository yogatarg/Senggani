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
        // Jika sudah login, langsung ke halaman awal sesuai peran
        if (session('admin_logged_in')) {
            return redirect()->route(self::ruteAwal(session('admin_peran')));
        }

        return view('admin.auth.login');
    }

    /** Halaman pertama setelah login: semua peran (admin & produksi) ke daftar Pesanan. */
    public static function ruteAwal(?string $peran): string
    {
        return 'admin.pesanan.index';
    }

    // Proses login. Field "login" berisi email ATAU username (field lama "email" tetap diterima).
    public function login(Request $request)
    {
        $request->merge(['login' => trim((string) ($request->input('login') ?? $request->input('email')))]);

        $request->validate([
            'login'    => 'required|string|max:255',
            'password' => 'required|string',
        ], [
            'login.required'    => 'Email atau username wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
        ]);

        $identitas = Str::lower($request->input('login'));

        // Batasi percobaan login:
        // - maks. 5x gagal per menit per email/username + IP
        // - maks. 20x gagal per jam per email/username (dari IP mana pun)
        $kunciLimit      = 'login-admin:' . $identitas . '|' . $request->ip();
        $kunciLimitEmail = 'login-admin-email:' . $identitas;

        foreach ([$kunciLimit => 5, $kunciLimitEmail => 20] as $kunci => $maks) {
            if (RateLimiter::tooManyAttempts($kunci, $maks)) {
                $detik = RateLimiter::availableIn($kunci);

                return back()
                    ->withInput($request->only('login'))
                    ->with('error', "Terlalu banyak percobaan login. Coba lagi dalam {$detik} detik.");
            }
        }

        // Ada "@" berarti email; selain itu username (username tidak boleh berisi "@")
        $admin = str_contains($identitas, '@')
            ? Admin::where('email', $identitas)->first()
            : Admin::where('username', $identitas)->first();

        // Cek password. Bila akun tidak ditemukan, tetap jalankan Hash::check
        // terhadap hash dummy agar waktu respons sama (akun tidak bisa ditebak dari lamanya respons).
        $passwordCocok = Hash::check($request->password, $admin ? $admin->password : $this->hashDummy());

        if (!$admin || !$passwordCocok) {
            RateLimiter::hit($kunciLimit, 60);
            RateLimiter::hit($kunciLimitEmail, 3600);

            return back()
                ->withInput($request->only('login'))
                ->with('error', 'Email atau kata sandi salah.');
        }

        // Akun nonaktif tidak bisa masuk (pesan ini hanya terlihat bila password benar)
        if (!$admin->aktif) {
            return back()
                ->withInput($request->only('login'))
                ->with('error', 'Akun Anda sudah dinonaktifkan. Hubungi admin.');
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
            'admin_username'  => $admin->username,
            'admin_peran'     => $admin->peran->value,
        ]);

        return redirect()->route(self::ruteAwal($admin->peran->value))
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
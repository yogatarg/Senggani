<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // Cek apakah admin sudah login
        if (!session('admin_logged_in')) {
            return $this->keLogin($request, 'Silakan login terlebih dahulu.');
        }

        // Pastikan akun di session masih ada di database dan masih aktif.
        // Bila akun sudah dihapus atau dinonaktifkan, session dianggap tidak berlaku lagi.
        $adminId = session('admin_id');
        $admin   = $adminId ? Admin::find($adminId) : null;

        if (!$admin) {
            $this->buangSession($request);

            return $this->keLogin($request, 'Silakan login terlebih dahulu.');
        }

        if (!$admin->aktif) {
            $this->buangSession($request);

            return $this->keLogin($request, 'Akun Anda sudah dinonaktifkan. Hubungi admin.');
        }

        // Peran & nama selalu mengikuti database, jadi perubahan oleh admin langsung berlaku
        session([
            'admin_nama'  => $admin->nama,
            'admin_peran' => $admin->peran->value,
        ]);

        // Pengguna tersedia untuk Gate/Policy (@can, Gate::authorize) selama request ini saja
        Auth::setUser($admin);

        return $next($request);
    }

    private function buangSession(Request $request): void
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function keLogin(Request $request, string $pesan)
    {
        if ($request->expectsJson()) {
            return response()->json(['pesan' => $pesan], 401);
        }

        return redirect()->route('admin.login')->with('error', $pesan);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Http\Request;

class AuthAdmin
{
    public function handle(Request $request, Closure $next)
    {
        // Cek apakah admin sudah login
        if (!session('admin_logged_in')) {
            return redirect()->route('admin.login')
                             ->with('error', 'Silakan login terlebih dahulu.');
        }

        // Pastikan akun admin di session masih ada di database.
        // Bila akun sudah dihapus, session dianggap tidak berlaku lagi.
        $adminId = session('admin_id');

        if (!$adminId || !Admin::whereKey($adminId)->exists()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')
                             ->with('error', 'Silakan login terlebih dahulu.');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Batasi rute ke peran tertentu. Dipakai setelah auth.admin, mis. ->middleware('peran:admin').
 * Pengguna dengan peran lain: GET diarahkan ke daftar Pesanan, selain itu 403.
 */
class CekPeran
{
    public function handle(Request $request, Closure $next, string ...$peran)
    {
        $pengguna = Auth::user();

        if ($pengguna && in_array($pengguna->peran->value, $peran, true)) {
            return $next($request);
        }

        $pesan = 'Anda tidak punya akses ke halaman tersebut.';

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return redirect()->route('admin.pesanan.index')->with('error', $pesan);
        }

        abort(403, $pesan);
    }
}

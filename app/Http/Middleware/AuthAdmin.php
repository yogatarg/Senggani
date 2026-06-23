<?php

namespace App\Http\Middleware;

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

        return $next($request);
    }
}
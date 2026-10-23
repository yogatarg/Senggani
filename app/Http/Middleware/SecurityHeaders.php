<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons.
 * Sengaja tidak memasang CSP selain frame-ancestors agar aset CDN tidak terblokir.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS hanya berarti bila diakses lewat HTTPS
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // Anti-clickjacking hanya di produksi: di lokal panel admin
        // masih diuji lewat pratinjau HP berbasis iframe.
        if (app()->environment('production')) {
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'");
        }

        return $response;
    }
}

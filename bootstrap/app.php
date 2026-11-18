<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {

        // Daftarkan middleware auth.admin
        $middleware->alias([
            'auth.admin' => \App\Http\Middleware\AuthAdmin::class,
            'peran'      => \App\Http\Middleware\CekPeran::class,
        ]);

        // Header keamanan di semua respons
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

    })
    ->withExceptions(function (Exceptions $exceptions) {

        // 403/404 di panel /admin (sudah login): redirect ke daftar SPP / JSON berbahasa Indonesia,
        // bukan halaman error bawaan. Halaman error publik tidak berubah.
        \App\Exceptions\GalatPanelAdmin::daftarkan($exceptions);

    })->create();
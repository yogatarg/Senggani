<?php

namespace App\Providers;

use App\Models\Admin;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Peran "admin" (CS): katalog, kelola pengguna, input & ubah order.
        // Pesanan memakai App\Policies\PesananPolicy (ditemukan otomatis).
        Gate::define('admin', fn (Admin $pengguna) => $pengguna->isAdmin());
        Gate::define('kelola-pengguna', fn (Admin $pengguna) => $pengguna->isAdmin());
    }
}

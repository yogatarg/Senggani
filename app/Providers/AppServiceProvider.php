<?php

namespace App\Providers;

use App\Models\Admin;
use App\Services\NotifikasiDeadline;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        // Nama hari/bulan berbahasa Indonesia untuk translatedFormat() (mis. "18 Nov").
        // Hanya locale Carbon; locale aplikasi & pesan validasi tidak diubah.
        \Illuminate\Support\Carbon::setLocale('id');

        // Peran "admin" (CS): katalog, kelola pengguna, input & ubah order.
        // Pesanan memakai App\Policies\PesananPolicy (ditemukan otomatis).
        Gate::define('admin', fn (Admin $pengguna) => $pengguna->isAdmin());
        Gate::define('kelola-pengguna', fn (Admin $pengguna) => $pengguna->isAdmin());
        Gate::define('kelola-customer', fn (Admin $pengguna) => $pengguna->isAdmin());

        // Lonceng notifikasi deadline di layout modul Pesanan (admin & produksi):
        // $notifDeadline = ['total', 'lewat', 'mendekat', 'item' => maks 20 entri] (lihat NotifikasiDeadline).
        View::composer('admin.layouts.pesanan', function ($view) {
            $view->with('notifDeadline', Auth::user() instanceof Admin
                ? app(NotifikasiDeadline::class)->ambil()
                : ['total' => 0, 'lewat' => 0, 'mendekat' => 0, 'item' => []]);
        });
    }
}

<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Pesanan;

/**
 * Hak akses modul Pesanan.
 * - Admin: semua aksi.
 * - Produksi: lihat semua order (termasuk harga), ubah status maju/mundur satu langkah,
 *   tambah catatan progres, lihat gambar. Tidak bisa membuat/mengubah/menghapus order,
 *   membatalkan/memulihkan, mencatat lunas, atau menghapus gambar.
 */
class PesananPolicy
{
    public function viewAny(Admin $pengguna): bool
    {
        return true;
    }

    public function view(Admin $pengguna, Pesanan $pesanan): bool
    {
        return true;
    }

    public function ubahStatus(Admin $pengguna, Pesanan $pesanan): bool
    {
        return true;
    }

    public function tambahCatatan(Admin $pengguna, Pesanan $pesanan): bool
    {
        return true;
    }

    public function create(Admin $pengguna): bool
    {
        return $pengguna->isAdmin();
    }

    public function update(Admin $pengguna, Pesanan $pesanan): bool
    {
        return $pengguna->isAdmin();
    }

    public function delete(Admin $pengguna, Pesanan $pesanan): bool
    {
        return $pengguna->isAdmin();
    }

    public function batalkan(Admin $pengguna, Pesanan $pesanan): bool
    {
        return $pengguna->isAdmin();
    }

    public function pulihkan(Admin $pengguna, Pesanan $pesanan): bool
    {
        return $pengguna->isAdmin();
    }

    public function catatLunas(Admin $pengguna, Pesanan $pesanan): bool
    {
        return $pengguna->isAdmin();
    }

    public function hapusFile(Admin $pengguna, Pesanan $pesanan): bool
    {
        return $pengguna->isAdmin();
    }
}

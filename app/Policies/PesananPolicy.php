<?php

namespace App\Policies;

use App\Enums\StatusProduksi;
use App\Exceptions\AksiDitolak;
use App\Models\Admin;
use App\Models\Pesanan;
use Illuminate\Auth\Access\Response;

/**
 * Hak akses modul Pesanan.
 * - Admin: semua aksi.
 * - Produksi: lihat semua order (termasuk harga), isi harga selama status hitung_harga,
 *   ubah status maju/mundur satu langkah (aturan alur di PesananService::ubahStatus),
 *   tambah catatan progres, lihat gambar. Tidak bisa membuat/mengubah/menghapus order,
 *   mencatat DP/lunas, menandai tidak jadi, membatalkan/memulihkan, atau menghapus gambar.
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

    /** Isi/ubah harga item: admin kapan saja, produksi hanya saat status hitung_harga. */
    public function isiHarga(Admin $pengguna, Pesanan $pesanan): Response
    {
        if ($pengguna->isAdmin() || $pesanan->status_produksi === StatusProduksi::HitungHarga) {
            return Response::allow();
        }

        return Response::deny(AksiDitolak::HARGA_DIKUNCI);
    }

    public function catatDp(Admin $pengguna, Pesanan $pesanan): bool
    {
        return $pengguna->isAdmin();
    }

    public function tidakJadi(Admin $pengguna, Pesanan $pesanan): bool
    {
        return $pengguna->isAdmin();
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

    /**
     * Unduh invoice PDF: khusus admin, dan hanya untuk status Menunggu DP s.d. Selesai
     * (Hitung Harga: harga belum lengkap; Tidak Jadi/Dibatalkan: tidak ada tagihan).
     */
    public function unduhInvoice(Admin $pengguna, Pesanan $pesanan): Response
    {
        if (! $pengguna->isAdmin()) {
            return Response::deny('Invoice hanya untuk admin.');
        }

        if (! $pesanan->bisa_invoice) {
            return Response::deny($pesanan->status_produksi === StatusProduksi::HitungHarga
                ? 'Invoice tersedia setelah semua harga terisi.'
                : 'Invoice tidak tersedia untuk SPP berstatus ' . $pesanan->status_produksi->label() . '.');
        }

        return Response::allow();
    }
}

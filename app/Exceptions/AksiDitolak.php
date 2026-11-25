<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Aksi SPP ditolak karena aturan alur (mis. dikirim sebelum lunas). Controller
 * mengubahnya menjadi flash 'error' (HTML) atau JSON 422 {"pesan": ...}.
 */
class AksiDitolak extends RuntimeException
{
    public const BELUM_LUNAS = 'Belum lunas — minta admin mencatat pelunasan dulu.';

    // Untuk pengguna yang bisa mencatat lunas sendiri (admin)
    public const BELUM_LUNAS_ADMIN = 'Belum lunas — catat pelunasan dulu untuk mengirim.';

    /** Teks "belum lunas" sesuai pengguna: admin diminta mencatat sendiri, produksi diminta menghubungi admin. */
    public static function teksBelumLunas(bool $bisaCatatLunas): string
    {
        return $bisaCatatLunas ? self::BELUM_LUNAS_ADMIN : self::BELUM_LUNAS;
    }

    public const HARGA_DIKUNCI = 'Harga hanya bisa diubah produksi selama status Hitung Harga.';
}

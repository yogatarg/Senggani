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

    public const HARGA_DIKUNCI = 'Harga hanya bisa diubah produksi selama status Hitung Harga.';
}

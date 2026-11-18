<?php

namespace App\Exceptions;

use App\Enums\StatusProduksi;
use RuntimeException;

/**
 * Status order sudah diubah orang lain sejak halaman dibuka (update bersyarat gagal).
 */
class StatusBentrok extends RuntimeException
{
    public function __construct(string $pesan, public readonly ?StatusProduksi $statusSekarang = null)
    {
        parent::__construct($pesan);
    }
}

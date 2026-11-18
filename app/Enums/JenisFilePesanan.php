<?php

namespace App\Enums;

/** Jenis gambar yang dilampirkan ke order (kolom pesanan_file.jenis). */
enum JenisFilePesanan: string
{
    case Mockup    = 'mockup';
    case Referensi = 'referensi';

    public function label(): string
    {
        return match ($this) {
            self::Mockup    => 'Mockup',
            self::Referensi => 'Referensi',
        };
    }
}

<?php

namespace App\Enums;

/** Status approval desain oleh customer (tidak memblokir status produksi). */
enum StatusDesain: string
{
    case BelumDikirim = 'belum_dikirim';
    case Menunggu     = 'menunggu';
    case Revisi       = 'revisi';
    case Disetujui    = 'disetujui';

    public function label(): string
    {
        return match ($this) {
            self::BelumDikirim => 'Belum Dikirim',
            self::Menunggu     => 'Menunggu Approval',
            self::Revisi       => 'Revisi',
            self::Disetujui    => 'Disetujui',
        };
    }

    /** @return array<string, string> nilai => label */
    public static function pilihan(): array
    {
        $hasil = [];
        foreach (self::cases() as $status) {
            $hasil[$status->value] = $status->label();
        }

        return $hasil;
    }
}

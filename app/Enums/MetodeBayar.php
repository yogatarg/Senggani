<?php

namespace App\Enums;

/** Metode pembayaran DP & pelunasan (kolom pesanan.metode_dp / metode_lunas). */
enum MetodeBayar: string
{
    case TransferBca = 'transfer_bca';
    case TransferBri = 'transfer_bri';
    case Qris        = 'qris';
    case Cash        = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Cash        => 'Cash',
            self::TransferBca => 'Transfer BCA',
            self::TransferBri => 'Transfer BRI',
            self::Qris        => 'QRIS',
        };
    }

    /** Ikon Font Awesome untuk ubin pilihan di modal (lihat rancangan invoice 5.2). */
    public function ikon(): string
    {
        return match ($this) {
            self::Cash                           => 'fa-money-bill-wave',
            self::TransferBca, self::TransferBri => 'fa-building-columns',
            self::Qris                           => 'fa-qrcode',
        };
    }

    /** @return array<string, string> nilai => label, urutan tampil: BCA, BRI, QRIS, Cash */
    public static function pilihan(): array
    {
        $hasil = [];
        foreach (self::cases() as $m) {
            $hasil[$m->value] = $m->label();
        }

        return $hasil;
    }
}

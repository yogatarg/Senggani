<?php

namespace App\Enums;

/** Peran pengguna panel admin (kolom admin.peran). */
enum Peran: string
{
    case Admin    = 'admin';
    case Produksi = 'produksi';

    public function label(): string
    {
        return match ($this) {
            self::Admin    => 'Admin',
            self::Produksi => 'Produksi',
        };
    }

    /** @return array<string, string> nilai => label */
    public static function pilihan(): array
    {
        return [self::Admin->value => self::Admin->label(), self::Produksi->value => self::Produksi->label()];
    }
}

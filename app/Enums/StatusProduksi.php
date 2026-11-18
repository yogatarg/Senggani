<?php

namespace App\Enums;

/**
 * Status produksi order. Alur linear antrian → proses → selesai_produksi →
 * pengiriman → selesai; "dibatalkan" berada di luar urutan.
 */
enum StatusProduksi: string
{
    case Antrian         = 'antrian';
    case Proses          = 'proses';
    case SelesaiProduksi = 'selesai_produksi';
    case Pengiriman      = 'pengiriman';
    case Selesai         = 'selesai';
    case Dibatalkan      = 'dibatalkan';

    // Urutan langkah alur linear (dibatalkan tidak termasuk)
    public const ALUR = ['antrian', 'proses', 'selesai_produksi', 'pengiriman', 'selesai'];

    public function label(): string
    {
        return match ($this) {
            self::Antrian         => 'Antrian Produksi',
            self::Proses          => 'Dalam Proses',
            self::SelesaiProduksi => 'Selesai Produksi',
            self::Pengiriman      => 'Pengiriman',
            self::Selesai         => 'Selesai',
            self::Dibatalkan      => 'Dibatalkan',
        };
    }

    /** Posisi 1..5 di alur, null untuk dibatalkan. */
    public function urutan(): ?int
    {
        $i = array_search($this->value, self::ALUR, true);

        return $i === false ? null : $i + 1;
    }

    public function berikutnya(): ?self
    {
        $i = array_search($this->value, self::ALUR, true);

        return $i === false || ! isset(self::ALUR[$i + 1]) ? null : self::from(self::ALUR[$i + 1]);
    }

    public function sebelumnya(): ?self
    {
        $i = array_search($this->value, self::ALUR, true);

        return $i === false || $i === 0 ? null : self::from(self::ALUR[$i - 1]);
    }

    /** Masuk tab History (arsip). */
    public function riwayat(): bool
    {
        return $this === self::Selesai || $this === self::Dibatalkan;
    }

    /** Boleh dibatalkan: semua status aktif (antrian s.d. pengiriman). */
    public function bisaDibatalkan(): bool
    {
        return ! $this->riwayat();
    }

    /** Penanda deadline hanya berlaku untuk order yang belum selesai produksi. */
    public function belumSelesaiProduksi(): bool
    {
        return $this === self::Antrian || $this === self::Proses;
    }

    /** @return list<string> */
    public static function nilaiRiwayat(): array
    {
        return [self::Selesai->value, self::Dibatalkan->value];
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

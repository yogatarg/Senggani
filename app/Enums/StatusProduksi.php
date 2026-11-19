<?php

namespace App\Enums;

/**
 * Status SPP. Alur linear:
 * hitung_harga → menunggu_dp → proses → selesai_produksi → pengiriman → selesai.
 * Di luar urutan: tidak_jadi (batal sebelum produksi) dan dibatalkan (batal setelah produksi dimulai).
 *
 * "antrian" sudah dihapus dari alur (digabung ke proses). Nilainya tetap ada di enum
 * HANYA supaya riwayat lama masih terbaca dengan label "Antrian Produksi".
 */
enum StatusProduksi: string
{
    case HitungHarga     = 'hitung_harga';
    case MenungguDp      = 'menunggu_dp';
    case Proses          = 'proses';
    case SelesaiProduksi = 'selesai_produksi';
    case Pengiriman      = 'pengiriman';
    case Selesai         = 'selesai';
    case TidakJadi       = 'tidak_jadi';
    case Dibatalkan      = 'dibatalkan';

    /** @deprecated Hanya untuk riwayat lama; tidak dipakai lagi oleh SPP mana pun. */
    case Antrian         = 'antrian';

    // Urutan langkah alur linear (tidak_jadi/dibatalkan/antrian tidak termasuk)
    public const ALUR = ['hitung_harga', 'menunggu_dp', 'proses', 'selesai_produksi', 'pengiriman', 'selesai'];

    // Status aktif (tab "Semua")
    public const AKTIF = ['hitung_harga', 'menunggu_dp', 'proses', 'selesai_produksi', 'pengiriman'];

    // Status yang dipantau deadline-nya (penanda & notifikasi)
    public const PANTAU_DEADLINE = ['hitung_harga', 'menunggu_dp', 'proses', 'selesai_produksi'];

    public function label(): string
    {
        return match ($this) {
            self::HitungHarga     => 'Hitung Harga',
            self::MenungguDp      => 'Menunggu DP',
            self::Proses          => 'Dalam Proses',
            self::SelesaiProduksi => 'Pesanan Selesai',
            self::Pengiriman      => 'Pengiriman',
            self::Selesai         => 'Selesai',
            self::TidakJadi       => 'Tidak Jadi',
            self::Dibatalkan      => 'Dibatalkan',
            self::Antrian         => 'Antrian Produksi',
        };
    }

    /** Posisi 1..6 di alur, null untuk tidak_jadi/dibatalkan/antrian. */
    public function urutan(): ?int
    {
        $i = array_search($this->value, self::ALUR, true);

        return $i === false ? null : $i + 1;
    }

    /**
     * Posisi untuk membandingkan maju/mundur di riwayat. Antrian lama berada di antara
     * menunggu_dp dan proses (dulu langkah pertama sebelum proses).
     */
    public function posisi(): ?float
    {
        return $this === self::Antrian ? 2.5 : $this->urutan();
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

    /** Masuk tab History (arsip): selesai, tidak jadi, dibatalkan. */
    public function riwayat(): bool
    {
        return in_array($this, [self::Selesai, self::TidakJadi, self::Dibatalkan], true);
    }

    /** Belum masuk produksi (harga/DP belum beres): boleh "Tidak Jadi". */
    public function sebelumProduksi(): bool
    {
        return $this === self::HitungHarga || $this === self::MenungguDp;
    }

    /** Produksi sudah dimulai dan belum selesai: boleh "Dibatalkan". */
    public function bisaDibatalkan(): bool
    {
        return in_array($this, [self::Proses, self::SelesaiProduksi, self::Pengiriman], true);
    }

    /** Status yang diberi penanda deadline & masuk notifikasi deadline. */
    public function pantauDeadline(): bool
    {
        return in_array($this->value, self::PANTAU_DEADLINE, true);
    }

    /** Sudah masuk produksi (DP tercatat): pembayaran/pelunasan boleh dicatat. */
    public function sudahProduksi(): bool
    {
        return in_array($this, [self::Proses, self::SelesaiProduksi, self::Pengiriman, self::Selesai, self::Dibatalkan], true);
    }

    /** @return list<string> */
    public static function nilaiRiwayat(): array
    {
        return [self::Selesai->value, self::TidakJadi->value, self::Dibatalkan->value];
    }

    /** @return array<string, string> nilai => label (tanpa status lama "antrian") */
    public static function pilihan(): array
    {
        $hasil = [];
        foreach (self::cases() as $status) {
            if ($status !== self::Antrian) {
                $hasil[$status->value] = $status->label();
            }
        }

        return $hasil;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan aplikasi kunci–nilai (nilai disimpan JSON). Akses lewat layanan
 * (mis. App\Services\PengaturanInvoice), bukan langsung dari view.
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $fillable = ['kunci', 'nilai'];

    protected function casts(): array
    {
        return ['nilai' => 'json'];
    }

    /** Nilai banyak kunci sekaligus: [kunci => nilai]; kunci yang belum ada tidak ikut. */
    public static function ambil(array $kunci): array
    {
        return static::query()->whereIn('kunci', $kunci)->pluck('nilai', 'kunci')->all();
    }

    public static function simpan(string $kunci, mixed $nilai): void
    {
        static::query()->updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
    }
}

<?php

namespace App\Models;

use App\Enums\StatusProduksi;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Linimasa order: perubahan status dan catatan progres. Tidak pernah diedit.
 * nama_pengguna = salinan nama pelaku saat itu (tetap terbaca bila akun dihapus).
 */
class PesananRiwayat extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pesanan_riwayat';

    protected $fillable = [
        'pesanan_id', 'admin_id', 'nama_pengguna', 'status_dari', 'status_ke', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'status_dari' => StatusProduksi::class,
            'status_ke'   => StatusProduksi::class,
            'created_at'  => 'datetime',
        ];
    }

    public function pesanan()
    {
        return $this->belongsTo(Pesanan::class);
    }

    public function pengguna()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    /**
     * Jenis entri untuk memilih ikon di timeline:
     * dibuat | maju | mundur | batal | tidak_jadi | pulihkan | catatan.
     * Riwayat lama dengan status "antrian" tetap terbaca (label "Antrian Produksi").
     */
    protected function jenis(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->status_ke) {
                return 'catatan';
            }
            if (! $this->status_dari) {
                return 'dibuat';
            }
            if ($this->status_ke === StatusProduksi::Dibatalkan) {
                return 'batal';
            }
            if ($this->status_ke === StatusProduksi::TidakJadi) {
                return 'tidak_jadi';
            }
            if ($this->status_dari === StatusProduksi::Dibatalkan || $this->status_dari === StatusProduksi::TidakJadi) {
                return 'pulihkan';
            }

            return (float) $this->status_ke->posisi() > (float) $this->status_dari->posisi() ? 'maju' : 'mundur';
        });
    }
}

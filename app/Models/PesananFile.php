<?php

namespace App\Models;

use App\Enums\JenisFilePesanan;
use Illuminate\Database\Eloquent\Model;

/**
 * Gambar mockup/referensi order. Disimpan di disk "local" (storage/app/private),
 * hanya bisa diakses lewat rute admin.pesanan.file.lihat yang wajib login.
 */
class PesananFile extends Model
{
    public const DISK = 'local';

    public const MAKS_PER_ORDER = 10;

    public const MAKS_KB = 5120;

    protected $table = 'pesanan_file';

    protected $fillable = [
        'pesanan_id', 'jenis', 'path', 'nama_asli', 'mime', 'ukuran', 'diunggah_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jenis'  => JenisFilePesanan::class,
            'ukuran' => 'integer',
        ];
    }

    public function pesanan()
    {
        return $this->belongsTo(Pesanan::class);
    }

    public function pengunggah()
    {
        return $this->belongsTo(Admin::class, 'diunggah_oleh');
    }
}

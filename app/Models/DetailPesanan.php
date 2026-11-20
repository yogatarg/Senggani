<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu item order. Semua spesifikasi berupa teks snapshot (tanpa FK ke master),
 * jadi mengubah master tidak mengubah order lama. subtotal dihitung server.
 */
class DetailPesanan extends Model
{
    use HasFactory;

    protected $table = 'detail_pesanan';

    protected $fillable = [
        'pesanan_id', 'urutan', 'jenis_packaging', 'panjang', 'lebar', 'tinggi',
        'jenis_bahan', 'gramasi', 'jenis_cetak', 'warna_cetak', 'finishing',
        'aksesori', 'catatan', 'jumlah', 'harga_satuan', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'panjang'      => 'decimal:2',
            'lebar'        => 'decimal:2',
            'tinggi'       => 'decimal:2',
            'jumlah'       => 'integer',
            'harga_satuan' => 'decimal:2',
            'subtotal'     => 'decimal:2',
        ];
    }

    public function pesanan()
    {
        return $this->belongsTo(Pesanan::class);
    }

    /** Ukuran untuk tampilan, mis. "25 × 10 × 30 cm" / "12,5 × 10 cm" (bagian kosong dilewati). */
    protected function ukuranTeks(): Attribute
    {
        return Attribute::get(function () {
            $bagian = array_filter(
                [$this->panjang, $this->lebar, $this->tinggi],
                fn ($n) => $n !== null && $n !== '',
            );

            if (! $bagian) {
                return null;
            }

            // 25.00 → "25", 12.50 → "12,5" (koma desimal seperti teks SPP)
            $ringkas = fn ($n) => str_replace('.', ',', rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.'));

            return implode(' × ', array_map($ringkas, $bagian)) . ' cm';
        });
    }

    /** Finishing sebagai array (disimpan sebagai teks dipisah koma). */
    protected function daftarFinishing(): Attribute
    {
        return Attribute::get(fn () => $this->finishing
            ? array_values(array_filter(array_map('trim', explode(',', $this->finishing))))
            : []);
    }
}

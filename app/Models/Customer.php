<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Data induk customer. nomor_whatsapp (62xxx) unik.
 * Pesanan menyimpan salinan nama/WA/alamat/kota sendiri (snapshot), jadi mengubah
 * data customer tidak mengubah SPP lama.
 */
class Customer extends Model
{
    use HasFactory;

    protected $table = 'customer';

    protected $fillable = ['nama', 'nomor_whatsapp', 'alamat', 'kota'];

    public function pesanan()
    {
        return $this->hasMany(Pesanan::class);
    }

    /** Kode tampil dari id: 7 → "C-0007". */
    protected function kode(): Attribute
    {
        return Attribute::get(fn () => $this->id ? self::kodeDari($this->id) : null);
    }

    public static function kodeDari(int $id): string
    {
        return 'C-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
    }

    /** 6281234567890 → "+62 812-3456-7890" */
    protected function whatsappTampil(): Attribute
    {
        return Attribute::get(function () {
            $wa = (string) $this->nomor_whatsapp;
            if (! str_starts_with($wa, '62') || strlen($wa) <= 5) {
                return $wa;
            }
            $sisa = substr($wa, 2);

            return '+62 ' . substr($sisa, 0, 3) . '-' . implode('-', str_split(substr($sisa, 3), 4));
        });
    }

    /**
     * Cari nama, nomor WA (boleh 08…, +62…, spasi/strip), atau kode ("C-0007", "c7").
     */
    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        $kata = trim((string) $kata);
        if ($kata === '') {
            return $query;
        }

        $pola  = '%' . addcslashes($kata, '\\%_') . '%';
        $angka = preg_replace('/\D+/', '', $kata) ?? '';
        if (str_starts_with($angka, '0')) {
            $angka = '62' . substr($angka, 1);
        }

        return $query->where(function (Builder $q) use ($kata, $pola, $angka) {
            $q->where('nama', 'like', $pola);

            if (preg_match('/^c-?\s*0*(\d{1,9})$/i', $kata, $m)) {
                $q->orWhere('id', (int) $m[1]);
            }

            if (strlen($angka) >= 4) {
                $q->orWhere('nomor_whatsapp', 'like', '%' . $angka . '%');
            }
        });
    }
}

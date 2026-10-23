<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait SlugUnik
{
    /**
     * Buat slug dari teks. Jika slug sudah dipakai data lain,
     * tambahkan angka di belakang: box-kraft, box-kraft-2, box-kraft-3, ...
     */
    public static function slugUnik(string $teks, ?int $kecualiId = null): string
    {
        $dasar = Str::slug($teks) ?: 'item';
        $slug  = $dasar;
        $nomor = 2;

        while (static::where('slug', $slug)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId))
            ->exists()) {
            $slug = $dasar . '-' . $nomor++;
        }

        return $slug;
    }
}

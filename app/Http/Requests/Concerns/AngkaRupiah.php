<?php

namespace App\Http\Requests\Concerns;

/**
 * Ubah isian rupiah ("Rp 2.500", "2.500,00") menjadi string angka sebelum validasi.
 */
trait AngkaRupiah
{
    // "Rp 2.500" → "2500"; nilai kosong menjadi null agar aturan required/nullable bekerja
    protected function keAngka(mixed $nilai): mixed
    {
        if (! is_string($nilai)) {
            return $nilai;
        }

        $nilai = trim($nilai);
        if ($nilai === '') {
            return null;
        }

        // Buang sen ",00" bila ada, lalu semua pemisah ribuan
        $nilai = preg_replace('/,\d{1,2}$/', '', $nilai);
        $angka = preg_replace('/[^\d\-]/', '', $nilai);

        return $angka === '' ? $nilai : $angka;
    }
}

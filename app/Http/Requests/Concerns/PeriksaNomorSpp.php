<?php

namespace App\Http\Requests\Concerns;

use App\Models\Pesanan;
use Illuminate\Validation\Validator;

/**
 * Aturan nomor SPP bersama (form edit & Catat DP):
 * - unik termasuk SPP yang dihapus;
 * - nomor yang loncat jauh dari saran butuh konfirmasi_nomor=1.
 */
trait PeriksaNomorSpp
{
    // Batas kolom unsignedInteger yang masuk akal untuk nomor SPP
    protected const MAKS_NOMOR = 99_999_999;

    /** "#176" / "SPP #176" → 176. Format lain dibiarkan agar ditolak aturan integer. */
    protected function rapikanNomorSpp(): void
    {
        if ($this->has('nomor_spp') && is_string($this->input('nomor_spp'))) {
            $mentah = trim($this->input('nomor_spp'));
            $nomor  = Pesanan::angkaNomorSpp($mentah);
            $this->merge(['nomor_spp' => $nomor ?? ($mentah === '' ? null : $mentah)]);
        }
    }

    /** @return bool true bila nomor sudah dipakai SPP lain (galat ditambahkan) */
    protected function periksaNomorUnik(Validator $validator, int $nomor, ?Pesanan $kecuali): bool
    {
        $pemilik = Pesanan::withTrashed()
            ->where('nomor_spp', $nomor)
            ->when($kecuali, fn ($q) => $q->whereKeyNot($kecuali->id))
            ->first(['id', 'nama_customer', 'deleted_at']);

        if (! $pemilik) {
            return false;
        }

        $validator->errors()->add('nomor_spp', "SPP #{$nomor} sudah dipakai oleh {$pemilik->nama_customer}"
            . ($pemilik->trashed() ? ' (SPP itu sudah dihapus).' : '.'));

        return true;
    }

    protected function periksaNomorLoncat(Validator $validator, int $nomor): void
    {
        if (! $this->boolean('konfirmasi_nomor') && Pesanan::nomorSppLoncat($nomor)) {
            $saran = Pesanan::saranNomorSpp();
            $validator->errors()->add('konfirmasi_nomor',
                "Nomor SPP #{$nomor} jauh dari nomor berikutnya (#{$saran}). Periksa lagi; bila memang benar, "
                . 'centang konfirmasi lalu simpan lagi.');
        }
    }
}

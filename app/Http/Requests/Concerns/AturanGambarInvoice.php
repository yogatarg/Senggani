<?php

namespace App\Http\Requests\Concerns;

use App\Services\PengaturanInvoice;
use Illuminate\Http\UploadedFile;

/**
 * Aturan & pesan validasi gambar invoice (tanda tangan, stempel, QRIS): PNG/JPG/WebP, maks 2 MB,
 * `bail` agar file bukan gambar cukup satu galat. QRIS minimal 300 px di sisi terpendek.
 */
trait AturanGambarInvoice
{
    /** @return list<string> */
    protected function aturanGambar(string $jenis): array
    {
        $aturan = [
            'bail', 'file', 'image', 'mimes:png,jpg,jpeg,webp', 'max:' . PengaturanInvoice::MAKS_KB,
            'dimensions:max_width=8000,max_height=8000',
        ];

        if ($jenis === 'qris') {
            $aturan[] = 'dimensions:min_width=' . PengaturanInvoice::MIN_SISI_QRIS . ',min_height=' . PengaturanInvoice::MIN_SISI_QRIS;
        }

        return $aturan;
    }

    /** Pesan galat untuk field $field yang berisi gambar jenis $jenis. */
    protected function pesanGambar(string $field, string $jenis): array
    {
        $file = $this->file($field);
        $nama = $file instanceof UploadedFile ? $file->getClientOriginalName() : PengaturanInvoice::JENIS_GAMBAR[$jenis];
        $format = "{$nama} tidak bisa diunggah: format harus PNG, JPG, atau WebP.";

        $kecil = 'gambar terlalu kecil';
        if ($jenis === 'qris' && $file instanceof UploadedFile && ($ukuran = @getimagesize($file->getRealPath()))) {
            $kecil = 'QRIS terlalu kecil (' . min($ukuran[0], $ukuran[1]) . ' px)';
        }

        return [
            "{$field}.file"       => $format,
            "{$field}.image"      => $format,
            "{$field}.mimes"      => $format,
            "{$field}.uploaded"   => "{$nama} gagal diunggah. Coba lagi, atau pakai file yang lebih kecil (maks 2 MB).",
            "{$field}.max"        => "{$nama} terlalu besar. Ukuran maksimal 2 MB.",
            "{$field}.dimensions" => $jenis === 'qris'
                ? "{$kecil}. Pakai gambar minimal " . PengaturanInvoice::MIN_SISI_QRIS . ' px agar bisa dipindai (dan maksimal 8000 px).'
                : "{$nama} terlalu besar. Lebar dan tinggi maksimal 8000 piksel.",
        ];
    }
}

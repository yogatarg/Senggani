<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Bantuan upload gambar admin: aturan dimensi bersama dan pengubahan kegagalan
 * OlahGambar (GD) menjadi error validasi form, bukan halaman 500.
 *
 * Controller pemakai wajib punya properti $olahGambar (App\Services\OlahGambar).
 */
trait UnggahGambar
{
    // Batas resolusi sumber; foto lebih besar ditolak sebelum diproses GD
    protected const ATURAN_DIMENSI = 'dimensions:max_width=8000,max_height=8000';

    /**
     * Pesan validasi dimensi untuk field gambar yang diberikan.
     * Contoh: $this->pesanDimensi(['gambar' => 'Gambar', 'galeri.*' => 'Foto galeri'])
     */
    protected function pesanDimensi(array $field): array
    {
        $pesan = [];
        foreach ($field as $kunci => $label) {
            $pesan[$kunci . '.dimensions'] = $label . ' terlalu besar. Lebar dan tinggi maksimal 8000 piksel.';
        }

        return $pesan;
    }

    /**
     * Simpan gambar lewat OlahGambar. Bila GD gagal (format rusak, resolusi terlalu
     * besar, memori tidak cukup), lempar ValidationException pada $field sehingga
     * Laravel mengembalikan ke form dengan input lama dan pesan error.
     */
    protected function simpanGambar(UploadedFile $file, string $folder, array $batas, string $field): string
    {
        try {
            return $this->olahGambar->simpan($file, $folder, $batas);
        } catch (RuntimeException $e) {
            report($e);

            throw ValidationException::withMessages([
                $field => 'Gambar "' . $file->getClientOriginalName() . '" gagal diproses: ' . $e->getMessage(),
            ]);
        }
    }
}

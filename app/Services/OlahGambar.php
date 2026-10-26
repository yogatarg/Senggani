<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mengecilkan & mengubah gambar unggahan ke WebP memakai GD (tanpa dependensi tambahan).
 *
 * Alur: cek resolusi & memori → perkecil agar muat di batas ukuran (tanpa
 * memperbesar) → putar sesuai EXIF (foto HP) → simpan sebagai WebP. Dipakai
 * semua upload admin dan perintah
 * `gambar:kompres` untuk gambar lama.
 */
class OlahGambar
{
    // Batas [lebar, tinggi] maksimal per jenis gambar (null = tidak dibatasi)
    public const BANNER_DESKTOP = [1920, null];
    public const BANNER_MOBILE  = [1080, 1440];
    public const UMUM           = [1200, 1200]; // produk, galeri, kategori

    public const KUALITAS = 80;

    // Batas wajar resolusi sumber (megapiksel). Di atasnya ditolak supaya
    // proses tidak kehabisan memori di shared hosting.
    public const MAKS_MEGAPIKSEL = 40;

    // Batas atas memory_limit yang boleh dinaikkan saat memproses satu gambar
    public const MAKS_MEMORI = 512 * 1024 * 1024;

    /**
     * Olah file unggahan lalu simpan sebagai WebP di folder disk.
     * Mengembalikan path relatif (mis. "produk/AbC...xyz.webp").
     */
    public function simpan(UploadedFile $file, string $folder, array $batas, string $disk = 'public'): string
    {
        $isi = file_get_contents($file->getRealPath());
        if ($isi === false) {
            throw new RuntimeException('File gambar tidak dapat dibaca.');
        }

        $path = trim($folder, '/') . '/' . Str::random(40) . '.webp';

        if (! Storage::disk($disk)->put($path, $this->keWebp($isi, $batas))) {
            throw new RuntimeException('Gambar gagal disimpan.');
        }

        return $path;
    }

    /**
     * Ubah isi biner gambar (JPG/PNG/WebP/GIF) menjadi biner WebP yang sudah diperkecil.
     */
    public function keWebp(string $isi, array $batas, int $kualitas = self::KUALITAS): string
    {
        // Cek dimensi dari header dulu (murah), baru decode piksel
        $info = @getimagesizefromstring($isi);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            throw new RuntimeException('Format gambar tidak dikenali.');
        }
        $this->siapkanMemori((int) $info[0], (int) $info[1], (int) ($info['channels'] ?? 4));

        $orientasi = $this->orientasiExif($isi);

        $gambar = @imagecreatefromstring($isi);
        unset($isi); // salinan lokal biner tidak dibutuhkan lagi
        if (! $gambar instanceof GdImage) {
            throw new RuntimeException('Format gambar tidak dikenali atau terlalu besar untuk diproses.');
        }

        // Perkecil dulu, baru diputar: rotasi dilakukan pada gambar kecil sehingga
        // tidak ada dua salinan gambar resolusi penuh di memori. Untuk rotasi 90°
        // batas lebar/tinggi ditukar agar hasil akhirnya tetap sesuai batas.
        [$maksLebar, $maksTinggi] = [$batas[0] ?? null, $batas[1] ?? null];
        if ($orientasi >= 5 && $orientasi <= 8) {
            [$maksLebar, $maksTinggi] = [$maksTinggi, $maksLebar];
        }

        $gambar = $this->perkecil($gambar, $maksLebar, $maksTinggi);
        $gambar = $this->putar($gambar, $orientasi);

        // WebP butuh truecolor; jaga transparansi PNG
        if (! imageistruecolor($gambar)) {
            imagepalettetotruecolor($gambar);
        }
        imagealphablending($gambar, false);
        imagesavealpha($gambar, true);

        ob_start();
        $berhasil = imagewebp($gambar, null, $kualitas);
        $hasil    = ob_get_clean();

        if (! $berhasil || $hasil === false || $hasil === '') {
            throw new RuntimeException('Konversi ke WebP gagal.');
        }

        return $hasil;
    }

    /**
     * Tolak gambar yang resolusinya tidak wajar dan naikkan memory_limit secukupnya
     * (maks. MAKS_MEMORI) sebelum GD men-decode piksel. GD menyimpan tiap piksel
     * truecolor sebagai 4 byte; faktor 1,8 menutup overhead decoder + kanvas hasil.
     */
    private function siapkanMemori(int $lebar, int $tinggi, int $channel): void
    {
        if ($lebar * $tinggi > self::MAKS_MEGAPIKSEL * 1_000_000) {
            throw new RuntimeException(sprintf(
                'Resolusi gambar terlalu besar (%d×%d piksel). Maksimal %d megapiksel; perkecil gambar lalu unggah lagi.',
                $lebar, $tinggi, self::MAKS_MEGAPIKSEL
            ));
        }

        $perkiraan = (int) ceil($lebar * $tinggi * max(4, $channel) * 1.8);
        $butuh     = memory_get_usage(true) + $perkiraan + 16 * 1024 * 1024; // + cadangan

        $limit = $this->keByte((string) ini_get('memory_limit'));
        if ($limit < 0 || $butuh <= $limit) {
            return; // tanpa batas atau sudah cukup
        }

        if ($butuh <= self::MAKS_MEMORI) {
            // Boleh gagal (mis. dikunci hosting); dicek ulang di bawah
            @ini_set('memory_limit', (string) (int) ceil($butuh / 1048576) . 'M');
        }

        $limit = $this->keByte((string) ini_get('memory_limit'));
        if ($limit >= 0 && $butuh > $limit) {
            throw new RuntimeException(sprintf(
                'Gambar terlalu besar untuk diproses server (%d×%d piksel). Perkecil resolusinya lalu unggah lagi.',
                $lebar, $tinggi
            ));
        }
    }

    // "128M" / "1G" / "-1" → byte (-1 = tanpa batas)
    private function keByte(string $nilai): int
    {
        $nilai = trim($nilai);
        if ($nilai === '' || $nilai === '-1') {
            return -1;
        }

        $angka = (int) $nilai;

        return match (strtolower(substr($nilai, -1))) {
            'g'     => $angka * 1024 ** 3,
            'm'     => $angka * 1024 ** 2,
            'k'     => $angka * 1024,
            default => $angka,
        };
    }

    // Foto kamera/HP sering disimpan miring dengan tanda orientasi di EXIF (khusus JPEG)
    private function orientasiExif(string $isi): int
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($isi, "\xFF\xD8")) {
            return 1;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($isi));

        return (int) ($exif['Orientation'] ?? 1);
    }

    private function putar(GdImage $gambar, int $orientasi): GdImage
    {
        if ($orientasi === 2 || $orientasi === 4) {
            imageflip($gambar, $orientasi === 2 ? IMG_FLIP_HORIZONTAL : IMG_FLIP_VERTICAL);

            return $gambar;
        }

        $sudut = match ($orientasi) {
            3       => 180,
            5, 6    => -90,
            7, 8    => 90,
            default => 0,
        };

        if ($sudut === 0) {
            return $gambar;
        }

        $hasil = imagerotate($gambar, $sudut, 0);
        if (! $hasil instanceof GdImage) {
            return $gambar;
        }
        unset($gambar); // lepas referensi gambar sebelum diputar

        if ($orientasi === 5 || $orientasi === 7) {
            imageflip($hasil, IMG_FLIP_HORIZONTAL);
        }

        return $hasil;
    }

    // Perkecil proporsional agar muat di batas; gambar kecil tidak diperbesar
    private function perkecil(GdImage $gambar, ?int $maksLebar, ?int $maksTinggi): GdImage
    {
        $lebar  = imagesx($gambar);
        $tinggi = imagesy($gambar);

        $skala = min(
            1,
            $maksLebar ? $maksLebar / $lebar : 1,
            $maksTinggi ? $maksTinggi / $tinggi : 1,
        );

        if ($skala >= 1) {
            return $gambar;
        }

        $lebarBaru  = max(1, (int) round($lebar * $skala));
        $tinggiBaru = max(1, (int) round($tinggi * $skala));

        $kanvas = imagecreatetruecolor($lebarBaru, $tinggiBaru);
        imagealphablending($kanvas, false);
        imagesavealpha($kanvas, true);
        imagefill($kanvas, 0, 0, imagecolorallocatealpha($kanvas, 0, 0, 0, 127));

        imagecopyresampled($kanvas, $gambar, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);

        return $kanvas;
    }
}

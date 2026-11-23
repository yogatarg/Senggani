<?php

namespace App\Services;

use App\Models\Pengaturan;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Pengaturan Invoice: penanda tangan, rekening, QRIS, keterangan bawah, persen DP, dan file
 * gambar (tanda tangan, stempel, QRIS). Nilai disimpan di tabel `pengaturan` (kunci "invoice.*");
 * file gambar di disk privat `local` (storage/app/private/invoice/), tidak pernah di public.
 *
 * Gambar diolah ulang dengan GD (membuang metadata/isi selain piksel):
 * - PNG & WebP → PNG (transparansi dipertahankan; tanda tangan/stempel tidak diubah ke WebP
 *   karena DomPDF paling aman dengan PNG/JPG).
 * - JPG → JPG.
 */
class PengaturanInvoice
{
    public const DISK = 'local';

    public const FOLDER = 'invoice';

    // Jenis gambar yang bisa diunggah => label untuk pesan
    public const JENIS_GAMBAR = [
        'ttd'     => 'Tanda tangan',
        'stempel' => 'Stempel',
        'qris'    => 'QRIS',
    ];

    public const MAKS_KB = 2048;

    public const MAKS_REKENING = 4;

    // Sisi terpendek minimal QRIS supaya tetap bisa dipindai setelah dicetak
    public const MIN_SISI_QRIS = 300;

    // Sisi terpanjang hasil simpan (cukup untuk cetak A4; QRIS 30 mm ≈ 350 px di 300 dpi)
    public const MAKS_SISI = 2000;

    private const KUNCI = [
        'penandatangan_nama', 'penandatangan_jabatan', 'rekening', 'keterangan_bawah',
        'persen_dp', 'qris_keterangan', 'file_ttd', 'file_stempel', 'file_qris',
    ];

    // Dipakai bila baris pengaturan belum ada (mis. tabel dikosongkan manual)
    private const BAWAAN = [
        'penandatangan_nama'    => '',
        'penandatangan_jabatan' => '',
        'rekening'              => [],
        'keterangan_bawah'      => '',
        'persen_dp'             => 50,
        'qris_keterangan'       => 'QRIS a.n. SENGGANI - FOTOCOPY (satu usaha).',
        'file_ttd'              => null,
        'file_stempel'          => null,
        'file_qris'             => null,
    ];

    /**
     * Semua pengaturan invoice.
     *
     * @return array{penandatangan_nama: string, penandatangan_jabatan: string,
     *   rekening: list<array{bank: string, nomor: string, atas_nama: string}>, keterangan_bawah: string,
     *   persen_dp: int, qris_keterangan: string, file_ttd: ?string, file_stempel: ?string, file_qris: ?string}
     */
    public function semua(): array
    {
        $tersimpan = Pengaturan::ambil(array_map(fn ($k) => 'invoice.' . $k, self::KUNCI));
        $hasil = [];

        foreach (self::KUNCI as $k) {
            $hasil[$k] = array_key_exists('invoice.' . $k, $tersimpan) ? $tersimpan['invoice.' . $k] : self::BAWAAN[$k];
        }

        $hasil['penandatangan_nama']    = (string) $hasil['penandatangan_nama'];
        $hasil['penandatangan_jabatan'] = (string) $hasil['penandatangan_jabatan'];
        $hasil['keterangan_bawah']      = (string) $hasil['keterangan_bawah'];
        $hasil['qris_keterangan']       = (string) $hasil['qris_keterangan'];
        $hasil['persen_dp']             = (int) $hasil['persen_dp'];
        $hasil['rekening']              = array_values(array_filter(
            is_array($hasil['rekening']) ? $hasil['rekening'] : [],
            fn ($r) => is_array($r) && filled($r['bank'] ?? null) && filled($r['nomor'] ?? null),
        ));

        return $hasil;
    }

    /** Simpan isian teks (hasil SimpanPengaturanInvoiceRequest::dataPengaturan()). */
    public function simpan(array $data): void
    {
        foreach (['penandatangan_nama', 'penandatangan_jabatan', 'rekening', 'keterangan_bawah', 'persen_dp', 'qris_keterangan'] as $k) {
            if (array_key_exists($k, $data)) {
                Pengaturan::simpan('invoice.' . $k, $data[$k]);
            }
        }
    }

    // ── Gambar ─────────────────────────────────────────

    /** Path file gambar yang tersimpan dan masih ada di disk; null bila belum diunggah. */
    public function pathGambar(string $jenis): ?string
    {
        $path = $this->semua()['file_' . $jenis] ?? null;

        return $path && Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    /**
     * Olah & simpan gambar unggahan, lalu hapus file lama. Mengembalikan path baru.
     *
     * @throws RuntimeException bila gambar tidak bisa diolah
     */
    public function simpanGambar(string $jenis, UploadedFile $file): string
    {
        $this->pastikanJenis($jenis);

        $isi = file_get_contents($file->getRealPath());
        if ($isi === false) {
            throw new RuntimeException('File gambar tidak dapat dibaca.');
        }

        [$biner, $ekstensi] = $this->olah($isi);

        $path = self::FOLDER . '/' . $jenis . '-' . Str::lower(Str::random(12)) . '.' . $ekstensi;
        if (! Storage::disk(self::DISK)->put($path, $biner)) {
            throw new RuntimeException('Gambar gagal disimpan.');
        }

        $lama = $this->semua()['file_' . $jenis] ?? null;
        Pengaturan::simpan('invoice.file_' . $jenis, $path);

        if ($lama && $lama !== $path) {
            Storage::disk(self::DISK)->delete($lama);
        }

        return $path;
    }

    public function hapusGambar(string $jenis): void
    {
        $this->pastikanJenis($jenis);

        $lama = $this->semua()['file_' . $jenis] ?? null;
        Pengaturan::simpan('invoice.file_' . $jenis, null);

        if ($lama) {
            Storage::disk(self::DISK)->delete($lama);
        }
    }

    /**
     * Gambar sebagai data URI untuk DomPDF (tanpa perlu membuka chroot/remote).
     *
     * @return array{uri: string, lebar: int, tinggi: int}|null
     */
    public function dataUri(string $jenis): ?array
    {
        $path = $this->pathGambar($jenis);
        if (! $path) {
            return null;
        }

        return self::dataUriDariIsi((string) Storage::disk(self::DISK)->get($path));
    }

    /** @return array{uri: string, lebar: int, tinggi: int}|null */
    public static function dataUriDariIsi(string $isi): ?array
    {
        $info = @getimagesizefromstring($isi);
        if ($info === false || ! in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
            return null;
        }

        return [
            'uri'    => 'data:' . $info['mime'] . ';base64,' . base64_encode($isi),
            'lebar'  => (int) $info[0],
            'tinggi' => (int) $info[1],
        ];
    }

    private function pastikanJenis(string $jenis): void
    {
        if (! array_key_exists($jenis, self::JENIS_GAMBAR)) {
            throw new \InvalidArgumentException("Jenis gambar invoice tidak dikenal: {$jenis}");
        }
    }

    /**
     * Decode lalu encode ulang dengan GD; perkecil bila sisi terpanjang > MAKS_SISI.
     *
     * @return array{0: string, 1: string} [biner, ekstensi]
     */
    private function olah(string $isi): array
    {
        $info = @getimagesizefromstring($isi);
        if ($info === false || empty($info[0]) || empty($info[1])) {
            throw new RuntimeException('Format gambar tidak dikenali.');
        }
        if ($info[0] * $info[1] > OlahGambar::MAKS_MEGAPIKSEL * 1_000_000) {
            throw new RuntimeException('Resolusi gambar terlalu besar.');
        }

        $mime = $info['mime'] ?? '';
        if (! in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            throw new RuntimeException('Format harus PNG, JPG, atau WebP.');
        }

        $gambar = @imagecreatefromstring($isi);
        if (! $gambar instanceof GdImage) {
            throw new RuntimeException('Gambar rusak atau tidak bisa dibaca.');
        }

        $jpg = $mime === 'image/jpeg';

        if (! $jpg && ! imageistruecolor($gambar)) {
            imagepalettetotruecolor($gambar);
        }

        $lebar  = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $skala  = min(1, self::MAKS_SISI / max($lebar, $tinggi));

        if ($skala < 1) {
            $lb = max(1, (int) round($lebar * $skala));
            $tb = max(1, (int) round($tinggi * $skala));
            $kecil = imagecreatetruecolor($lb, $tb);
            if (! $jpg) {
                imagealphablending($kecil, false);
                imagesavealpha($kecil, true);
                imagefill($kecil, 0, 0, imagecolorallocatealpha($kecil, 0, 0, 0, 127));
            }
            imagecopyresampled($kecil, $gambar, 0, 0, 0, 0, $lb, $tb, $lebar, $tinggi);
            $gambar = $kecil;
        }

        ob_start();
        if ($jpg) {
            imagejpeg($gambar, null, 90);
        } else {
            imagealphablending($gambar, false);
            imagesavealpha($gambar, true);
            imagepng($gambar, null, 9);
        }
        $biner = (string) ob_get_clean();

        if ($biner === '') {
            throw new RuntimeException('Gambar gagal diolah.');
        }

        return [$biner, $jpg ? 'jpg' : 'png'];
    }
}

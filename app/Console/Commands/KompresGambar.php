<?php

namespace App\Console\Commands;

use App\Services\OlahGambar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Mengubah gambar lama (JPG/PNG) di disk public menjadi WebP yang sudah diperkecil.
 *
 * Urutan per file: cadangkan file asli (disk "cadangan", tidak publik) → tulis WebP →
 * perbarui path di database dalam transaksi → baru hapus file asli dari disk public.
 * Aman diulang: path yang sudah .webp dilewati.
 */
class KompresGambar extends Command
{
    protected $signature = 'gambar:kompres
                            {--dry-run : Hanya hitung perkiraan penghematan tanpa mengubah file atau database}';

    protected $description = 'Kompres gambar lama (banner, produk, galeri, kategori) ke WebP dan perbarui path di database';

    // tabel => [kolom => batas ukuran]
    private const SUMBER = [
        'banner'          => ['gambar' => OlahGambar::BANNER_DESKTOP, 'gambar_mobile' => OlahGambar::BANNER_MOBILE],
        'produk'          => ['gambar' => OlahGambar::UMUM],
        'galeri_produk'   => ['nama_file' => OlahGambar::UMUM],
        'kategori_produk' => ['gambar' => OlahGambar::UMUM],
    ];

    public function handle(OlahGambar $olahGambar): int
    {
        @ini_set('memory_limit', '1024M');

        $coba     = (bool) $this->option('dry-run');
        $public   = Storage::disk('public');
        $cadangan = Storage::disk('cadangan');

        $daftar = $this->kumpulkanPath();

        $hasil = ['diproses' => 0, 'dilewati' => 0, 'hilang' => 0, 'gagal' => 0];
        $ukuranAwal  = 0;
        $ukuranAkhir = 0;
        $baris = [];

        foreach ($daftar as $path => $info) {
            if (str_ends_with(strtolower($path), '.webp')) {
                $hasil['dilewati']++;
                continue;
            }

            if (! $public->exists($path)) {
                $hasil['hilang']++;
                $this->warn("File tidak ditemukan, dilewati: {$path}");
                continue;
            }

            try {
                $isiAsli = $public->get($path);
                $webp    = $olahGambar->keWebp($isiAsli, $info['batas']);
            } catch (Throwable $e) {
                $hasil['gagal']++;
                $this->error("Gagal mengolah {$path}: {$e->getMessage()}");
                continue;
            }

            $awal  = strlen($isiAsli);
            $akhir = strlen($webp);

            if (! $coba) {
                try {
                    $this->ganti($path, $isiAsli, $webp, $info['rujukan'], $public, $cadangan);
                } catch (Throwable $e) {
                    $hasil['gagal']++;
                    $this->error("Gagal mengganti {$path}: {$e->getMessage()}");
                    continue;
                }
            }

            $hasil['diproses']++;
            $ukuranAwal  += $awal;
            $ukuranAkhir += $akhir;
            $baris[] = [$path, $this->kb($awal), $this->kb($akhir), $this->persen($awal, $akhir)];
        }

        if ($baris) {
            $this->table(['File', 'Asli', 'WebP', 'Hemat'], $baris);
        }

        $this->info(($coba ? '[DRY-RUN] ' : '') . sprintf(
            '%d dikompres, %d dilewati (sudah WebP), %d file hilang, %d gagal.',
            $hasil['diproses'], $hasil['dilewati'], $hasil['hilang'], $hasil['gagal'],
        ));

        if ($hasil['diproses']) {
            $this->info(sprintf(
                '%s %s → %s (hemat %s, %s).',
                $coba ? 'Perkiraan ukuran:' : 'Ukuran:',
                $this->mb($ukuranAwal),
                $this->mb($ukuranAkhir),
                $this->mb($ukuranAwal - $ukuranAkhir),
                $this->persen($ukuranAwal, $ukuranAkhir),
            ));
        }

        if ($coba) {
            $this->comment('Tidak ada yang diubah. Jalankan tanpa --dry-run untuk menerapkan.');
        } elseif ($hasil['diproses']) {
            $this->comment('File asli dicadangkan di storage/app/cadangan-gambar-asli.');
        }

        return $hasil['gagal'] ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Kumpulkan path unik beserta semua baris yang merujuknya.
     * Satu file yang dipakai beberapa baris cukup dikompres sekali.
     *
     * @return array<string, array{batas: array, rujukan: list<array{0:string,1:string,2:int}>}>
     */
    private function kumpulkanPath(): array
    {
        $daftar = [];

        foreach (self::SUMBER as $tabel => $kolomBatas) {
            foreach ($kolomBatas as $kolom => $batas) {
                $baris = DB::table($tabel)
                    ->whereNotNull($kolom)
                    ->where($kolom, '!=', '')
                    ->orderBy('id')
                    ->get(['id', $kolom]);

                foreach ($baris as $b) {
                    $path = $b->{$kolom};
                    $daftar[$path] ??= ['batas' => $batas, 'rujukan' => []];
                    $daftar[$path]['rujukan'][] = [$tabel, $kolom, $b->id];
                }
            }
        }

        return $daftar;
    }

    private function ganti(string $path, string $isiAsli, string $webp, array $rujukan, $public, $cadangan): void
    {
        $pathBaru = $this->pathWebp($path, $public);

        // 1. Cadangkan file asli (tidak ditimpa bila sudah ada dari percobaan sebelumnya)
        if (! $cadangan->exists($path) && ! $cadangan->put($path, $isiAsli)) {
            throw new \RuntimeException('Cadangan gagal ditulis.');
        }

        // 2. Tulis WebP baru
        if (! $public->put($pathBaru, $webp)) {
            throw new \RuntimeException('File WebP gagal ditulis.');
        }

        // 3. Perbarui semua rujukan di DB; bila gagal, WebP baru dibuang dan file asli tetap
        try {
            DB::transaction(function () use ($rujukan, $path, $pathBaru) {
                foreach ($rujukan as [$tabel, $kolom, $id]) {
                    DB::table($tabel)->where('id', $id)->where($kolom, $path)->update([$kolom => $pathBaru]);
                }
            });
        } catch (Throwable $e) {
            $public->delete($pathBaru);
            throw $e;
        }

        // 4. Hapus file asli hanya setelah DB terbarui
        $public->delete($path);
    }

    // banner/abc.png → banner/abc.webp (beri akhiran bila nama itu sudah dipakai)
    private function pathWebp(string $path, $public): string
    {
        $info  = pathinfo($path);
        $dasar = ($info['dirname'] !== '.' ? $info['dirname'] . '/' : '') . $info['filename'];
        $baru  = $dasar . '.webp';
        $nomor = 2;

        while ($public->exists($baru)) {
            $baru = $dasar . '-' . $nomor++ . '.webp';
        }

        return $baru;
    }

    private function kb(int $byte): string
    {
        return number_format($byte / 1024, 0, ',', '.') . ' KB';
    }

    private function mb(int $byte): string
    {
        return number_format($byte / 1048576, 2, ',', '.') . ' MB';
    }

    private function persen(int $awal, int $akhir): string
    {
        return $awal ? number_format((1 - $akhir / $awal) * 100, 1, ',', '.') . '%' : '0%';
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Pesanan;
use App\Services\InvoicePdf;
use App\Services\PengaturanInvoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mencari file di disk public yang tidak dirujuk kolom gambar mana pun di database, serta cache PDF
 * invoice (disk privat) yang pesanannya sudah tidak ada atau berumur lebih dari UMUR_CACHE_HARI.
 * Pesanan yang di-soft-delete masih dianggap ada (bisa dipulihkan).
 * Tanpa --hapus hanya menampilkan daftar; .gitignore tidak pernah disentuh.
 */
class GambarYatim extends Command
{
    protected $signature = 'gambar:yatim {--hapus : Hapus permanen file yatim yang ditemukan}';

    protected $description = 'Tampilkan (atau hapus) file gambar yatim di disk public dan cache PDF invoice yang yatim/kedaluwarsa';

    public const UMUR_CACHE_HARI = 30;

    private const KOLOM = [
        'banner'          => ['gambar', 'gambar_mobile'],
        'produk'          => ['gambar'],
        'galeri_produk'   => ['nama_file'],
        'kategori_produk' => ['gambar'],
    ];

    public function handle(): int
    {
        $disk = Storage::disk('public');

        $rujukan = [];
        foreach (self::KOLOM as $tabel => $kolom) {
            foreach ($kolom as $k) {
                foreach (DB::table($tabel)->whereNotNull($k)->pluck($k) as $path) {
                    $rujukan[$path] = true;
                }
            }
        }

        $yatim = array_values(array_filter(
            $disk->allFiles(),
            fn ($f) => basename($f) !== '.gitignore' && ! isset($rujukan[$f])
        ));

        $total = 0;
        foreach ($yatim as $f) {
            $total += $ukuran = $disk->size($f);
            $this->line(sprintf('%s (%.2f MB)', $f, $ukuran / 1048576));
        }
        $this->info(sprintf('%d file yatim, total %.2f MB.', count($yatim), $total / 1048576));

        if ($this->option('hapus') && $yatim) {
            $disk->delete($yatim);
            $this->info('File yatim sudah dihapus.');
        }

        $this->cacheInvoice();

        return self::SUCCESS;
    }

    private function cacheInvoice(): void
    {
        $disk  = Storage::disk(PengaturanInvoice::DISK);
        $batas = now()->subDays(self::UMUR_CACHE_HARI)->getTimestamp();
        $ada   = array_flip(Pesanan::withTrashed()->pluck('id')->map(fn ($id) => (string) $id)->all());

        $hapus = [];
        $folderYatim = [];
        foreach ($disk->directories(InvoicePdf::FOLDER_CACHE) as $folder) {
            $yatim = ! isset($ada[basename($folder)]);
            if ($yatim) {
                $folderYatim[] = $folder;
            }
            foreach ($disk->allFiles($folder) as $f) {
                if ($yatim || $disk->lastModified($f) < $batas) {
                    $hapus[] = $f;
                }
            }
        }

        $total = 0;
        foreach ($hapus as $f) {
            $total += $ukuran = $disk->size($f);
            $this->line(sprintf('%s (%.2f MB)', $f, $ukuran / 1048576));
        }
        $this->info(sprintf(
            '%d cache invoice yatim/lebih dari %d hari, total %.2f MB.',
            count($hapus), self::UMUR_CACHE_HARI, $total / 1048576
        ));

        if ($this->option('hapus') && ($hapus || $folderYatim)) {
            $disk->delete($hapus);
            foreach ($folderYatim as $folder) {
                $disk->deleteDirectory($folder);
            }
            // Folder pesanan yang isinya habis karena kedaluwarsa ikut dirapikan
            foreach ($disk->directories(InvoicePdf::FOLDER_CACHE) as $folder) {
                if (! $disk->allFiles($folder)) {
                    $disk->deleteDirectory($folder);
                }
            }
            $this->info('Cache invoice yatim/kedaluwarsa sudah dihapus.');
        }
    }
}

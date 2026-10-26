<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Mencari file di disk public yang tidak dirujuk kolom gambar mana pun di database.
 * Tanpa --hapus hanya menampilkan daftar; folder dan .gitignore tidak pernah disentuh.
 */
class GambarYatim extends Command
{
    protected $signature = 'gambar:yatim {--hapus : Hapus permanen file yatim yang ditemukan}';

    protected $description = 'Tampilkan (atau hapus) file gambar di disk public yang tidak dirujuk database';

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

        return self::SUCCESS;
    }
}

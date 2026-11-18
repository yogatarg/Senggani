<?php

namespace App\Services;

use App\Enums\StatusProduksi;
use App\Exceptions\StatusBentrok;
use App\Models\Admin;
use App\Models\Pesanan;
use App\Models\PesananFile;
use App\Models\PesananRiwayat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Satu-satunya tempat yang menyimpan order, item, total, file, dan riwayat,
 * supaya total tidak pernah selisih dengan jumlah subtotal item.
 */
class PesananService
{
    // Folder sementara hasil olah gambar sebelum order tersimpan (disk local)
    public const FOLDER_SEMENTARA = 'pesanan/sementara';

    // ── Nomor order ────────────────────────────────────

    /**
     * Nomor SGN-YYMM-NNN dari waktu simpan. WAJIB dipanggil di dalam transaksi
     * yang sama dengan insert order: baris periode dikunci (lockForUpdate) sehingga
     * dua penyimpanan bersamaan antre dan tidak mendapat nomor kembar.
     */
    public function nomorBerikutnya(?Carbon $waktu = null): string
    {
        $periode = ($waktu ?? now())->format('ym');

        DB::table('pesanan_nomor_urut')->insertOrIgnore(['periode' => $periode, 'terakhir' => 0]);

        $terakhir = (int) DB::table('pesanan_nomor_urut')
            ->where('periode', $periode)
            ->lockForUpdate()
            ->value('terakhir');

        // Jaga-jaga bila penghitung tertinggal dari data (mis. order diinput manual):
        // ambil nomor terbesar yang sudah ada di periode ini, termasuk yang di-soft-delete.
        $nomorTerbesar = Pesanan::withTrashed()
            ->where('nomor_order', 'like', "SGN-{$periode}-%")
            ->orderByRaw('LENGTH(nomor_order) DESC')
            ->orderByDesc('nomor_order')
            ->value('nomor_order');
        if ($nomorTerbesar) {
            $terakhir = max($terakhir, (int) substr($nomorTerbesar, strlen("SGN-{$periode}-")));
        }

        $urut = $terakhir + 1;

        DB::table('pesanan_nomor_urut')->where('periode', $periode)->update(['terakhir' => $urut]);

        return sprintf('SGN-%s-%s', $periode, str_pad((string) $urut, 3, '0', STR_PAD_LEFT));
    }

    // ── Simpan order ───────────────────────────────────

    /**
     * Buat order baru beserta item, file, dan riwayat "Order dibuat".
     *
     * @param  array  $data      kolom pesanan (tanpa total/nomor)
     * @param  array  $item      daftar item (lihat isiItem)
     * @param  array  $fileBaru  [['jenis' => 'mockup', 'path' => 'pesanan/sementara/x.webp', 'nama_asli' => '...'], ...]
     */
    public function buat(array $data, array $item, array $fileBaru, Admin $oleh): Pesanan
    {
        $dipindah = [];

        try {
            return DB::transaction(function () use ($data, $item, $fileBaru, $oleh, &$dipindah) {
                $pesanan = Pesanan::create([
                    ...$data,
                    'nomor_order'     => $this->nomorBerikutnya(),
                    'status_produksi' => StatusProduksi::Antrian->value,
                    'selesai_pada'    => null,
                    'dibuat_oleh'     => $oleh->id,
                    'total'           => 0,
                ]);

                $this->isiItem($pesanan, $item);
                $this->lampirkanFile($pesanan, $fileBaru, $oleh, $dipindah);
                $this->catat($pesanan, $oleh, null, StatusProduksi::Antrian, 'Order dibuat');

                return $pesanan;
            });
        } catch (Throwable $e) {
            $this->buangFile([...array_column($fileBaru, 'path'), ...$dipindah]);

            throw $e;
        }
    }

    /** Perbarui data order, ganti seluruh item, tambah file baru. */
    public function perbarui(Pesanan $pesanan, array $data, array $item, array $fileBaru, Admin $oleh): Pesanan
    {
        $dipindah = [];

        try {
            DB::transaction(function () use ($pesanan, $data, $item, $fileBaru, $oleh, &$dipindah) {
                // Kolom yang tidak boleh diubah lewat form
                unset($data['nomor_order'], $data['status_produksi'], $data['selesai_pada'], $data['dibuat_oleh'], $data['total']);

                $pesanan->fill($data)->save();

                $pesanan->detailPesanan()->delete();
                $this->isiItem($pesanan, $item);
                $this->lampirkanFile($pesanan, $fileBaru, $oleh, $dipindah);
                $this->catat($pesanan, $oleh, null, null, 'Order diubah');
            });
        } catch (Throwable $e) {
            $this->buangFile([...array_column($fileBaru, 'path'), ...$dipindah]);

            throw $e;
        }

        return $pesanan->refresh();
    }

    /**
     * Simpan item dan hitung ulang total di server (subtotal = jumlah × harga_satuan).
     * Nilai subtotal/total kiriman form diabaikan.
     */
    private function isiItem(Pesanan $pesanan, array $item): void
    {
        $total = 0;

        foreach (array_values($item) as $i => $baris) {
            $jumlah   = (int) $baris['jumlah'];
            $harga    = (int) $baris['harga_satuan'];
            $subtotal = $jumlah * $harga;
            $total   += $subtotal;

            $pesanan->detailPesanan()->create([
                'urutan'          => $i,
                'jenis_packaging' => $baris['jenis_packaging'],
                'panjang'         => $baris['panjang'] ?? null,
                'lebar'           => $baris['lebar'] ?? null,
                'tinggi'          => $baris['tinggi'] ?? null,
                'jenis_bahan'     => $baris['jenis_bahan'],
                'gramasi'         => $baris['gramasi'] ?? null,
                'jenis_cetak'     => $baris['jenis_cetak'] ?? null,
                'warna_cetak'     => $baris['warna_cetak'] ?? null,
                'finishing'       => $baris['finishing'] ?? null,
                'aksesori'        => $baris['aksesori'] ?? null,
                'jumlah'          => $jumlah,
                'harga_satuan'    => $harga,
                'subtotal'        => $subtotal,
            ]);
        }

        $pesanan->forceFill(['total' => $total])->save();
    }

    /** Pindahkan file dari folder sementara ke pesanan/{id}/ lalu catat di tabel. */
    private function lampirkanFile(Pesanan $pesanan, array $fileBaru, Admin $oleh, array &$dipindah): void
    {
        $disk = Storage::disk(PesananFile::DISK);

        foreach ($fileBaru as $file) {
            $tujuan = 'pesanan/' . $pesanan->id . '/' . Str::uuid() . '.' . pathinfo($file['path'], PATHINFO_EXTENSION);

            if (! $disk->move($file['path'], $tujuan)) {
                throw new \RuntimeException('Gambar gagal dipindahkan ke folder order.');
            }
            $dipindah[] = $tujuan;

            $pesanan->file()->create([
                'jenis'         => $file['jenis'],
                'path'          => $tujuan,
                'nama_asli'     => Str::limit($file['nama_asli'], 250, ''),
                'mime'          => $disk->mimeType($tujuan) ?: 'image/webp',
                'ukuran'        => (int) $disk->size($tujuan),
                'diunggah_oleh' => $oleh->id,
            ]);
        }
    }

    public function hapusFile(PesananFile $file): void
    {
        $path = $file->path;
        $file->delete();

        Storage::disk(PesananFile::DISK)->delete($path);
    }

    /** Hapus file sisa (sementara/terlanjur dipindah) saat penyimpanan gagal. */
    public function buangFile(array $path): void
    {
        $path = array_values(array_filter($path));
        if ($path) {
            Storage::disk(PesananFile::DISK)->delete($path);
        }
    }

    // ── Status produksi ────────────────────────────────

    /**
     * Maju/mundur SATU langkah. $dari = status yang dilihat pengguna di layar;
     * bila di database sudah berbeda, StatusBentrok dilempar dan tidak ada yang berubah.
     */
    public function ubahStatus(Pesanan $pesanan, StatusProduksi $dari, StatusProduksi $ke, ?string $catatan, Admin $oleh): void
    {
        if ($ke !== $dari->berikutnya() && $ke !== $dari->sebelumnya()) {
            throw ValidationException::withMessages([
                'status_baru' => 'Status hanya bisa dipindah satu langkah maju atau mundur.',
            ]);
        }

        $this->pindahStatus($pesanan, $dari, $ke, $catatan, $oleh);
    }

    /** Batalkan order aktif (khusus admin, dicek di Policy). */
    public function batalkan(Pesanan $pesanan, StatusProduksi $dari, ?string $catatan, Admin $oleh): void
    {
        if (! $dari->bisaDibatalkan()) {
            throw ValidationException::withMessages([
                'status_lama' => 'Order yang sudah selesai atau dibatalkan tidak bisa dibatalkan.',
            ]);
        }

        $this->pindahStatus($pesanan, $dari, StatusProduksi::Dibatalkan, $catatan, $oleh);
    }

    /** Kembalikan order batal ke status sebelum dibatalkan (khusus admin). */
    public function pulihkan(Pesanan $pesanan, ?string $catatan, Admin $oleh): StatusProduksi
    {
        $ke = PesananRiwayat::where('pesanan_id', $pesanan->id)
            ->where('status_ke', StatusProduksi::Dibatalkan->value)
            ->whereNotNull('status_dari')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->value('status_dari');

        $ke = $ke instanceof StatusProduksi ? $ke : (StatusProduksi::tryFrom((string) $ke) ?? StatusProduksi::Antrian);

        $this->pindahStatus($pesanan, StatusProduksi::Dibatalkan, $ke, $catatan, $oleh);

        return $ke;
    }

    private function pindahStatus(Pesanan $pesanan, StatusProduksi $dari, StatusProduksi $ke, ?string $catatan, Admin $oleh): void
    {
        DB::transaction(function () use ($pesanan, $dari, $ke, $catatan, $oleh) {
            // Update bersyarat: hanya berhasil bila status di DB masih sama dengan yang dilihat pengguna
            $berubah = Pesanan::whereKey($pesanan->id)
                ->where('status_produksi', $dari->value)
                ->update([
                    'status_produksi' => $ke->value,
                    'selesai_pada'    => $ke->riwayat() ? now() : null,
                ]);

            if ($berubah === 0) {
                throw $this->bentrok($pesanan);
            }

            $this->catat($pesanan, $oleh, $dari, $ke, $catatan);
        });

        $pesanan->refresh();
    }

    private function bentrok(Pesanan $pesanan): StatusBentrok
    {
        $segar = Pesanan::withTrashed()->find($pesanan->id);

        if (! $segar || $segar->trashed()) {
            return new StatusBentrok('Order ini sudah dihapus. Muat ulang halaman.');
        }

        $pelaku = PesananRiwayat::where('pesanan_id', $pesanan->id)
            ->whereNotNull('status_ke')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->value('nama_pengguna');

        $status = $segar->status_produksi;
        $oleh   = $pelaku ? " oleh {$pelaku}" : '';

        return new StatusBentrok(
            "Status pesanan ini sudah diubah{$oleh} menjadi {$status->label()}. Muat ulang halaman.",
            $status,
        );
    }

    // ── Catatan & pembayaran ───────────────────────────

    public function tambahCatatan(Pesanan $pesanan, string $catatan, Admin $oleh): PesananRiwayat
    {
        $riwayat = $this->catat($pesanan, $oleh, null, null, $catatan);
        $pesanan->touch(); // penanda perubahan untuk auto-refresh dashboard

        return $riwayat;
    }

    public function catatLunas(Pesanan $pesanan, Carbon $tanggal, Admin $oleh): void
    {
        DB::transaction(function () use ($pesanan, $tanggal, $oleh) {
            $pesanan->update(['tanggal_lunas' => $tanggal->toDateString()]);
            $this->catat($pesanan, $oleh, null, null, 'Pelunasan dicatat, tanggal ' . $tanggal->translatedFormat('d/m/Y'));
        });
    }

    private function catat(Pesanan $pesanan, Admin $oleh, ?StatusProduksi $dari, ?StatusProduksi $ke, ?string $catatan): PesananRiwayat
    {
        $catatan = $catatan !== null ? trim($catatan) : null;

        return PesananRiwayat::create([
            'pesanan_id'    => $pesanan->id,
            'admin_id'      => $oleh->id,
            'nama_pengguna' => Str::limit($oleh->nama, 100, ''),
            'status_dari'   => $dari?->value,
            'status_ke'     => $ke?->value,
            'catatan'       => $catatan === '' ? null : $catatan,
        ]);
    }
}

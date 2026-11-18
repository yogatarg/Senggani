<?php

namespace App\Services;

use App\Enums\StatusProduksi;
use App\Exceptions\StatusBentrok;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Pesanan;
use App\Models\PesananFile;
use App\Models\PesananRiwayat;
use Illuminate\Database\UniqueConstraintViolationException;
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

    /**
     * Info untuk admin setelah buat/perbarui, mis. SPP dikaitkan ke customer lama
     * karena nomor WA-nya sudah terdaftar. null bila tidak ada.
     */
    public ?string $infoCustomer = null;

    // ── Simpan SPP ─────────────────────────────────────

    /**
     * Buat SPP baru beserta item, file, dan riwayat "SPP dibuat".
     *
     * @param  array  $data      kolom pesanan termasuk nomor_spp (tanpa total/customer_id)
     * @param  array  $item      daftar item (lihat isiItem)
     * @param  array  $fileBaru  [['jenis' => 'mockup', 'path' => 'pesanan/sementara/x.webp', 'nama_asli' => '...'], ...]
     * @param  ?int   $customerId customer yang dipilih admin (null = cari lewat WA / buat baru)
     * @param  bool   $perbaruiCustomer timpa data customer dengan isian form
     *
     * @throws ValidationException bila nomor SPP keduluan admin lain (indeks unik)
     */
    public function buat(array $data, array $item, array $fileBaru, Admin $oleh, ?int $customerId = null, bool $perbaruiCustomer = false): Pesanan
    {
        $dipindah = [];
        $this->infoCustomer = null;

        try {
            return DB::transaction(function () use ($data, $item, $fileBaru, $oleh, $customerId, $perbaruiCustomer, &$dipindah) {
                $customer = $this->tentukanCustomer($data, $customerId, $perbaruiCustomer);

                $pesanan = Pesanan::create([
                    ...$data,
                    'customer_id'     => $customer->id,
                    'status_produksi' => StatusProduksi::Antrian->value,
                    'selesai_pada'    => null,
                    'dibuat_oleh'     => $oleh->id,
                    'total'           => 0,
                ]);

                $this->isiItem($pesanan, $item);
                $this->lampirkanFile($pesanan, $fileBaru, $oleh, $dipindah);
                $this->catat($pesanan, $oleh, null, StatusProduksi::Antrian, 'SPP dibuat');

                return $pesanan;
            });
        } catch (Throwable $e) {
            $this->buangFile([...array_column($fileBaru, 'path'), ...$dipindah]);

            throw $this->terjemahkanDuplikat($e, (int) ($data['nomor_spp'] ?? 0));
        }
    }

    /**
     * Perbarui data SPP, ganti seluruh item, tambah file baru. Pergantian nomor SPP
     * dicatat di riwayat (konfirmasinya diperiksa di Form Request).
     *
     * @param  bool  $lepasCustomer  customer_id kosong + true = lepas dari customer lama
     */
    public function perbarui(Pesanan $pesanan, array $data, array $item, array $fileBaru, Admin $oleh, ?int $customerId = null, bool $perbaruiCustomer = false, bool $lepasCustomer = false): Pesanan
    {
        $dipindah = [];
        $this->infoCustomer = null;

        try {
            DB::transaction(function () use ($pesanan, $data, $item, $fileBaru, $oleh, $customerId, $perbaruiCustomer, $lepasCustomer, &$dipindah) {
                // Kolom yang tidak boleh diubah lewat form
                unset($data['status_produksi'], $data['selesai_pada'], $data['dibuat_oleh'], $data['total'], $data['customer_id']);

                $nomorLama = (int) $pesanan->nomor_spp;
                $nomorBaru = (int) ($data['nomor_spp'] ?? $nomorLama);

                // customer_id kosong: tetap customer lama, kecuali admin menekan "Lepas" (lepas_customer=1)
                // sehingga customer ditentukan ulang dari isian seperti saat tambah SPP.
                $customer = $this->tentukanCustomer(
                    $data,
                    $customerId ?? ($lepasCustomer ? null : $pesanan->customer_id),
                    $perbaruiCustomer,
                );

                $pesanan->fill([...$data, 'customer_id' => $customer->id])->save();

                $pesanan->detailPesanan()->delete();
                $this->isiItem($pesanan, $item);
                $this->lampirkanFile($pesanan, $fileBaru, $oleh, $dipindah);

                if ($nomorBaru !== $nomorLama) {
                    $this->catat($pesanan, $oleh, null, null, "Nomor SPP diubah dari #{$nomorLama} menjadi #{$nomorBaru} oleh {$oleh->nama}");
                }
                $this->catat($pesanan, $oleh, null, null, 'SPP diubah');
            });
        } catch (Throwable $e) {
            $this->buangFile([...array_column($fileBaru, 'path'), ...$dipindah]);
            $pesanan->refresh();

            throw $this->terjemahkanDuplikat($e, (int) ($data['nomor_spp'] ?? 0));
        }

        return $pesanan->refresh();
    }

    /**
     * Pelanggaran indeks unik nomor_spp (dua admin menyimpan nomor yang sama hampir
     * bersamaan) diubah menjadi pesan validasi, bukan error 500.
     */
    private function terjemahkanDuplikat(Throwable $e, int $nomor): Throwable
    {
        if (! $e instanceof UniqueConstraintViolationException || ! str_contains($e->getMessage(), 'nomor_spp')) {
            return $e;
        }

        $pemilik = Pesanan::withTrashed()->where('nomor_spp', $nomor)->value('nama_customer');

        return ValidationException::withMessages([
            'nomor_spp' => $pemilik
                ? "SPP #{$nomor} sudah dipakai oleh {$pemilik}."
                : "SPP #{$nomor} baru saja dipakai admin lain. Pakai nomor lain.",
        ]);
    }

    // ── Customer ───────────────────────────────────────

    /**
     * Pilih customer untuk SPP:
     * 1. Nomor WA isian sudah milik customer lain → pakai customer itu (beri tahu admin).
     * 2. Admin memilih customer (customer_id) → pakai customer itu.
     * 3. Selain itu buat customer baru dari isian.
     * Bila $perbarui, data customer ditimpa isian form (nama, alamat, kota, dan WA bila
     * nomornya belum dipakai customer lain). Salinan di SPP lain tidak berubah.
     */
    private function tentukanCustomer(array $data, ?int $customerId, bool $perbarui): Customer
    {
        $wa        = (string) $data['nomor_whatsapp'];
        $dipilih   = $customerId ? Customer::find($customerId) : null;
        $pemilikWa = Customer::where('nomor_whatsapp', $wa)->first();

        if ($pemilikWa && (! $dipilih || $dipilih->id !== $pemilikWa->id)) {
            $this->infoCustomer = "Nomor WhatsApp {$pemilikWa->whatsapp_tampil} sudah terdaftar atas customer "
                . "{$pemilikWa->kode} ({$pemilikWa->nama}), jadi SPP ini dikaitkan ke customer tersebut.";
            $customer = $pemilikWa;
        } elseif ($dipilih) {
            $customer = $dipilih;
        } else {
            // createOrFirst: aman bila dua admin membuat customer ber-WA sama bersamaan
            return Customer::createOrFirst(['nomor_whatsapp' => $wa], [
                'nama'   => $data['nama_customer'],
                'alamat' => $data['alamat'] ?? null,
                'kota'   => $data['kota'] ?? null,
            ]);
        }

        if ($perbarui) {
            $ubah = [
                'nama'   => $data['nama_customer'],
                'alamat' => $data['alamat'] ?? null,
                'kota'   => $data['kota'] ?? null,
            ];
            if ($customer->nomor_whatsapp !== $wa && ! Customer::where('nomor_whatsapp', $wa)->exists()) {
                $ubah['nomor_whatsapp'] = $wa;
            }
            $customer->update($ubah);
        }

        return $customer;
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
                throw new \RuntimeException('Gambar gagal dipindahkan ke folder SPP.');
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
                'status_lama' => 'SPP yang sudah selesai atau dibatalkan tidak bisa dibatalkan.',
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
            return new StatusBentrok('SPP ini sudah dihapus oleh admin.');
        }

        $pelaku = PesananRiwayat::where('pesanan_id', $pesanan->id)
            ->whereNotNull('status_ke')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->value('nama_pengguna');

        $status = $segar->status_produksi;
        $oleh   = $pelaku ? " oleh {$pelaku}" : '';

        return new StatusBentrok(
            "Status SPP ini sudah diubah{$oleh} menjadi {$status->label()}. Tampilan sudah diperbarui.",
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

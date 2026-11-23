<?php

namespace App\Services;

use App\Enums\MetodeBayar;
use App\Enums\StatusProduksi;
use App\Exceptions\AksiDitolak;
use App\Exceptions\StatusBentrok;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Pesanan;
use App\Models\PesananFile;
use App\Models\PesananRiwayat;
use Illuminate\Auth\Access\AuthorizationException;
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
     * Buat pesanan baru beserta item, file, dan riwayat "SPP dibuat". Tanpa nomor SPP,
     * DP, maupun pelunasan (semuanya dicatat setelah harga disepakati).
     * Status awal: hitung_harga, atau langsung menunggu_dp bila semua harga item sudah diisi.
     *
     * @param  array  $data      kolom pesanan (tanpa nomor_spp/total/customer_id/dp/tanggal_lunas)
     * @param  array  $item      daftar item (lihat isiItem; harga_satuan boleh null)
     * @param  array  $fileBaru  [['jenis' => 'mockup', 'path' => 'pesanan/sementara/x.webp', 'nama_asli' => '...'], ...]
     * @param  ?int   $customerId customer yang dipilih admin (null = cari lewat WA / buat baru)
     * @param  bool   $perbaruiCustomer timpa data customer dengan isian form
     */
    public function buat(array $data, array $item, array $fileBaru, Admin $oleh, ?int $customerId = null, bool $perbaruiCustomer = false): Pesanan
    {
        $dipindah = [];
        $this->infoCustomer = null;

        unset($data['nomor_spp'], $data['dp'], $data['tanggal_dp'], $data['tanggal_lunas'], $data['metode_dp'], $data['metode_lunas']);

        try {
            return DB::transaction(function () use ($data, $item, $fileBaru, $oleh, $customerId, $perbaruiCustomer, &$dipindah) {
                $customer = $this->tentukanCustomer($data, $customerId, $perbaruiCustomer);

                $status = self::hargaItemLengkap($item) ? StatusProduksi::MenungguDp : StatusProduksi::HitungHarga;

                $pesanan = Pesanan::create([
                    ...$data,
                    'nomor_spp'       => null,
                    'customer_id'     => $customer->id,
                    'status_produksi' => $status->value,
                    'selesai_pada'    => null,
                    'dibuat_oleh'     => $oleh->id,
                    'total'           => 0,
                    'dp'              => 0,
                ]);

                $this->isiItem($pesanan, $item);
                $this->lampirkanFile($pesanan, $fileBaru, $oleh, $dipindah);
                $this->catat($pesanan, $oleh, null, $status, 'SPP dibuat');

                return $pesanan;
            });
        } catch (Throwable $e) {
            $this->buangFile([...array_column($fileBaru, 'path'), ...$dipindah]);

            throw $e;
        }
    }

    /** Semua item punya harga_satuan (bukan null). */
    private static function hargaItemLengkap(array $item): bool
    {
        if ($item === []) {
            return false;
        }

        foreach ($item as $baris) {
            if (! isset($baris['harga_satuan']) || $baris['harga_satuan'] === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Perbarui data SPP, ganti seluruh item, tambah file baru. Pergantian nomor SPP dan
     * perubahan total harga dicatat di riwayat (konfirmasi nomor diperiksa di Form Request).
     * Pesanan berstatus hitung_harga yang kini semua harganya terisi pindah ke menunggu_dp.
     * Nomor SPP, DP, dan tanggal lunas hanya diubah lewat form ini bila SPP sudah bernomor /
     * sudah masuk produksi (Form Request membuang isian itu bila belum).
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
                $totalLama  = (float) $pesanan->total;
                $ongkirLama = (float) $pesanan->ongkir;
                $diskonLama = (float) $pesanan->diskon;

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

                if ($nomorLama && $nomorBaru !== $nomorLama) {
                    $this->catat($pesanan, $oleh, null, null, "Nomor SPP diubah dari #{$nomorLama} menjadi #{$nomorBaru} oleh {$oleh->nama}");
                }
                if ($totalLama !== (float) $pesanan->total) {
                    $this->catat($pesanan, $oleh, null, null, 'Harga diubah oleh ' . $oleh->nama . ': total '
                        . self::rupiah($totalLama) . ' → ' . self::rupiah((float) $pesanan->total));
                }
                if ($ongkirLama !== (float) $pesanan->ongkir) {
                    $this->catat($pesanan, $oleh, null, null, 'Ongkir diubah oleh ' . $oleh->nama . ': '
                        . self::rupiah($ongkirLama) . ' → ' . self::rupiah((float) $pesanan->ongkir));
                }
                if ($diskonLama !== (float) $pesanan->diskon) {
                    $this->catat($pesanan, $oleh, null, null, 'Diskon diubah oleh ' . $oleh->nama . ': '
                        . self::rupiah($diskonLama) . ' → ' . self::rupiah((float) $pesanan->diskon));
                }
                $this->catat($pesanan, $oleh, null, null, 'SPP diubah');

                if ($pesanan->status_produksi === StatusProduksi::HitungHarga && self::hargaItemLengkap($item)) {
                    $this->pindahStatusDalamTransaksi($pesanan, StatusProduksi::HitungHarga, StatusProduksi::MenungguDp,
                        "Harga dihitung oleh {$oleh->nama}", $oleh);
                }
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
     * Harga kosong (null) = belum dihitung: subtotal null dan tidak ikut total.
     * Nilai subtotal/total kiriman form diabaikan.
     */
    private function isiItem(Pesanan $pesanan, array $item): void
    {
        $total = 0;

        foreach (array_values($item) as $i => $baris) {
            $jumlah   = (int) $baris['jumlah'];
            $adaHarga = isset($baris['harga_satuan']) && $baris['harga_satuan'] !== '';
            $harga    = $adaHarga ? (int) $baris['harga_satuan'] : null;
            $subtotal = $adaHarga ? $jumlah * $harga : null;
            $total   += (int) $subtotal;

            $pesanan->detailPesanan()->create([
                'urutan'          => $i,
                'jenis_packaging' => $baris['jenis_packaging'],
                'panjang'         => $baris['panjang'] ?? null,
                'lebar'           => $baris['lebar'] ?? null,
                'tinggi'          => $baris['tinggi'] ?? null,
                'jenis_bahan'     => $baris['jenis_bahan'],
                'jenis_cetak'     => $baris['jenis_cetak'] ?? null,
                'finishing'       => $baris['finishing'] ?? null,
                'aksesori'        => $baris['aksesori'] ?? null,
                'catatan'         => ($c = trim((string) ($baris['catatan'] ?? ''))) === '' ? null : $c,
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

    // ── Harga ──────────────────────────────────────────

    /**
     * Isi/ubah harga satuan per item (produksi selama hitung_harga, admin kapan saja).
     * Item yang tidak ada di $harga tidak diubah; null = kosongkan (hanya boleh sebelum
     * menunggu DP, dicek di Form Request). Total dihitung ulang. Bila status hitung_harga
     * dan semua item kini berharga → otomatis menunggu_dp ("Harga dihitung oleh {nama}").
     *
     * @param  array<int, int|null>  $harga  detail_pesanan.id => harga satuan rupiah
     * @param  ?string  $catatan  catatan perhitungan (null = tidak diubah, '' = dikosongkan)
     * @return string pesan untuk pengguna
     *
     * @throws AksiDitolak bila produksi menyimpan setelah status bukan hitung_harga lagi
     * @throws ValidationException bila total tagihan baru lebih kecil dari DP, atau diskon melebihi subtotal
     */
    public function isiHarga(Pesanan $pesanan, array $harga, ?string $catatan, Admin $oleh): string
    {
        $pesan = DB::transaction(function () use ($pesanan, $harga, $catatan, $oleh) {
            $segar = Pesanan::whereKey($pesanan->id)->lockForUpdate()->first();
            if (! $segar) {
                throw $this->bentrok($pesanan);
            }

            $status = $segar->status_produksi;
            if (! $oleh->isAdmin() && $status !== StatusProduksi::HitungHarga) {
                throw new AksiDitolak(AksiDitolak::HARGA_DIKUNCI);
            }

            $totalLama = (float) $segar->total;
            $item      = $segar->detailPesanan()->get();
            $berubah   = false;
            $total     = 0;

            foreach ($item as $d) {
                if (array_key_exists($d->id, $harga)) {
                    $baru = $harga[$d->id] === null ? null : (int) $harga[$d->id];
                    $lama = $d->harga_satuan === null ? null : (int) $d->harga_satuan;

                    if ($baru !== $lama) {
                        $d->forceFill([
                            'harga_satuan' => $baru,
                            'subtotal'     => $baru === null ? null : $baru * (int) $d->jumlah,
                        ])->save();
                        $berubah = true;
                    }
                }
                $total += (int) $d->subtotal;
            }

            $lengkap = $item->every(fn ($d) => $d->harga_satuan !== null);

            // Diskon dibandingkan dengan subtotal hanya bila semua harga sudah terisi
            if ($lengkap && (float) $segar->diskon > $total) {
                throw ValidationException::withMessages([
                    'harga' => 'Subtotal produk baru (' . self::rupiah($total) . ') lebih kecil dari diskon ('
                        . self::rupiah((float) $segar->diskon) . '). Ubah diskon di form SPP dulu.',
                ]);
            }

            $tagihan = Pesanan::hitungTotalTagihan($total, $segar->ongkir, $segar->diskon);
            if ($tagihan < (float) $segar->dp) {
                throw ValidationException::withMessages([
                    'harga' => 'Total tagihan baru (' . self::rupiah($tagihan) . ') lebih kecil dari DP yang sudah dicatat ('
                        . self::rupiah((float) $segar->dp) . ').',
                ]);
            }

            $ubah = ['total' => $total];
            if ($catatan !== null) {
                $ubah['catatan_harga'] = trim($catatan) === '' ? null : trim($catatan);
            }
            $segar->forceFill($ubah)->save();
            $segar->touch();

            $tambahan = filled($catatan) ? "\nCatatan: " . trim($catatan) : '';

            if ($status === StatusProduksi::HitungHarga && $lengkap) {
                $this->pindahStatusDalamTransaksi($segar, StatusProduksi::HitungHarga, StatusProduksi::MenungguDp,
                    "Harga dihitung oleh {$oleh->nama}" . $tambahan, $oleh);

                return 'Harga disimpan. SPP pindah ke ' . StatusProduksi::MenungguDp->label() . '.';
            }

            if ($status === StatusProduksi::HitungHarga) {
                if ($berubah || $tambahan !== '') {
                    $terisi = $item->filter(fn ($d) => $d->harga_satuan !== null)->count();
                    $this->catat($segar, $oleh, null, null,
                        "Harga disimpan sebagian oleh {$oleh->nama} ({$terisi} dari {$item->count()} item)" . $tambahan);
                }

                return 'Harga disimpan. Masih ada item yang belum diberi harga.';
            }

            if ($berubah || $tambahan !== '') {
                $this->catat($segar, $oleh, null, null, 'Harga diubah oleh ' . $oleh->nama . ': total '
                    . self::rupiah($totalLama) . ' → ' . self::rupiah($total) . $tambahan);
            }

            return 'Harga disimpan.';
        });

        $pesanan->refresh();

        return $pesan;
    }

    private static function rupiah(float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    // ── DP & masuk produksi ────────────────────────────

    /**
     * Catat DP, beri nomor SPP, dan pindahkan menunggu_dp → proses (khusus admin).
     *
     * @throws StatusBentrok bila status bukan menunggu_dp lagi
     * @throws ValidationException bila nomor SPP keduluan admin lain (indeks unik)
     */
    public function catatDp(Pesanan $pesanan, int $dp, Carbon $tanggal, int $nomor, MetodeBayar $metode, Admin $oleh): void
    {
        $nomorLama = $pesanan->nomor_spp ? (int) $pesanan->nomor_spp : null;

        try {
            $this->pindahStatus(
                $pesanan, StatusProduksi::MenungguDp, StatusProduksi::Proses,
                'DP ' . self::rupiah($dp) . ' via ' . $metode->label() . ' dicatat ('
                    . $tanggal->translatedFormat('d/m/Y') . "), nomor SPP #{$nomor}",
                $oleh,
                kolom: [
                    'dp'         => $dp,
                    'tanggal_dp' => $tanggal->toDateString(),
                    'metode_dp'  => $metode->value,
                    'nomor_spp'  => $nomor,
                ],
                setelah: function (Pesanan $p) use ($nomorLama, $nomor, $oleh) {
                    if ($nomorLama && $nomorLama !== $nomor) {
                        $this->catat($p, $oleh, null, null, "Nomor SPP diubah dari #{$nomorLama} menjadi #{$nomor} oleh {$oleh->nama}");
                    }
                },
            );
        } catch (Throwable $e) {
            $pesanan->refresh();

            throw $this->terjemahkanDuplikat($e, $nomor);
        }
    }

    // ── Status produksi ────────────────────────────────

    /**
     * Maju/mundur SATU langkah. $dari = status yang dilihat pengguna di layar;
     * bila di database sudah berbeda, StatusBentrok dilempar dan tidak ada yang berubah.
     *
     * - hitung_harga → menunggu_dp hanya lewat isiHarga; menunggu_dp → proses hanya lewat catatDp.
     * - proses → menunggu_dp khusus admin (nomor SPP tetap).
     * - selesai_produksi → pengiriman wajib lunas (AksiDitolak::BELUM_LUNAS).
     */
    public function ubahStatus(Pesanan $pesanan, StatusProduksi $dari, StatusProduksi $ke, ?string $catatan, Admin $oleh): void
    {
        if ($ke !== $dari->berikutnya() && $ke !== $dari->sebelumnya()) {
            throw ValidationException::withMessages([
                'status_baru' => 'Status hanya bisa dipindah satu langkah maju atau mundur.',
            ]);
        }

        if ($dari === StatusProduksi::HitungHarga && $ke === StatusProduksi::MenungguDp) {
            throw ValidationException::withMessages([
                'status_baru' => 'Isi harga semua item dulu. SPP pindah ke Menunggu DP otomatis setelah harga disimpan.',
            ]);
        }

        if ($dari === StatusProduksi::MenungguDp && $ke === StatusProduksi::Proses) {
            throw ValidationException::withMessages([
                'status_baru' => 'Gunakan "Catat DP & masukkan ke produksi" untuk memindahkan SPP ke Dalam Proses.',
            ]);
        }

        if ($dari === StatusProduksi::Proses && $ke === StatusProduksi::MenungguDp && ! $oleh->isAdmin()) {
            throw new AuthorizationException('Hanya admin yang bisa mengembalikan SPP ke Menunggu DP.');
        }

        $syarat = null;
        if ($dari === StatusProduksi::SelesaiProduksi && $ke === StatusProduksi::Pengiriman) {
            $syarat = function (Pesanan $segar) {
                if (! $segar->tanggal_lunas) {
                    throw new AksiDitolak(AksiDitolak::BELUM_LUNAS);
                }
            };
        }

        $this->pindahStatus($pesanan, $dari, $ke, $catatan, $oleh, $syarat);
    }

    /**
     * Langkah maju/mundur yang boleh dipakai pengguna lewat tombol status (admin.pesanan.status).
     * hitung_harga & menunggu_dp tidak punya tombol maju (lewat isi harga / Catat DP);
     * proses → menunggu_dp hanya admin. Status di luar alur: tidak ada langkah.
     *
     * @return array{maju: ?StatusProduksi, mundur: ?StatusProduksi}
     */
    public static function langkahManual(StatusProduksi $status, ?Admin $pengguna): array
    {
        $maju   = $status->sebelumProduksi() ? null : $status->berikutnya();
        $mundur = $status->sebelumnya();

        if ($status === StatusProduksi::Proses && ! $pengguna?->isAdmin()) {
            $mundur = null;
        }

        return ['maju' => $maju, 'mundur' => $mundur];
    }

    /** Batalkan SPP yang sudah masuk produksi (khusus admin, dicek di Policy). */
    public function batalkan(Pesanan $pesanan, StatusProduksi $dari, ?string $catatan, Admin $oleh): void
    {
        if (! $dari->bisaDibatalkan()) {
            throw ValidationException::withMessages([
                'status_lama' => $dari->sebelumProduksi()
                    ? 'SPP yang belum masuk produksi ditandai "Tidak Jadi", bukan dibatalkan.'
                    : 'SPP yang sudah selesai, tidak jadi, atau dibatalkan tidak bisa dibatalkan.',
            ]);
        }

        $this->pindahStatus($pesanan, $dari, StatusProduksi::Dibatalkan, $catatan, $oleh);
    }

    /** Tandai "Tidak Jadi" SPP yang belum masuk produksi (khusus admin). */
    public function tidakJadi(Pesanan $pesanan, StatusProduksi $dari, ?string $catatan, Admin $oleh): void
    {
        if (! $dari->sebelumProduksi()) {
            throw ValidationException::withMessages([
                'status_lama' => 'Hanya SPP berstatus Hitung Harga atau Menunggu DP yang bisa ditandai Tidak Jadi. '
                    . 'SPP yang sudah masuk produksi dibatalkan lewat "Batalkan SPP".',
            ]);
        }

        $this->pindahStatus($pesanan, $dari, StatusProduksi::TidakJadi, $catatan, $oleh);
    }

    /**
     * Kembalikan SPP "dibatalkan" / "tidak jadi" ke status sebelumnya (khusus admin).
     * Status lama "antrian" dipulihkan ke proses; menunggu_dp dengan harga belum lengkap
     * ke hitung_harga; status produksi tanpa nomor SPP ke menunggu_dp; pengiriman/selesai yang belum
     * lunas ke selesai_produksi (alasannya dicatat di riwayat).
     */
    public function pulihkan(Pesanan $pesanan, ?string $catatan, Admin $oleh): StatusProduksi
    {
        $dari = $pesanan->status_produksi;

        if ($dari !== StatusProduksi::Dibatalkan && $dari !== StatusProduksi::TidakJadi) {
            throw ValidationException::withMessages([
                'status_lama' => 'Hanya SPP yang dibatalkan atau tidak jadi yang bisa dipulihkan.',
            ]);
        }

        $ke = PesananRiwayat::where('pesanan_id', $pesanan->id)
            ->where('status_ke', $dari->value)
            ->whereNotNull('status_dari')
            ->orderByDesc('created_at')->orderByDesc('id')
            ->value('status_dari');

        $ke = $ke instanceof StatusProduksi ? $ke : StatusProduksi::tryFrom((string) $ke);

        if ($ke === null || $ke === StatusProduksi::Dibatalkan || $ke === StatusProduksi::TidakJadi) {
            $ke = $dari === StatusProduksi::TidakJadi ? StatusProduksi::MenungguDp : StatusProduksi::Proses;
        }
        if ($ke === StatusProduksi::Antrian) {
            $ke = StatusProduksi::Proses;
        }
        if ($ke->sudahProduksi() && ! $pesanan->nomor_spp) {
            $ke = StatusProduksi::MenungguDp;
        }
        if ($ke === StatusProduksi::MenungguDp && ! $pesanan->harga_lengkap) {
            $ke = StatusProduksi::HitungHarga;
        }
        // Kunci pelunasan: pengiriman/selesai hanya untuk SPP yang sudah lunas
        if (($ke === StatusProduksi::Pengiriman || $ke === StatusProduksi::Selesai) && ! $pesanan->tanggal_lunas) {
            $alasan  = "Dipulihkan ke " . StatusProduksi::SelesaiProduksi->label() . " (bukan {$ke->label()}) karena SPP belum lunas.";
            $catatan = filled($catatan) ? trim($catatan) . "\n" . $alasan : $alasan;
            $ke      = StatusProduksi::SelesaiProduksi;
        }

        $this->pindahStatus($pesanan, $dari, $ke, $catatan, $oleh);

        return $ke;
    }

    /**
     * Pindah status dengan kunci baris: hanya berhasil bila status di DB masih $dari
     * (status yang dilihat pengguna). $syarat(Pesanan $segar) boleh melempar untuk menolak;
     * $kolom = kolom tambahan yang ikut diubah; $setelah(Pesanan) dijalankan di transaksi yang sama.
     */
    private function pindahStatus(Pesanan $pesanan, StatusProduksi $dari, StatusProduksi $ke, ?string $catatan, Admin $oleh,
        ?callable $syarat = null, array $kolom = [], ?callable $setelah = null): void
    {
        try {
            DB::transaction(function () use ($pesanan, $dari, $ke, $catatan, $oleh, $syarat, $kolom, $setelah) {
                $segar = Pesanan::whereKey($pesanan->id)->lockForUpdate()->first();

                if (! $segar || $segar->status_produksi !== $dari) {
                    throw $this->bentrok($pesanan);
                }

                if ($syarat) {
                    $syarat($segar);
                }

                $this->pindahStatusDalamTransaksi($segar, $dari, $ke, $catatan, $oleh, $kolom);

                if ($setelah) {
                    $setelah($segar);
                }
            });
        } finally {
            $pesanan->refresh();
        }
    }

    /** Ubah status (+ kolom tambahan) dan catat riwayat; dipanggil di dalam transaksi. */
    private function pindahStatusDalamTransaksi(Pesanan $pesanan, StatusProduksi $dari, StatusProduksi $ke, ?string $catatan, Admin $oleh, array $kolom = []): void
    {
        // Update bersyarat: hanya berhasil bila status di DB masih sama dengan yang dilihat pengguna
        $berubah = Pesanan::whereKey($pesanan->id)
            ->where('status_produksi', $dari->value)
            ->update([
                ...$kolom,
                'status_produksi' => $ke->value,
                'selesai_pada'    => $ke->riwayat() ? now() : null,
            ]);

        if ($berubah === 0) {
            throw $this->bentrok($pesanan);
        }

        $this->catat($pesanan, $oleh, $dari, $ke, $catatan);
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

    public function catatLunas(Pesanan $pesanan, Carbon $tanggal, MetodeBayar $metode, Admin $oleh): void
    {
        DB::transaction(function () use ($pesanan, $tanggal, $metode, $oleh) {
            $pesanan->update(['tanggal_lunas' => $tanggal->toDateString(), 'metode_lunas' => $metode]);
            $this->catat($pesanan, $oleh, null, null, 'Pelunasan via ' . $metode->label() . ' dicatat, tanggal '
                . $tanggal->translatedFormat('d/m/Y'));
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

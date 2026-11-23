<?php

namespace App\Services;

use App\Enums\StatusProduksi;
use App\Models\Customer;
use App\Models\DetailPesanan;
use App\Models\Pesanan;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DokumenPdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Invoice PDF SPP (DomPDF, A4). Rancangan: catatan/2026-11-23-desain-invoice-senggani.md.
 * Semua angka uang dihitung dari Pesanan (total tagihan = subtotal + ongkir − diskon).
 * Gambar (logo, ttd, stempel, QRIS) disisipkan sebagai data URI, jadi chroot/remote tidak dibuka.
 */
class InvoicePdf
{
    public const VIEW = 'admin.pesanan.invoice-pdf';

    public const MAKS_CATATAN = 200;

    public function __construct(private PengaturanInvoice $pengaturan)
    {
    }

    /** Bangun PDF (sudah dirender, termasuk footer nomor halaman). */
    public function buat(Pesanan $pesanan): DokumenPdf
    {
        $pesanan->loadMissing('detailPesanan');

        File::ensureDirectoryExists(storage_path('fonts'));

        $pdf = Pdf::setOption([
            'isFontSubsettingEnabled' => true,
            'isRemoteEnabled'         => false,
            'defaultFont'             => 'DejaVu Sans',
            'fontDir'                 => storage_path('fonts'),
            'fontCache'               => storage_path('fonts'),
            'chroot'                  => base_path(),
        ])
            ->setPaper('a4', 'portrait')
            ->loadView(self::VIEW, $this->dataView($pesanan));

        // Metadata PDF memakai tanda hubung biasa: DomPDF tidak menyandikan "·" dengan benar di info dokumen
        $judul = 'Invoice ' . $pesanan->nomor_invoice . ' - ' . config('usaha.nama');
        $pdf->addInfo(['Title' => $judul, 'Author' => (string) config('usaha.nama'), 'Creator' => (string) config('usaha.nama')]);

        $pdf->render();
        $this->tulisFooter($pdf, $pesanan);

        return $pdf;
    }

    /** "Invoice-INV-176-Putri-Maulidina.pdf" (nama customer maksimal 40 karakter, huruf/angka/tanda hubung). */
    public static function namaFile(Pesanan $pesanan): string
    {
        $nama = Str::ascii((string) $pesanan->nama_customer);
        $nama = trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', $nama), '-');
        $nama = trim(Str::limit($nama, 40, ''), '-');

        return 'Invoice-' . $pesanan->nomor_invoice . ($nama !== '' ? '-' . $nama : '') . '.pdf';
    }

    /**
     * SPP untuk "Lihat contoh PDF": SPP terbaru yang memenuhi syarat invoice, atau data contoh
     * (tidak disimpan) bila belum ada.
     */
    public function pesananContoh(): Pesanan
    {
        $pesanan = Pesanan::query()
            ->whereIn('status_produksi', Pesanan::STATUS_INVOICE)
            ->latest('id')
            ->first();

        if ($pesanan) {
            return $pesanan;
        }

        $contoh = new Pesanan([
            'nama_customer'   => 'Putri Maulidina',
            'nama_brand'      => 'BTS Arirang',
            'nomor_whatsapp'  => '6282228946660',
            'tanggal_order'   => today(),
            'deadline'        => today()->addDays(9),
            'jenis_deadline'  => 'kirim',
            'total'           => 2250000,
            'ongkir'          => 25000,
            'diskon'          => 50000,
            'dp'              => 1200000,
            'tanggal_dp'      => today(),
            'metode_dp'       => 'transfer_bca',
            'status_produksi' => StatusProduksi::Proses->value,
        ]);
        $contoh->setRelation('detailPesanan', collect([
            new DetailPesanan([
                'jenis_packaging' => 'Paperbag', 'panjang' => 25, 'lebar' => 10, 'tinggi' => 30,
                'jenis_bahan' => 'Kraft', 'gramasi' => '150 gsm', 'finishing' => 'Laminasi doff',
                'jenis_cetak' => 'Sablon', 'warna_cetak' => '1 warna', 'aksesori' => 'Tali kur',
                'catatan' => 'Logo sisi depan saja', 'jumlah' => 500, 'harga_satuan' => 2500, 'subtotal' => 1250000,
            ]),
            new DetailPesanan([
                'jenis_packaging' => 'Box', 'jenis_bahan' => 'Duplex', 'gramasi' => '310 gsm',
                'jumlah' => 200, 'harga_satuan' => 5000, 'subtotal' => 1000000,
            ]),
        ]));

        return $contoh;
    }

    // ── Data view ──────────────────────────────────────

    private function dataView(Pesanan $pesanan): array
    {
        $set    = $this->pengaturan->semua();
        $status = $pesanan->status_bayar_invoice;

        return [
            'pesanan'     => $pesanan,
            'nomor'       => $pesanan->nomor_invoice,
            'usaha'       => $this->usaha(),
            'logo'        => $this->logo(),
            'kepada'      => $this->kepada($pesanan),
            'tanggal'     => [
                'order'         => self::tanggal($pesanan->tanggal_order),
                'deadline'      => self::tanggal($pesanan->deadline),
                'jenisDeadline' => match ($pesanan->jenis_deadline) {
                    'kirim' => '(dikirim)',
                    'siap'  => '(siap diambil)',
                    default => null,
                },
            ],
            'statusBayar' => $status,
            'labelStatus' => Pesanan::STATUS_BAYAR_INVOICE[$status],
            'item'        => $this->item($pesanan),
            'ringkasan'   => $this->ringkasan($pesanan, $set),
            'rekening'    => $set['rekening'],
            'atasNamaSama' => $this->atasNamaSama($set['rekening']),
            'qris'        => $this->pengaturan->dataUri('qris'),
            'qrisKeterangan' => $set['qris_keterangan'],
            // Tidak ada yang perlu dibayar lagi (LUNAS atau sisa <= 0) → tanpa pengingat
            'qrisPengingat'  => match (true) {
                $status === 'lunas' || (float) $pesanan->sisa <= 0 => null,
                $status === 'belum_bayar' => 'Ketik nominal DP/tagihan sendiri saat membayar.',
                default => 'Ketik nominal sesuai sisa tagihan saat membayar.',
            },
            'keterangan'  => $set['keterangan_bawah'],
            'penandatangan' => [
                'nama'    => $set['penandatangan_nama'],
                'jabatan' => $set['penandatangan_jabatan'],
                'ttd'     => $this->ukurGambar($this->pengaturan->dataUri('ttd'), maksLebar: 58, maksTinggi: 20),
                'stempel' => $this->ukurGambar($this->pengaturan->dataUri('stempel'), maksLebar: 40, maksTinggi: 22),
            ],
            'fontRegular' => str_replace(DIRECTORY_SEPARATOR, '/', resource_path('fonts/Poppins-Regular.ttf')),
            'fontBold'    => str_replace(DIRECTORY_SEPARATOR, '/', resource_path('fonts/Poppins-SemiBold.ttf')),
        ];
    }

    private function usaha(): array
    {
        $a = (array) config('usaha.alamat');
        $alamat = implode(', ', array_filter([$a['jalan'] ?? null, $a['kelurahan'] ?? null, $a['kecamatan'] ?? null]));
        $kota = trim(($a['kota'] ?? '') . ' ' . ($a['kode_pos'] ?? ''));

        return [
            'nama'   => (string) config('usaha.nama'),
            'alamat' => trim($alamat . ($kota !== '' ? ', ' . $kota : '')),
            'wa'     => self::waLokal((string) config('usaha.whatsapp')),
        ];
    }

    /** 6283829208316 → 0838-2920-8316 */
    public static function waLokal(string $nomor): string
    {
        $angka = preg_replace('/\D+/', '', $nomor) ?? '';
        if (str_starts_with($angka, '62')) {
            $angka = '0' . substr($angka, 2);
        }

        return implode('-', str_split($angka, 4));
    }

    private function logo(): ?array
    {
        $path = public_path('images/logo.png');

        return is_file($path) ? PengaturanInvoice::dataUriDariIsi((string) file_get_contents($path)) : null;
    }

    private function kepada(Pesanan $pesanan): array
    {
        $nama = trim((string) $pesanan->nama_customer);
        if (filled($pesanan->nama_brand)) {
            $nama .= ' / ' . trim($pesanan->nama_brand);
        }

        $wa = (string) $pesanan->nomor_whatsapp;

        return [
            'nama' => $nama,
            'wa'   => $wa !== '' ? (new Customer(['nomor_whatsapp' => $wa]))->whatsapp_tampil : null,
        ];
    }

    /** @return list<array{no: int, judul: string, spek: string, catatan: ?string, jumlah: string, harga: string, subtotal: string}> */
    private function item(Pesanan $pesanan): array
    {
        $hasil = [];

        foreach ($pesanan->detailPesanan->values() as $i => $d) {
            $ukuran = array_filter([$d->panjang, $d->lebar, $d->tinggi], fn ($n) => $n !== null && $n !== '' && (float) $n > 0);
            $judul  = trim((string) $d->jenis_packaging);
            if ($ukuran) {
                $judul .= ' · ' . implode(' × ', array_map(fn ($n) => self::angkaRingkas($n), $ukuran)) . ' cm';
            }

            $catatan = trim((string) preg_replace('/\s*\R\s*/u', ' ', (string) $d->catatan));

            $hasil[] = [
                'no'       => $i + 1,
                'judul'    => $judul,
                'spek'     => implode(' + ', Pesanan::spekItem($d)),
                'catatan'  => $catatan === '' ? null : Str::limit($catatan, self::MAKS_CATATAN, '…'),
                'jumlah'   => number_format((int) $d->jumlah, 0, ',', '.') . ' pcs',
                'harga'    => $d->harga_satuan === null ? '–' : self::rupiah($d->harga_satuan),
                'subtotal' => $d->subtotal === null ? '–' : self::rupiah($d->subtotal),
            ];
        }

        return $hasil;
    }

    /**
     * Baris ringkasan kanan. Tiap baris: label, nilai (null = tanpa nilai), gaya.
     *
     * @return array{baris: list<array{label: string, nilai: ?string, gaya: string}>, total: string,
     *   bayar: list<array{label: string, nilai: ?string, gaya: string}>, sisa: string, sisaLunas: bool,
     *   kelebihan: ?string, dpMinimal: ?string}
     */
    private function ringkasan(Pesanan $pesanan, array $set): array
    {
        $baris = [['label' => 'Subtotal produk', 'nilai' => self::rupiah($pesanan->subtotal_produk), 'gaya' => '']];
        if ((float) $pesanan->ongkir > 0) {
            $baris[] = ['label' => 'Ongkir', 'nilai' => self::rupiah($pesanan->ongkir), 'gaya' => ''];
        }
        if ((float) $pesanan->diskon > 0) {
            $baris[] = ['label' => 'Diskon', 'nilai' => '− ' . self::rupiah($pesanan->diskon), 'gaya' => ''];
        }

        $bayar = [];
        if ((float) $pesanan->dp > 0) {
            $label = $pesanan->metode_dp ? 'DP via ' . $pesanan->metode_dp->label() : 'DP';
            if ($pesanan->tanggal_dp) {
                $label .= ' (' . self::tanggal($pesanan->tanggal_dp) . ')';
            }
            $bayar[] = ['label' => $label, 'nilai' => '− ' . self::rupiah($pesanan->dp), 'gaya' => ''];
        }
        if ($pesanan->tanggal_lunas && $pesanan->nilai_pelunasan > 0) {
            $label = $pesanan->metode_lunas ? 'Pelunasan via ' . $pesanan->metode_lunas->label() : 'Pelunasan';
            $label .= ' (' . self::tanggal($pesanan->tanggal_lunas) . ')';
            $bayar[] = ['label' => $label, 'nilai' => '− ' . self::rupiah($pesanan->nilai_pelunasan), 'gaya' => ''];
        }
        if ($bayar === []) {
            $bayar[] = ['label' => 'Belum ada pembayaran', 'nilai' => null, 'gaya' => 'redup'];
        }

        // Sisa di invoice: lunas → 0; selain itu total tagihan − DP (tidak negatif)
        $sisa = $pesanan->status_bayar_invoice === 'lunas' ? 0.0 : $pesanan->sisa;

        $dpMinimal = null;
        $persen = (int) $set['persen_dp'];
        if ($pesanan->status_bayar_invoice === 'belum_bayar' && $persen > 0 && $pesanan->total_tagihan > 0) {
            $dpMinimal = 'DP minimal ' . $persen . '%: ' . self::rupiah(ceil($pesanan->total_tagihan * $persen / 100));
        }

        return [
            'baris'     => $baris,
            'total'     => self::rupiah($pesanan->total_tagihan),
            'bayar'     => $bayar,
            'sisa'      => self::rupiah($sisa),
            'sisaLunas' => $sisa <= 0,
            'kelebihan' => $pesanan->kelebihan_bayar > 0 ? 'Kelebihan bayar ' . self::rupiah($pesanan->kelebihan_bayar) : null,
            'dpMinimal' => $dpMinimal,
        ];
    }

    /** Atas nama bersama bila semua rekening atas nama sama (ditulis sekali); null bila berbeda. */
    private function atasNamaSama(array $rekening): ?string
    {
        $nama = array_unique(array_map(fn ($r) => trim((string) ($r['atas_nama'] ?? '')), $rekening));

        return count($nama) === 1 ? (reset($nama) ?: null) : null;
    }

    /**
     * Ukuran tampil (mm) gambar ttd/stempel: muat di kotak maksLebar × maksTinggi tanpa mengubah rasio.
     *
     * @return array{uri: string, lebar: float, tinggi: float}|null
     */
    private function ukurGambar(?array $gambar, float $maksLebar, float $maksTinggi): ?array
    {
        if (! $gambar || $gambar['lebar'] <= 0 || $gambar['tinggi'] <= 0) {
            return null;
        }

        $skala = min($maksLebar / $gambar['lebar'], $maksTinggi / $gambar['tinggi']);

        return [
            'uri'    => $gambar['uri'],
            'lebar'  => round($gambar['lebar'] * $skala, 2),
            'tinggi' => round($gambar['tinggi'] * $skala, 2),
        ];
    }

    /** Footer tiap halaman: kiri "INV-176 · Senggani…", kanan "Halaman X dari Y". */
    private function tulisFooter(DokumenPdf $pdf, Pesanan $pesanan): void
    {
        $dompdf  = $pdf->getDomPDF();
        $kanvas  = $dompdf->getCanvas();
        $metrik  = $dompdf->getFontMetrics();
        $font    = $metrik->getFont('Poppins') ?? $metrik->getFont('DejaVu Sans');
        $ukuran  = 7;
        $warna   = [0x57 / 255, 0x53 / 255, 0x4E / 255];
        $mm      = 72 / 25.4;
        $kiri    = 14 * $mm;
        $kanan   = $kanvas->get_width() - 14 * $mm;
        $y       = $kanvas->get_height() - 12 * $mm;

        $kanvas->page_line($kiri, $y - 3, $kanan, $y - 3, [0xD6 / 255, 0xD3 / 255, 0xD1 / 255], 0.75);
        $kanvas->page_text($kiri, $y, $pesanan->nomor_invoice . ' · ' . config('usaha.nama'), $font, $ukuran, $warna);

        $teks  = 'Halaman {PAGE_NUM} dari {PAGE_COUNT}';
        $lebar = $metrik->getTextWidth('Halaman 99 dari 99', $font, $ukuran);
        $kanvas->page_text($kanan - $lebar, $y, $teks, $font, $ukuran, $warna);
    }

    // ── Format ─────────────────────────────────────────

    public static function rupiah(mixed $n): string
    {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }

    /** 21 Nov 2026 */
    public static function tanggal(?Carbon $tanggal): ?string
    {
        return $tanggal?->locale('id')->translatedFormat('j M Y');
    }

    /** 33.00 → "33", 12.50 → "12,5" */
    private static function angkaRingkas(mixed $n): string
    {
        $teks = rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        return str_replace('.', ',', $teks);
    }
}

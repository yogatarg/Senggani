<?php

namespace App\Services;

use App\Enums\StatusProduksi;
use App\Models\Customer;
use App\Models\DetailPesanan;
use App\Models\Pesanan;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DokumenPdf;
use GdImage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Invoice PDF SPP (DomPDF, A4), mengikuti layout final admin (Downloads/layout terbaru.jpeg, 23 Nov 2026).
 * Total tagihan = subtotal produk + ongkir (diskon tidak dipakai). Status bayar hanya dua: BELUM LUNAS / LUNAS.
 * Gambar (logo bank, ikon, ttd, stempel, QRIS) disisipkan sebagai data URI, jadi chroot/remote tidak dibuka.
 *
 * Blok bawah (pembayaran + rincian + ttd) selalu menempel di bawah halaman terakhir. Caranya dua kali
 * render: render pertama mengukur posisi blok bawah, render kedua menyisipkan "sela" (garis tipis +
 * watermark stempel) setinggi sisa ruang di antara tabel dan blok bawah. Bila tabel terlalu panjang,
 * blok bawah (tidak terbelah) pindah ke halaman berikutnya dan sela mengisi halaman itu.
 */
class InvoicePdf
{
    public const VIEW = 'admin.pesanan.invoice-pdf';

    public const MAKS_CATATAN = 200;

    // Folder aset tetap invoice (logo bank, logo QRIS, ikon kop)
    public const FOLDER_ASET = 'invoice/aset';

    // Tepi bawah blok bawah (mm dari atas kertas A4), sama dengan acuan (±250 mm)
    public const BATAS_BAWAH_BLOK = 252;

    private const MM = 72 / 25.4;

    public const OPASITAS_WATERMARK = 0.06;

    public function __construct(private PengaturanInvoice $pengaturan)
    {
    }

    /** Bangun PDF (sudah dirender, termasuk footer nomor halaman). */
    public function buat(Pesanan $pesanan): DokumenPdf
    {
        $pesanan->loadMissing('detailPesanan');

        File::ensureDirectoryExists(storage_path('fonts'));

        $data = $this->dataView($pesanan);

        // Render 1: ukur sisa ruang di atas blok bawah; render 2: isi ruang itu dengan sela
        $ukur = [];
        $pdf  = $this->render($data, $pesanan, null, $ukur);
        $sela = $this->hitungSela($ukur);
        if ($sela !== null) {
            $pdf = $this->render($data, $pesanan, $sela);
        }

        $this->tulisFooter($pdf, $pesanan);

        return $pdf;
    }

    /** @param  array{bawah?: float}  $ukur  diisi tepi bawah blok bawah (pt dari atas halamannya) saat render */
    private function render(array $data, Pesanan $pesanan, ?float $sela, array &$ukur = []): DokumenPdf
    {
        $pdf = Pdf::setOption([
            'isFontSubsettingEnabled' => true,
            'isRemoteEnabled'         => false,
            'defaultFont'             => 'DejaVu Sans',
            'fontDir'                 => storage_path('fonts'),
            'fontCache'               => storage_path('fonts'),
            'chroot'                  => base_path(),
            // Tinggi font Poppins menurut DomPDF = (ascender − descender + offset) × rasio = 1,5 em × rasio.
            // Rasio 2/3 membuat tinggi baris = font-size × line-height, sama seperti browser.
            'fontHeightRatio'         => 2 / 3,
        ])
            ->setPaper('a4', 'portrait')
            ->loadView(self::VIEW, [...$data, 'sela' => $sela === null ? null : $this->dataSela($sela)]);

        // Metadata PDF memakai tanda hubung biasa: DomPDF tidak menyandikan "·" dengan benar di info dokumen
        $judul = 'Invoice ' . $pesanan->nomor_invoice . ' - ' . config('usaha.nama');
        $pdf->addInfo(['Title' => $judul, 'Author' => (string) config('usaha.nama'), 'Creator' => (string) config('usaha.nama')]);

        // Posisi frame hanya bisa dibaca selama render (pohon frame dibuang sesudahnya)
        $pdf->getDomPDF()->setCallbacks([[
            'event' => 'end_frame',
            'f'     => function ($frame) use (&$ukur) {
                $node = $frame->get_node();
                if ($node instanceof \DOMElement && $node->getAttribute('id') === 'blok-bawah') {
                    $ukur['bawah'] = (float) $frame->get_position('y') + (float) $frame->get_margin_height();
                }
            },
        ]]);

        $pdf->render();

        return $pdf;
    }

    /**
     * Tinggi sela (mm) agar tepi bawah blok bawah jatuh di BATAS_BAWAH_BLOK. Null bila blok tidak
     * terukur atau ruang yang tersisa terlalu kecil (< 1 mm).
     */
    private function hitungSela(array $ukur): ?float
    {
        if (! isset($ukur['bawah'])) {
            return null;
        }

        $sisa = self::BATAS_BAWAH_BLOK - $ukur['bawah'] / self::MM - 0.5;

        return $sisa >= 1 ? round($sisa, 2) : null;
    }

    /**
     * Isi sela: garis tipis tiap 18 mm (berhenti ±16 mm di atas blok bawah) dan watermark stempel
     * (lebar maks 160 mm) di antara garis pertama dan kedua. Watermark hanya bila stempel ada dan ruang cukup.
     *
     * @return array{tinggi: float, garis: list<float>, watermark: ?array{uri: string, lebar: float, tinggi: float, atas: float, kiri: float}}
     */
    private function dataSela(float $tinggi): array
    {
        $garis = [];
        for ($y = 18; $y <= $tinggi - 16; $y += 18) {
            $garis[] = (float) $y;
        }

        $watermark = null;
        $stempel   = $this->watermark();
        $ruang     = $garis ? end($garis) + 12 : $tinggi - 8;
        if ($stempel && $ruang >= 20) {
            $rasio  = $stempel['tinggi'] / max($stempel['lebar'], 1);
            $lebar  = min(160, ($ruang - 4) / $rasio);
            $tinggiW = $lebar * $rasio;
            // Seperti acuan: watermark di tengah antara garis pertama dan kedua
            $tengah = match (true) {
                count($garis) >= 2 => ($garis[0] + $garis[1]) / 2,
                count($garis) === 1 => $garis[0],
                default => $ruang / 2,
            };
            $watermark = [
                'uri'    => $stempel['uri'],
                'lebar'  => round($lebar, 2),
                'tinggi' => round($tinggiW, 2),
                'atas'   => round(max($tengah - $tinggiW / 2, 2), 2),
                'kiri'   => round((182 - $lebar) / 2, 2),
            ];
        }

        return ['tinggi' => $tinggi, 'garis' => $garis, 'watermark' => $watermark];
    }

    /**
     * Stempel versi pudar (±6%) untuk watermark, dibuat dengan GD (alfa tiap piksel dikali 6%), supaya
     * tidak bergantung pada dukungan CSS opacity DomPDF. Latar tetap transparan, garis sela tetap terlihat.
     *
     * @return array{uri: string, lebar: int, tinggi: int}|null
     */
    private function watermark(): ?array
    {
        $path = $this->pengaturan->pathGambar('stempel');
        if (! $path || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $gambar = @imagecreatefromstring((string) Storage::disk(PengaturanInvoice::DISK)->get($path));
        if (! $gambar instanceof GdImage) {
            return null;
        }
        if (! imageistruecolor($gambar)) {
            imagepalettetotruecolor($gambar);
        }

        $lebar  = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $hasil  = imagecreatetruecolor($lebar, $tinggi);
        imagealphablending($hasil, false);
        imagesavealpha($hasil, true);
        imagefill($hasil, 0, 0, imagecolorallocatealpha($hasil, 255, 255, 255, 127));

        for ($x = 0; $x < $lebar; $x++) {
            for ($y = 0; $y < $tinggi; $y++) {
                $c = imagecolorsforindex($gambar, imagecolorat($gambar, $x, $y));
                $tampak = (127 - $c['alpha']) * self::OPASITAS_WATERMARK;
                if ($tampak <= 0) {
                    continue;
                }
                $warna = imagecolorallocatealpha($hasil, $c['red'], $c['green'], $c['blue'], (int) round(127 - $tampak));
                imagesetpixel($hasil, $x, $y, $warna);
            }
        }

        ob_start();
        imagepng($hasil, null, 9);
        $biner = (string) ob_get_clean();

        return PengaturanInvoice::dataUriDariIsi($biner);
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
        $set     = $this->pengaturan->semua();
        $status  = $pesanan->status_bayar_invoice;
        $stempel = $this->pengaturan->dataUri('stempel');

        return [
            'pesanan'     => $pesanan,
            'nomor'       => $pesanan->nomor_invoice,
            'usaha'       => $this->usaha(),
            'ikon'        => [
                'wa'  => self::aset('ikon-wa'),
                'web' => self::aset('ikon-web'),
                'ig'  => self::aset('ikon-ig'),
            ],
            // Logo kop kanan = gambar stempel dari Pengaturan Invoice (disembunyikan bila kosong)
            'logoKop'     => $this->ukurGambar($stempel, maksLebar: 29, maksTinggi: 9),
            'kepada'      => $this->kepada($pesanan),
            'tanggal'     => self::tanggalPanjang($pesanan->tanggal_order),
            'statusBayar' => $status,
            'labelStatus' => Pesanan::STATUS_BAYAR_INVOICE[$status],
            'item'        => $this->item($pesanan),
            'ringkasan'   => $this->ringkasan($pesanan, $set),
            'rekening'    => array_map(fn ($r) => [
                'bank'      => trim((string) $r['bank']),
                'logo'      => self::aset(Str::slug((string) $r['bank'])),
                'nomor'     => self::nomorRekening((string) $r['nomor']),
                'atas_nama' => trim((string) ($r['atas_nama'] ?? '')),
            ], $set['rekening']),
            'qris'           => $this->pengaturan->dataUri('qris'),
            'logoQris'       => self::aset('qris'),
            'qrisKeterangan' => $set['qris_keterangan'],
            'keterangan'     => $set['keterangan_bawah'],
            'penandatangan'  => [
                'nama'    => $set['penandatangan_nama'],
                'jabatan' => $set['penandatangan_jabatan'],
                'ttd'     => $this->ukurGambar($this->pengaturan->dataUri('ttd'), maksLebar: 30, maksTinggi: 22),
                'stempel' => $this->ukurGambar($stempel, maksLebar: 31, maksTinggi: 9),
            ],
            'fontRegular' => str_replace(DIRECTORY_SEPARATOR, '/', resource_path('fonts/Poppins-Regular.ttf')),
            'fontBold'    => str_replace(DIRECTORY_SEPARATOR, '/', resource_path('fonts/Poppins-SemiBold.ttf')),
        ];
    }

    private function usaha(): array
    {
        $a = (array) config('usaha.alamat');
        $alamat = implode(', ', array_filter([$a['jalan'] ?? null, $a['kelurahan'] ?? null, $a['kecamatan'] ?? null, $a['kota'] ?? null]));

        return [
            'nama'      => (string) config('usaha.nama'),
            'alamat'    => $alamat,
            'wa'        => self::waLokal((string) config('usaha.whatsapp')),
            'situs'     => (string) config('usaha.situs'),
            'instagram' => (string) config('usaha.instagram'),
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

    /** 300801017388537 → "3008 0101 7388 537" (kelompok 4 digit dari kiri). */
    public static function nomorRekening(string $nomor): string
    {
        $angka = preg_replace('/\s+/', '', trim($nomor)) ?? '';

        return $angka === '' ? '' : implode(' ', str_split($angka, 4));
    }

    /** Aset tetap invoice (resources/invoice/aset/<nama>.png) sebagai data URI; null bila belum ada. */
    public static function aset(string $nama): ?array
    {
        $path = resource_path(self::FOLDER_ASET . '/' . $nama . '.png');

        return $nama !== '' && is_file($path) ? PengaturanInvoice::dataUriDariIsi((string) file_get_contents($path)) : null;
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
            'wa'   => $wa !== '' ? self::waLokal($wa) : null,
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
                $judul .= ' · ' . implode('×', array_map(fn ($n) => self::angkaRingkas($n), $ukuran)) . 'cm';
            }

            $catatan = trim((string) preg_replace('/\s*\R\s*/u', ' ', (string) $d->catatan));

            $hasil[] = [
                'no'       => $i + 1,
                'judul'    => $judul,
                'spek'     => implode(' + ', Pesanan::spekItem($d)),
                'catatan'  => $catatan === '' ? null : Str::limit($catatan, self::MAKS_CATATAN, '…'),
                'jumlah'   => number_format((int) $d->jumlah, 0, ',', '.') . ' pcs',
                'harga'    => $d->harga_satuan === null ? '–' : 'Rp ' . self::angka($d->harga_satuan),
                'subtotal' => $d->subtotal === null ? '–' : 'Rp ' . self::angka($d->subtotal),
            ];
        }

        return $hasil;
    }

    /**
     * Rincian angka kanan blok bawah. Tiap baris: label, nilai, tebal (baris utama), garis (garis di bawahnya).
     * BELUM LUNAS: Subtotal produk, Biaya ongkir, Total tagihan, DP min X% (DP belum dibayar) atau
     * DP dibayar (metode, tanggal), Sisa pelunasan. LUNAS: ... DP dibayar, Pelunasan, Sisa pelunasan Rp0.
     *
     * @return array{baris: list<array{label: string, info?: ?string, nilai: string, tebal: bool, garis: bool}>, kelebihan: ?string}
     */
    private function ringkasan(Pesanan $pesanan, array $set): array
    {
        $lunas  = $pesanan->status_bayar_invoice === 'lunas';
        $total  = $pesanan->total_tagihan;
        $dp     = (float) $pesanan->dp;
        $persen = (int) $set['persen_dp'];

        $baris = [
            ['label' => 'Subtotal produk', 'nilai' => self::rupiah($pesanan->subtotal_produk), 'tebal' => false, 'garis' => false],
            ['label' => 'Biaya ongkir', 'nilai' => (float) $pesanan->ongkir > 0 ? self::rupiah($pesanan->ongkir) : '-', 'tebal' => false, 'garis' => true],
            ['label' => 'Total tagihan', 'nilai' => self::rupiah($total), 'tebal' => true, 'garis' => false],
        ];

        $sisa = max($total - $dp, 0.0);

        if ($dp > 0) {
            $baris[] = ['label' => 'DP dibayar', 'info' => self::infoBayar($pesanan->metode_dp?->label(), $pesanan->tanggal_dp),
                'nilai' => self::rupiah($dp), 'tebal' => false, 'garis' => false];
        } elseif (! $lunas && $persen > 0 && $total > 0) {
            // Belum bayar: tampilkan DP minimal; sisa pelunasan = total − DP minimal (seperti acuan)
            $dpMin   = (float) ceil($total * $persen / 100);
            $baris[] = ['label' => 'DP min ' . $persen . '%', 'nilai' => self::rupiah($dpMin), 'tebal' => false, 'garis' => false];
            $sisa    = max($total - $dpMin, 0.0);
        }

        if ($lunas) {
            if ($pesanan->tanggal_lunas && $pesanan->nilai_pelunasan > 0) {
                $baris[] = ['label' => 'Pelunasan', 'info' => self::infoBayar($pesanan->metode_lunas?->label(), $pesanan->tanggal_lunas),
                    'nilai' => self::rupiah($pesanan->nilai_pelunasan), 'tebal' => false, 'garis' => false];
            }
            $sisa = 0.0;
        }

        $baris[count($baris) - 1]['garis'] = true;
        $baris[] = ['label' => 'Sisa pelunasan', 'nilai' => self::rupiah($sisa), 'tebal' => true, 'garis' => false];

        return [
            'baris'     => $baris,
            'kelebihan' => $pesanan->kelebihan_bayar > 0 ? 'Kelebihan bayar ' . self::rupiah($pesanan->kelebihan_bayar) : null,
        ];
    }

    /** "(Transfer BCA, 21 Nov)" di belakang label DP/Pelunasan; null bila metode & tanggal kosong. */
    private static function infoBayar(?string $metode, ?Carbon $tanggal): ?string
    {
        $info = array_filter([$metode, $tanggal?->locale('id')->translatedFormat('j M')]);

        return $info ? '(' . implode(', ', $info) . ')' : null;
    }

    /**
     * Ukuran tampil (mm) gambar: muat di kotak maksLebar × maksTinggi tanpa mengubah rasio.
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

    /** Footer tiap halaman: kiri "INV-176 · Senggani…", kanan "Halaman X dari Y" (rata kanan). */
    private function tulisFooter(DokumenPdf $pdf, Pesanan $pesanan): void
    {
        $dompdf = $pdf->getDomPDF();
        $kanvas = $dompdf->getCanvas();
        $metrik = $dompdf->getFontMetrics();
        $font   = $metrik->getFont('Poppins') ?? $metrik->getFont('DejaVu Sans');
        $ukuran = 8;
        $warna  = [0x57 / 255, 0x53 / 255, 0x4E / 255];
        $kiri   = 15 * self::MM;
        $kanan  = $kanvas->get_width() - 15 * self::MM;
        $garisY = 274 * self::MM;
        $teksY  = 276.5 * self::MM;
        $kiriTeks = $pesanan->nomor_invoice . ' · ' . config('usaha.nama');

        $kanvas->page_script(function (int $halaman, int $jumlah, $kanvas) use ($metrik, $font, $ukuran, $warna, $kiri, $kanan, $garisY, $teksY, $kiriTeks) {
            $kanvas->line($kiri, $garisY, $kanan, $garisY, [0xD6 / 255, 0xD6 / 255, 0xD6 / 255], 0.75);
            $kanvas->text($kiri, $teksY, $kiriTeks, $font, $ukuran, $warna);
            $teks = 'Halaman ' . $halaman . ' dari ' . $jumlah;
            $kanvas->text($kanan - $metrik->getTextWidth($teks, $font, $ukuran), $teksY, $teks, $font, $ukuran, $warna);
        });
    }

    // ── Format ─────────────────────────────────────────

    /** "Rp18.000.000" (tanpa spasi, seperti layout acuan) */
    public static function rupiah(mixed $n): string
    {
        return 'Rp' . self::angka($n);
    }

    public static function angka(mixed $n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }

    /** 21 Nov 2026 */
    public static function tanggal(?Carbon $tanggal): ?string
    {
        return $tanggal?->locale('id')->translatedFormat('j M Y');
    }

    /** 20 NOVEMBER 2026 */
    public static function tanggalPanjang(?Carbon $tanggal): ?string
    {
        return $tanggal ? Str::upper($tanggal->locale('id')->translatedFormat('j F Y')) : null;
    }

    /** 33.00 → "33", 12.50 → "12,5" */
    private static function angkaRingkas(mixed $n): string
    {
        $teks = rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        return str_replace('.', ',', $teks);
    }
}

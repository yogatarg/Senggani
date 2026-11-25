<?php

namespace App\Services;

use App\Enums\StatusProduksi;
use App\Models\DetailPesanan;
use App\Models\Pesanan;
use App\Models\PesananRiwayat;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DokumenPdf;
use GdImage;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Invoice PDF SPP (DomPDF, A4), mengikuti layout final admin (Downloads/layout terbaru.jpeg, 23 Nov 2026).
 * Total tagihan = subtotal produk + ongkir (diskon tidak dipakai). Status bayar hanya dua: BELUM LUNAS / LUNAS.
 * Gambar (logo bank, ikon, ttd, stempel, QRIS) disisipkan sebagai data URI, jadi chroot/remote tidak dibuka.
 * Gambar unggahan diperkecil dulu ke ±300 dpi ukuran cetaknya: PNG beralfa ukuran penuh membuat DomPDF
 * lambat (tanda tangan 1794×1704 px saja ±1,8 detik per render).
 *
 * Blok bawah (pembayaran + rincian + ttd) selalu berakhir di BATAS_BAWAH_BLOK pada halaman terakhir:
 * 1. Pass ukur: render ringan tanpa gambar (semua <img> berukuran tetap lewat CSS, jadi tata letaknya
 *    sama persis) untuk membaca posisi blok bawah dan halaman akhir tabel (±0,1 detik).
 * 2. Render penuh: "sela" (garis tipis) disisipkan setinggi sisa ruang. Bila blok bawah tidak bisa
 *    berakhir di batas pada halaman tabel, sela dimulai di halaman baru (page-break).
 *
 * Watermark stempel pudar tampil di tengah area isi SETIAP halaman (elemen position: fixed di awal <body>,
 * di belakang isi), lepas dari sela; tanpa stempel tanpa watermark. Lihat dataWatermark().
 *
 * Hasil PDF disimpan di disk privat (FOLDER_CACHE/<id pesanan>/<hash>.pdf), lihat isi() dan kunciCache().
 */
class InvoicePdf
{
    public const VIEW = 'admin.pesanan.invoice-pdf';

    public const MAKS_CATATAN = 200;

    // Folder aset tetap invoice (logo bank, logo QRIS, ikon kop)
    public const FOLDER_ASET = 'invoice/aset';

    // Cache PDF hasil render (disk privat PengaturanInvoice::DISK)
    public const FOLDER_CACHE = 'invoice/cache';

    // Tepi bawah blok bawah (mm dari atas kertas A4), sama dengan acuan (±250 mm)
    public const BATAS_BAWAH_BLOK = 252;

    // Tepi atas area isi (margin atas @page di view)
    public const MARGIN_ATAS = 13;

    // Ukuran area isi halaman (mm): A4 dikurangi margin @page 13/14/26/14 mm
    public const TINGGI_ISI = 258;

    public const LEBAR_ISI = 182;

    // Sela lebih pendek dari ini: cukup satu garis di tengah (bukan garis berjarak rata)
    public const SELA_MIN_BERGARIS = 40;

    // Lebar maksimal watermark stempel (mm)
    public const LEBAR_WATERMARK = 160;

    // Jarak garis sela (mm) kira-kira setinggi satu baris tabel item
    public const JARAK_GARIS = 18;

    // Resolusi gambar unggahan di PDF
    public const DPI = 300;

    private const MM = 72 / 25.4;

    public const OPASITAS_WATERMARK = 0.06;

    // PNG 1×1 transparan: pengganti gambar saat pass ukur
    private const GAMBAR_KOSONG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** Hasil ukur render penuh terakhir (mm dari tepi kertas): bawah blok bawah, halamannya, kanan tabel, sela. Untuk uji. */
    public array $ukuranTerakhir = [];

    public function __construct(private PengaturanInvoice $pengaturan)
    {
    }

    /**
     * Isi PDF siap kirim. Pesanan tersimpan memakai cache di disk privat; cache lain milik pesanan yang
     * sama dihapus saat menyimpan yang baru. Data contoh (belum tersimpan) selalu dirender.
     */
    public function isi(Pesanan $pesanan): string
    {
        $pesanan->loadMissing('detailPesanan');

        $kunci = $this->kunciCache($pesanan);
        $disk  = Storage::disk(PengaturanInvoice::DISK);
        $path  = $kunci ? self::folderCache($pesanan) . '/' . $kunci . '.pdf' : null;

        if ($path && $disk->exists($path)) {
            $isi = (string) $disk->get($path);
            if (str_starts_with($isi, '%PDF')) {
                return $isi;
            }
        }

        $isi = $this->buat($pesanan)->output();

        if ($path) {
            foreach ($disk->files(self::folderCache($pesanan)) as $lama) {
                if ($lama !== $path) {
                    $disk->delete($lama);
                }
            }
            $disk->put($path, $isi);
        }

        return $isi;
    }

    /** Respons PDF (attachment = unduh, inline = tampil di browser); tidak disimpan cache browser/proxy. */
    public static function respons(string $isi, string $namaFile, string $jenis = 'attachment'): Response
    {
        return new Response($isi, 200, [
            'Content-Type'           => 'application/pdf',
            'Content-Disposition'    => HeaderUtils::makeDisposition($jenis, $namaFile, Str::ascii($namaFile)),
            'Content-Length'         => (string) strlen($isi),
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function folderCache(Pesanan $pesanan): string
    {
        return self::FOLDER_CACHE . '/' . $pesanan->id;
    }

    /**
     * Kunci cache: sha256 dari semua sumber isi PDF, yaitu kolom pesanan (termasuk updated_at), kolom
     * tiap item, riwayat (jumlah, id & created_at terakhir), pengaturan invoice + path/ukuran/waktu file
     * gambar, config usaha, aset tetap, serta isi kelas ini dan view-nya (perubahan kode = cache baru).
     * Nilai kolom ikut di-hash (bukan hanya updated_at) supaya dua perubahan dalam detik yang sama tetap
     * terdeteksi. Null untuk pesanan yang belum tersimpan.
     */
    public function kunciCache(Pesanan $pesanan): ?string
    {
        if (! $pesanan->exists || ! $pesanan->id) {
            return null;
        }

        $pesanan->loadMissing('detailPesanan');

        $set  = $this->pengaturan->semua();
        $disk = Storage::disk(PengaturanInvoice::DISK);
        $file = [];
        foreach (array_keys(PengaturanInvoice::JENIS_GAMBAR) as $jenis) {
            $path = $set['file_' . $jenis] ?? null;
            $file[$jenis] = $path && $disk->exists($path) ? [$path, $disk->size($path), $disk->lastModified($path)] : null;
        }

        $riwayat = PesananRiwayat::query()->where('pesanan_id', $pesanan->id)
            ->selectRaw('count(*) as jumlah, max(id) as akhir, max(created_at) as dibuat')->first(); // riwayat tidak pernah diubah

        return hash('sha256', serialize([
            'kode'    => [md5_file(__FILE__), md5_file(view()->getFinder()->find(self::VIEW))],
            'pesanan' => $pesanan->getAttributes(),
            'item'    => $pesanan->detailPesanan->map(fn (DetailPesanan $d) => $d->getAttributes())->all(),
            'riwayat' => $riwayat?->only(['jumlah', 'akhir', 'dibuat']),
            'setelan' => [$set, $file],
            'usaha'   => config('usaha'),
            'aset'    => array_map(fn ($f) => [basename($f), filesize($f), filemtime($f)], glob(resource_path(self::FOLDER_ASET . '/*.png')) ?: []),
            'bahasa'  => app()->getLocale(),
        ]));
    }

    /** Bangun PDF (sudah dirender, termasuk footer nomor halaman). Tanpa cache; lihat isi(). */
    public function buat(Pesanan $pesanan): DokumenPdf
    {
        $pesanan->loadMissing('detailPesanan');

        File::ensureDirectoryExists(storage_path('fonts'));

        $data = $this->dataView($pesanan);

        // Pass ukur (tanpa gambar, ringan), lalu satu render penuh dengan sela yang pas
        $sela = $this->hitungSela($this->ukur($data, $pesanan));

        $akhir = [];
        $pdf   = $this->render($data, $pesanan, $sela, $akhir);
        $this->ukuranTerakhir = [
            'bawah'   => isset($akhir['bawah']) ? round($akhir['bawah'] / self::MM, 2) : null,
            'halaman' => $akhir['halaman_blok'] ?? null,
            'kanan_tabel' => isset($akhir['kanan_tabel']) ? round($akhir['kanan_tabel'] / self::MM, 2) : null,
            'sela'    => $sela,
        ];

        $this->tulisFooter($pdf, $pesanan);

        return $pdf;
    }

    /**
     * Pass ukur: semua gambar diganti PNG 1×1 (ukuran tampil tetap dari CSS/atribut style), tanpa sela.
     * Watermark (position: fixed, di luar alur) ikut diganti, jadi tata letaknya tetap sama.
     */
    private function ukur(array $data, Pesanan $pesanan): array
    {
        $kosong = fn ($g) => is_array($g) && isset($g['uri']) ? ['uri' => self::GAMBAR_KOSONG] + $g : $g;

        $data['logoKop']  = $kosong($data['logoKop']);
        $data['watermark'] = $kosong($data['watermark']);
        $data['qris']     = $kosong($data['qris']);
        $data['logoQris'] = $kosong($data['logoQris']);
        $data['ikon']     = array_map($kosong, $data['ikon']);
        $data['penandatangan']['ttd']     = $kosong($data['penandatangan']['ttd']);
        $data['penandatangan']['stempel'] = $kosong($data['penandatangan']['stempel']);
        $data['rekening'] = array_map(fn ($r) => ['logo' => $kosong($r['logo'])] + $r, $data['rekening']);

        $ukur = [];
        $this->render($data, $pesanan, null, $ukur);

        return $ukur;
    }

    /**
     * @param  array  $ukur  diisi saat render: atas & bawah blok bawah (pt dari atas kertas), halaman_blok,
     *                       halaman_tabel (halaman baris item terakhir, id "baris-akhir")
     */
    private function render(array $data, Pesanan $pesanan, ?array $sela, array &$ukur = []): DokumenPdf
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
        $dompdf = $pdf->getDomPDF();
        $dompdf->setCallbacks([[
            'event' => 'end_frame',
            'f'     => function ($frame) use (&$ukur, $dompdf) {
                $node = $frame->get_node();
                if (! $node instanceof \DOMElement) {
                    return;
                }
                $id = $node->getAttribute('id');
                if ($id === 'blok-bawah') {
                    $ukur['atas']         = (float) $frame->get_position('y');
                    $ukur['bawah']        = (float) $frame->get_position('y') + (float) $frame->get_margin_height();
                    $ukur['halaman_blok'] = $dompdf->getCanvas()->get_page_number();
                } elseif ($id === 'baris-akhir') {
                    // Baris item terakhir (tidak terbelah) = halaman akhir tabel; tepi kanannya untuk uji lebar
                    $ukur['halaman_tabel'] = $dompdf->getCanvas()->get_page_number();
                    $ukur['kanan_tabel']   = (float) $frame->get_position('x') + (float) $frame->get_margin_width();
                }
            },
        ]]);

        $pdf->render();

        return $pdf;
    }

    /**
     * Sela agar tepi bawah blok bawah jatuh di BATAS_BAWAH_BLOK. Bila di pass ukur blok bawah melewati
     * batas atau sudah terdorong ke halaman setelah tabel, sela dimulai di halaman baru dengan tinggi
     * batas − margin atas − tinggi blok. Null bila tidak terukur atau ruangnya < 1 mm.
     *
     * @return array{tinggi: float, halaman_baru: bool}|null
     */
    private function hitungSela(array $ukur): ?array
    {
        if (! isset($ukur['atas'], $ukur['bawah'])) {
            return null;
        }

        $bawah      = $ukur['bawah'] / self::MM;
        $tinggiBlok = ($ukur['bawah'] - $ukur['atas']) / self::MM;
        $pindah     = ($ukur['halaman_blok'] ?? 1) > ($ukur['halaman_tabel'] ?? 1) || $bawah > self::BATAS_BAWAH_BLOK + 0.5;

        $sisa = $pindah
            ? self::BATAS_BAWAH_BLOK - self::MARGIN_ATAS - $tinggiBlok - 0.5
            : self::BATAS_BAWAH_BLOK - $bawah - 0.5;

        if ($sisa < 1) {
            return null;
        }

        return ['tinggi' => round($sisa, 2), 'halaman_baru' => $pindah];
    }

    /**
     * Isi sela. Sela ≥ SELA_MIN_BERGARIS: garis tipis berjarak rata (±JARAK_GARIS) dari bawah tabel sampai
     * 6 mm di atas blok bawah (garis terakhir). Sela 10 mm s.d. < SELA_MIN_BERGARIS: satu garis di tengah.
     * Di bawah 10 mm: kosong.
     *
     * @param  array{tinggi: float, halaman_baru: bool}  $sela
     * @return array{tinggi: float, halaman_baru: bool, garis: list<float>}
     */
    private function dataSela(array $sela): array
    {
        $tinggi = $sela['tinggi'];
        $garis  = [];

        if ($tinggi >= self::SELA_MIN_BERGARIS) {
            $area   = $tinggi - 6;
            $jumlah = max(2, (int) round($area / self::JARAK_GARIS));
            for ($i = 1; $i <= $jumlah; $i++) {
                $garis[] = round($area * $i / $jumlah, 2);
            }
        } elseif ($tinggi >= 10) {
            $garis[] = round($tinggi / 2, 2);
        }

        return ['tinggi' => $tinggi, 'halaman_baru' => $sela['halaman_baru'], 'garis' => $garis];
    }

    /**
     * Watermark tiap halaman: stempel pudar selebar maks LEBAR_WATERMARK (proporsional, tetap muat tinggi
     * area isi), di tengah area isi halaman. Posisi (mm) relatif terhadap pojok kiri atas area isi, karena
     * DomPDF menempatkan elemen position: fixed di dalam margin @page. Null bila stempel belum diunggah.
     *
     * @return array{uri: string, lebar: float, tinggi: float, atas: float, kiri: float}|null
     */
    private function dataWatermark(): ?array
    {
        $stempel = $this->watermark();
        if (! $stempel) {
            return null;
        }

        $rasio  = $stempel['tinggi'] / max($stempel['lebar'], 1);
        $lebar  = min(self::LEBAR_WATERMARK, (self::TINGGI_ISI - 20) / max($rasio, 0.001));
        $tinggi = $lebar * $rasio;

        return [
            'uri'    => $stempel['uri'],
            'lebar'  => round($lebar, 2),
            'tinggi' => round($tinggi, 2),
            'atas'   => round((self::TINGGI_ISI - $tinggi) / 2, 2),
            'kiri'   => round((self::LEBAR_ISI - $lebar) / 2, 2),
        ];
    }

    /**
     * Stempel versi pudar (±6%) untuk watermark, dibuat dengan GD (alfa tiap piksel dikali 6%), supaya
     * tidak bergantung pada dukungan CSS opacity DomPDF. Latar tetap transparan, isi di atasnya tetap terbaca.
     * Lebar dibatasi 945 px (160 mm pada 150 dpi; gambar pudar tidak butuh lebih).
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
        $gambar = self::perkecil($gambar, 945, 945);

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

        return self::png($hasil);
    }

    /**
     * Gambar unggahan untuk PDF: diperkecil agar muat maksLebar × maksTinggi (mm) pada DPI; transparansi
     * dipertahankan. $ratakan = true: latar transparan diratakan ke putih dan disimpan tanpa kanal alfa
     * (QRIS), karena PNG tanpa alfa jauh lebih cepat diproses DomPDF. lebar/tinggi tetap ukuran asli
     * (hanya rasionya yang dipakai view).
     *
     * @return array{uri: string, lebar: int, tinggi: int}|null
     */
    private function gambarPdf(string $jenis, float $maksLebar, float $maksTinggi, bool $ratakan = false): ?array
    {
        $asli = $this->pengaturan->dataUri($jenis);
        $path = $this->pengaturan->pathGambar($jenis);
        if (! $asli || ! $path || ! function_exists('imagecreatefromstring')) {
            return $asli;
        }

        $gambar = @imagecreatefromstring((string) Storage::disk(PengaturanInvoice::DISK)->get($path));
        if (! $gambar instanceof GdImage) {
            return $asli;
        }

        $jpg   = str_starts_with($asli['uri'], 'data:image/jpeg');
        $px    = fn (float $mm) => (int) ceil($mm / 25.4 * self::DPI);
        $kecil = self::perkecil($gambar, $px($maksLebar), $px($maksTinggi));

        if ($kecil === $gambar && ($jpg || ! $ratakan)) {
            return $asli; // sudah cukup kecil dan tidak perlu diratakan
        }

        if ($ratakan && ! $jpg) {
            $putih = imagecreatetruecolor(imagesx($kecil), imagesy($kecil));
            imagefill($putih, 0, 0, imagecolorallocate($putih, 255, 255, 255));
            imagealphablending($putih, true);
            imagecopy($putih, $kecil, 0, 0, 0, 0, imagesx($kecil), imagesy($kecil));
            imagesavealpha($putih, false);
            $kecil = $putih;
        }

        $hasil = $jpg ? self::jpg($kecil) : self::png($kecil);

        return $hasil ? ['uri' => $hasil['uri'], 'lebar' => $asli['lebar'], 'tinggi' => $asli['tinggi']] : $asli;
    }

    /** Perkecil (tidak pernah memperbesar) agar muat maksLebar × maksTinggi piksel; alfa dipertahankan. */
    private static function perkecil(GdImage $gambar, int $maksLebar, int $maksTinggi): GdImage
    {
        if (! imageistruecolor($gambar)) {
            imagepalettetotruecolor($gambar);
        }

        $lebar  = imagesx($gambar);
        $tinggi = imagesy($gambar);
        $skala  = min(1, $maksLebar / max($lebar, 1), $maksTinggi / max($tinggi, 1));
        if ($skala >= 1) {
            return $gambar;
        }

        $lb    = max(1, (int) round($lebar * $skala));
        $tb    = max(1, (int) round($tinggi * $skala));
        $kecil = imagecreatetruecolor($lb, $tb);
        imagealphablending($kecil, false);
        imagesavealpha($kecil, true);
        imagefill($kecil, 0, 0, imagecolorallocatealpha($kecil, 0, 0, 0, 127));
        imagecopyresampled($kecil, $gambar, 0, 0, 0, 0, $lb, $tb, $lebar, $tinggi);

        return $kecil;
    }

    /** @return array{uri: string, lebar: int, tinggi: int}|null */
    private static function png(GdImage $gambar): ?array
    {
        ob_start();
        imagepng($gambar, null, 6);

        return PengaturanInvoice::dataUriDariIsi((string) ob_get_clean());
    }

    /** @return array{uri: string, lebar: int, tinggi: int}|null */
    private static function jpg(GdImage $gambar): ?array
    {
        ob_start();
        imagejpeg($gambar, null, 90);

        return PengaturanInvoice::dataUriDariIsi((string) ob_get_clean());
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
        $lunas   = $status === 'lunas';
        // Stempel dipakai di kop (29 × 9 mm) dan di area ttd (31 × 9 mm): satu versi untuk keduanya
        $stempel = $this->gambarPdf('stempel', 31, 9);

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
            // Stempel pudar di tengah tiap halaman (juga saat LUNAS); null bila stempel kosong
            'watermark'   => $this->dataWatermark(),
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
            'qris'           => $this->gambarPdf('qris', 27.8, 27.8, ratakan: true),
            'logoQris'       => self::aset('qris'),
            'qrisKeterangan' => $set['qris_keterangan'],
            // Invoice LUNAS: tanpa keterangan bawah (mis. "DP minimal 50%…") dan tanpa ajakan kirim bukti transfer
            'keterangan'     => $lunas ? '' : $set['keterangan_bawah'],
            'mohonBukti'     => ! $lunas,
            'penandatangan'  => [
                'nama'    => $set['penandatangan_nama'],
                'jabatan' => $set['penandatangan_jabatan'],
                'ttd'     => $this->ukurGambar($this->gambarPdf('ttd', 30, 22), maksLebar: 30, maksTinggi: 22),
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

    /** Nomor rekening persis seperti diketik admin di Pengaturan (hanya dirapikan ujungnya; tidak dikelompokkan). */
    public static function nomorRekening(string $nomor): string
    {
        return trim($nomor);
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
     * BELUM LUNAS tanpa DP: Subtotal produk, Biaya ongkir, Total tagihan, DP dibayar "-", Sisa tagihan (= total),
     * lalu baris sorot "Bayar DP min X%" (ajakan bayar, di luar penjumlahan; tidak ada bila persen/total 0).
     * BELUM LUNAS dengan DP: ... DP dibayar (metode, tanggal), Sisa pelunasan.
     * LUNAS setelah DP: ... DP dibayar, Pelunasan (= total − DP), Sisa pelunasan Rp0.
     * LUNAS langsung (Bayar lunas / DP ≥ total): ... Dibayar lunas (= total), Sisa pelunasan Rp0.
     *
     * @return array{baris: list<array{label: string, info?: ?string, nilai: string, tebal: bool, garis: bool, sorot?: bool}>, kelebihan: ?string}
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

        $sisa      = max($total - $dp, 0.0);
        $belumDp   = ! $lunas && $dp <= 0;
        $labelSisa = 'Sisa pelunasan';

        if ($lunas && $pesanan->lunas_langsung) {
            // Bayar penuh sekaligus (tombol "Bayar lunas" atau DP ≥ total): satu baris, bukan "DP + Pelunasan Rp0"
            $baris[] = ['label' => 'Dibayar lunas', 'info' => self::infoBayar($pesanan->metode_lunas?->label(), $pesanan->tanggal_lunas),
                'nilai' => self::rupiah($total), 'tebal' => false, 'garis' => false];
        } elseif ($dp > 0) {
            $baris[] = ['label' => 'DP dibayar', 'info' => self::infoBayar($pesanan->metode_dp?->label(), $pesanan->tanggal_dp),
                'nilai' => self::rupiah($dp), 'tebal' => false, 'garis' => false];
        } elseif ($belumDp) {
            // Belum ada DP: sisa tagihan = total (DP minimal TIDAK dikurangkan, supaya tidak terkesan sudah dibayar)
            $baris[]   = ['label' => 'DP dibayar', 'nilai' => '-', 'tebal' => false, 'garis' => false];
            $labelSisa = 'Sisa tagihan';
        }

        if ($lunas) {
            if ($pesanan->tanggal_lunas && $pesanan->nilai_pelunasan > 0) {
                $baris[] = ['label' => 'Pelunasan', 'info' => self::infoBayar($pesanan->metode_lunas?->label(), $pesanan->tanggal_lunas),
                    'nilai' => self::rupiah($pesanan->nilai_pelunasan), 'tebal' => false, 'garis' => false];
            }
            $sisa = 0.0;
        }

        $baris[count($baris) - 1]['garis'] = true;
        $baris[] = ['label' => $labelSisa, 'nilai' => self::rupiah($sisa), 'tebal' => true, 'garis' => false];

        // Ajakan bayar DP minimal: baris sorot terpisah, bukan bagian penjumlahan
        if ($belumDp && $persen > 0 && $total > 0) {
            $baris[] = ['label' => 'Bayar DP min ' . $persen . '%', 'nilai' => self::rupiah((float) ceil($total * $persen / 100)),
                'tebal' => true, 'garis' => false, 'sorot' => true];
        }

        return [
            'baris'     => $baris,
            'kelebihan' => $pesanan->kelebihan_bayar > 0 ? 'Kelebihan bayar ' . self::rupiah($pesanan->kelebihan_bayar) : null,
        ];
    }

    /**
     * "Transfer BCA · 21 Nov": baris kecil di bawah label DP/Pelunasan (supaya label tidak turun baris saat
     * nominal panjang); null bila metode & tanggal kosong.
     */
    private static function infoBayar(?string $metode, ?Carbon $tanggal): ?string
    {
        $info = array_filter([$metode, $tanggal?->locale('id')->translatedFormat('j M')]);

        return $info ? implode(' · ', $info) : null;
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

<?php

namespace App\Models;

use App\Enums\MetodeBayar;
use App\Enums\StatusDesain;
use App\Enums\StatusProduksi;
use App\Services\InvoicePdf;
use App\Services\PengaturanInvoice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * Order internal (bukan dari customer langsung). total disimpan & dihitung server
 * dari item (lihat PesananService); sisa & status_pembayaran dihitung di sini.
 *
 * Alur: hitung_harga (harga belum lengkap) → menunggu_dp → proses (nomor SPP diberikan
 * saat DP dicatat) → selesai_produksi → pengiriman (wajib lunas) → selesai.
 * Sebelum punya nomor SPP, pesanan dikenali dengan kode penawaran PNW-{id}.
 */
class Pesanan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pesanan';

    /** Hapus permanen: cache PDF invoice ikut dihapus. Soft delete membiarkan cache (SPP bisa dipulihkan). */
    protected static function booted(): void
    {
        static::forceDeleted(function (Pesanan $pesanan) {
            Storage::disk(PengaturanInvoice::DISK)->deleteDirectory(InvoicePdf::folderCache($pesanan));
        });
    }

    // Tab dashboard (kunci query string ?tab=)
    public const TAB = [
        'semua'            => 'Semua SPP',
        'hitung_harga'     => 'Hitung Harga',
        'menunggu_dp'      => 'Menunggu DP',
        'proses'           => 'Dalam Proses',
        'selesai_produksi' => 'Pesanan Selesai',
        'pengiriman'       => 'Pengiriman',
        'history'          => 'History SPP',
    ];

    // Pilihan urutan (?urut=)
    public const URUT = [
        'deadline'      => 'Deadline terdekat',
        'deadline_jauh' => 'Deadline terjauh',
        'terbaru'       => 'SPP terbaru',
    ];

    // Nilai <select> untuk opsi "Lainnya…" (teks bebas di field <nama>_lainnya)
    public const LAINNYA = '__lainnya__';

    // Ambang "mendekati deadline" (hari sebelum deadline, termasuk hari-H)
    public const HARI_MENDEKAT = 3;

    // Awalan kode penawaran untuk pesanan yang belum punya nomor SPP (PNW-23)
    public const AWALAN_PENAWARAN = 'PNW-';

    // Jenis deadline: barang harus siap (diambil) atau sudah dikirim pada tanggal itu
    public const JENIS_DEADLINE = [
        'siap'  => 'Siap',
        'kirim' => 'Kirim',
    ];

    /**
     * Nomor SPP yang diketik admin dianggap "loncat" bila lebih dari ambang ini
     * di atas/di bawah saran (nomor terbesar + 1). Loncatan butuh konfirmasi.
     */
    public const AMBANG_LONCAT_NOMOR = 10;

    public const STATUS_BAYAR = [
        'belum_dp' => 'Belum DP',
        'dp'       => 'DP Masuk',
        'lunas'    => 'Lunas',
    ];

    // Status bayar di invoice PDF (lihat statusBayarInvoice): hanya dua, otomatis
    public const STATUS_BAYAR_INVOICE = [
        'belum_lunas' => 'BELUM LUNAS',
        'lunas'       => 'LUNAS',
    ];

    // Invoice tersedia untuk status ini (Hitung Harga: harga belum lengkap; Tidak Jadi/Dibatalkan: tidak ada tagihan)
    public const STATUS_INVOICE = ['menunggu_dp', 'proses', 'selesai_produksi', 'pengiriman', 'selesai'];

    // Awalan nomor invoice: INV-176 setelah DP (nomor SPP), INV-PNW-23 sebelumnya
    public const AWALAN_INVOICE = 'INV-';

    protected $fillable = [
        'nomor_spp', 'customer_id', 'nama_customer', 'nama_brand', 'nomor_whatsapp', 'alamat', 'kota',
        'tanggal_order', 'deadline', 'jenis_deadline', 'total', 'ongkir', 'diskon', 'dp', 'tanggal_dp', 'metode_dp',
        'tanggal_lunas', 'metode_lunas',
        'link_desain', 'catatan_desain', 'status_desain', 'catatan_admin', 'catatan_harga',
        'status_produksi', 'selesai_pada', 'dibuat_oleh',
    ];

    protected $attributes = [
        'status_produksi' => 'hitung_harga',
        'status_desain'   => 'belum_dikirim',
        'jenis_deadline'  => 'kirim',
        'total'           => 0,
        'ongkir'          => 0,
        'diskon'          => 0,
        'dp'              => 0,
    ];

    protected function casts(): array
    {
        return [
            'nomor_spp'       => 'integer',
            'tanggal_order'   => 'date',
            'deadline'        => 'date',
            'tanggal_dp'      => 'date',
            'tanggal_lunas'   => 'date',
            'selesai_pada'    => 'datetime',
            'total'           => 'decimal:2',
            'ongkir'          => 'decimal:2',
            'diskon'          => 'decimal:2',
            'dp'              => 'decimal:2',
            'metode_dp'       => MetodeBayar::class,
            'metode_lunas'    => MetodeBayar::class,
            'status_produksi' => StatusProduksi::class,
            'status_desain'   => StatusDesain::class,
        ];
    }

    // ── Relasi ─────────────────────────────────────────

    public function detailPesanan()
    {
        return $this->hasMany(DetailPesanan::class)->orderBy('urutan')->orderBy('id');
    }

    public function file()
    {
        return $this->hasMany(PesananFile::class)->orderBy('id');
    }

    public function riwayat()
    {
        return $this->hasMany(PesananRiwayat::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function pembuat()
    {
        return $this->belongsTo(Admin::class, 'dibuat_oleh');
    }

    // ── Pembayaran (dihitung) ──────────────────────────
    //
    // Kolom `total` = subtotal produk (jumlah subtotal item, dihitung PesananService).
    // Total tagihan = subtotal produk + ongkir; semua hitungan sisa, status bayar,
    // batas DP, dan syarat lunas memakai total tagihan. Diskon tidak dipakai lagi (kolom `diskon`
    // dibiarkan di DB, nilainya diabaikan).

    /** Subtotal produk (alias kolom total). */
    protected function subtotalProduk(): Attribute
    {
        return Attribute::get(fn () => round((float) $this->total, 2));
    }

    /** Subtotal produk + ongkir (tidak pernah negatif). */
    protected function totalTagihan(): Attribute
    {
        return Attribute::get(fn () => self::hitungTotalTagihan($this->total, $this->ongkir));
    }

    public static function hitungTotalTagihan(mixed $subtotal, mixed $ongkir): float
    {
        return max(round((float) $subtotal + (float) $ongkir, 2), 0.0);
    }

    /** Sisa tagihan: 0 bila sudah lunas, selain itu max(total tagihan − dp, 0). */
    protected function sisa(): Attribute
    {
        return Attribute::get(fn () => $this->tanggal_lunas
            ? 0.0
            : max(round($this->total_tagihan - (float) $this->dp, 2), 0.0));
    }

    /** DP melebihi total tagihan (mis. harga diturunkan setelah DP). 0 bila tidak. */
    protected function kelebihanBayar(): Attribute
    {
        return Attribute::get(fn () => max(round((float) $this->dp - $this->total_tagihan, 2), 0.0));
    }

    /** Nilai pelunasan = total tagihan − DP (0 bila belum lunas atau DP sudah menutup total). */
    protected function nilaiPelunasan(): Attribute
    {
        return Attribute::get(fn () => $this->tanggal_lunas
            ? max(round($this->total_tagihan - (float) $this->dp, 2), 0.0)
            : 0.0);
    }

    /**
     * Lunas sekaligus tanpa pelunasan terpisah: tombol "Bayar lunas" dari Menunggu DP, atau DP ≥ total tagihan
     * (lunas otomatis). Ditampilkan sebagai satu baris "Dibayar lunas" (bukan "DP … + Pelunasan Rp0").
     */
    protected function lunasLangsung(): Attribute
    {
        return Attribute::get(fn () => (bool) $this->tanggal_lunas
            && $this->total_tagihan > 0
            && (float) $this->dp >= $this->total_tagihan);
    }

    /**
     * Status bayar di invoice (dua saja): lunas hanya bila tanggal_lunas terisi (sama dengan status admin),
     * selain itu belum_lunas. DP yang menutup seluruh tagihan sudah otomatis dicatat lunas saat Catat DP /
     * Edit (PesananService::lunasOtomatis), jadi tidak perlu diturunkan dari DP ≥ total di sini.
     */
    protected function statusBayarInvoice(): Attribute
    {
        return Attribute::get(fn () => $this->tanggal_lunas ? 'lunas' : 'belum_lunas');
    }

    /** "INV-176" bila sudah bernomor SPP, "INV-PNW-23" sebelum DP, "INV-CONTOH" untuk data contoh. */
    protected function nomorInvoice(): Attribute
    {
        return Attribute::get(fn () => self::AWALAN_INVOICE . ($this->nomor_spp
            ? $this->nomor_spp
            : ($this->kode_penawaran ?? 'CONTOH')));
    }

    /** Status SPP mengizinkan invoice (hak akses admin dicek di PesananPolicy::unduhInvoice). */
    protected function bisaInvoice(): Attribute
    {
        return Attribute::get(fn () => in_array($this->status_produksi?->value, self::STATUS_INVOICE, true));
    }

    /** belum_dp | dp | lunas. "Lunas" hanya ditentukan oleh tanggal_lunas. */
    protected function statusPembayaran(): Attribute
    {
        return Attribute::get(function () {
            if ($this->tanggal_lunas) {
                return 'lunas';
            }

            return (float) $this->dp > 0 ? 'dp' : 'belum_dp';
        });
    }

    protected function labelStatusPembayaran(): Attribute
    {
        return Attribute::get(fn () => self::STATUS_BAYAR[$this->status_pembayaran]);
    }

    /** Selesai produksi tetapi belum lunas: belum boleh dikirim (lihat PesananService::ubahStatus). */
    protected function menungguPelunasan(): Attribute
    {
        return Attribute::get(fn () => $this->status_produksi === StatusProduksi::SelesaiProduksi && ! $this->tanggal_lunas);
    }

    // ── Harga ──────────────────────────────────────────

    /** Semua item sudah punya harga satuan (minimal satu item). */
    protected function hargaLengkap(): Attribute
    {
        return Attribute::get(function () {
            $item = $this->relationLoaded('detailPesanan') ? $this->detailPesanan : $this->detailPesanan()->get();

            return $item->isNotEmpty() && $item->every(fn ($d) => $d->harga_satuan !== null);
        });
    }

    /** Jumlah item yang belum diberi harga. */
    protected function jumlahItemTanpaHarga(): Attribute
    {
        return Attribute::get(function () {
            $item = $this->relationLoaded('detailPesanan') ? $this->detailPesanan : $this->detailPesanan()->get();

            return $item->filter(fn ($d) => $d->harga_satuan === null)->count();
        });
    }

    // ── Deadline (dihitung) ────────────────────────────

    /** Selisih hari kalender dari hari ini ke deadline (negatif = sudah lewat). */
    protected function hariMenujuDeadline(): Attribute
    {
        return Attribute::get(fn () => $this->deadline
            ? (int) today()->diffInDays($this->deadline->copy()->startOfDay(), false)
            : null);
    }

    /**
     * "lewat" | "mendekat" | null. Hanya untuk status hitung_harga, menunggu_dp, proses,
     * selesai_produksi. Mendekat = deadline hari ini s.d. HARI_MENDEKAT hari lagi.
     */
    protected function penandaDeadline(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->deadline || ! $this->status_produksi?->pantauDeadline()) {
                return null;
            }

            $hari = $this->hari_menuju_deadline;

            if ($hari < 0) {
                return 'lewat';
            }

            return $hari <= self::HARI_MENDEKAT ? 'mendekat' : null;
        });
    }

    // ── Nomor SPP ──────────────────────────────────────

    /**
     * Saran nomor SPP berikutnya = nomor terbesar dari SPP yang TIDAK dihapus + 1;
     * 1 bila belum ada. Karena keunikan tetap menghitung SPP yang dihapus, nomor yang sudah
     * dipakai (termasuk oleh SPP terhapus) dilewati sampai ketemu nomor kosong. Nomor typo
     * besar yang sudah dihapus tidak menyeret saran (titik awal hanya dari SPP aktif).
     */
    public static function saranNomorSpp(): int
    {
        $nomor = (int) static::query()->max('nomor_spp') + 1;

        $terpakai = static::withTrashed()
            ->where('nomor_spp', '>=', $nomor)
            ->orderBy('nomor_spp')
            ->pluck('nomor_spp');

        foreach ($terpakai as $n) {
            if ((int) $n > $nomor) {
                break;
            }
            if ((int) $n === $nomor) {
                $nomor++;
            }
        }

        return $nomor;
    }

    /**
     * Nomor baru dianggap loncat bila selisihnya dari saran lebih dari AMBANG_LONCAT_NOMOR.
     * Tidak berlaku untuk nomor SPP pertama (admin melanjutkan nomor dari buku lama).
     */
    public static function nomorSppLoncat(int $nomor): bool
    {
        if (! static::query()->whereNotNull('nomor_spp')->exists()) {
            return false;
        }

        return abs($nomor - static::saranNomorSpp()) > self::AMBANG_LONCAT_NOMOR;
    }

    /** Ambil angka dari "176", "#176", "SPP #176"; null bila bukan nomor. */
    public static function angkaNomorSpp(?string $kata): ?int
    {
        if (preg_match('/^\s*(?:spp\s*)?#?\s*(\d{1,9})\s*$/i', (string) $kata, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** Ambil id dari kode penawaran "PNW-23", "pnw23", "PNW 23"; null bila bukan kode itu. */
    public static function idKodePenawaran(?string $kata): ?int
    {
        if (preg_match('/^\s*pnw\s*-?\s*(\d{1,18})\s*$/i', (string) $kata, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /** "SPP #176"; null bila belum punya nomor. */
    protected function nomorSppTeks(): Attribute
    {
        return Attribute::get(fn () => $this->nomor_spp ? 'SPP #' . $this->nomor_spp : null);
    }

    /** "PNW-23" (kode penawaran; null sebelum tersimpan). */
    protected function kodePenawaran(): Attribute
    {
        return Attribute::get(fn () => $this->id ? self::AWALAN_PENAWARAN . $this->id : null);
    }

    /** Kode untuk tampilan: "SPP #176" bila sudah bernomor, selain itu "PNW-23". */
    protected function kodeTampil(): Attribute
    {
        return Attribute::get(fn () => $this->nomor_spp_teks ?? $this->kode_penawaran);
    }

    // ── Deadline: label & teks ─────────────────────────

    protected function labelJenisDeadline(): Attribute
    {
        return Attribute::get(fn () => self::JENIS_DEADLINE[$this->jenis_deadline] ?? null);
    }

    /** "30 nov kirim" (tahun ditambahkan bila berbeda dari tahun order). */
    protected function deadlineTeks(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->deadline) {
                return null;
            }

            $tahunAcuan = ($this->tanggal_order ?? now())->year;
            $format     = $this->deadline->year === $tahunAcuan ? 'j M' : 'j M Y';
            $teks       = mb_strtolower($this->deadline->translatedFormat($format));

            return trim($teks . ' ' . mb_strtolower((string) $this->label_jenis_deadline));
        });
    }

    // ── Teks salin SPP (dikirim admin sendiri ke WA) ───

    /**
     * Teks SPP siap salin, format mengikuti contoh pengguna. Banyak item → blok
     * bernomor dalam satu pesan. Baris dengan isian kosong dilewati.
     * null bila SPP belum punya nomor (belum masuk produksi).
     */
    protected function teksSpp(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->nomor_spp) {
                return null;
            }

            $item = $this->relationLoaded('detailPesanan') ? $this->detailPesanan : $this->detailPesanan()->get();

            $blok = [];

            $blok[] = ['📋 SPP | #' . $this->nomor_spp];

            $nama = trim((string) $this->nama_customer);
            if (filled($this->nama_brand)) {
                $nama .= ' / ' . trim($this->nama_brand);
            }
            $kepala = ['Nama Customer: ' . $nama];

            $deadline = $this->deadline_teks ? '📅 Deadline: ' . $this->deadline_teks : null;

            if ($item->count() === 1) {
                // Satu item: harga & deadline satu blok, persis contoh pengguna
                $d = $item->first();
                $blok[] = [...$kepala, ...$this->barisItem($d, false)];
                $blok[] = array_values(array_filter([$this->barisHarga($d), $deadline]));
                $deadline = null;
            } else {
                $blok[] = $kepala;
                foreach ($item->values() as $i => $d) {
                    $baris = $this->barisItem($d, true, $i + 1);
                    if ($harga = $this->barisHarga($d)) {
                        $baris[] = $harga;
                    }
                    $blok[] = $baris;
                }
            }

            if ($deadline) {
                $blok[] = [$deadline];
            }

            // Ongkir & total hanya bila ada ongkir, supaya SPP biasa tetap format lama.
            // DP & Lunas hanya bila ada datanya; metode dalam kurung hanya bila tercatat (rancangan 5.5).
            $biaya = [];
            if ((float) $this->ongkir > 0) {
                $biaya[] = '🚚 Ongkir: ' . self::rupiahSpp($this->ongkir);
                $biaya[] = '💰 Total: ' . self::rupiahSpp($this->total_tagihan);
            }
            $infoLunas = array_filter([$this->metode_lunas?->label(), $this->tanggalSpp($this->tanggal_lunas)]);
            $infoLunas = $infoLunas ? ' (' . implode(', ', $infoLunas) . ')' : '';
            if ($this->lunas_langsung) {
                // Bayar penuh sekaligus: satu baris "✅ Dibayar lunas: Rp600.000 (QRIS, 23 Nov)", tanpa baris DP
                $biaya[] = '✅ Dibayar lunas: ' . self::rupiahSpp($this->total_tagihan) . $infoLunas;
            } else {
                if ((float) $this->dp > 0) {
                    $info = array_filter([$this->metode_dp?->label(), $this->tanggalSpp($this->tanggal_dp)]);
                    $biaya[] = '💳 DP: ' . self::rupiahSpp($this->dp) . ($info ? ' (' . implode(', ', $info) . ')' : '');
                }
                if ($this->tanggal_lunas) {
                    // "✅ Lunas: Rp1.625.000 (QRIS, 23 Nov)" = pelunasan setelah DP
                    $biaya[] = '✅ Lunas: ' . self::rupiahSpp($this->nilai_pelunasan) . $infoLunas;
                }
            }
            $blok[] = $biaya;

            $akhir = [];
            if (filled($this->link_desain)) {
                $akhir[] = 'File Desain: ' . $this->link_desain;
            }
            if ($this->exists) {
                $akhir[] = 'Detail SPP: ' . route('admin.pesanan.show', $this);
            }
            $blok[] = $akhir;

            $blok = array_filter($blok, fn ($b) => $b !== []);

            return implode("\n\n", array_map(fn ($b) => implode("\n", $b), $blok));
        });
    }

    /** Baris Produk/Ukuran/Jumlah/Bahan(/Catatan) satu item (tanpa harga). */
    private function barisItem(DetailPesanan $d, bool $bernomor, int $no = 1): array
    {
        $baris = [($bernomor ? "{$no}. " : '') . 'Produk: ' . $d->jenis_packaging];

        $ukuran = array_filter([$d->panjang, $d->lebar, $d->tinggi], fn ($n) => $n !== null && $n !== '');
        if ($ukuran) {
            $baris[] = 'Ukuran: ' . implode(' x ', array_map(fn ($n) => self::angkaRingkas($n), $ukuran));
        }

        $baris[] = 'Jumlah: ' . number_format((int) $d->jumlah, 0, ',', '.') . ' pcs';

        $bahan = self::spekItem($d);
        if ($bahan) {
            $baris[] = 'Bahan: ' . implode(' + ', $bahan);
        }

        // Baris baru di catatan diganti spasi agar format WA tetap satu baris
        $catatan = trim((string) preg_replace('/\s*\R\s*/u', ' ', (string) $d->catatan));
        if ($catatan !== '') {
            $baris[] = 'Catatan: ' . $catatan;
        }

        return $baris;
    }

    /**
     * Spek item (bahan gramasi, finishing…, cetak warna, aksesori; yang kosong dilewati).
     * Urutan sama dengan baris "Bahan:" di teks SPP; dipakai juga invoice PDF.
     *
     * @return list<string>
     */
    public static function spekItem(DetailPesanan $d): array
    {
        return array_values(array_filter([
            trim($d->jenis_bahan . ' ' . $d->gramasi),
            ...$d->daftar_finishing,
            trim($d->jenis_cetak . ' ' . $d->warna_cetak),
            trim((string) $d->aksesori),
        ], fn ($b) => $b !== ''));
    }

    /** "📌Harga : Rp4.300 /pcs" (dilewati bila harga kosong/0). */
    private function barisHarga(DetailPesanan $d): ?string
    {
        if ($d->harga_satuan === null || (float) $d->harga_satuan <= 0) {
            return null;
        }

        return '📌Harga : Rp' . number_format((float) $d->harga_satuan, 0, ',', '.') . ' /pcs';
    }

    /** Tanggal ringkas teks SPP: "21 Nov" (tahun ditambahkan bila berbeda dari tahun order). */
    private function tanggalSpp(?\Carbon\CarbonInterface $tanggal): ?string
    {
        if (! $tanggal) {
            return null;
        }

        $tahunAcuan = ($this->tanggal_order ?? now())->year;

        return $tanggal->translatedFormat($tanggal->year === $tahunAcuan ? 'j M' : 'j M Y');
    }

    /** Rupiah gaya teks SPP: "Rp25.000" (tanpa spasi). */
    private static function rupiahSpp(mixed $n): string
    {
        return 'Rp' . number_format((float) $n, 0, ',', '.');
    }

    /** 33.00 → "33", 12.50 → "12,5" */
    private static function angkaRingkas(mixed $n): string
    {
        $teks = rtrim(rtrim(number_format((float) $n, 2, '.', ''), '0'), '.');

        return str_replace('.', ',', $teks);
    }

    /** Link WA tanpa nomor tujuan: admin memilih sendiri kontak/grup produksi. */
    protected function linkWhatsappSpp(): Attribute
    {
        return Attribute::get(fn () => $this->teks_spp === null ? null : 'https://wa.me/?text=' . rawurlencode($this->teks_spp));
    }

    // ── Lain-lain ──────────────────────────────────────

    /** Link wa.me ke customer (nomor sudah dinormalisasi 62xxx saat simpan). */
    protected function linkWhatsapp(): Attribute
    {
        return Attribute::get(fn () => 'https://wa.me/' . $this->nomor_whatsapp
            . '?text=' . rawurlencode("Halo {$this->nama_customer}, kami dari Senggani terkait pesanan Anda."));
    }

    protected function jumlahFileTerunggah(): Attribute
    {
        return Attribute::get(fn () => $this->relationLoaded('file') ? $this->file->count() : $this->file()->count());
    }

    // ── Scope ──────────────────────────────────────────

    /** Filter tab dashboard. Semua = status aktif; History = selesai + tidak jadi + dibatalkan. */
    public function scopeTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'history' => $query->whereIn('status_produksi', StatusProduksi::nilaiRiwayat()),
            'hitung_harga', 'menunggu_dp', 'proses', 'selesai_produksi', 'pengiriman' => $query->where('status_produksi', $tab),
            default   => $query->whereIn('status_produksi', StatusProduksi::AKTIF),
        };
    }

    /**
     * Pesanan untuk notifikasi deadline: status yang dipantau dan deadline sudah lewat
     * atau paling lambat HARI_MENDEKAT hari lagi. Urut paling mendesak dulu.
     */
    public function scopeDeadlineDekat(Builder $query): Builder
    {
        return $query->whereIn('status_produksi', StatusProduksi::PANTAU_DEADLINE)
            ->whereNotNull('deadline')
            ->whereDate('deadline', '<=', today()->addDays(self::HARI_MENDEKAT)->toDateString())
            ->orderBy('deadline')->orderBy('id');
    }

    /**
     * Cari nama customer, nama brand, nomor SPP ("176" / "#176" / "SPP #176" cocok persis
     * ke nomor_spp), atau kode penawaran ("PNW-23" / "pnw23" cocok ke id).
     * Karakter wildcard LIKE di-escape.
     */
    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        $kata = trim((string) $kata);
        if ($kata === '') {
            return $query;
        }

        $pola  = '%' . addcslashes($kata, '\\%_') . '%';
        $nomor = self::angkaNomorSpp($kata);
        $idPnw = self::idKodePenawaran($kata);

        return $query->where(function (Builder $q) use ($pola, $nomor, $idPnw) {
            $q->where('nama_customer', 'like', $pola)
                ->orWhere('nama_brand', 'like', $pola);

            if ($nomor !== null) {
                $q->orWhere('nomor_spp', $nomor);
            }
            if ($idPnw !== null) {
                $q->orWhere('id', $idPnw);
            }
        });
    }

    public function scopeUrutkan(Builder $query, string $urut, string $tab): Builder
    {
        if ($tab === 'history' && $urut === 'deadline') {
            // Bawaan History: yang paling baru selesai/tidak jadi/dibatalkan di atas
            return $query->orderByDesc('selesai_pada')->orderByDesc('id');
        }

        return match ($urut) {
            'deadline_jauh' => $query->orderByDesc('deadline')->orderByDesc('id'),
            'terbaru'       => $query->orderByDesc('created_at')->orderByDesc('id'),
            default         => $query->orderBy('deadline')->orderBy('id'),
        };
    }
}

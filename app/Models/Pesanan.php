<?php

namespace App\Models;

use App\Enums\StatusDesain;
use App\Enums\StatusProduksi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Order internal (bukan dari customer langsung). total disimpan & dihitung server
 * dari item (lihat PesananService); sisa & status_pembayaran dihitung di sini.
 */
class Pesanan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'pesanan';

    // Tab dashboard (kunci query string ?tab=)
    public const TAB = [
        'semua'            => 'Semua SPP',
        'antrian'          => 'Antrian Produksi',
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

    protected $fillable = [
        'nomor_spp', 'customer_id', 'nama_customer', 'nama_brand', 'nomor_whatsapp', 'alamat', 'kota',
        'tanggal_order', 'deadline', 'jenis_deadline', 'total', 'dp', 'tanggal_lunas',
        'link_desain', 'catatan_desain', 'status_desain', 'catatan_admin',
        'status_produksi', 'selesai_pada', 'dibuat_oleh',
    ];

    protected $attributes = [
        'status_produksi' => 'antrian',
        'status_desain'   => 'belum_dikirim',
        'jenis_deadline'  => 'kirim',
        'total'           => 0,
        'dp'              => 0,
    ];

    protected function casts(): array
    {
        return [
            'nomor_spp'       => 'integer',
            'tanggal_order'   => 'date',
            'deadline'        => 'date',
            'tanggal_lunas'   => 'date',
            'selesai_pada'    => 'datetime',
            'total'           => 'decimal:2',
            'dp'              => 'decimal:2',
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

    /** Sisa tagihan: 0 bila sudah lunas, selain itu max(total − dp, 0). */
    protected function sisa(): Attribute
    {
        return Attribute::get(fn () => $this->tanggal_lunas
            ? 0.0
            : max(round((float) $this->total - (float) $this->dp, 2), 0.0));
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

    // ── Deadline (dihitung) ────────────────────────────

    /** Selisih hari kalender dari hari ini ke deadline (negatif = sudah lewat). */
    protected function hariMenujuDeadline(): Attribute
    {
        return Attribute::get(fn () => $this->deadline
            ? (int) today()->diffInDays($this->deadline->copy()->startOfDay(), false)
            : null);
    }

    /**
     * "lewat" | "mendekat" | null. Hanya untuk order yang belum selesai produksi
     * (antrian/proses). Mendekat = deadline hari ini s.d. HARI_MENDEKAT hari lagi.
     */
    protected function penandaDeadline(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->deadline || ! $this->status_produksi?->belumSelesaiProduksi()) {
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
     * Saran nomor SPP berikutnya = nomor terbesar yang pernah dipakai (termasuk yang
     * dihapus) + 1; 1 bila belum ada pesanan sama sekali.
     */
    public static function saranNomorSpp(): int
    {
        return (int) static::withTrashed()->max('nomor_spp') + 1;
    }

    /**
     * Nomor baru dianggap loncat bila selisihnya dari saran lebih dari AMBANG_LONCAT_NOMOR.
     * Tidak berlaku untuk SPP pertama (admin melanjutkan nomor dari buku lama).
     */
    public static function nomorSppLoncat(int $nomor): bool
    {
        if (! static::withTrashed()->exists()) {
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

    /** "SPP #176" */
    protected function nomorSppTeks(): Attribute
    {
        return Attribute::get(fn () => $this->nomor_spp ? 'SPP #' . $this->nomor_spp : null);
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
     */
    protected function teksSpp(): Attribute
    {
        return Attribute::get(function () {
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

    /** Baris Produk/Ukuran/Jumlah/Bahan satu item (tanpa harga). */
    private function barisItem(DetailPesanan $d, bool $bernomor, int $no = 1): array
    {
        $baris = [($bernomor ? "{$no}. " : '') . 'Produk: ' . $d->jenis_packaging];

        $ukuran = array_filter([$d->panjang, $d->lebar, $d->tinggi], fn ($n) => $n !== null && $n !== '');
        if ($ukuran) {
            $baris[] = 'Ukuran: ' . implode(' x ', array_map(fn ($n) => self::angkaRingkas($n), $ukuran));
        }

        $baris[] = 'Jumlah: ' . number_format((int) $d->jumlah, 0, ',', '.') . ' pcs';

        $bahan = array_filter([
            trim($d->jenis_bahan . ' ' . $d->gramasi),
            ...$d->daftar_finishing,
            trim($d->jenis_cetak . ' ' . $d->warna_cetak),
            trim((string) $d->aksesori),
        ], fn ($b) => $b !== '');
        if ($bahan) {
            $baris[] = 'Bahan: ' . implode(' + ', $bahan);
        }

        return $baris;
    }

    /** "📌Harga : Rp4.300 /pcs" (dilewati bila harga 0). */
    private function barisHarga(DetailPesanan $d): ?string
    {
        if ((float) $d->harga_satuan <= 0) {
            return null;
        }

        return '📌Harga : Rp' . number_format((float) $d->harga_satuan, 0, ',', '.') . ' /pcs';
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
        return Attribute::get(fn () => 'https://wa.me/?text=' . rawurlencode($this->teks_spp));
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

    /** Filter tab dashboard. Semua = order aktif saja; History = selesai + dibatalkan. */
    public function scopeTab(Builder $query, string $tab): Builder
    {
        return match ($tab) {
            'history' => $query->whereIn('status_produksi', StatusProduksi::nilaiRiwayat()),
            'antrian', 'proses', 'selesai_produksi', 'pengiriman' => $query->where('status_produksi', $tab),
            default   => $query->whereNotIn('status_produksi', StatusProduksi::nilaiRiwayat()),
        };
    }

    /**
     * Cari nama customer, nama brand, atau nomor SPP ("176" / "#176" / "SPP #176"
     * cocok persis ke nomor_spp). Karakter wildcard LIKE di-escape.
     */
    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        $kata = trim((string) $kata);
        if ($kata === '') {
            return $query;
        }

        $pola  = '%' . addcslashes($kata, '\\%_') . '%';
        $nomor = self::angkaNomorSpp($kata);

        return $query->where(function (Builder $q) use ($pola, $nomor) {
            $q->where('nama_customer', 'like', $pola)
                ->orWhere('nama_brand', 'like', $pola);

            if ($nomor !== null) {
                $q->orWhere('nomor_spp', $nomor);
            }
        });
    }

    public function scopeUrutkan(Builder $query, string $urut, string $tab): Builder
    {
        if ($tab === 'history' && $urut === 'deadline') {
            // Bawaan History: yang paling baru selesai/dibatalkan di atas
            return $query->orderByDesc('selesai_pada')->orderByDesc('id');
        }

        return match ($urut) {
            'deadline_jauh' => $query->orderByDesc('deadline')->orderByDesc('id'),
            'terbaru'       => $query->orderByDesc('created_at')->orderByDesc('id'),
            default         => $query->orderBy('deadline')->orderBy('id'),
        };
    }
}

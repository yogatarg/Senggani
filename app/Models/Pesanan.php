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
        'semua'            => 'Semua Order',
        'antrian'          => 'Antrian Produksi',
        'proses'           => 'Dalam Proses',
        'selesai_produksi' => 'Pesanan Selesai',
        'pengiriman'       => 'Pengiriman',
        'history'          => 'History Order',
    ];

    // Pilihan urutan (?urut=)
    public const URUT = [
        'deadline'      => 'Deadline terdekat',
        'deadline_jauh' => 'Deadline terjauh',
        'terbaru'       => 'Order terbaru',
    ];

    // Nilai <select> untuk opsi "Lainnya…" (teks bebas di field <nama>_lainnya)
    public const LAINNYA = '__lainnya__';

    // Ambang "mendekati deadline" (hari sebelum deadline, termasuk hari-H)
    public const HARI_MENDEKAT = 3;

    public const STATUS_BAYAR = [
        'belum_dp' => 'Belum DP',
        'dp'       => 'DP Masuk',
        'lunas'    => 'Lunas',
    ];

    protected $fillable = [
        'nomor_order', 'nama_customer', 'nomor_whatsapp', 'alamat', 'kota',
        'tanggal_order', 'deadline', 'total', 'dp', 'tanggal_lunas',
        'link_desain', 'catatan_desain', 'status_desain', 'catatan_admin',
        'status_produksi', 'selesai_pada', 'dibuat_oleh',
    ];

    protected $attributes = [
        'status_produksi' => 'antrian',
        'status_desain'   => 'belum_dikirim',
        'total'           => 0,
        'dp'              => 0,
    ];

    protected function casts(): array
    {
        return [
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

    // ── Lain-lain ──────────────────────────────────────

    /** Link wa.me ke customer (nomor sudah dinormalisasi 62xxx saat simpan). */
    protected function linkWhatsapp(): Attribute
    {
        return Attribute::get(fn () => 'https://wa.me/' . $this->nomor_whatsapp
            . '?text=' . rawurlencode("Halo {$this->nama_customer}, kami dari Senggani terkait pesanan {$this->nomor_order}."));
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

    /** Cari nama customer atau nomor order (karakter wildcard LIKE di-escape). */
    public function scopeCari(Builder $query, ?string $kata): Builder
    {
        $kata = trim((string) $kata);
        if ($kata === '') {
            return $query;
        }

        $pola = '%' . addcslashes($kata, '\\%_') . '%';

        return $query->where(fn (Builder $q) => $q
            ->where('nomor_order', 'like', $pola)
            ->orWhere('nama_customer', 'like', $pola));
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

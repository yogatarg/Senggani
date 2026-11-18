<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatusDesain;
use App\Enums\StatusProduksi;
use App\Exceptions\StatusBentrok;
use App\Http\Controllers\Admin\Concerns\UnggahGambar;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatalPesananRequest;
use App\Http\Requests\CatatanPesananRequest;
use App\Http\Requests\LunasPesananRequest;
use App\Http\Requests\PulihkanPesananRequest;
use App\Http\Requests\SimpanPesananRequest;
use App\Http\Requests\UbahStatusPesananRequest;
use App\Models\Bahan;
use App\Models\Cetak;
use App\Models\Finishing;
use App\Models\KategoriProduk;
use App\Models\Pesanan;
use App\Services\OlahGambar;
use App\Services\PesananService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * Modul Pesanan internal (admin & produksi). Hak akses: App\Policies\PesananPolicy.
 */
class PesananController extends Controller
{
    use UnggahGambar;

    public const PER_HALAMAN = 20;

    public function __construct(private OlahGambar $olahGambar, private PesananService $layanan)
    {
    }

    // ── Daftar & jumlah per tab ────────────────────────

    public function index(Request $request)
    {
        Gate::authorize('viewAny', Pesanan::class);

        $tab  = array_key_exists($request->query('tab'), Pesanan::TAB) ? $request->query('tab') : 'semua';
        $urut = array_key_exists($request->query('urut'), Pesanan::URUT) ? $request->query('urut') : 'deadline';
        $q    = trim((string) $request->query('q', ''));
        $q    = mb_substr($q, 0, 100);

        $query = Pesanan::query()
            ->with('detailPesanan')
            ->withCount('detailPesanan')
            ->withSum('detailPesanan as total_jumlah', 'jumlah');

        if ($q !== '') {
            // Pencarian menjangkau SEMUA order (termasuk History), tidak dibatasi tab
            $query->cari($q)->urutkan($urut, 'semua');
        } else {
            $query->tab($tab)->urutkan($urut, $tab);
        }

        $pesanan = $query->paginate(self::PER_HALAMAN)->withQueryString();

        return view('admin.pesanan.index', [
            'pesanan'     => $pesanan,
            'tab'         => $tab,
            'q'           => $q,
            'urut'        => $urut,
            'daftarTab'   => Pesanan::TAB,
            'daftarUrut'  => Pesanan::URUT,
            'ringkasan'   => $this->ringkasanTab(),
        ]);
    }

    /** JSON ringan untuk auto-refresh 60 detik: jumlah per tab + penanda perubahan terakhir. */
    public function jumlah()
    {
        Gate::authorize('viewAny', Pesanan::class);

        return response()->json($this->ringkasanTab());
    }

    private function ringkasanTab(): array
    {
        $perStatus = Pesanan::query()
            ->select('status_produksi', DB::raw('COUNT(*) as jumlah'))
            ->groupBy('status_produksi')
            ->pluck('jumlah', 'status_produksi')
            ->map(fn ($n) => (int) $n);

        $ambil = fn (string $s) => $perStatus[$s] ?? 0;

        $jumlah = [
            'semua'            => $ambil('antrian') + $ambil('proses') + $ambil('selesai_produksi') + $ambil('pengiriman'),
            'antrian'          => $ambil('antrian'),
            'proses'           => $ambil('proses'),
            'selesai_produksi' => $ambil('selesai_produksi'),
            'pengiriman'       => $ambil('pengiriman'),
            'history'          => $ambil('selesai') + $ambil('dibatalkan'),
        ];

        // Termasuk order yang di-soft-delete agar penghapusan juga terdeteksi
        $terakhir = Pesanan::withTrashed()->max('updated_at');
        $terakhir = $terakhir ? Carbon::parse($terakhir) : null;

        return [
            'jumlah'          => $jumlah,
            'terakhir_diubah' => $terakhir?->toIso8601String(),
            'penanda'         => md5(json_encode($jumlah) . '|' . $terakhir?->format('Y-m-d H:i:s')),
            'diperbarui_pada' => now()->toIso8601String(),
        ];
    }

    // ── Tambah / edit ──────────────────────────────────

    public function create()
    {
        Gate::authorize('create', Pesanan::class);

        $saran = Pesanan::saranNomorSpp();

        return view('admin.pesanan.create', [
            'pesanan' => new Pesanan([
                'nomor_spp'      => $saran,
                'tanggal_order'  => today(),
                'jenis_deadline' => 'kirim',
                'status_desain'  => StatusDesain::BelumDikirim->value,
            ]),
            'saranNomor' => $saran,
            ...$this->dataMaster(),
        ]);
    }

    public function store(SimpanPesananRequest $request)
    {
        $fileBaru = $this->olahGambarBaru($request);

        $pesanan = $this->layanan->buat(
            $request->dataPesanan(), $request->dataItem(), $fileBaru, Auth::user(),
            $request->customerId(), $request->perbaruiCustomer(),
        );

        return $this->kembaliKeDetail($pesanan, "{$pesanan->nomor_spp_teks} berhasil disimpan.");
    }

    /** Redirect ke detail + pesan sukses; info customer (bila ada) ikut di pesan & di session 'info_customer'. */
    private function kembaliKeDetail(Pesanan $pesanan, string $pesan)
    {
        $info = $this->layanan->infoCustomer;

        return redirect()->route('admin.pesanan.show', $pesanan)
            ->with('success', $info ? "{$pesan} {$info}" : $pesan)
            ->with('info_customer', $info);
    }

    public function show(Pesanan $pesanan)
    {
        Gate::authorize('view', $pesanan);

        $pesanan->load(['detailPesanan', 'file', 'riwayat', 'pembuat', 'customer']);

        $status = $pesanan->status_produksi;

        return view('admin.pesanan.show', [
            'pesanan'          => $pesanan,
            'teksSpp'          => $pesanan->teks_spp,
            'linkWhatsappSpp'  => $pesanan->link_whatsapp_spp,
            'statusBerikutnya' => $status->berikutnya(),
            'statusSebelumnya' => $status->sebelumnya(),
            'alurStatus'       => array_map(fn ($s) => StatusProduksi::from($s), StatusProduksi::ALUR),
            'waktuStatus'      => $this->waktuMasukStatus($pesanan),
            'saranCatatan'     => self::SARAN_CATATAN,
        ]);
    }

    public const SARAN_CATATAN = [
        'Material sudah disiapkan',
        'Proses cetak selesai',
        'Finishing sedang dikerjakan',
        'Barang masuk QC',
        'Barang siap dikirim',
    ];

    /** Waktu terakhir order masuk ke tiap status (untuk stepper "sejak 17 Nov"). */
    private function waktuMasukStatus(Pesanan $pesanan): array
    {
        $waktu = [];
        foreach ($pesanan->riwayat->sortBy('created_at') as $r) {
            if ($r->status_ke) {
                $waktu[$r->status_ke->value] = $r->created_at;
            }
        }

        return $waktu;
    }

    public function edit(Pesanan $pesanan)
    {
        Gate::authorize('update', $pesanan);

        $pesanan->load(['detailPesanan', 'file', 'customer']);

        return view('admin.pesanan.edit', [
            'pesanan'    => $pesanan,
            'saranNomor' => Pesanan::saranNomorSpp(),
            ...$this->dataMaster(),
        ]);
    }

    public function update(SimpanPesananRequest $request, Pesanan $pesanan)
    {
        $fileBaru = $this->olahGambarBaru($request);

        $this->layanan->perbarui(
            $pesanan, $request->dataPesanan(), $request->dataItem(), $fileBaru, Auth::user(),
            $request->customerId(), $request->perbaruiCustomer(), $request->lepasCustomer(),
        );

        return $this->kembaliKeDetail($pesanan, "{$pesanan->nomor_spp_teks} berhasil diperbarui.");
    }

    /** Hapus order salah input (soft delete, khusus admin). */
    public function destroy(Pesanan $pesanan)
    {
        Gate::authorize('delete', $pesanan);

        $pesanan->delete();

        return redirect()->route('admin.pesanan.index')
            ->with('success', "{$pesanan->nomor_spp_teks} dihapus.");
    }

    /** Pilihan master untuk form (yang disimpan tetap teks snapshot). */
    private function dataMaster(): array
    {
        return [
            'masterPackaging' => KategoriProduk::orderBy('nama_kategori')->pluck('nama_kategori')->all(),
            'masterBahan'     => Bahan::orderBy('nama_bahan')->pluck('nama_bahan')->all(),
            'masterFinishing' => Finishing::orderBy('nama_finishing')->pluck('nama_finishing')->all(),
            'masterCetak'     => Cetak::orderBy('nama_cetak')->pluck('nama_cetak')->all(),
            'statusDesain'    => StatusDesain::pilihan(),
            'lainnya'         => Pesanan::LAINNYA,
            'jenisDeadline'   => Pesanan::JENIS_DEADLINE,
            'urlCariCustomer' => route('admin.customer.cari'),
        ];
    }

    /**
     * Olah semua gambar baru (WebP, disk privat) ke folder sementara sebelum transaksi DB,
     * supaya pemrosesan GD yang lama tidak menahan kunci nomor order.
     */
    private function olahGambarBaru(SimpanPesananRequest $request): array
    {
        $hasil = [];

        try {
            foreach ($request->gambarBaru() as $g) {
                $hasil[] = [
                    'jenis'     => $g['jenis'],
                    'nama_asli' => $g['file']->getClientOriginalName(),
                    'path'      => $this->simpanGambar($g['file'], PesananService::FOLDER_SEMENTARA, OlahGambar::PESANAN, $g['field'], 'local'),
                ];
            }
        } catch (Throwable $e) {
            $this->layanan->buangFile(array_column($hasil, 'path'));

            throw $e;
        }

        return $hasil;
    }

    // ── Status, catatan, pembayaran ────────────────────

    public function ubahStatus(UbahStatusPesananRequest $request, Pesanan $pesanan)
    {
        $dari = StatusProduksi::from($request->validated('status_lama'));
        $ke   = StatusProduksi::from($request->validated('status_baru'));

        return $this->jalankanStatus($request, $pesanan, function () use ($pesanan, $dari, $ke, $request) {
            $this->layanan->ubahStatus($pesanan, $dari, $ke, $request->validated('catatan'), Auth::user());

            return "Status diubah ke {$ke->label()}.";
        });
    }

    public function batalkan(BatalPesananRequest $request, Pesanan $pesanan)
    {
        $dari = StatusProduksi::from($request->validated('status_lama'));

        return $this->jalankanStatus($request, $pesanan, function () use ($pesanan, $dari, $request) {
            $this->layanan->batalkan($pesanan, $dari, $request->validated('catatan'), Auth::user());

            return "{$pesanan->nomor_spp_teks} dibatalkan.";
        });
    }

    public function pulihkan(PulihkanPesananRequest $request, Pesanan $pesanan)
    {
        return $this->jalankanStatus($request, $pesanan, function () use ($pesanan, $request) {
            $ke = $this->layanan->pulihkan($pesanan, $request->validated('catatan'), Auth::user());

            return "SPP dipulihkan ke {$ke->label()}.";
        });
    }

    private function jalankanStatus(Request $request, Pesanan $pesanan, callable $aksi)
    {
        try {
            $pesan = $aksi();
        } catch (StatusBentrok $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'pesan'           => $e->getMessage(),
                    'bentrok'         => true,
                    'status_sekarang' => $e->statusSekarang?->value,
                    'label_status'    => $e->statusSekarang?->label(),
                ], 409);
            }

            return redirect()->route('admin.pesanan.show', $pesanan)
                ->with('error', $e->getMessage())
                ->with('bentrok', true);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'pesan'        => $pesan,
                'status'       => $pesanan->status_produksi->value,
                'label_status' => $pesanan->status_produksi->label(),
            ]);
        }

        return redirect()->route('admin.pesanan.show', $pesanan)->with('success', $pesan);
    }

    public function tambahCatatan(CatatanPesananRequest $request, Pesanan $pesanan)
    {
        $riwayat = $this->layanan->tambahCatatan($pesanan, $request->validated('catatan'), Auth::user());

        if ($request->expectsJson()) {
            return response()->json([
                'pesan'   => 'Catatan disimpan.',
                'riwayat' => [
                    'id'            => $riwayat->id,
                    'nama_pengguna' => $riwayat->nama_pengguna,
                    'catatan'       => $riwayat->catatan,
                    'jenis'         => $riwayat->jenis,
                    'created_at'    => $riwayat->created_at?->toIso8601String(),
                ],
            ], 201);
        }

        return redirect()->route('admin.pesanan.show', $pesanan)->with('success', 'Catatan disimpan.');
    }

    public function catatLunas(LunasPesananRequest $request, Pesanan $pesanan)
    {
        $this->layanan->catatLunas($pesanan, Carbon::parse($request->validated('tanggal_lunas')), Auth::user());

        return redirect()->route('admin.pesanan.show', $pesanan)->with('success', 'Pelunasan dicatat.');
    }
}

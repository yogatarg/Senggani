<?php

namespace App\Services;

use App\Models\Pesanan;

/**
 * Data lonceng notifikasi deadline (admin & produksi): SPP berstatus hitung_harga,
 * menunggu_dp, proses, atau selesai_produksi yang deadline-nya sudah lewat atau
 * paling lambat Pesanan::HARI_MENDEKAT hari lagi. Urut paling mendesak dulu.
 *
 * Dipakai view composer layout pesanan ($notifDeadline) dan endpoint admin.pesanan.jumlah (kunci "deadline").
 */
class NotifikasiDeadline
{
    public const MAKS_ITEM = 20;

    /**
     * @return array{total:int, lewat:int, mendekat:int, item:list<array<string, mixed>>}
     */
    public function ambil(int $maks = self::MAKS_ITEM): array
    {
        $dasar = Pesanan::query()->deadlineDekat();

        $total = (clone $dasar)->count();
        $lewat = (clone $dasar)->whereDate('deadline', '<', today()->toDateString())->count();

        $item = $total === 0 ? collect() : (clone $dasar)
            ->limit($maks)
            ->get(['id', 'nomor_spp', 'nama_customer', 'nama_brand', 'status_produksi', 'tanggal_order', 'deadline', 'jenis_deadline']);

        return [
            'total'    => $total,
            'lewat'    => $lewat,
            'mendekat' => $total - $lewat,
            'item'     => $item->map(fn (Pesanan $p) => self::entri($p))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public static function entri(Pesanan $p): array
    {
        return [
            'id'                   => $p->id,
            'kode_tampil'          => $p->kode_tampil,
            'nama_customer'        => $p->nama_customer,
            'nama_brand'           => $p->nama_brand,
            'status'               => $p->status_produksi->value,
            'label_status'         => $p->status_produksi->label(),
            'deadline'             => $p->deadline?->toDateString(),
            'deadline_teks'        => $p->deadline_teks,
            'penanda'              => $p->penanda_deadline,
            'hari_menuju_deadline' => $p->hari_menuju_deadline,
            'url'                  => route('admin.pesanan.show', $p->id),
        ];
    }
}

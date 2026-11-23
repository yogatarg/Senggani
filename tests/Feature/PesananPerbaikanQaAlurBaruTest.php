<?php

namespace Tests\Feature;

use App\Enums\StatusProduksi;
use App\Models\Admin;
use App\Models\Pesanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perbaikan temuan QA alur baru SPP:
 * B1 kunci pelunasan lewat form Edit & pulihkan, DP minimal Rp 1 setelah produksi;
 * B2 saran nomor melompati nomor SPP terhapus; B5 harga satuan minimal Rp 1;
 * B6 label "Pesanan Selesai".
 */
class PesananPerbaikanQaAlurBaruTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN_LUNAS = 'SPP yang sudah dikirim/selesai harus tetap tercatat lunas. Kembalikan status ke Pesanan Selesai dulu bila pelunasan perlu dikoreksi.';

    private Admin $admin;
    private Admin $produksi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(9, 0));

        $this->admin    = Admin::factory()->create(['nama' => 'Rina CS']);
        $this->produksi = Admin::factory()->produksi()->create(['nama' => 'Ayah']);
    }

    private function sebagai(Admin $pengguna): static
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_id' => $pengguna->id]);
    }

    private function data(array $ubah = []): array
    {
        return array_replace_recursive([
            'nama_customer'  => 'Ismun',
            'nomor_whatsapp' => '0812-3456-7890',
            'alamat'         => 'Jl. Melati 5',
            'kota'           => 'Surakarta',
            'tanggal_order'  => '2026-11-19',
            'deadline'       => '2026-11-30',
            'jenis_deadline' => 'kirim',
            'item'           => [
                ['jenis_packaging' => 'Paperbag', 'jenis_bahan' => 'Kraft', 'jumlah' => '500', 'harga_satuan' => '4.300'],
                ['jenis_packaging' => 'Box', 'jenis_bahan' => 'Duplex', 'jumlah' => '100', 'harga_satuan' => '2000'],
            ],
        ], $ubah);
    }

    private function buat(array $ubah = []): Pesanan
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data($ubah))
            ->assertSessionHasNoErrors()->assertRedirect();

        return Pesanan::latest('id')->firstOrFail();
    }

    /** SPP bernomor (proses) lalu dipaksa ke status tertentu. */
    private function spp(int $nomor, string $status = 'proses', array $kolom = [], array $ubah = []): Pesanan
    {
        $p = $this->buat($ubah);
        $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $p), [
            'dp' => '1.000.000', 'nomor_spp' => (string) $nomor, 'konfirmasi_nomor' => '1', 'metode_dp' => 'transfer_bca',
        ])->assertSessionHasNoErrors();

        $p->forceFill(['status_produksi' => $status, ...$kolom])->save();

        return $p->fresh();
    }

    private function edit(Pesanan $p, array $ubah = [])
    {
        return $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), $this->data([
            'nomor_spp' => (string) $p->nomor_spp, 'dp' => '1.000.000', ...$ubah,
        ]));
    }

    // ── B1: kunci pelunasan lewat form Edit ────────────

    public function test_edit_spp_pengiriman_atau_selesai_tidak_bisa_mengosongkan_tanggal_lunas(): void
    {
        foreach (['pengiriman' => 176, 'selesai' => 177] as $status => $nomor) {
            $p = $this->spp($nomor, $status, ['tanggal_lunas' => '2026-11-18'], ['nomor_whatsapp' => "0812 3456 {$nomor}0"]);

            $this->edit($p, ['tanggal_lunas' => ''])->assertSessionHasErrors(['tanggal_lunas' => self::PESAN_LUNAS]);
            $this->assertSame('2026-11-18', $p->fresh()->tanggal_lunas->toDateString());

            // Mengoreksi tanggal (tetap terisi) boleh; tanggal berubah = metode pelunasan wajib
            $this->edit($p, ['tanggal_lunas' => '2026-11-19', 'metode_lunas' => 'cash'])->assertSessionHasNoErrors();
            $this->assertSame('2026-11-19', $p->fresh()->tanggal_lunas->toDateString());
            $this->assertSame($status, $p->fresh()->status_produksi->value);
        }

        // Pesanan Selesai (belum dikirim) masih boleh mengosongkan pelunasan
        $q = $this->spp(178, 'selesai_produksi', ['tanggal_lunas' => '2026-11-18'], ['nomor_whatsapp' => '0813 1111 2222']);
        $this->edit($q, ['tanggal_lunas' => ''])->assertSessionHasNoErrors();
        $this->assertNull($q->fresh()->tanggal_lunas);
    }

    public function test_edit_spp_produksi_dp_minimal_1(): void
    {
        foreach (['proses' => 176, 'selesai_produksi' => 177, 'dibatalkan' => 178] as $status => $nomor) {
            $p = $this->spp($nomor, $status, [], ['nomor_whatsapp' => "0812 3456 {$nomor}0"]);

            $this->edit($p, ['dp' => '0'])->assertSessionHasErrors(['dp' => 'DP minimal Rp 1 karena SPP sudah masuk produksi.']);
            $this->edit($p, ['dp' => ''])->assertSessionHasErrors(['dp' => 'DP wajib diisi karena SPP sudah masuk produksi (minimal Rp 1).']);
            $this->assertEquals(1000000, (float) $p->fresh()->dp);

            $this->edit($p, ['dp' => '1'])->assertSessionHasNoErrors();
            $this->assertEquals(1, (float) $p->fresh()->dp);
        }
    }

    public function test_pulihkan_ke_pengiriman_yang_belum_lunas_jatuh_ke_pesanan_selesai(): void
    {
        $p = $this->spp(176, 'pengiriman', ['tanggal_lunas' => '2026-11-18']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.batal', $p), ['status_lama' => 'pengiriman'])->assertSessionHas('success');

        // Pelunasan hilang (data lama / koreksi langsung) → tidak boleh kembali ke Pengiriman
        $p->forceFill(['tanggal_lunas' => null])->save();

        $this->sebagai($this->admin)->post(route('admin.pesanan.pulihkan', $p), ['catatan' => 'Customer jadi ambil'])
            ->assertSessionHas('success', 'SPP dipulihkan ke Pesanan Selesai.');
        $p->refresh();
        $this->assertSame(StatusProduksi::SelesaiProduksi, $p->status_produksi);
        $this->assertTrue($p->menunggu_pelunasan);
        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $p->id, 'status_dari' => 'dibatalkan', 'status_ke' => 'selesai_produksi',
            'catatan'    => "Customer jadi ambil\nDipulihkan ke Pesanan Selesai (bukan Pengiriman) karena SPP belum lunas.",
        ]);

        // Yang sudah lunas tetap kembali ke Pengiriman, tanpa catatan tambahan
        $q = $this->spp(177, 'pengiriman', ['tanggal_lunas' => '2026-11-18'], ['nomor_whatsapp' => '0813 1111 2222']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.batal', $q), ['status_lama' => 'pengiriman'])->assertSessionHas('success');
        $this->sebagai($this->admin)->post(route('admin.pesanan.pulihkan', $q))
            ->assertSessionHas('success', 'SPP dipulihkan ke Pengiriman.');
        $this->assertSame(StatusProduksi::Pengiriman, $q->fresh()->status_produksi);
        $this->assertNull($q->riwayat()->latest('id')->value('catatan'));
    }

    // ── B2: saran nomor melompati nomor terhapus ───────

    public function test_saran_nomor_melompati_spp_terbesar_yang_dihapus(): void
    {
        $this->spp(176);
        $this->spp(177, ubah: ['nomor_whatsapp' => '0813 1111 2222'])->delete();
        $this->spp(178, ubah: ['nomor_whatsapp' => '0813 1111 3333'])->delete();

        // Terbesar aktif 176 → 177 & 178 sudah dipakai SPP terhapus → 179
        $this->assertSame(179, Pesanan::saranNomorSpp());

        // Saran itu sendiri lolos validasi Catat DP
        $c = $this->buat(['nomor_whatsapp' => '0813 1111 4444']);
        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $c))->assertViewHas('saranNomor', 179);
        $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $c), ['dp' => '1.000.000', 'nomor_spp' => '179', 'metode_dp' => 'cash'])
            ->assertSessionHasNoErrors();
        $this->assertSame(179, $c->fresh()->nomor_spp);
        $this->assertSame(180, Pesanan::saranNomorSpp());
    }

    public function test_nomor_typo_besar_yang_dihapus_tidak_menyeret_saran(): void
    {
        $this->spp(176);
        $this->spp(1776, ubah: ['nomor_whatsapp' => '0813 1111 2222'])->delete();

        $this->assertSame(177, Pesanan::saranNomorSpp());

        // Saran juga dipakai sebagai acuan nomor loncat: 177 tidak dianggap loncat
        $c = $this->buat(['nomor_whatsapp' => '0813 1111 4444']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $c), ['dp' => '1.000.000', 'nomor_spp' => '177', 'metode_dp' => 'cash'])
            ->assertSessionHasNoErrors();
        // Nomor typo yang dihapus tetap tidak boleh dipakai ulang
        $d = $this->buat(['nomor_whatsapp' => '0813 1111 5555']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $d), ['dp' => '1.000.000', 'nomor_spp' => '1776', 'konfirmasi_nomor' => '1', 'metode_dp' => 'cash'])
            ->assertSessionHasErrors('nomor_spp');
    }

    // ── B5: harga satuan minimal Rp 1 ──────────────────

    public function test_harga_satuan_minimal_1_di_isi_harga_dan_form(): void
    {
        $p = $this->buat(['item' => [['harga_satuan' => ''], ['harga_satuan' => '']]]);
        $this->assertSame(StatusProduksi::HitungHarga, $p->status_produksi);
        $ids = $p->detailPesanan()->pluck('id')->all();

        // Isi harga (produksi & admin): 0 ditolak, kosong tetap boleh selama Hitung Harga
        foreach ([$this->produksi, $this->admin] as $pengguna) {
            $this->sebagai($pengguna)->post(route('admin.pesanan.harga', $p), ['harga' => [$ids[0] => '0', $ids[1] => '']])
                ->assertSessionHasErrors(["harga.{$ids[0]}" => 'Harga satuan minimal Rp 1.']);
        }
        $this->assertSame(StatusProduksi::HitungHarga, $p->fresh()->status_produksi);

        $this->sebagai($this->produksi)->post(route('admin.pesanan.harga', $p), ['harga' => [$ids[0] => '1', $ids[1] => '']])
            ->assertSessionHasNoErrors();
        $this->assertSame(StatusProduksi::HitungHarga, $p->fresh()->status_produksi);

        // Form tambah & edit
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data(['item' => [['harga_satuan' => '0']]]))
            ->assertSessionHasErrors(['item.0.harga_satuan' => 'Harga satuan minimal Rp 1.']);
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), $this->data(['item' => [['harga_satuan' => 'Rp 0']]]))
            ->assertSessionHasErrors(['item.0.harga_satuan' => 'Harga satuan minimal Rp 1.']);
    }

    // ── B6: istilah ────────────────────────────────────

    public function test_label_selesai_produksi_menjadi_pesanan_selesai(): void
    {
        $this->assertSame('Pesanan Selesai', StatusProduksi::SelesaiProduksi->label());
        $this->assertSame('Pesanan Selesai', StatusProduksi::pilihan()['selesai_produksi']);
    }
}

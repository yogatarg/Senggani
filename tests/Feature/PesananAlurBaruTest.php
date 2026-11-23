<?php

namespace Tests\Feature;

use App\Enums\StatusProduksi;
use App\Exceptions\AksiDitolak;
use App\Models\Admin;
use App\Models\Banner;
use App\Models\Pesanan;
use App\Models\PesananRiwayat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Alur baru SPP: input tanpa harga → Hitung Harga (produksi) → Menunggu DP → Catat DP & nomor
 * SPP → Dalam Proses → ... → Pengiriman (wajib lunas). Tidak Jadi & pulihkan, kode PNW,
 * notifikasi deadline, migrasi antrian, dan galat panel katalog.
 */
class PesananAlurBaruTest extends TestCase
{
    use RefreshDatabase;

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
                ['jenis_packaging' => 'Paperbag', 'jenis_bahan' => 'Kraft', 'jumlah' => '500', 'harga_satuan' => ''],
                ['jenis_packaging' => 'Box', 'jenis_bahan' => 'Duplex', 'jumlah' => '100'],
            ],
        ], $ubah);
    }

    private function buat(array $ubah = []): Pesanan
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data($ubah))
            ->assertSessionHasNoErrors()->assertRedirect();

        return Pesanan::latest('id')->firstOrFail();
    }

    /** harga[<detail id>] untuk semua item sesuai urutan. */
    private function harga(Pesanan $p, array $nilai): array
    {
        $ids = $p->detailPesanan()->pluck('id')->all();

        return array_combine(array_slice($ids, 0, count($nilai)), $nilai);
    }

    /** Pesanan dengan harga lengkap (menunggu_dp): 500 × 4.300 + 100 × 2.000 = 2.350.000. */
    private function buatBerharga(array $ubah = []): Pesanan
    {
        return $this->buat(array_replace_recursive([
            'item' => [['harga_satuan' => '4.300'], ['harga_satuan' => '2000']],
        ], $ubah));
    }

    private function catatDp(Pesanan $p, array $ubah = [])
    {
        return $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $p), [
            'dp' => '1.000.000', 'nomor_spp' => '176', 'metode_dp' => 'transfer_bca', ...$ubah,
        ]);
    }

    // ── 1. Input tanpa harga ───────────────────────────

    public function test_input_tanpa_harga_masuk_hitung_harga_dengan_kode_pnw(): void
    {
        $p = $this->buat();

        $this->assertSame(StatusProduksi::HitungHarga, $p->status_produksi);
        $this->assertNull($p->nomor_spp);
        $this->assertEquals(0, (float) $p->total);
        $this->assertSame([null, null], $p->detailPesanan->pluck('harga_satuan')->all());
        $this->assertSame([null, null], $p->detailPesanan->pluck('subtotal')->all());
        $this->assertFalse($p->harga_lengkap);
        $this->assertSame(2, $p->jumlah_item_tanpa_harga);
        $this->assertSame("PNW-{$p->id}", $p->kode_penawaran);
        $this->assertSame("PNW-{$p->id}", $p->kode_tampil);
        $this->assertNull($p->nomor_spp_teks);
        $this->assertNull($p->teks_spp);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $p->id, 'status_dari' => null, 'status_ke' => 'hitung_harga', 'catatan' => 'SPP dibuat']);

        // Sebagian harga terisi tetap Hitung Harga; total hanya dari item berharga
        $q = $this->buat(['item' => [['harga_satuan' => '4.300']]]);
        $this->assertSame(StatusProduksi::HitungHarga, $q->status_produksi);
        $this->assertEquals(2150000, (float) $q->total);

        // Semua harga terisi saat input → langsung Menunggu DP
        $this->assertSame(StatusProduksi::MenungguDp, $this->buatBerharga()->status_produksi);
    }

    // ── 2. Produksi mengisi harga ──────────────────────

    public function test_produksi_mengisi_harga_lalu_otomatis_menunggu_dp(): void
    {
        $p = $this->buat();

        // Sebagian dulu
        $this->sebagai($this->produksi)->post(route('admin.pesanan.harga', $p), ['harga' => $this->harga($p, ['Rp 4.300'])])
            ->assertRedirect(route('admin.pesanan.show', $p))
            ->assertSessionHas('success', 'Harga disimpan. Masih ada item yang belum diberi harga.');
        $p->refresh();
        $this->assertSame(StatusProduksi::HitungHarga, $p->status_produksi);
        $this->assertEquals(2150000, (float) $p->total);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $p->id, 'catatan' => 'Harga disimpan sebagian oleh Ayah (1 dari 2 item)']);

        // Lengkap → Menunggu DP
        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.harga', $p), [
            'harga' => $this->harga($p, ['4300', '2.000']), 'catatan_harga' => 'Kraft naik 5%',
        ])->assertOk()->assertJson(['status' => 'menunggu_dp', 'label_status' => 'Menunggu DP', 'total' => 2350000]);

        $p->refresh();
        $this->assertSame(StatusProduksi::MenungguDp, $p->status_produksi);
        $this->assertEquals(2350000, (float) $p->total);
        $this->assertEquals(200000, (float) $p->detailPesanan[1]->subtotal);
        $this->assertSame('Kraft naik 5%', $p->catatan_harga);
        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $p->id, 'admin_id' => $this->produksi->id, 'status_dari' => 'hitung_harga', 'status_ke' => 'menunggu_dp',
            'catatan' => "Harga dihitung oleh Ayah\nCatatan: Kraft naik 5%",
        ]);
    }

    public function test_produksi_tidak_bisa_mengubah_harga_di_luar_hitung_harga(): void
    {
        $p = $this->buatBerharga(); // menunggu_dp
        $harga = ['harga' => $this->harga($p, ['1', '1'])];

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.harga', $p), $harga)
            ->assertForbidden()->assertExactJson(['pesan' => AksiDitolak::HARGA_DIKUNCI]);
        $this->sebagai($this->produksi)->from(route('admin.pesanan.show', $p))->post(route('admin.pesanan.harga', $p), $harga)
            ->assertRedirect(route('admin.pesanan.show', $p))->assertSessionHas('error', AksiDitolak::HARGA_DIKUNCI);
        $this->assertEquals(2350000, (float) $p->fresh()->total);

        // Produksi boleh mengembalikan ke Hitung Harga untuk revisi, lalu mengisi lagi
        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $p), ['status_lama' => 'menunggu_dp', 'status_baru' => 'hitung_harga'])
            ->assertSessionHas('success');
        $this->sebagai($this->produksi)->post(route('admin.pesanan.harga', $p), ['harga' => $this->harga($p, ['4000', '2000'])])
            ->assertSessionHasNoErrors();
        $this->assertSame(StatusProduksi::MenungguDp, $p->fresh()->status_produksi);
        $this->assertEquals(2200000, (float) $p->fresh()->total);
    }

    public function test_admin_mengubah_harga_kapan_saja_dan_tercatat(): void
    {
        $p = $this->buatBerharga();
        $this->catatDp($p)->assertSessionHasNoErrors();

        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $p), ['harga' => $this->harga($p, ['5000'])])
            ->assertSessionHas('success', 'Harga disimpan.');
        $p->refresh();
        $this->assertSame(StatusProduksi::Proses, $p->status_produksi);
        $this->assertEquals(2700000, (float) $p->total);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $p->id, 'catatan' => 'Harga diubah oleh Rina CS: total Rp 2.350.000 → Rp 2.700.000']);

        // Setelah Hitung Harga, harga tidak boleh dikosongkan; total tidak boleh di bawah DP
        $id = $p->detailPesanan->first()->id;
        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $p), ['harga' => [$id => '']])
            ->assertSessionHasErrors("harga.{$id}");
        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $p), ['harga' => $this->harga($p, ['1', '1'])])
            ->assertSessionHasErrors(['harga' => 'Total tagihan baru (Rp 600) lebih kecil dari DP yang sudah dicatat (Rp 1.000.000).']);
        $this->assertEquals(2700000, (float) $p->fresh()->total);

        // Item SPP lain ditolak
        $lain = $this->buat(['nomor_whatsapp' => '0813 1111 2222']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $p), ['harga' => $this->harga($lain, ['1'])])
            ->assertSessionHasErrors('harga');

        // Edit form oleh admin: harga wajib setelah Hitung Harga, perubahan total tercatat
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), $this->data([
            'nomor_spp' => '176', 'item' => [['harga_satuan' => '']],
        ]))->assertSessionHasErrors('item.0.harga_satuan');
    }

    public function test_edit_admin_mengisi_semua_harga_memindahkan_ke_menunggu_dp(): void
    {
        $p = $this->buat();

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), $this->data([
            'item' => [['harga_satuan' => '1000'], ['harga_satuan' => '500']],
        ]))->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(StatusProduksi::MenungguDp, $p->status_produksi);
        $this->assertEquals(550000, (float) $p->total);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $p->id, 'status_ke' => 'menunggu_dp', 'catatan' => 'Harga dihitung oleh Rina CS']);
    }

    public function test_tombol_status_tidak_bisa_melewati_hitung_harga_atau_catat_dp(): void
    {
        $p = $this->buat();
        $this->sebagai($this->admin)->post(route('admin.pesanan.status', $p), ['status_lama' => 'hitung_harga', 'status_baru' => 'menunggu_dp'])
            ->assertSessionHasErrors('status_baru');

        $q = $this->buatBerharga();
        $this->sebagai($this->admin)->post(route('admin.pesanan.status', $q), ['status_lama' => 'menunggu_dp', 'status_baru' => 'proses'])
            ->assertSessionHasErrors(['status_baru' => 'Gunakan "Catat DP & masukkan ke produksi" untuk memindahkan SPP ke Dalam Proses.']);

        $this->assertSame(StatusProduksi::HitungHarga, $p->fresh()->status_produksi);
        $this->assertSame(StatusProduksi::MenungguDp, $q->fresh()->status_produksi);
    }

    // ── 3. Catat DP & nomor SPP ────────────────────────

    public function test_catat_dp_memasukkan_ke_produksi_dengan_nomor_spp(): void
    {
        $p = $this->buatBerharga();

        $this->catatDp($p, ['dp' => 'Rp 1.000.000', 'tanggal_dp' => '2026-11-19', 'nomor_spp' => 'SPP #176'])
            ->assertRedirect(route('admin.pesanan.show', $p))
            ->assertSessionHas('success', 'DP dicatat. SPP #176 masuk produksi.');

        $p->refresh();
        $this->assertSame(StatusProduksi::Proses, $p->status_produksi);
        $this->assertSame(176, $p->nomor_spp);
        $this->assertEquals(1000000, (float) $p->dp);
        $this->assertSame('2026-11-19', $p->tanggal_dp->toDateString());
        $this->assertSame('dp', $p->status_pembayaran);
        $this->assertSame('SPP #176', $p->kode_tampil);
        $this->assertStringStartsWith('📋 SPP | #176', $p->teks_spp);
        $this->assertStringContainsString('Detail SPP: ' . route('admin.pesanan.show', $p), $p->teks_spp);
        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $p->id, 'status_dari' => 'menunggu_dp', 'status_ke' => 'proses',
            'catatan' => 'DP Rp 1.000.000 via Transfer BCA dicatat (19/11/2026), nomor SPP #176', 'nama_pengguna' => 'Rina CS',
        ]);

        // Tanggal DP kosong = hari ini
        $q = $this->buatBerharga();
        $this->catatDp($q, ['nomor_spp' => '177', 'tanggal_dp' => ''])->assertSessionHasNoErrors();
        $this->assertTrue($q->fresh()->tanggal_dp->isToday());

        // JSON
        $r = $this->buatBerharga();
        $this->sebagai($this->admin)->postJson(route('admin.pesanan.dp', $r), ['dp' => '100000', 'nomor_spp' => '178', 'metode_dp' => 'qris'])
            ->assertOk()->assertJson(['status' => 'proses', 'kode_tampil' => 'SPP #178']);
    }

    public function test_dp_nol_lebih_dari_total_dan_status_salah_ditolak(): void
    {
        $p = $this->buatBerharga();

        $this->catatDp($p, ['dp' => '0'])->assertSessionHasErrors(['dp' => 'DP harus lebih dari Rp 0.']);
        $this->catatDp($p, ['dp' => ''])->assertSessionHasErrors(['dp' => 'DP wajib diisi.']);
        $this->catatDp($p, ['dp' => '2.350.001'])->assertSessionHasErrors(['dp' => 'DP tidak boleh lebih dari total tagihan (Rp 2.350.000).']);
        $this->catatDp($p, ['tanggal_dp' => '2026-11-20'])->assertSessionHasErrors(['tanggal_dp' => 'Tanggal DP tidak boleh setelah hari ini.']);
        $this->catatDp($p, ['dp' => '2.350.000'])->assertSessionHasNoErrors(); // DP = total boleh

        // Belum Menunggu DP (masih hitung harga) / sudah proses
        $hitung = $this->buat();
        $this->catatDp($hitung, ['nomor_spp' => '177'])->assertSessionHasErrors(['status' => 'DP hanya bisa dicatat saat SPP berstatus Menunggu DP (sekarang: Hitung Harga).']);
        $this->catatDp($p->fresh(), ['nomor_spp' => '177'])->assertSessionHasErrors('status');

        // Produksi tidak boleh
        $q = $this->buatBerharga();
        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.dp', $q), ['dp' => '1', 'nomor_spp' => '177'])->assertForbidden();
        $this->assertSame(StatusProduksi::MenungguDp, $q->fresh()->status_produksi);
    }

    public function test_proses_kembali_ke_menunggu_dp_hanya_admin_dan_nomor_tetap(): void
    {
        $p = $this->buatBerharga();
        $this->catatDp($p)->assertSessionHasNoErrors();

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.status', $p), ['status_lama' => 'proses', 'status_baru' => 'menunggu_dp'])
            ->assertForbidden()->assertExactJson(['pesan' => 'Hanya admin yang bisa mengembalikan SPP ke Menunggu DP.']);
        $this->assertSame(StatusProduksi::Proses, $p->fresh()->status_produksi);

        $this->sebagai($this->admin)->post(route('admin.pesanan.status', $p), ['status_lama' => 'proses', 'status_baru' => 'menunggu_dp'])
            ->assertSessionHas('success');
        $p->refresh();
        $this->assertSame(StatusProduksi::MenungguDp, $p->status_produksi);
        $this->assertSame(176, $p->nomor_spp);
        $this->assertSame('SPP #176', $p->kode_tampil);

        // Catat DP lagi: nomor sendiri tidak dianggap terpakai/loncat; saran = nomor lama
        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $p))->assertViewHas('saranNomor', 176);
        $this->catatDp($p, ['dp' => '500000'])->assertSessionHasNoErrors();
        $this->assertSame(StatusProduksi::Proses, $p->fresh()->status_produksi);
        $this->assertEquals(500000, (float) $p->fresh()->dp);
    }

    // ── 4. Tidak jadi & pulihkan ───────────────────────

    public function test_tidak_jadi_dan_pulihkan_khusus_admin(): void
    {
        $p = $this->buat(); // hitung_harga

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.tidak_jadi', $p), ['status_lama' => 'hitung_harga'])
            ->assertForbidden();

        $this->sebagai($this->admin)->post(route('admin.pesanan.tidak_jadi', $p), ['status_lama' => 'hitung_harga', 'catatan' => 'Harga kemahalan'])
            ->assertSessionHas('success', "PNW-{$p->id} ditandai tidak jadi.");
        $p->refresh();
        $this->assertSame(StatusProduksi::TidakJadi, $p->status_produksi);
        $this->assertNotNull($p->selesai_pada);
        $this->assertSame('tidak_jadi', $p->riwayat->first()->jenis);
        $this->assertSame('Harga kemahalan', $p->riwayat->first()->catatan);

        // Masuk History
        $history = $this->sebagai($this->admin)->get(route('admin.pesanan.index', ['tab' => 'history']))->viewData('pesanan');
        $this->assertSame([$p->id], $history->pluck('id')->all());

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.pulihkan', $p))->assertForbidden();
        $this->sebagai($this->admin)->post(route('admin.pesanan.pulihkan', $p))
            ->assertSessionHas('success', 'SPP dipulihkan ke Hitung Harga.');
        $p->refresh();
        $this->assertSame(StatusProduksi::HitungHarga, $p->status_produksi);
        $this->assertNull($p->selesai_pada);
        $this->assertSame('pulihkan', $p->riwayat->first()->jenis);

        // Dari Menunggu DP → pulih ke Menunggu DP
        $q = $this->buatBerharga();
        $this->sebagai($this->admin)->post(route('admin.pesanan.tidak_jadi', $q), ['status_lama' => 'menunggu_dp'])->assertSessionHas('success');
        $this->sebagai($this->admin)->post(route('admin.pesanan.pulihkan', $q))->assertSessionHas('success', 'SPP dipulihkan ke Menunggu DP.');
    }

    public function test_tidak_jadi_hanya_sebelum_produksi_dan_batal_hanya_setelahnya(): void
    {
        $p = $this->buatBerharga();
        $this->catatDp($p)->assertSessionHasNoErrors();

        $this->sebagai($this->admin)->post(route('admin.pesanan.tidak_jadi', $p), ['status_lama' => 'proses'])
            ->assertSessionHasErrors('status_lama');

        $q = $this->buatBerharga();
        $this->sebagai($this->admin)->post(route('admin.pesanan.batal', $q), ['status_lama' => 'menunggu_dp'])
            ->assertSessionHasErrors(['status_lama' => 'SPP yang belum masuk produksi ditandai "Tidak Jadi", bukan dibatalkan.']);

        // Bentrok: layar masih Hitung Harga padahal sudah Menunggu DP
        $this->sebagai($this->admin)->postJson(route('admin.pesanan.tidak_jadi', $q), ['status_lama' => 'hitung_harga'])
            ->assertStatus(409)->assertJson(['bentrok' => true, 'status_sekarang' => 'menunggu_dp']);

        // Pulihkan hanya untuk tidak_jadi / dibatalkan
        $this->sebagai($this->admin)->post(route('admin.pesanan.pulihkan', $q))->assertSessionHasErrors('status_lama');
    }

    // ── 5. Pengiriman dikunci sampai lunas ─────────────

    public function test_pengiriman_dikunci_sampai_lunas(): void
    {
        $p = Pesanan::factory()->status('selesai_produksi')->create(['total' => 1000000, 'dp' => 500000]);
        $this->assertTrue($p->menunggu_pelunasan);

        $kirim = ['status_lama' => 'selesai_produksi', 'status_baru' => 'pengiriman'];

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $p), $kirim)
            ->assertRedirect(route('admin.pesanan.show', $p))
            ->assertSessionHas('error', 'Belum lunas — minta admin mencatat pelunasan dulu.');
        $this->sebagai($this->admin)->postJson(route('admin.pesanan.status', $p), $kirim)
            ->assertStatus(422)->assertExactJson(['pesan' => 'Belum lunas — minta admin mencatat pelunasan dulu.']);
        $this->assertSame(StatusProduksi::SelesaiProduksi, $p->fresh()->status_produksi);
        $this->assertSame(0, $p->riwayat()->count());

        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'cash'])->assertSessionHasNoErrors();
        $this->assertFalse($p->fresh()->menunggu_pelunasan);

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $p), $kirim)->assertSessionHas('success');
        $this->assertSame(StatusProduksi::Pengiriman, $p->fresh()->status_produksi);

        // Mundur dari pengiriman tetap boleh
        $this->assertFalse(Pesanan::factory()->status('proses')->make()->menunggu_pelunasan);
    }

    // ── 6. Migrasi antrian → proses ────────────────────

    public function test_migrasi_antrian_menjadi_proses_dan_riwayat_lama_tetap_terbaca(): void
    {
        $p = Pesanan::factory()->create();
        DB::table('pesanan')->where('id', $p->id)->update(['status_produksi' => 'antrian']);
        $dibuat = PesananRiwayat::create(['pesanan_id' => $p->id, 'admin_id' => $this->admin->id, 'nama_pengguna' => 'X', 'status_ke' => 'antrian', 'catatan' => 'SPP dibuat']);
        $maju   = PesananRiwayat::create(['pesanan_id' => $p->id, 'admin_id' => $this->admin->id, 'nama_pengguna' => 'X', 'status_dari' => 'antrian', 'status_ke' => 'proses']);
        $mundur = PesananRiwayat::create(['pesanan_id' => $p->id, 'admin_id' => $this->admin->id, 'nama_pengguna' => 'X', 'status_dari' => 'proses', 'status_ke' => 'antrian']);

        $migrasi = require database_path('migrations/2026_11_19_000001_alur_hitung_harga_dan_dp.php');
        $this->assertSame(1, $migrasi->pindahkanAntrian());

        $this->assertSame(StatusProduksi::Proses, $p->fresh()->status_produksi);
        $this->assertDatabaseHas('pesanan_riwayat', ['id' => $maju->id, 'status_dari' => 'antrian']); // riwayat tidak diubah

        $this->assertSame('Antrian Produksi', $dibuat->fresh()->status_ke->label());
        $this->assertSame('dibuat', $dibuat->fresh()->jenis);
        $this->assertSame('maju', $maju->fresh()->jenis);
        $this->assertSame('mundur', $mundur->fresh()->jenis);
        $this->assertArrayNotHasKey('antrian', StatusProduksi::pilihan());
        $this->assertNotContains('antrian', StatusProduksi::ALUR);

        // Detail dengan riwayat lama tetap tampil
        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $p))->assertOk()->assertSee('Antrian Produksi');
    }

    // ── 7. Kode PNW & pencarian ────────────────────────

    public function test_pencarian_dengan_kode_penawaran(): void
    {
        $p = $this->buat();
        $lain = $this->buat(['nomor_whatsapp' => '0813 1111 2222', 'nama_customer' => 'Budi']);

        $cari = fn ($q) => $this->sebagai($this->produksi)->get(route('admin.pesanan.index', ['q' => $q]))
            ->viewData('pesanan')->pluck('id')->all();

        foreach (["PNW-{$p->id}", "pnw{$p->id}", "PNW {$p->id}", " pnw-{$p->id} "] as $q) {
            $this->assertSame([$p->id], $cari($q), $q);
        }
        $this->assertSame([$lain->id], $cari("PNW-{$lain->id}"));
        $this->assertSame([], $cari('PNW-999999'));
        $this->assertSame($p->id, Pesanan::idKodePenawaran("pnw-{$p->id}"));
        $this->assertNull(Pesanan::idKodePenawaran('PNWX'));
    }

    // ── 8. Notifikasi deadline ─────────────────────────

    public function test_notifikasi_deadline_untuk_status_yang_dipantau(): void
    {
        $f = fn (string $status, int $hari, array $lain = []) => Pesanan::factory()->status($status)
            ->create(['deadline' => today()->addDays($hari), ...$lain]);

        $lewat2   = $f('proses', -2, ['nama_customer' => 'Lewat Dua', 'nama_brand' => 'Alea']);
        $hitung   = $f('hitung_harga', 0);
        $dp       = $f('menunggu_dp', 3);
        $selesaiP = $f('selesai_produksi', -1);
        $f('proses', 4);            // > 3 hari: tidak
        $f('pengiriman', -5);       // sudah dikirim: tidak
        $f('selesai', -5);          // History: tidak
        $f('tidak_jadi', -5);
        $f('dibatalkan', -5);
        $f('proses', -9)->delete(); // terhapus: tidak

        $json = $this->sebagai($this->produksi)->getJson(route('admin.pesanan.jumlah'))->assertOk()->json('deadline');

        $this->assertSame(4, $json['total']);
        $this->assertSame(2, $json['lewat']);
        $this->assertSame(2, $json['mendekat']);
        $this->assertSame([$lewat2->id, $selesaiP->id, $hitung->id, $dp->id], array_column($json['item'], 'id'));

        $this->assertSame([
            'id'                   => $lewat2->id,
            'kode_tampil'          => $lewat2->kode_tampil,
            'nama_customer'        => 'Lewat Dua',
            'nama_brand'           => 'Alea',
            'status'               => 'proses',
            'label_status'         => 'Dalam Proses',
            'deadline'             => '2026-11-17',
            'deadline_teks'        => '17 nov kirim',
            'penanda'              => 'lewat',
            'hari_menuju_deadline' => -2,
            'url'                  => route('admin.pesanan.show', $lewat2->id),
        ], $json['item'][0]);
        $this->assertSame('mendekat', $json['item'][2]['penanda']);
        $this->assertSame("PNW-{$hitung->id}", $json['item'][2]['kode_tampil']);
        $this->assertSame('Hitung Harga', $json['item'][2]['label_status']);

        // Variabel layout untuk admin & produksi
        // Variabel layout (view composer) untuk admin & produksi
        $tertangkap = null;
        View::composer('admin.layouts.pesanan', function ($view) use (&$tertangkap) {
            $tertangkap = $view->getData()['notifDeadline'] ?? null;
        });
        foreach ([$this->admin, $this->produksi] as $pengguna) {
            $tertangkap = null;
            $this->sebagai($pengguna)->get(route('admin.pesanan.index'))->assertOk();
            $this->assertSame($json, $tertangkap);
        }
    }

    public function test_notifikasi_deadline_maksimal_20_item_tetapi_total_lengkap(): void
    {
        Pesanan::factory()->count(22)->status('proses')->create(['deadline' => today()]);

        $json = $this->sebagai($this->admin)->getJson(route('admin.pesanan.jumlah'))->json('deadline');
        $this->assertSame(22, $json['total']);
        $this->assertCount(20, $json['item']);
    }

    // ── 9. Galat panel katalog ─────────────────────────

    public function test_404_di_rute_katalog_kembali_ke_index_katalognya(): void
    {
        $kasus = [
            '/admin/produk/999999/edit'          => 'admin.produk.index',
            '/admin/produk/999999/kelola'        => 'admin.produk.index',
            '/admin/kategori-produk/999999/edit' => 'admin.kategori-produk.index',
            '/admin/banner/999999/edit'          => 'admin.banner.index',
            '/admin/bahan/999999/edit'           => 'admin.bahan.index',
            '/admin/finishing/999999/edit'       => 'admin.finishing.index',
            '/admin/cetak/999999/edit'           => 'admin.cetak.index',
        ];

        foreach ($kasus as $url => $rute) {
            $this->sebagai($this->admin)->get($url)
                ->assertRedirect(route($rute))->assertSessionHas('error', 'Halaman atau data tidak ditemukan.');
        }

        $this->sebagai($this->admin)->getJson('/admin/banner/999999/edit')->assertNotFound()
            ->assertExactJson(['pesan' => 'Halaman atau data tidak ditemukan.']);

        // Pesanan tetap ke daftar SPP
        $this->sebagai($this->admin)->get('/admin/pesanan/999999')->assertRedirect(route('admin.pesanan.index'));

        // Produksi (tidak punya akses katalog) diarahkan ke daftar SPP
        $this->sebagai($this->produksi)->get('/admin/produk/999999/edit')->assertRedirect(route('admin.pesanan.index'));
    }

    public function test_form_katalog_403_kembali_dengan_pesan_bukan_json(): void
    {
        $banner = Banner::create(['judul' => 'Promo', 'gambar' => 'banner/x.webp', 'aktif' => true, 'urutan' => 1]);

        $this->sebagai($this->produksi)->from('/admin/pesanan')->delete(route('admin.banner.destroy', $banner))
            ->assertRedirect('/admin/pesanan')->assertSessionHas('error', 'Anda tidak punya akses ke halaman tersebut.');
        $this->sebagai($this->produksi)->deleteJson(route('admin.banner.destroy', $banner))
            ->assertForbidden()->assertExactJson(['pesan' => 'Anda tidak punya akses ke halaman tersebut.']);
        $this->assertDatabaseHas('banner', ['id' => $banner->id]);
    }
}

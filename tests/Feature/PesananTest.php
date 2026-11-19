<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bahan;
use App\Models\KategoriProduk;
use App\Models\Pesanan;
use App\Models\PesananFile;
use App\Services\PesananService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Modul Pesanan: simpan order, nomor, pembayaran, tab/cari, status, file privat, migrasi.
 */
class PesananTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Admin $produksi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = Admin::factory()->create(['nama' => 'Rina CS']);
        $this->produksi = Admin::factory()->produksi()->create(['nama' => 'Ayah']);
    }

    private function sebagai(Admin $pengguna): static
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_id' => $pengguna->id]);
    }

    private function dataOrder(array $ubah = []): array
    {
        return array_replace_recursive([
            'jenis_deadline' => 'kirim',
            'nama_customer'  => 'Bu Rina Susanti',
            'nomor_whatsapp' => '0812-3456-7890',
            'alamat'         => 'Jl. Melati 5',
            'kota'           => 'Yogyakarta',
            'tanggal_order'  => today()->toDateString(),
            'deadline'       => today()->addDays(7)->toDateString(),
            'link_desain'    => 'https://drive.google.com/drive/folders/abc',
            'status_desain'  => 'menunggu',
            'item'           => [
                [
                    'jenis_packaging' => 'Paperbag', 'panjang' => '25', 'lebar' => '10', 'tinggi' => '30',
                    'jenis_bahan' => 'Kraft', 'gramasi' => '150 gsm', 'jenis_cetak' => 'Sablon', 'warna_cetak' => '1 warna',
                    'finishing' => ['Laminasi doff', 'Hot print'], 'finishing_lainnya' => 'Emboss',
                    'aksesori' => 'Tali kur', 'jumlah' => '500', 'harga_satuan' => 'Rp 2.500',
                    'subtotal' => '1', // harus diabaikan server
                ],
                [
                    'jenis_packaging' => Pesanan::LAINNYA, 'jenis_packaging_lainnya' => 'Dus custom',
                    'jenis_bahan' => 'Ivory', 'jumlah' => '1000', 'harga_satuan' => '3000',
                ],
            ],
        ], $ubah);
    }

    /** Data form edit: nomor SPP tetap milik pesanan itu kecuali diubah. */
    private function dataEdit(Pesanan $pesanan, array $ubah = []): array
    {
        return array_replace_recursive($this->dataOrder(['nomor_spp' => (string) $pesanan->nomor_spp]), $ubah);
    }

    /** Catat DP & masukkan ke produksi (admin). */
    private function masukProduksi(Pesanan $pesanan, int $nomor = 1, int $dp = 500000): Pesanan
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $pesanan), [
            'dp' => (string) $dp, 'nomor_spp' => (string) $nomor, 'konfirmasi_nomor' => '1',
        ])->assertSessionHasNoErrors();

        return $pesanan->fresh();
    }

    private function buatOrder(array $ubah = []): Pesanan
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->dataOrder($ubah))->assertRedirect();

        return Pesanan::latest('id')->firstOrFail();
    }

    // ── Simpan & hitung ────────────────────────────────

    public function test_admin_menyimpan_order_banyak_item_total_dihitung_server(): void
    {
        $pesanan = $this->buatOrder(['total' => '1']);

        $this->assertSame('6281234567890', $pesanan->nomor_whatsapp);
        $this->assertEquals(4250000, (float) $pesanan->total); // 500×2.500 + 1.000×3.000
        $this->assertEquals(0, (float) $pesanan->dp);
        $this->assertNull($pesanan->nomor_spp);
        // Semua harga sudah diisi admin saat input → langsung Menunggu DP
        $this->assertSame('menunggu_dp', $pesanan->status_produksi->value);
        $this->assertSame($this->admin->id, $pesanan->dibuat_oleh);

        $item = $pesanan->detailPesanan;
        $this->assertCount(2, $item);
        $this->assertEquals(1250000, (float) $item[0]->subtotal);
        $this->assertSame('Laminasi doff, Hot print, Emboss', $item[0]->finishing);
        $this->assertSame('25 × 10 × 30 cm', $item[0]->ukuran_teks);
        $this->assertSame('Dus custom', $item[1]->jenis_packaging);

        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $pesanan->id, 'status_dari' => null, 'status_ke' => 'menunggu_dp',
            'catatan' => 'SPP dibuat', 'nama_pengguna' => 'Rina CS',
        ]);
    }

    public function test_form_tambah_menyediakan_data_master(): void
    {
        KategoriProduk::factory()->create(['nama_kategori' => 'Paperbag Kraft']);
        Bahan::create(['nama_bahan' => 'Art Carton']);

        $this->sebagai($this->admin)->get(route('admin.pesanan.create'))
            ->assertOk()
            ->assertViewHas('masterPackaging', ['Paperbag Kraft'])
            ->assertViewHas('masterBahan', ['Art Carton'])
            ->assertSee('Paperbag Kraft');
    }

    public function test_validasi_order_bahasa_indonesia(): void
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->dataOrder([
            'deadline'       => today()->subDay()->toDateString(),
            'nomor_whatsapp' => '12',
            'link_desain'    => 'https://contoh.com/desain',
            'item'           => [1 => ['jenis_packaging' => Pesanan::LAINNYA, 'jenis_packaging_lainnya' => '', 'jumlah' => '0']],
        ]))->assertSessionHasErrors([
            'deadline' => 'Deadline tidak boleh sebelum tanggal SPP.',
            'link_desain',
            'item.1.jenis_packaging_lainnya' => 'Tulis jenis packaging lainnya.',
            'item.1.jumlah' => 'Jumlah item ke-2 minimal 1.',
        ]);

        $tanpaItem = $this->dataOrder();
        $tanpaItem['item'] = [];
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $tanpaItem)
            ->assertSessionHasErrors(['item' => 'Tambahkan minimal satu item pesanan.']);

        $this->assertDatabaseCount('pesanan', 0);
    }

    public function test_dp_lunas_dan_nomor_diabaikan_saat_tambah_dan_wa_divalidasi(): void
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->dataOrder(['nomor_whatsapp' => '0812']))
            ->assertSessionHasErrors('nomor_whatsapp');
        $this->assertDatabaseCount('pesanan', 0);

        // DP, pelunasan, dan nomor SPP dicatat belakangan (Catat DP / Catat lunas)
        $pesanan = $this->buatOrder(['dp' => '9.999.999', 'tanggal_lunas' => today()->toDateString(), 'nomor_spp' => '176']);
        $this->assertEquals(0, (float) $pesanan->dp);
        $this->assertNull($pesanan->tanggal_lunas);
        $this->assertNull($pesanan->nomor_spp);
    }

    public function test_edit_mengganti_item_dan_menghitung_ulang_total(): void
    {
        $pesanan = $this->buatOrder();

        $data = $this->dataEdit($pesanan);
        $data['item'] = [['jenis_packaging' => 'Box', 'jenis_bahan' => 'Duplex', 'jumlah' => '10', 'harga_satuan' => '1000']];
        $data['dp'] = '0';

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $data)
            ->assertRedirect(route('admin.pesanan.show', $pesanan));

        $pesanan->refresh();
        $this->assertEquals(10000, (float) $pesanan->total);
        $this->assertCount(1, $pesanan->detailPesanan);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $pesanan->id, 'catatan' => 'SPP diubah']);

        $this->sebagai($this->admin)->get(route('admin.pesanan.edit', $pesanan))->assertOk()->assertSee('Box');
    }

    // ── Pembayaran ─────────────────────────────────────

    public function test_status_bayar_dan_sisa_dihitung(): void
    {
        $p = Pesanan::factory()->make(['total' => 1000000, 'dp' => 0]);
        $this->assertSame('belum_dp', $p->status_pembayaran);
        $this->assertSame('Belum DP', $p->label_status_pembayaran);
        $this->assertEquals(1000000, $p->sisa);

        $p->dp = 400000;
        $this->assertSame('dp', $p->status_pembayaran);
        $this->assertSame('DP Masuk', $p->label_status_pembayaran);
        $this->assertEquals(600000, $p->sisa);

        $p->dp = 1000000; // dp = total tetapi belum dicatat lunas
        $this->assertSame('dp', $p->status_pembayaran);
        $this->assertEquals(0, $p->sisa);

        $p->tanggal_lunas = today();
        $this->assertSame('lunas', $p->status_pembayaran);
        $this->assertSame('Lunas', $p->label_status_pembayaran);
        $this->assertEquals(0, $p->sisa);
    }

    public function test_admin_mencatat_lunas_tanpa_mengubah_status_produksi(): void
    {
        $pesanan = $this->buatOrder();

        // Belum masuk produksi: pelunasan belum bisa dicatat
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $pesanan))
            ->assertSessionHasErrors(['tanggal_lunas' => 'Pelunasan dicatat setelah DP dicatat dan SPP masuk produksi.']);

        $pesanan = $this->masukProduksi($pesanan);
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $pesanan))->assertRedirect();

        $pesanan->refresh();
        $this->assertSame('lunas', $pesanan->status_pembayaran);
        $this->assertTrue($pesanan->tanggal_lunas->isToday());
        $this->assertSame('proses', $pesanan->status_produksi->value);

        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $pesanan), ['tanggal_lunas' => today()->addDay()->toDateString()])
            ->assertSessionHasErrors('tanggal_lunas');
    }

    // ── Deadline ───────────────────────────────────────

    public function test_penanda_deadline(): void
    {
        $this->assertSame('lewat', Pesanan::factory()->make(['deadline' => today()->subDay()])->penanda_deadline);
        $this->assertSame('mendekat', Pesanan::factory()->make(['deadline' => today()])->penanda_deadline);
        $this->assertSame('mendekat', Pesanan::factory()->make(['deadline' => today()->addDays(3)])->penanda_deadline);
        $this->assertNull(Pesanan::factory()->make(['deadline' => today()->addDays(4)])->penanda_deadline);
        $this->assertSame('lewat', Pesanan::factory()->status('proses')->make(['deadline' => today()->subDays(2)])->penanda_deadline);
        $this->assertSame(-2, Pesanan::factory()->make(['deadline' => today()->subDays(2)])->hari_menuju_deadline);

        // Dipantau: hitung harga, menunggu DP, proses, selesai produksi
        foreach (['hitung_harga', 'menunggu_dp', 'proses', 'selesai_produksi'] as $s) {
            $this->assertSame('lewat', Pesanan::factory()->status($s)->make(['deadline' => today()->subDay()])->penanda_deadline);
        }

        // Sudah dikirim / History: tidak diberi penanda
        foreach (['pengiriman', 'selesai', 'tidak_jadi', 'dibatalkan'] as $s) {
            $this->assertNull(Pesanan::factory()->status($s)->make(['deadline' => today()->subDays(5)])->penanda_deadline);
        }
    }

    // ── Tab, pencarian, jumlah ─────────────────────────

    public function test_tab_semua_hanya_order_aktif_dan_history_terpisah(): void
    {
        $hitung  = Pesanan::factory()->status('hitung_harga')->create(['deadline' => today()->addDays(6)]);
        $dp      = Pesanan::factory()->status('menunggu_dp')->create(['deadline' => today()->addDays(5)]);
        $proses  = Pesanan::factory()->status('proses')->create(['deadline' => today()->addDay()]);
        $kirim   = Pesanan::factory()->status('pengiriman')->create();
        $selesai = Pesanan::factory()->status('selesai')->create(['selesai_pada' => now()->subDays(3)]);
        $tidak   = Pesanan::factory()->status('tidak_jadi')->create(['selesai_pada' => now()->subDays(2)]);
        $batal   = Pesanan::factory()->status('dibatalkan')->create(['selesai_pada' => now()->subDay()]);

        $ids = fn ($res) => $res->viewData('pesanan')->pluck('id')->all();
        $tab = fn ($t) => $ids($this->sebagai($this->produksi)->get(route('admin.pesanan.index', ['tab' => $t])));

        $semua = $this->sebagai($this->produksi)->get(route('admin.pesanan.index'));
        $this->assertSame([$proses->id, $dp->id, $hitung->id, $kirim->id], $ids($semua)); // urut deadline terdekat

        $this->assertSame([$hitung->id], $tab('hitung_harga'));
        $this->assertSame([$dp->id], $tab('menunggu_dp'));
        $this->assertSame([$proses->id], $tab('proses'));
        $this->assertSame([$batal->id, $tidak->id, $selesai->id], $tab('history'));
        $this->assertSame(['semua', 'hitung_harga', 'menunggu_dp', 'proses', 'selesai_produksi', 'pengiriman', 'history'], array_keys(Pesanan::TAB));

        $jumlah = $this->sebagai($this->produksi)->getJson(route('admin.pesanan.jumlah'))->assertOk()->json();
        $this->assertSame([
            'semua' => 4, 'hitung_harga' => 1, 'menunggu_dp' => 1, 'proses' => 1, 'selesai_produksi' => 0, 'pengiriman' => 1, 'history' => 3,
        ], $jumlah['jumlah']);
        $this->assertNotEmpty($jumlah['penanda']);
        $this->assertNotEmpty($jumlah['terakhir_diubah']);
    }

    public function test_pencarian_menjangkau_semua_order_termasuk_history(): void
    {
        $budi    = Pesanan::factory()->create(['nama_customer' => 'Budi Santoso']);
        $budiLam = Pesanan::factory()->status('selesai')->create(['nama_customer' => 'Pak Budi']);
        Pesanan::factory()->create(['nama_customer' => 'Sari']);
        $nomor   = Pesanan::factory()->status('selesai')->create(['nomor_spp' => 42, 'nama_customer' => 'Andi']);
        $brand   = Pesanan::factory()->create(['nama_customer' => 'Ismun', 'nama_brand' => 'Paperbag Alea']);
        Pesanan::factory()->create(['nama_customer' => 'Persen 100%']);

        $cari = fn ($q, $tab = 'proses') => $this->sebagai($this->admin)
            ->get(route('admin.pesanan.index', ['q' => $q, 'tab' => $tab]))
            ->viewData('pesanan')->pluck('id')->sort()->values()->all();

        $this->assertSame([$budi->id, $budiLam->id], $cari('budi'));
        $this->assertSame([$nomor->id], $cari('42', 'history'));
        $this->assertSame([$nomor->id], $cari('#42'));
        $this->assertSame([$nomor->id], $cari('SPP #42'));
        $this->assertSame([$brand->id], $cari('alea'));
        $this->assertSame([], $cari('_')); // wildcard di-escape
    }

    public function test_penanda_jumlah_berubah_saat_status_berubah(): void
    {
        $pesanan = Pesanan::factory()->create();
        $sebelum = $this->sebagai($this->admin)->getJson(route('admin.pesanan.jumlah'))->json('penanda');

        $this->travel(5)->seconds();
        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'proses', 'status_baru' => 'selesai_produksi']);

        $sesudah = $this->sebagai($this->admin)->getJson(route('admin.pesanan.jumlah'))->json('penanda');
        $this->assertNotSame($sebelum, $sesudah);
    }

    public function test_paginasi_20_per_halaman(): void
    {
        Pesanan::factory()->count(21)->create();

        $res = $this->sebagai($this->admin)->get(route('admin.pesanan.index'));
        $this->assertCount(20, $res->viewData('pesanan'));
        $this->assertSame(21, $res->viewData('pesanan')->total());
    }

    // ── Status produksi ────────────────────────────────

    public function test_produksi_maju_dan_mundur_satu_langkah_tercatat_di_riwayat(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), [
            'status_lama' => 'proses', 'status_baru' => 'selesai_produksi', 'catatan' => 'Cetak selesai',
        ])->assertRedirect(route('admin.pesanan.show', $pesanan))->assertSessionHas('success', 'Status diubah ke Pesanan Selesai.');

        $this->assertSame('selesai_produksi', $pesanan->fresh()->status_produksi->value);
        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $pesanan->id, 'admin_id' => $this->produksi->id, 'nama_pengguna' => 'Ayah',
            'status_dari' => 'proses', 'status_ke' => 'selesai_produksi', 'catatan' => 'Cetak selesai',
        ]);

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), [
            'status_lama' => 'selesai_produksi', 'status_baru' => 'proses',
        ])->assertSessionHas('success');
        $this->assertSame('proses', $pesanan->fresh()->status_produksi->value);
        $this->assertSame('mundur', $pesanan->riwayat()->first()->jenis);
    }

    public function test_status_tidak_boleh_lompat_dua_langkah_atau_ke_dibatalkan(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), [
            'status_lama' => 'proses', 'status_baru' => 'pengiriman',
        ])->assertSessionHasErrors(['status_baru' => 'Status hanya bisa dipindah satu langkah maju atau mundur.']);

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), [
            'status_lama' => 'proses', 'status_baru' => 'dibatalkan',
        ])->assertSessionHasErrors('status_baru');

        // Status lama "antrian" sudah bukan bagian alur
        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), [
            'status_lama' => 'antrian', 'status_baru' => 'proses',
        ])->assertSessionHasErrors('status_lama');

        $this->assertSame('proses', $pesanan->fresh()->status_produksi->value);
    }

    public function test_selesai_mengisi_selesai_pada_dan_mundur_mengosongkannya(): void
    {
        $pesanan = Pesanan::factory()->status('pengiriman')->create();

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'pengiriman', 'status_baru' => 'selesai']);
        $this->assertNotNull($pesanan->fresh()->selesai_pada);

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'selesai', 'status_baru' => 'pengiriman']);
        $this->assertNull($pesanan->fresh()->selesai_pada);
    }

    public function test_bentrok_status_ditolak_dengan_pesan_jelas(): void
    {
        $pesanan = Pesanan::factory()->create();

        // Perangkat 1 (Mamah) memajukan status
        $mamah = Admin::factory()->produksi()->create(['nama' => 'Mamah']);
        $this->sebagai($mamah)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'proses', 'status_baru' => 'selesai_produksi']);

        // Perangkat 2 (Ayah) masih melihat "proses" lalu menekan tombol yang sama
        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'proses', 'status_baru' => 'selesai_produksi'])
            ->assertRedirect(route('admin.pesanan.show', $pesanan))
            ->assertSessionHas('error', 'Status SPP ini sudah diubah oleh Mamah menjadi Pesanan Selesai. Tampilan sudah diperbarui.')
            ->assertSessionHas('bentrok', true);

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.status', $pesanan), ['status_lama' => 'proses', 'status_baru' => 'selesai_produksi'])
            ->assertStatus(409)
            ->assertJson(['bentrok' => true, 'status_sekarang' => 'selesai_produksi']);

        // Hanya perubahan pertama yang tercatat (factory tidak membuat riwayat "Order dibuat")
        $this->assertSame(1, $pesanan->riwayat()->count());
    }

    public function test_status_lewat_json(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.status', $pesanan), ['status_lama' => 'proses', 'status_baru' => 'selesai_produksi'])
            ->assertOk()
            ->assertJson(['status' => 'selesai_produksi', 'label_status' => 'Pesanan Selesai', 'kode_tampil' => $pesanan->kode_tampil]);
    }

    public function test_admin_batal_dan_pulihkan_ke_status_sebelumnya(): void
    {
        $pesanan = Pesanan::factory()->status('proses')->create();

        $this->sebagai($this->admin)->post(route('admin.pesanan.batal', $pesanan), ['status_lama' => 'proses', 'catatan' => 'Customer batal'])
            ->assertSessionHas('success');
        $pesanan->refresh();
        $this->assertSame('dibatalkan', $pesanan->status_produksi->value);
        $this->assertNotNull($pesanan->selesai_pada);

        // Produksi tidak boleh memulihkan, dan tidak bisa memakai tombol maju/mundur dari dibatalkan
        $this->sebagai($this->produksi)->post(route('admin.pesanan.pulihkan', $pesanan))->assertRedirect()->assertSessionHas('error', 'Aksi ini khusus admin.');
        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.pulihkan', $pesanan))->assertForbidden();
        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'dibatalkan', 'status_baru' => 'proses'])
            ->assertSessionHasErrors('status_lama');

        $this->sebagai($this->admin)->post(route('admin.pesanan.pulihkan', $pesanan))
            ->assertSessionHas('success', 'SPP dipulihkan ke Dalam Proses.');
        $pesanan->refresh();
        $this->assertSame('proses', $pesanan->status_produksi->value);
        $this->assertNull($pesanan->selesai_pada);
        $this->assertSame(['pulihkan', 'batal'], $pesanan->riwayat->pluck('jenis')->all());
    }

    public function test_order_selesai_tidak_bisa_dibatalkan(): void
    {
        $pesanan = Pesanan::factory()->status('selesai')->create();

        $this->sebagai($this->admin)->post(route('admin.pesanan.batal', $pesanan), ['status_lama' => 'selesai'])
            ->assertSessionHasErrors('status_lama');
        $this->assertSame('selesai', $pesanan->fresh()->status_produksi->value);
    }

    public function test_catatan_progres_oleh_produksi(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->produksi)->post(route('admin.pesanan.catatan', $pesanan), ['catatan' => 'Material sudah disiapkan'])
            ->assertSessionHas('success', 'Catatan disimpan.');

        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $pesanan->id, 'status_ke' => null, 'catatan' => 'Material sudah disiapkan', 'nama_pengguna' => 'Ayah',
        ]);

        $this->sebagai($this->produksi)->post(route('admin.pesanan.catatan', $pesanan), ['catatan' => ''])
            ->assertSessionHasErrors(['catatan' => 'Tulis catatan progres dulu.']);

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.catatan', $pesanan), ['catatan' => 'Barang masuk QC'])
            ->assertCreated()->assertJsonPath('riwayat.jenis', 'catatan');
    }

    public function test_detail_order_tampil_untuk_admin(): void
    {
        $pesanan = $this->masukProduksi($this->buatOrder(), 12);

        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $pesanan))
            ->assertOk()
            ->assertSee('SPP #12')
            ->assertSee('Pindahkan ke Pesanan Selesai')
            ->assertSee('Batalkan SPP')
            ->assertViewHas('statusBerikutnya', \App\Enums\StatusProduksi::SelesaiProduksi)
            ->assertViewHas('statusSebelumnya', \App\Enums\StatusProduksi::MenungguDp);

        // Produksi tidak punya tombol mundur dari Dalam Proses ke Menunggu DP
        $this->sebagai($this->produksi)->get(route('admin.pesanan.show', $pesanan))
            ->assertOk()->assertViewHas('statusSebelumnya', null);

        // Sebelum produksi: tidak ada tombol maju manual, kode PNW
        $baru = $this->buatOrder(['nomor_whatsapp' => '0813 5555 6666']);
        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $baru))
            ->assertOk()
            ->assertSee('PNW-' . $baru->id)
            ->assertViewHas('statusBerikutnya', null)
            ->assertViewHas('bisaCatatDp', true)
            ->assertViewHas('bisaTidakJadi', true)
            ->assertViewHas('teksSpp', null);
    }

    public function test_hapus_order_soft_delete_khusus_admin(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->admin)->delete(route('admin.pesanan.destroy', $pesanan))->assertRedirect(route('admin.pesanan.index'));

        $this->assertSoftDeleted($pesanan);
        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $pesanan))
            ->assertRedirect(route('admin.pesanan.index'))
            ->assertSessionHas('error', "{$pesanan->nomor_spp_teks} sudah dihapus oleh admin.");
        $this->assertSame(0, $this->sebagai($this->admin)->getJson(route('admin.pesanan.jumlah'))->json('jumlah.semua'));
    }

    // ── File privat ────────────────────────────────────

    public function test_upload_mockup_disimpan_privat_dan_hanya_bisa_dilihat_setelah_login(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $pesanan = $this->buatOrder([
            'mockup'    => [UploadedFile::fake()->image('desain final.jpg', 300, 200)],
            'referensi' => [UploadedFile::fake()->image('contoh.png', 100, 100)],
        ]);

        $this->assertCount(2, $pesanan->file);
        $file = $pesanan->file->firstWhere('jenis.value', 'mockup');
        $this->assertSame('desain final.jpg', $file->nama_asli);
        $this->assertStringStartsWith("pesanan/{$pesanan->id}/", $file->path);
        $this->assertStringEndsWith('.webp', $file->path);
        Storage::disk('local')->assertExists($file->path);
        Storage::disk('public')->assertMissing($file->path);
        $this->assertSame([], Storage::disk('local')->files(PesananService::FOLDER_SEMENTARA));

        // Produksi boleh melihat
        $this->sebagai($this->produksi)->get(route('admin.pesanan.file.lihat', [$pesanan, $file]))
            ->assertOk()->assertHeader('Content-Type', 'image/webp');

        // Tamu tidak
        $this->flushSession();
        $this->get(route('admin.pesanan.file.lihat', [$pesanan, $file]))->assertRedirect(route('admin.login'));

        // File order lain lewat URL order ini → tidak ditemukan (redirect ramah, bukan 404 publik)
        $lain = Pesanan::factory()->create();
        $this->sebagai($this->admin)->get(route('admin.pesanan.file.lihat', [$lain, $file]))
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error');
    }

    public function test_hapus_file_khusus_admin(): void
    {
        Storage::fake('local');

        $pesanan = $this->buatOrder(['mockup' => [UploadedFile::fake()->image('a.jpg', 50, 50)]]);
        $file    = $pesanan->file->first();

        $this->sebagai($this->produksi)->delete(route('admin.pesanan.file.hapus', [$pesanan, $file]))
            ->assertRedirect()->assertSessionHas('error', 'Aksi ini khusus admin.');
        $this->sebagai($this->produksi)->deleteJson(route('admin.pesanan.file.hapus', [$pesanan, $file]))->assertForbidden();
        Storage::disk('local')->assertExists($file->path);

        $this->sebagai($this->admin)->delete(route('admin.pesanan.file.hapus', [$pesanan, $file]))->assertRedirect();
        Storage::disk('local')->assertMissing($file->path);
        $this->assertDatabaseMissing('pesanan_file', ['id' => $file->id]);
    }

    public function test_validasi_file_jenis_ukuran_dan_jumlah(): void
    {
        Storage::fake('local');

        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->dataOrder([
            'mockup' => [
                UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->image('besar.jpg')->size(6000),
            ],
        ]))->assertSessionHasErrors(['mockup.0', 'mockup.1']);

        $sebelas = array_map(fn ($i) => UploadedFile::fake()->image("m{$i}.jpg", 20, 20), range(1, 11));
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->dataOrder(['mockup' => $sebelas]))
            ->assertSessionHasErrors('mockup');

        $this->assertDatabaseCount('pesanan', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_batas_10_file_memperhitungkan_file_yang_sudah_ada(): void
    {
        Storage::fake('local');

        $pesanan = $this->buatOrder(['mockup' => array_map(fn ($i) => UploadedFile::fake()->image("m{$i}.jpg", 20, 20), range(1, 9))]);
        $this->assertCount(9, $pesanan->file);

        $data = $this->dataEdit($pesanan, ['mockup' => [UploadedFile::fake()->image('x.jpg', 20, 20), UploadedFile::fake()->image('y.jpg', 20, 20)]]);
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $data)
            ->assertSessionHasErrors(['mockup' => 'Gambar maksimal 10 per SPP. Masih bisa menambah 1 gambar.']);
    }

    // ── Migrasi ────────────────────────────────────────

    public function test_migrasi_pengganti_berhenti_bila_tabel_pesanan_berisi(): void
    {
        Pesanan::factory()->create();
        $migrasi = require database_path('migrations/2026_11_18_000002_ganti_tabel_pesanan.php');

        try {
            $migrasi->up();
            $this->fail('Migrasi seharusnya berhenti.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Tabel pesanan berisi data. Migrasi dihentikan', $e->getMessage());
        }

        // Tidak ada DDL yang terjadi: tabel & isinya utuh
        $this->assertSame(1, DB::table('pesanan')->count());
    }
}

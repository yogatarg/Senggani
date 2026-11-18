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
            'nama_customer'  => 'Bu Rina Susanti',
            'nomor_whatsapp' => '0812-3456-7890',
            'alamat'         => 'Jl. Melati 5',
            'kota'           => 'Yogyakarta',
            'tanggal_order'  => today()->toDateString(),
            'deadline'       => today()->addDays(7)->toDateString(),
            'dp'             => '500.000',
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
        $this->assertEquals(500000, (float) $pesanan->dp);
        $this->assertSame('antrian', $pesanan->status_produksi->value);
        $this->assertSame($this->admin->id, $pesanan->dibuat_oleh);

        $item = $pesanan->detailPesanan;
        $this->assertCount(2, $item);
        $this->assertEquals(1250000, (float) $item[0]->subtotal);
        $this->assertSame('Laminasi doff, Hot print, Emboss', $item[0]->finishing);
        $this->assertSame('25 × 10 × 30 cm', $item[0]->ukuran_teks);
        $this->assertSame('Dus custom', $item[1]->jenis_packaging);

        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $pesanan->id, 'status_dari' => null, 'status_ke' => 'antrian',
            'catatan' => 'Order dibuat', 'nama_pengguna' => 'Rina CS',
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
            'deadline' => 'Deadline tidak boleh sebelum tanggal order.',
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

    public function test_dp_tidak_boleh_melebihi_total_dan_wa_dinormalisasi_divalidasi(): void
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->dataOrder(['dp' => '9.999.999']))
            ->assertSessionHasErrors('dp');

        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->dataOrder(['nomor_whatsapp' => '0812']))
            ->assertSessionHasErrors('nomor_whatsapp');

        $this->assertDatabaseCount('pesanan', 0);
    }

    public function test_edit_mengganti_item_dan_menghitung_ulang_total(): void
    {
        $pesanan = $this->buatOrder();

        $data = $this->dataOrder();
        $data['item'] = [['jenis_packaging' => 'Box', 'jenis_bahan' => 'Duplex', 'jumlah' => '10', 'harga_satuan' => '1000']];
        $data['dp'] = '0';

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $data)
            ->assertRedirect(route('admin.pesanan.show', $pesanan));

        $pesanan->refresh();
        $this->assertEquals(10000, (float) $pesanan->total);
        $this->assertCount(1, $pesanan->detailPesanan);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $pesanan->id, 'catatan' => 'Order diubah']);

        $this->sebagai($this->admin)->get(route('admin.pesanan.edit', $pesanan))->assertOk()->assertSee('Box');
    }

    // ── Nomor order ────────────────────────────────────

    public function test_nomor_order_format_dan_berurutan_per_bulan(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18));
        $a = $this->buatOrder();
        $b = $this->buatOrder();

        $this->assertSame('SGN-2611-001', $a->nomor_order);
        $this->assertSame('SGN-2611-002', $b->nomor_order);

        $this->travelTo(now()->setDate(2026, 12, 1));
        $this->assertSame('SGN-2612-001', $this->buatOrder()->nomor_order);
    }

    public function test_nomor_tidak_didaur_ulang_dan_mengejar_data_yang_ada(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18));
        $a = $this->buatOrder();
        $this->sebagai($this->admin)->delete(route('admin.pesanan.destroy', $a));

        // Order diinput manual dengan nomor lebih besar dari penghitung
        Pesanan::factory()->create(['nomor_order' => 'SGN-2611-007']);

        $this->assertSame('SGN-2611-008', $this->buatOrder()->nomor_order);
        $this->assertSame(1, Pesanan::where('nomor_order', 'SGN-2611-008')->count());
    }

    public function test_nomor_lebih_dari_999_menjadi_empat_digit(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 18));
        DB::table('pesanan_nomor_urut')->insert(['periode' => '2611', 'terakhir' => 999]);

        $nomor = DB::transaction(fn () => app(PesananService::class)->nomorBerikutnya());

        $this->assertSame('SGN-2611-1000', $nomor);
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

        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $pesanan))->assertRedirect();

        $pesanan->refresh();
        $this->assertSame('lunas', $pesanan->status_pembayaran);
        $this->assertTrue($pesanan->tanggal_lunas->isToday());
        $this->assertSame('antrian', $pesanan->status_produksi->value);

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

        // Sudah selesai produksi: tidak diberi penanda
        foreach (['selesai_produksi', 'pengiriman', 'selesai', 'dibatalkan'] as $s) {
            $this->assertNull(Pesanan::factory()->status($s)->make(['deadline' => today()->subDays(5)])->penanda_deadline);
        }
    }

    // ── Tab, pencarian, jumlah ─────────────────────────

    public function test_tab_semua_hanya_order_aktif_dan_history_terpisah(): void
    {
        $antrian = Pesanan::factory()->create(['deadline' => today()->addDays(5)]);
        $proses  = Pesanan::factory()->status('proses')->create(['deadline' => today()->addDay()]);
        $kirim   = Pesanan::factory()->status('pengiriman')->create();
        $selesai = Pesanan::factory()->status('selesai')->create(['selesai_pada' => now()->subDays(2)]);
        $batal   = Pesanan::factory()->status('dibatalkan')->create(['selesai_pada' => now()->subDay()]);

        $ids = fn ($res) => $res->viewData('pesanan')->pluck('id')->all();

        $semua = $this->sebagai($this->produksi)->get(route('admin.pesanan.index'));
        $this->assertSame([$proses->id, $antrian->id, $kirim->id], $ids($semua)); // urut deadline terdekat

        $this->assertSame([$proses->id], $ids($this->sebagai($this->produksi)->get(route('admin.pesanan.index', ['tab' => 'proses']))));
        $this->assertSame([$batal->id, $selesai->id], $ids($this->sebagai($this->produksi)->get(route('admin.pesanan.index', ['tab' => 'history']))));

        $jumlah = $this->sebagai($this->produksi)->getJson(route('admin.pesanan.jumlah'))->assertOk()->json();
        $this->assertSame(['semua' => 3, 'antrian' => 1, 'proses' => 1, 'selesai_produksi' => 0, 'pengiriman' => 1, 'history' => 2], $jumlah['jumlah']);
        $this->assertNotEmpty($jumlah['penanda']);
        $this->assertNotEmpty($jumlah['terakhir_diubah']);
    }

    public function test_pencarian_menjangkau_semua_order_termasuk_history(): void
    {
        $budi    = Pesanan::factory()->create(['nama_customer' => 'Budi Santoso']);
        $budiLam = Pesanan::factory()->status('selesai')->create(['nama_customer' => 'Pak Budi']);
        Pesanan::factory()->create(['nama_customer' => 'Sari']);
        $nomor   = Pesanan::factory()->create(['nomor_order' => 'SGN-2611-042', 'nama_customer' => 'Andi']);
        Pesanan::factory()->create(['nama_customer' => 'Persen 100%']);

        $cari = fn ($q, $tab = 'antrian') => $this->sebagai($this->admin)
            ->get(route('admin.pesanan.index', ['q' => $q, 'tab' => $tab]))
            ->viewData('pesanan')->pluck('id')->sort()->values()->all();

        $this->assertSame([$budi->id, $budiLam->id], $cari('budi'));
        $this->assertSame([$nomor->id], $cari('2611-042', 'history'));
        $this->assertSame([], $cari('_')); // wildcard di-escape
    }

    public function test_penanda_jumlah_berubah_saat_status_berubah(): void
    {
        $pesanan = Pesanan::factory()->create();
        $sebelum = $this->sebagai($this->admin)->getJson(route('admin.pesanan.jumlah'))->json('penanda');

        $this->travel(5)->seconds();
        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'antrian', 'status_baru' => 'proses']);

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
            'status_lama' => 'antrian', 'status_baru' => 'proses', 'catatan' => 'Mulai cetak',
        ])->assertRedirect(route('admin.pesanan.show', $pesanan))->assertSessionHas('success', 'Status diubah ke Dalam Proses.');

        $this->assertSame('proses', $pesanan->fresh()->status_produksi->value);
        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $pesanan->id, 'admin_id' => $this->produksi->id, 'nama_pengguna' => 'Ayah',
            'status_dari' => 'antrian', 'status_ke' => 'proses', 'catatan' => 'Mulai cetak',
        ]);

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), [
            'status_lama' => 'proses', 'status_baru' => 'antrian',
        ])->assertSessionHas('success');
        $this->assertSame('antrian', $pesanan->fresh()->status_produksi->value);
        $this->assertSame('mundur', $pesanan->riwayat()->first()->jenis);
    }

    public function test_status_tidak_boleh_lompat_dua_langkah_atau_ke_dibatalkan(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), [
            'status_lama' => 'antrian', 'status_baru' => 'selesai_produksi',
        ])->assertSessionHasErrors(['status_baru' => 'Status hanya bisa dipindah satu langkah maju atau mundur.']);

        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), [
            'status_lama' => 'antrian', 'status_baru' => 'dibatalkan',
        ])->assertSessionHasErrors('status_baru');

        $this->assertSame('antrian', $pesanan->fresh()->status_produksi->value);
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
        $this->sebagai($mamah)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'antrian', 'status_baru' => 'proses']);

        // Perangkat 2 (Ayah) masih melihat "antrian" lalu menekan tombol yang sama
        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'antrian', 'status_baru' => 'proses'])
            ->assertRedirect(route('admin.pesanan.show', $pesanan))
            ->assertSessionHas('error', 'Status pesanan ini sudah diubah oleh Mamah menjadi Dalam Proses. Muat ulang halaman.')
            ->assertSessionHas('bentrok', true);

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.status', $pesanan), ['status_lama' => 'antrian', 'status_baru' => 'proses'])
            ->assertStatus(409)
            ->assertJson(['bentrok' => true, 'status_sekarang' => 'proses']);

        // Hanya perubahan pertama yang tercatat (factory tidak membuat riwayat "Order dibuat")
        $this->assertSame(1, $pesanan->riwayat()->count());
    }

    public function test_status_lewat_json(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.status', $pesanan), ['status_lama' => 'antrian', 'status_baru' => 'proses'])
            ->assertOk()
            ->assertJson(['status' => 'proses', 'label_status' => 'Dalam Proses']);
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
        $this->sebagai($this->produksi)->post(route('admin.pesanan.pulihkan', $pesanan))->assertForbidden();
        $this->sebagai($this->produksi)->post(route('admin.pesanan.status', $pesanan), ['status_lama' => 'dibatalkan', 'status_baru' => 'antrian'])
            ->assertSessionHasErrors('status_lama');

        $this->sebagai($this->admin)->post(route('admin.pesanan.pulihkan', $pesanan))
            ->assertSessionHas('success', 'Order dipulihkan ke Dalam Proses.');
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
        $pesanan = $this->buatOrder();

        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $pesanan))
            ->assertOk()
            ->assertSee($pesanan->nomor_order)
            ->assertSee('Pindahkan ke Dalam Proses')
            ->assertSee('Batalkan order')
            ->assertViewHas('statusBerikutnya');
    }

    public function test_hapus_order_soft_delete_khusus_admin(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->admin)->delete(route('admin.pesanan.destroy', $pesanan))->assertRedirect(route('admin.pesanan.index'));

        $this->assertSoftDeleted($pesanan);
        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $pesanan))->assertNotFound();
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

        // File order lain lewat URL order ini → 404
        $lain = Pesanan::factory()->create();
        $this->sebagai($this->admin)->get(route('admin.pesanan.file.lihat', [$lain, $file]))->assertNotFound();
    }

    public function test_hapus_file_khusus_admin(): void
    {
        Storage::fake('local');

        $pesanan = $this->buatOrder(['mockup' => [UploadedFile::fake()->image('a.jpg', 50, 50)]]);
        $file    = $pesanan->file->first();

        $this->sebagai($this->produksi)->delete(route('admin.pesanan.file.hapus', [$pesanan, $file]))->assertForbidden();
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

        $data = $this->dataOrder(['mockup' => [UploadedFile::fake()->image('x.jpg', 20, 20), UploadedFile::fake()->image('y.jpg', 20, 20)]]);
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $data)
            ->assertSessionHasErrors(['mockup' => 'Gambar maksimal 10 per order. Masih bisa menambah 1 gambar.']);
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

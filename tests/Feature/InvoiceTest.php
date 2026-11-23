<?php

namespace Tests\Feature;

use App\Enums\MetodeBayar;
use App\Models\Admin;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Services\InvoicePdf;
use App\Services\PengaturanInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Invoice PDF: total tagihan (subtotal + ongkir − diskon), metode bayar DP/lunas, kunci ongkir/diskon
 * dan harga/jumlah item setelah lunas, nomor invoice PNW/SPP, akses (admin saja, status Menunggu DP s.d. Selesai),
 * unduh PDF, dan Pengaturan Invoice (simpan, unggah, hapus gambar privat).
 */
class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Admin $produksi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 23)->setTime(9, 0));
        Storage::fake('local');

        $this->admin    = Admin::factory()->create(['nama' => 'Rina CS']);
        $this->produksi = Admin::factory()->produksi()->create(['nama' => 'Ayah']);
    }

    private function sebagai(Admin $pengguna): static
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_id' => $pengguna->id]);
    }

    /** Data form SPP: 500 × 4.300 + 100 × 2.000 = 2.350.000 (harga lengkap). */
    private function data(array $ubah = []): array
    {
        return array_replace_recursive([
            'nama_customer'  => 'Putri Maulidina',
            'nomor_whatsapp' => '0822-2894-6660',
            'alamat'         => 'Jl. Melati 5',
            'kota'           => 'Yogyakarta',
            'tanggal_order'  => '2026-11-21',
            'deadline'       => '2026-11-30',
            'jenis_deadline' => 'kirim',
            'item'           => [
                ['jenis_packaging' => 'Paperbag', 'jenis_bahan' => 'Kraft', 'jumlah' => '500', 'harga_satuan' => '4.300', 'panjang' => '25', 'lebar' => '10', 'tinggi' => '30'],
                ['jenis_packaging' => 'Box', 'jenis_bahan' => 'Duplex', 'jumlah' => '100', 'harga_satuan' => '2.000'],
            ],
        ], $ubah);
    }

    private function buat(array $ubah = []): Pesanan
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data($ubah))
            ->assertSessionHasNoErrors()->assertRedirect();

        return Pesanan::latest('id')->firstOrFail();
    }

    private function catatDp(Pesanan $p, array $ubah = [])
    {
        return $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $p), [
            'dp' => '1.000.000', 'nomor_spp' => '176', 'metode_dp' => 'transfer_bca', ...$ubah,
        ]);
    }

    /** SPP 2.350.000 + ongkir 25.000 − diskon 50.000 = 2.325.000, DP 1.000.000 via BCA, nomor #176. */
    private function buatProduksi(): Pesanan
    {
        $p = $this->buat(['ongkir' => '25.000', 'diskon' => 'Rp 50.000']);
        $this->catatDp($p)->assertSessionHasNoErrors();

        return $p->fresh();
    }

    // ── Total tagihan ──────────────────────────────────

    public function test_total_tagihan_memakai_ongkir_dan_diskon(): void
    {
        $p = $this->buat(['ongkir' => '25.000', 'diskon' => 'Rp 50.000']);

        $this->assertSame(2350000.0, $p->subtotal_produk);
        $this->assertSame(25000.0, (float) $p->ongkir);
        $this->assertSame(50000.0, (float) $p->diskon);
        $this->assertSame(2325000.0, $p->total_tagihan);
        $this->assertSame('menunggu_dp', $p->status_produksi->value);

        // DP dibatasi total tagihan, bukan subtotal
        $this->catatDp($p, ['dp' => '2.325.001'])
            ->assertSessionHasErrors(['dp' => 'DP tidak boleh lebih dari total tagihan (Rp 2.325.000).']);
        $this->catatDp($p)->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(1325000.0, $p->sisa);
        $this->assertSame('dp', $p->status_bayar_invoice);

        // Teks SPP menambah blok ongkir/diskon/total
        $this->assertStringContainsString("🚚 Ongkir: Rp25.000\n🏷️ Diskon: -Rp50.000\n💰 Total: Rp2.325.000", $p->teks_spp);

        // Pelunasan: sisa 0, nilai pelunasan = total tagihan − DP
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'qris'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(0.0, $p->sisa);
        $this->assertSame(1325000.0, $p->nilai_pelunasan);
        $this->assertSame('lunas', $p->status_bayar_invoice);
    }

    public function test_teks_spp_tanpa_ongkir_diskon_tetap_format_lama(): void
    {
        $p = $this->buat();
        $this->catatDp($p)->assertSessionHasNoErrors();
        $teks = $p->fresh()->teks_spp;

        $this->assertStringNotContainsString('Ongkir', $teks);
        $this->assertStringNotContainsString('Diskon', $teks);
        $this->assertStringNotContainsString('💰 Total', $teks);
        $this->assertSame(0.0, (float) $p->fresh()->ongkir);
    }

    public function test_teks_spp_hanya_ongkir(): void
    {
        $p = $this->buat(['ongkir' => '15000']);
        $this->catatDp($p)->assertSessionHasNoErrors();
        $teks = $p->fresh()->teks_spp;

        $this->assertStringContainsString("🚚 Ongkir: Rp15.000\n💰 Total: Rp2.365.000", $teks);
        $this->assertStringNotContainsString('Diskon', $teks);
    }

    // ── Validasi ongkir & diskon ───────────────────────

    public function test_validasi_ongkir_dan_diskon(): void
    {
        $kirim = fn (array $ubah) => $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data($ubah));

        $kirim(['diskon' => '2.350.001'])->assertSessionHasErrors(['diskon' => 'Diskon tidak boleh melebihi subtotal produk (Rp 2.350.000).']);
        $kirim(['ongkir' => '-5000'])->assertSessionHasErrors('ongkir');
        $kirim(['diskon' => '-1'])->assertSessionHasErrors('diskon');
        $kirim(['ongkir' => 'abc'])->assertSessionHasErrors('ongkir');
        $this->assertSame(0, Pesanan::count());

        // Diskon = subtotal boleh; kosong = 0
        $kirim(['diskon' => '2.350.000', 'ongkir' => ''])->assertSessionHasNoErrors();
        $p = Pesanan::latest('id')->first();
        $this->assertSame(0.0, $p->total_tagihan);
        $this->assertSame(0.0, (float) $p->ongkir);

        // Harga belum lengkap (Hitung Harga): diskon belum dibandingkan dengan subtotal
        $kirim([
            'diskon' => '50.000',
            'item'   => [['harga_satuan' => ''], ['harga_satuan' => '']],
        ])->assertSessionHasNoErrors();
        $hitung = Pesanan::latest('id')->first();
        $this->assertSame('hitung_harga', $hitung->status_produksi->value);

        // ...tetapi saat harga diisi, diskon tidak boleh melebihi subtotal baru
        $ids = $hitung->detailPesanan()->pluck('id')->all();
        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $hitung), ['harga' => [$ids[0] => '10', $ids[1] => '10']])
            ->assertSessionHasErrors(['harga' => 'Subtotal produk baru (Rp 6.000) lebih kecil dari diskon (Rp 50.000). Ubah diskon di form SPP dulu.']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $hitung), ['harga' => [$ids[0] => '1.000', $ids[1] => '1.000']])
            ->assertSessionHasNoErrors();
        $this->assertSame(550000.0, $hitung->fresh()->total_tagihan);
    }

    public function test_edit_tanpa_field_ongkir_mempertahankan_nilai_lama(): void
    {
        $p = $this->buatProduksi();

        $data = $this->data(['dp' => '1.000.000', 'nomor_spp' => '176']);
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), $data)->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(25000.0, (float) $p->ongkir);
        $this->assertSame(50000.0, (float) $p->diskon);
        $this->assertSame(MetodeBayar::TransferBca, $p->metode_dp);

        // Ubah ongkir lewat edit → tercatat di riwayat
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'ongkir' => '30.000'])->assertSessionHasNoErrors();
        $this->assertSame(30000.0, (float) $p->fresh()->ongkir);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $p->id, 'catatan' => 'Ongkir diubah oleh Rina CS: Rp 25.000 → Rp 30.000']);

        // DP melebihi total tagihan baru ditolak
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'diskon' => '1.400.000'])
            ->assertSessionHasErrors(['dp' => 'DP (Rp 1.000.000) tidak boleh lebih dari total tagihan (Rp 980.000).']);
    }

    public function test_ongkir_dan_diskon_dikunci_setelah_lunas(): void
    {
        $p = $this->buatProduksi();
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'cash'])->assertSessionHasNoErrors();

        $data = $this->data(['dp' => '1.000.000', 'nomor_spp' => '176', 'tanggal_lunas' => '2026-11-23']);
        $pesan = 'Ongkir dan diskon dikunci karena SPP sudah lunas. Kosongkan tanggal lunas dulu bila perlu dikoreksi.';

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'ongkir' => '30.000', 'diskon' => '50.000'])
            ->assertSessionHasErrors(['ongkir' => $pesan]);
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'ongkir' => '25.000', 'diskon' => '0'])
            ->assertSessionHasErrors(['diskon' => $pesan]);

        // Nilai sama: boleh disimpan
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'ongkir' => '25.000', 'diskon' => '50.000'])
            ->assertSessionHasNoErrors();

        // Tanggal lunas dikosongkan: boleh dikoreksi, metode pelunasan ikut kosong
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'tanggal_lunas' => '', 'ongkir' => '30.000', 'diskon' => '50.000'])
            ->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(30000.0, (float) $p->ongkir);
        $this->assertNull($p->tanggal_lunas);
        $this->assertNull($p->metode_lunas);
    }

    public function test_harga_dan_jumlah_item_dikunci_setelah_lunas(): void
    {
        $p = $this->buatProduksi();
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'cash'])->assertSessionHasNoErrors();

        $data  = $this->data(['dp' => '1.000.000', 'nomor_spp' => '176', 'tanggal_lunas' => '2026-11-23', 'ongkir' => '25.000', 'diskon' => '50.000']);
        $pesan = 'Jumlah dan harga item dikunci karena SPP sudah lunas. Kosongkan tanggal lunas dulu bila perlu dikoreksi.';

        // Edit SPP: harga satuan, jumlah, maupun menambah item ditolak
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), array_replace_recursive($data, ['item' => [0 => ['harga_satuan' => '4.500']]]))
            ->assertSessionHasErrors(['item' => $pesan]);
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), array_replace_recursive($data, ['item' => [1 => ['jumlah' => '120']]]))
            ->assertSessionHasErrors(['item' => $pesan]);
        $tambah = $data;
        $tambah['item'][] = ['jenis_packaging' => 'Stiker', 'jenis_bahan' => 'Vinyl', 'jumlah' => '10', 'harga_satuan' => '1.000'];
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), $tambah)->assertSessionHasErrors(['item' => $pesan]);
        $this->assertSame(2350000.0, (float) $p->fresh()->total);

        // Isian teks item tetap boleh diubah selama jumlah & harga sama
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), array_replace_recursive($data, ['item' => [0 => ['catatan' => 'Sablon 1 warna']]]))
            ->assertSessionHasNoErrors();

        // Isi Harga: perubahan ditolak, harga sama (hanya catatan) boleh
        $ids = $p->detailPesanan()->pluck('id')->all();
        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $p), ['harga' => [$ids[0] => '4.500']])
            ->assertSessionHasErrors(['harga' => 'Harga item dikunci karena SPP sudah lunas. Kosongkan tanggal lunas di form SPP dulu bila perlu dikoreksi.']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $p), ['harga' => [$ids[0] => '4.300'], 'catatan_harga' => 'Harga final'])
            ->assertSessionHasNoErrors();
        $this->assertSame(4300, (int) $p->detailPesanan()->first()->harga_satuan);

        // Tanggal lunas dikosongkan: harga boleh dikoreksi
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), array_replace_recursive($data, ['tanggal_lunas' => '', 'item' => [0 => ['harga_satuan' => '4.500']]]))
            ->assertSessionHasNoErrors();
        $this->assertSame(2450000.0, (float) $p->fresh()->total);
    }

    // ── Metode bayar ───────────────────────────────────

    public function test_metode_wajib_saat_catat_dp(): void
    {
        $p = $this->buat();

        $this->catatDp($p, ['metode_dp' => ''])->assertSessionHasErrors(['metode_dp' => 'Pilih metode pembayaran.']);
        $this->catatDp($p, ['metode_dp' => 'gopay'])->assertSessionHasErrors('metode_dp');
        $this->assertSame('menunggu_dp', $p->fresh()->status_produksi->value);

        // Alias field "metode"
        $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $p), ['dp' => '1.000.000', 'nomor_spp' => '176', 'metode' => 'qris'])
            ->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(MetodeBayar::Qris, $p->metode_dp);
        $this->assertSame('proses', $p->status_produksi->value);
        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $p->id, 'status_ke' => 'proses',
            'catatan'    => 'DP Rp 1.000.000 via QRIS dicatat (23/11/2026), nomor SPP #176',
        ]);
    }

    public function test_metode_wajib_saat_catat_lunas(): void
    {
        $p = $this->buatProduksi();

        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p))->assertSessionHasErrors(['metode_lunas' => 'Pilih metode pembayaran.']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'ovo'])->assertSessionHasErrors('metode_lunas');
        $this->assertNull($p->fresh()->tanggal_lunas);

        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'transfer_bri'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(MetodeBayar::TransferBri, $p->metode_lunas);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $p->id, 'catatan' => 'Pelunasan via Transfer BRI dicatat, tanggal 23/11/2026']);
    }

    public function test_label_metode_bayar(): void
    {
        $this->assertSame([
            'transfer_bca' => 'Transfer BCA',
            'transfer_bri' => 'Transfer BRI',
            'qris'         => 'QRIS',
            'cash'         => 'Cash',
        ], MetodeBayar::pilihan());
    }

    // ── Nomor invoice ──────────────────────────────────

    public function test_nomor_invoice_pnw_lalu_spp(): void
    {
        $p = $this->buat();
        $this->assertSame('INV-PNW-' . $p->id, $p->nomor_invoice);

        $this->catatDp($p)->assertSessionHasNoErrors();
        $this->assertSame('INV-176', $p->fresh()->nomor_invoice);

        // Nomor SPP diganti → nomor invoice ikut nomor terbaru
        $p->forceFill(['nomor_spp' => 180])->save();
        $this->assertSame('INV-180', $p->fresh()->nomor_invoice);
        $this->assertSame('Invoice-INV-180-Putri-Maulidina.pdf', InvoicePdf::namaFile($p->fresh()));
    }

    // ── Akses & unduh PDF ──────────────────────────────

    public function test_unduh_invoice_pdf(): void
    {
        $p = $this->buatProduksi();

        $res = $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $p));

        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Invoice-INV-176-Putri-Maulidina.pdf', $res->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $res->getContent());

        // Menunggu DP (belum bernomor): INV-PNW-{id}
        $q = $this->buat(['nama_customer' => 'Ismun']);
        $res = $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $q))->assertOk();
        $this->assertStringContainsString("Invoice-INV-PNW-{$q->id}-Ismun.pdf", $res->headers->get('Content-Disposition'));
    }

    public function test_unduh_invoice_dengan_gambar_dan_banyak_item(): void
    {
        $set = app(PengaturanInvoice::class);
        $set->simpanGambar('stempel', UploadedFile::fake()->image('stempel.png', 533, 123));
        $set->simpanGambar('ttd', UploadedFile::fake()->image('ttd.png', 600, 200));
        $set->simpanGambar('qris', UploadedFile::fake()->image('qris.jpg', 400, 400));

        $item = [];
        for ($i = 0; $i < 15; $i++) {
            $item[] = ['jenis_packaging' => "Paperbag {$i}", 'jenis_bahan' => 'Kraft', 'jumlah' => '10000', 'harga_satuan' => '12.500',
                'catatan' => str_repeat("Catatan panjang\n", 30)];
        }
        $data = $this->data();
        $data['item'] = $item;
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $data)->assertSessionHasNoErrors();
        $p = Pesanan::latest('id')->first();

        $res = $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $p))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertGreaterThanOrEqual(2, preg_match_all('#/Type\s*/Page\b#', $res->getContent()));
    }

    public function test_akses_invoice_hanya_admin_dan_status_tertentu(): void
    {
        $p = $this->buatProduksi();

        // Produksi: 403 (HTML dialihkan ke daftar SPP dengan pesan, lihat GalatPanelAdmin)
        $this->sebagai($this->produksi)->getJson(route('admin.pesanan.invoice', $p))->assertForbidden();
        $this->sebagai($this->produksi)->get(route('admin.pesanan.invoice', $p))
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'Invoice hanya untuk admin.');

        // Hitung Harga: ditolak
        $hitung = $this->buat(['item' => [['harga_satuan' => ''], ['harga_satuan' => '']]]);
        $this->assertSame('hitung_harga', $hitung->status_produksi->value);
        $this->sebagai($this->admin)->getJson(route('admin.pesanan.invoice', $hitung))
            ->assertForbidden()->assertExactJson(['pesan' => 'Invoice tersedia setelah semua harga terisi.']);

        // Tidak Jadi & Dibatalkan: ditolak
        foreach (['tidak_jadi', 'dibatalkan'] as $status) {
            $x = Pesanan::factory()->status($status)->create(['total' => 100000]);
            $this->sebagai($this->admin)->getJson(route('admin.pesanan.invoice', $x))->assertForbidden();
        }

        // Menunggu DP s.d. Selesai: boleh
        foreach (Pesanan::STATUS_INVOICE as $status) {
            $x = Pesanan::factory()->status($status)->create(['total' => 100000]);
            $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $x))->assertOk();
        }

        // Tamu: diarahkan ke login
        $this->flushSession();
        $this->get(route('admin.pesanan.invoice', $p))->assertRedirect(route('admin.login'));
    }

    public function test_ringkasan_invoice_kasus_tepi(): void
    {
        // Lunas tanpa DP terpisah tidak mungkin lewat alur, tetapi data lama bisa: dp 0 + tanggal lunas
        $p = Pesanan::factory()->create(['total' => 500000, 'dp' => 0, 'tanggal_lunas' => '2026-11-22', 'metode_lunas' => 'cash']);
        $this->assertSame('lunas', $p->status_bayar_invoice);
        $this->assertSame(500000.0, $p->nilai_pelunasan);

        // Kelebihan bayar: diskon dinaikkan setelah DP (data lama)
        $q = Pesanan::factory()->create(['total' => 500000, 'diskon' => 100000, 'dp' => 425000]);
        $this->assertSame(0.0, $q->sisa);
        $this->assertSame(25000.0, $q->kelebihan_bayar);
        $this->assertSame('lunas', $q->status_bayar_invoice);

        // Belum bayar
        $r = Pesanan::factory()->status('menunggu_dp')->create(['total' => 500000]);
        $this->assertSame('belum_bayar', $r->status_bayar_invoice);
        $this->assertNull($r->metode_dp);

        foreach ([$p, $q, $r] as $x) {
            $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $x))->assertOk();
        }
    }

    // ── Pengaturan Invoice ─────────────────────────────

    public function test_nilai_awal_pengaturan_dari_migrasi(): void
    {
        $set = app(PengaturanInvoice::class)->semua();

        $this->assertSame('Putri Maulidina', $set['penandatangan_nama']);
        $this->assertSame(50, $set['persen_dp']);
        $this->assertSame('DP minimal 50%. Pengiriman barang setelah pelunasan.', $set['keterangan_bawah']);
        $this->assertSame([
            ['bank' => 'BCA', 'nomor' => '8023011164', 'atas_nama' => 'Herni Pri Nugrahani'],
            ['bank' => 'BRI', 'nomor' => '300801017388537', 'atas_nama' => 'Herni Pri Nugrahani'],
        ], $set['rekening']);
        $this->assertNull($set['file_qris']);
    }

    public function test_pengaturan_simpan(): void
    {
        $this->sebagai($this->admin)->get(route('admin.pengaturan.invoice.edit'))->assertOk()->assertSee('8023011164');

        $this->sebagai($this->admin)->put(route('admin.pengaturan.invoice.update'), [
            'penandatangan_nama'    => ' Herni Pri Nugrahani ',
            'penandatangan_jabatan' => 'Pemilik',
            'rekening'              => [
                ['bank' => 'BCA', 'nomor' => '802 301-1164', 'atas_nama' => 'Herni Pri Nugrahani'],
                ['bank' => '', 'nomor' => '', 'atas_nama' => ''],
                ['bank' => 'Mandiri', 'nomor' => '1234.5678.90', 'atas_nama' => 'Senggani'],
            ],
            'qris_keterangan'  => 'QRIS a.n. SENGGANI - FOTOCOPY (satu usaha).',
            'keterangan_bawah' => "DP minimal 40%.\r\nPengiriman setelah lunas.",
            'persen_dp'        => '40',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.pengaturan.invoice.edit'))
            ->assertSessionHas('success', 'Pengaturan invoice disimpan.');

        $set = app(PengaturanInvoice::class)->semua();
        $this->assertSame('Herni Pri Nugrahani', $set['penandatangan_nama']);
        $this->assertSame('Pemilik', $set['penandatangan_jabatan']);
        $this->assertSame(40, $set['persen_dp']);
        $this->assertSame("DP minimal 40%.\nPengiriman setelah lunas.", $set['keterangan_bawah']);
        $this->assertSame([
            ['bank' => 'BCA', 'nomor' => '8023011164', 'atas_nama' => 'Herni Pri Nugrahani'],
            ['bank' => 'Mandiri', 'nomor' => '1234567890', 'atas_nama' => 'Senggani'],
        ], $set['rekening']);

        // Validasi
        $this->sebagai($this->admin)->put(route('admin.pengaturan.invoice.update'), [
            'persen_dp' => '120',
            'rekening'  => [['bank' => 'BCA', 'nomor' => 'abc12', 'atas_nama' => '']],
        ])->assertSessionHasErrors(['persen_dp', 'rekening.0.nomor', 'rekening.0.atas_nama']);

        $this->sebagai($this->admin)->put(route('admin.pengaturan.invoice.update'), [
            'persen_dp' => '50',
            'rekening'  => array_fill(0, 5, ['bank' => 'BCA', 'nomor' => '123456', 'atas_nama' => 'X']),
        ])->assertSessionHasErrors('rekening');

        // Rekening boleh kosong semua
        $this->sebagai($this->admin)->put(route('admin.pengaturan.invoice.update'), ['persen_dp' => '50', 'rekening' => []])
            ->assertSessionHasNoErrors();
        $this->assertSame([], app(PengaturanInvoice::class)->semua()['rekening']);
    }

    public function test_pengaturan_unggah_dan_hapus_gambar(): void
    {
        // PNG transparan tetap PNG (bukan WebP) dan alfa dipertahankan
        $gambar = imagecreatetruecolor(400, 150);
        imagesavealpha($gambar, true);
        imagefill($gambar, 0, 0, imagecolorallocatealpha($gambar, 0, 0, 0, 127));
        $tmp = tempnam(sys_get_temp_dir(), 'ttd') . '.png';
        imagepng($gambar, $tmp);

        $this->sebagai($this->admin)->put(route('admin.pengaturan.invoice.update'), [
            'persen_dp' => '50',
            'ttd'       => new UploadedFile($tmp, 'ttd.png', 'image/png', null, true),
            'stempel'   => UploadedFile::fake()->image('stempel.webp', 533, 123),
        ])->assertSessionHasNoErrors();

        $set = app(PengaturanInvoice::class)->semua();
        $this->assertMatchesRegularExpression('#^invoice/ttd-[a-z0-9]{12}\.png$#', $set['file_ttd']);
        $this->assertMatchesRegularExpression('#^invoice/stempel-[a-z0-9]{12}\.png$#', $set['file_stempel']);
        Storage::disk('local')->assertExists($set['file_ttd']);

        $hasil = imagecreatefromstring(Storage::disk('local')->get($set['file_ttd']));
        $this->assertSame(127, (imagecolorat($hasil, 10, 10) >> 24) & 0x7F, 'Piksel transparan harus tetap transparan.');

        // Unggah satuan (QRIS JPG tetap JPG) lalu ganti: file lama dihapus
        $this->sebagai($this->admin)->post(route('admin.pengaturan.invoice.unggah', 'qris'), [
            'gambar' => UploadedFile::fake()->image('qris.jpg', 600, 600),
        ])->assertSessionHasNoErrors();
        $qrisLama = app(PengaturanInvoice::class)->semua()['file_qris'];
        $this->assertStringEndsWith('.jpg', $qrisLama);

        $this->sebagai($this->admin)->postJson(route('admin.pengaturan.invoice.unggah', 'qris'), [
            'gambar' => UploadedFile::fake()->image('qris2.png', 500, 500),
        ])->assertOk()->assertJsonStructure(['pesan', 'url']);
        $qrisBaru = app(PengaturanInvoice::class)->semua()['file_qris'];
        Storage::disk('local')->assertMissing($qrisLama);
        Storage::disk('local')->assertExists($qrisBaru);

        // Pratinjau privat
        $this->sebagai($this->admin)->get(route('admin.pengaturan.invoice.gambar', 'qris'))->assertOk();
        $this->sebagai($this->produksi)->getJson(route('admin.pengaturan.invoice.gambar', 'qris'))->assertForbidden();
        $this->sebagai($this->admin)->getJson('/admin/pengaturan-invoice/gambar/lain')->assertNotFound();

        // Hapus
        $this->sebagai($this->admin)->delete(route('admin.pengaturan.invoice.hapus', 'qris'))->assertRedirect();
        Storage::disk('local')->assertMissing($qrisBaru);
        $this->assertNull(app(PengaturanInvoice::class)->semua()['file_qris']);
        $this->sebagai($this->admin)->getJson(route('admin.pengaturan.invoice.gambar', 'qris'))->assertNotFound();
    }

    public function test_pengaturan_validasi_gambar(): void
    {
        $unggah = fn (string $jenis, UploadedFile $file) => $this->sebagai($this->admin)
            ->post(route('admin.pengaturan.invoice.unggah', $jenis), ['gambar' => $file]);

        $unggah('ttd', UploadedFile::fake()->create('ttd.heic', 100, 'image/heic'))
            ->assertSessionHasErrors(['gambar' => 'ttd.heic tidak bisa diunggah: format harus PNG, JPG, atau WebP.']);
        $unggah('ttd', UploadedFile::fake()->create('ttd.txt', 1, 'text/plain'))->assertSessionHasErrors('gambar');
        $unggah('stempel', UploadedFile::fake()->image('stempel.png', 400, 100)->size(2049))
            ->assertSessionHasErrors(['gambar' => 'stempel.png terlalu besar. Ukuran maksimal 2 MB.']);
        $unggah('qris', UploadedFile::fake()->image('qris.png', 180, 180))
            ->assertSessionHasErrors(['gambar' => 'QRIS terlalu kecil (180 px). Pakai gambar minimal 300 px agar bisa dipindai (dan maksimal 8000 px).']);

        // bail: satu galat saja untuk file bukan gambar
        $res = $unggah('qris', UploadedFile::fake()->create('qris.pdf', 10, 'application/pdf'));
        $this->assertCount(1, session('errors')->get('gambar'));

        $this->assertNull(app(PengaturanInvoice::class)->semua()['file_ttd']);
        $this->assertSame([], Storage::disk('local')->allFiles('invoice'));
    }

    public function test_pengaturan_hanya_admin(): void
    {
        $this->sebagai($this->produksi)->getJson(route('admin.pengaturan.invoice.edit'))->assertForbidden();
        $this->sebagai($this->produksi)->get(route('admin.pengaturan.invoice.edit'))->assertRedirect(route('admin.pesanan.index'));
        $this->sebagai($this->produksi)->putJson(route('admin.pengaturan.invoice.update'), ['persen_dp' => '10'])->assertForbidden();
        $this->sebagai($this->produksi)->postJson(route('admin.pengaturan.invoice.unggah', 'ttd'), [
            'gambar' => UploadedFile::fake()->image('ttd.png', 400, 100),
        ])->assertForbidden();
        $this->sebagai($this->produksi)->deleteJson(route('admin.pengaturan.invoice.hapus', 'ttd'))->assertForbidden();
        $this->sebagai($this->produksi)->getJson(route('admin.pengaturan.invoice.contoh'))->assertForbidden();

        $this->assertSame(50, app(PengaturanInvoice::class)->semua()['persen_dp']);
    }

    public function test_contoh_pdf_inline(): void
    {
        // Belum ada SPP: data contoh
        $res = $this->sebagai($this->admin)->get(route('admin.pengaturan.invoice.contoh'))->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $res->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Invoice-INV-CONTOH-Putri-Maulidina.pdf', $res->headers->get('Content-Disposition'));
        $this->assertSame(0, Pesanan::count(), 'Data contoh tidak boleh tersimpan.');

        // Ada SPP yang memenuhi syarat: dipakai
        $this->buatProduksi();
        $res = $this->sebagai($this->admin)->get(route('admin.pengaturan.invoice.contoh'))->assertOk();
        $this->assertStringContainsString('INV-176', $res->headers->get('Content-Disposition'));
    }

    public function test_halaman_detail_menampilkan_tombol_invoice_untuk_admin(): void
    {
        $p = $this->buatProduksi();

        $this->sebagai($this->admin)->get(route('admin.pesanan.show', $p))
            ->assertOk()->assertSee(route('admin.pesanan.invoice', $p), false)->assertSee('Rp 2.325.000');
        $this->sebagai($this->produksi)->get(route('admin.pesanan.show', $p))
            ->assertOk()->assertDontSee(route('admin.pesanan.invoice', $p), false);

        // Form edit memuat field ongkir/diskon/metode (placeholder sebelum dirapikan frontend)
        $this->sebagai($this->admin)->get(route('admin.pesanan.edit', $p))->assertOk()
            ->assertSee('name="ongkir"', false)->assertSee('name="diskon"', false)
            ->assertSee('name="metode_dp"', false)->assertSee('name="metode_lunas"', false);
        $this->sebagai($this->admin)->get(route('admin.pesanan.create'))->assertOk()->assertSee('name="ongkir"', false);

        $this->assertInstanceOf(Pengaturan::class, Pengaturan::where('kunci', 'invoice.rekening')->first());
    }
}

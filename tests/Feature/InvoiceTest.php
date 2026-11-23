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
 * Invoice PDF: total tagihan (subtotal + ongkir; diskon tidak dipakai), status bayar BELUM LUNAS/LUNAS,
 * metode bayar DP/lunas, kunci ongkir dan harga/jumlah item setelah lunas, nomor invoice PNW/SPP, akses (admin saja, status Menunggu DP s.d. Selesai),
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

    /** SPP 2.350.000 + ongkir 25.000 = 2.375.000, DP 1.000.000 via BCA, nomor #176. */
    private function buatProduksi(): Pesanan
    {
        $p = $this->buat(['ongkir' => '25.000']);
        $this->catatDp($p)->assertSessionHasNoErrors();

        return $p->fresh();
    }

    // ── Total tagihan ──────────────────────────────────

    public function test_total_tagihan_subtotal_ditambah_ongkir_tanpa_diskon(): void
    {
        // Field diskon dari form lama diabaikan (tidak divalidasi, tidak disimpan)
        $p = $this->buat(['ongkir' => '25.000', 'diskon' => 'Rp 50.000']);

        $this->assertSame(2350000.0, $p->subtotal_produk);
        $this->assertSame(25000.0, (float) $p->ongkir);
        $this->assertSame(0.0, (float) $p->diskon);
        $this->assertSame(2375000.0, $p->total_tagihan);
        $this->assertSame('menunggu_dp', $p->status_produksi->value);
        $this->assertSame('belum_lunas', $p->status_bayar_invoice);

        // DP dibatasi total tagihan, bukan subtotal
        $this->catatDp($p, ['dp' => '2.375.001'])
            ->assertSessionHasErrors(['dp' => 'DP tidak boleh lebih dari total tagihan (Rp 2.375.000).']);
        $this->catatDp($p)->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(1375000.0, $p->sisa);
        $this->assertSame('belum_lunas', $p->status_bayar_invoice);

        // Teks SPP: ongkir & total, tanpa diskon
        $this->assertStringContainsString("🚚 Ongkir: Rp25.000\n💰 Total: Rp2.375.000", $p->teks_spp);
        $this->assertStringNotContainsString('Diskon', $p->teks_spp);

        // Pelunasan: sisa 0, nilai pelunasan = total tagihan − DP
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'qris'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(0.0, $p->sisa);
        $this->assertSame(1375000.0, $p->nilai_pelunasan);
        $this->assertSame('lunas', $p->status_bayar_invoice);
    }

    public function test_diskon_lama_di_database_diabaikan(): void
    {
        $p = Pesanan::factory()->status('menunggu_dp')->create(['total' => 500000, 'ongkir' => 20000, 'diskon' => 100000, 'dp' => 0]);

        $this->assertSame(520000.0, $p->total_tagihan);
        $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $p))->assertOk();
    }

    public function test_teks_spp_tanpa_ongkir_tetap_format_lama(): void
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

    // ── Validasi ongkir ────────────────────────────────

    public function test_validasi_ongkir(): void
    {
        $kirim = fn (array $ubah) => $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data($ubah));

        $kirim(['ongkir' => '-5000'])->assertSessionHasErrors('ongkir');
        $kirim(['ongkir' => 'abc'])->assertSessionHasErrors('ongkir');
        $this->assertSame(0, Pesanan::count());

        // Diskon apa pun tidak lagi divalidasi; ongkir kosong = 0
        $kirim(['diskon' => '9.999.999', 'ongkir' => ''])->assertSessionHasNoErrors();
        $p = Pesanan::latest('id')->first();
        $this->assertSame(2350000.0, $p->total_tagihan);
        $this->assertSame(0.0, (float) $p->ongkir);
        $this->assertSame(0.0, (float) $p->diskon);
    }

    public function test_edit_tanpa_field_ongkir_mempertahankan_nilai_lama(): void
    {
        $p = $this->buatProduksi();

        $data = $this->data(['dp' => '1.000.000', 'nomor_spp' => '176']);
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), $data)->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(25000.0, (float) $p->ongkir);
        $this->assertSame(MetodeBayar::TransferBca, $p->metode_dp);

        // Ubah ongkir lewat edit → tercatat di riwayat
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'ongkir' => '30.000'])->assertSessionHasNoErrors();
        $this->assertSame(30000.0, (float) $p->fresh()->ongkir);
        $this->assertDatabaseHas('pesanan_riwayat', ['pesanan_id' => $p->id, 'catatan' => 'Ongkir diubah oleh Rina CS: Rp 25.000 → Rp 30.000']);

        // DP melebihi total tagihan baru ditolak
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), array_replace_recursive($data, [
            'item' => [0 => ['harga_satuan' => '1.000'], 1 => ['harga_satuan' => '1.000']],
        ]))->assertSessionHasErrors(['dp' => 'DP (Rp 1.000.000) tidak boleh lebih dari total tagihan (Rp 630.000).']);
    }

    public function test_ongkir_dikunci_setelah_lunas(): void
    {
        $p = $this->buatProduksi();
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'cash'])->assertSessionHasNoErrors();

        $data = $this->data(['dp' => '1.000.000', 'nomor_spp' => '176', 'tanggal_lunas' => '2026-11-23']);
        $pesan = 'Ongkir dikunci karena SPP sudah lunas. Kosongkan tanggal lunas dulu bila perlu dikoreksi.';

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'ongkir' => '30.000'])
            ->assertSessionHasErrors(['ongkir' => $pesan]);

        // Nilai sama: boleh disimpan (diskon yang masih terkirim diabaikan)
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'ongkir' => '25.000', 'diskon' => '50.000'])
            ->assertSessionHasNoErrors();
        $this->assertSame(0.0, (float) $p->fresh()->diskon);

        // Tanggal lunas dikosongkan: boleh dikoreksi, metode pelunasan ikut kosong
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $p), [...$data, 'tanggal_lunas' => '', 'ongkir' => '30.000'])
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

        $data  = $this->data(['dp' => '1.000.000', 'nomor_spp' => '176', 'tanggal_lunas' => '2026-11-23', 'ongkir' => '25.000']);
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

        // Kelebihan bayar: harga diturunkan setelah DP (data lama)
        $q = Pesanan::factory()->create(['total' => 400000, 'dp' => 425000]);
        $this->assertSame(0.0, $q->sisa);
        $this->assertSame(25000.0, $q->kelebihan_bayar);
        $this->assertSame('belum_lunas', $q->status_bayar_invoice, 'LUNAS hanya dari tanggal_lunas, bukan DP ≥ total.');

        // Belum bayar
        $r = Pesanan::factory()->status('menunggu_dp')->create(['total' => 500000]);
        $this->assertSame('belum_lunas', $r->status_bayar_invoice);
        $this->assertNull($r->metode_dp);

        foreach ([$p, $q, $r] as $x) {
            $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $x))->assertOk();
        }
    }

    public function test_rincian_invoice_belum_lunas_dp_dan_lunas(): void
    {
        $data = fn (Pesanan $p) => (fn () => $this->dataView($p))->call(app(InvoicePdf::class));
        $baris = fn (Pesanan $p) => array_map(
            fn ($b) => trim($b['label'] . ' ' . ($b['info'] ?? '')) . ' = ' . $b['nilai'],
            $data($p)['ringkasan']['baris'],
        );

        // BELUM LUNAS, belum DP: DP minimal 50% dan sisa = total − DP minimal
        $p = $this->buat();
        $v = $data($p);
        $this->assertSame('belum_lunas', $v['statusBayar']);
        $this->assertSame('BELUM LUNAS', $v['labelStatus']);
        $this->assertSame('21 NOVEMBER 2026', $v['tanggal']);
        $this->assertSame('0822-2894-6660', $v['kepada']['wa']);
        $this->assertSame([
            'Subtotal produk = Rp2.350.000',
            'Biaya ongkir = -',
            'Total tagihan = Rp2.350.000',
            'DP min 50% = Rp1.175.000',
            'Sisa pelunasan = Rp1.175.000',
        ], $baris($p));
        $this->assertSame('Paperbag · 25×10×30cm', $v['item'][0]['judul']);
        $this->assertSame('Rp 4.300', $v['item'][0]['harga']);

        // BELUM LUNAS sesudah DP
        $p = $this->buatProduksi();
        $this->assertSame('belum_lunas', $data($p)['statusBayar']);
        $this->assertSame([
            'Subtotal produk = Rp2.350.000',
            'Biaya ongkir = Rp25.000',
            'Total tagihan = Rp2.375.000',
            'DP dibayar Transfer BCA · 23 Nov = Rp1.000.000',
            'Sisa pelunasan = Rp1.375.000',
        ], $baris($p));

        // LUNAS
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'qris'])->assertSessionHasNoErrors();
        $p = $p->fresh();
        $this->assertSame('LUNAS', $data($p)['labelStatus']);
        // LUNAS: tanpa keterangan bawah ("DP minimal 50%…") dan tanpa ajakan kirim bukti transfer
        $this->assertSame('', $data($p)['keterangan']);
        $this->assertFalse($data($p)['mohonBukti']);
        $this->assertTrue($data($this->buat(['nama_customer' => 'Ismun']))['mohonBukti']);
        $this->assertSame([
            'Subtotal produk = Rp2.350.000',
            'Biaya ongkir = Rp25.000',
            'Total tagihan = Rp2.375.000',
            'DP dibayar Transfer BCA · 23 Nov = Rp1.000.000',
            'Pelunasan QRIS · 23 Nov = Rp1.375.000',
            'Sisa pelunasan = Rp0',
        ], $baris($p));
    }

    public function test_format_rekening_dan_kontak_invoice(): void
    {
        // Nomor rekening tampil persis seperti diketik admin (tidak dikelompokkan otomatis)
        $this->assertSame('300801017388537', InvoicePdf::nomorRekening('300801017388537'));
        $this->assertSame('8023011164', InvoicePdf::nomorRekening(' 8023011164 '));
        $this->assertSame('3008 0101 7388 537', InvoicePdf::nomorRekening('3008 0101 7388 537'));
        $this->assertSame('0838-2920-8316', InvoicePdf::waLokal('6283829208316'));
        $this->assertSame('@sengganipaperbag', config('usaha.instagram'));

        $v = (fn () => $this->dataView(Pesanan::factory()->create(['total' => 100000])))->call(app(InvoicePdf::class));
        $this->assertSame('a.n. SENGGANI', $v['qrisKeterangan']);
        $this->assertSame('8023011164', $v['rekening'][0]['nomor']);
        $this->assertSame('3008 0101 7388 537', $v['rekening'][1]['nomor']);
        $this->assertNull($v['logoKop'], 'Logo kop disembunyikan bila stempel belum diunggah.');
    }

    // ── Pengaturan Invoice ─────────────────────────────

    public function test_nilai_awal_pengaturan_dari_migrasi(): void
    {
        $set = app(PengaturanInvoice::class)->semua();

        $this->assertSame('Putri Maulidina', $set['penandatangan_nama']);
        $this->assertSame(50, $set['persen_dp']);
        $this->assertSame('DP minimal 50%. Pengiriman barang setelah pelunasan.', $set['keterangan_bawah']);
        $this->assertSame('a.n. SENGGANI', $set['qris_keterangan']);
        $this->assertSame([
            ['bank' => 'BCA', 'nomor' => '8023011164', 'atas_nama' => 'Herni Pri Nugrahani'],
            ['bank' => 'BRI', 'nomor' => '3008 0101 7388 537', 'atas_nama' => 'Herni Pri Nugrahani'],
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
                ['bank' => 'BCA', 'nomor' => ' 8023011164 ', 'atas_nama' => 'Herni Pri Nugrahani'],
                ['bank' => '', 'nomor' => '', 'atas_nama' => ''],
                ['bank' => 'BRI', 'nomor' => '3008 0101 7388 537', 'atas_nama' => 'Senggani'],
            ],
            'qris_keterangan'  => 'a.n. SENGGANI',
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
            ['bank' => 'BRI', 'nomor' => '3008 0101 7388 537', 'atas_nama' => 'Senggani'],
        ], $set['rekening']);

        // Hanya angka dan spasi: titik/tanda hubung ditolak (tidak lagi dibuang diam-diam)
        foreach (['802 301-1164', '1234.5678.90', '12 34', str_repeat('1 ', 21)] as $salah) {
            $this->sebagai($this->admin)->put(route('admin.pengaturan.invoice.update'), [
                'persen_dp' => '50',
                'rekening'  => [['bank' => 'BCA', 'nomor' => $salah, 'atas_nama' => 'X']],
            ])->assertSessionHasErrors('rekening.0.nomor');
        }

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
            ->assertOk()->assertSee(route('admin.pesanan.invoice', $p), false)->assertSee('Rp 2.375.000');
        $this->sebagai($this->produksi)->get(route('admin.pesanan.show', $p))
            ->assertOk()->assertDontSee(route('admin.pesanan.invoice', $p), false);

        // Form edit memuat field ongkir/metode
        $this->sebagai($this->admin)->get(route('admin.pesanan.edit', $p))->assertOk()
            ->assertSee('name="ongkir"', false)
            ->assertSee('name="metode_dp"', false)->assertSee('name="metode_lunas"', false);
        $this->sebagai($this->admin)->get(route('admin.pesanan.create'))->assertOk()->assertSee('name="ongkir"', false);

        $this->assertInstanceOf(Pengaturan::class, Pengaturan::where('kunci', 'invoice.rekening')->first());
    }

    // ── Perbaikan temuan QA invoice ────────────────────

    public function test_teks_spp_memuat_baris_dp_dan_lunas(): void
    {
        $p = $this->buatProduksi();
        $teks = $p->teks_spp;
        $this->assertStringContainsString("💰 Total: Rp2.375.000\n💳 DP: Rp1.000.000 (Transfer BCA, 23 Nov)", $teks);
        $this->assertStringNotContainsString('Lunas', $teks);

        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'qris'])->assertSessionHasNoErrors();
        $this->assertStringContainsString("💳 DP: Rp1.000.000 (Transfer BCA, 23 Nov)\n✅ Lunas: Rp1.375.000 (QRIS, 23 Nov)\n\n", $p->fresh()->teks_spp);

        // Tanpa ongkir: blok hanya berisi DP
        $q = $this->buat(['nama_customer' => 'Ismun']);
        $this->catatDp($q, ['nomor_spp' => '177', 'metode_dp' => 'cash'])->assertSessionHasNoErrors();
        $teks = $q->fresh()->teks_spp;
        $this->assertStringContainsString("\n\n💳 DP: Rp1.000.000 (Cash, 23 Nov)\n\n", $teks);
        $this->assertStringNotContainsString('💰 Total', $teks);

        // Data lama: metode & tanggal DP kosong → tanpa kurung; lunas tanpa metode → nominal + tanggal saja
        $q->refresh()->update(['metode_dp' => null, 'tanggal_dp' => null, 'tanggal_lunas' => '2026-11-22', 'metode_lunas' => null]);
        $this->assertStringContainsString("💳 DP: Rp1.000.000\n✅ Lunas: Rp1.350.000 (22 Nov)\n", $q->fresh()->teks_spp);

        // DP 0 & belum lunas: tidak ada baris DP/Lunas
        $q->update(['dp' => 0, 'tanggal_lunas' => null]);
        $teks = $q->fresh()->teks_spp;
        $this->assertStringNotContainsString('💳', $teks);
        $this->assertStringNotContainsString('✅', $teks);
    }

    public function test_metode_wajib_bila_dp_atau_lunas_diubah_lewat_edit(): void
    {
        $p    = $this->buatProduksi();
        $data = $this->data(['dp' => '1.000.000', 'nomor_spp' => '176', 'ongkir' => '25.000']);
        $url  = route('admin.pesanan.update', $p);

        // Tanggal lunas diisi lewat Edit tanpa metode → ditolak
        $this->sebagai($this->admin)->put($url, [...$data, 'tanggal_lunas' => '2026-11-23'])
            ->assertSessionHasErrors(['metode_lunas' => 'Pilih metode pelunasan karena tanggal lunas diisi atau diubah.']);
        $this->sebagai($this->admin)->put($url, [...$data, 'tanggal_lunas' => '2026-11-23', 'metode_lunas' => ''])
            ->assertSessionHasErrors('metode_lunas');
        $this->assertNull($p->fresh()->tanggal_lunas);

        // DP diubah dengan metode dikosongkan → ditolak; dengan metode → boleh
        $this->sebagai($this->admin)->put($url, [...$data, 'dp' => '1.200.000', 'metode_dp' => ''])
            ->assertSessionHasErrors(['metode_dp' => 'Pilih metode pembayaran DP karena nominal DP diisi atau diubah.']);
        $this->sebagai($this->admin)->put($url, [...$data, 'dp' => '1.200.000', 'metode_dp' => 'qris'])->assertSessionHasNoErrors();
        $this->assertSame(MetodeBayar::Qris, $p->fresh()->metode_dp);

        // Data lama (metode kosong): selama DP & tanggal lunas tidak berubah, tetap boleh disimpan
        $p->update(['metode_dp' => null, 'tanggal_lunas' => '2026-11-22', 'metode_lunas' => null]);
        $lama = [...$data, 'dp' => '1.200.000', 'tanggal_lunas' => '2026-11-22'];
        $this->sebagai($this->admin)->put($url, [...$lama, 'metode_dp' => '', 'metode_lunas' => ''])->assertSessionHasNoErrors();
        $this->sebagai($this->admin)->put($url, $lama)->assertSessionHasNoErrors();

        // Data lama: tanggal lunas diubah → metode pelunasan wajib
        $this->sebagai($this->admin)->put($url, [...$lama, 'tanggal_lunas' => '2026-11-23'])->assertSessionHasErrors('metode_lunas');
        $this->sebagai($this->admin)->put($url, [...$lama, 'tanggal_lunas' => '2026-11-23', 'metode_lunas' => 'cash'])->assertSessionHasNoErrors();
        $this->assertSame(MetodeBayar::Cash, $p->fresh()->metode_lunas);

        // Data lama: DP diubah (setelah tanggal lunas dikosongkan) → metode DP wajib
        $this->sebagai($this->admin)->put($url, [...$lama, 'tanggal_lunas' => '', 'dp' => '1.100.000'])->assertSessionHasErrors('metode_dp');
        $this->sebagai($this->admin)->put($url, [...$lama, 'tanggal_lunas' => '', 'dp' => '1.100.000', 'metode_dp' => 'transfer_bri'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1100000.0, (float) $p->fresh()->dp);
    }

    public function test_dp_dikunci_setelah_lunas(): void
    {
        $p = $this->buatProduksi();
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'cash'])->assertSessionHasNoErrors();

        $data = $this->data(['nomor_spp' => '176', 'tanggal_lunas' => '2026-11-23', 'ongkir' => '25.000']);
        $url  = route('admin.pesanan.update', $p);

        $this->sebagai($this->admin)->put($url, [...$data, 'dp' => '1.200.000', 'metode_dp' => 'transfer_bca'])
            ->assertSessionHasErrors(['dp' => 'DP dikunci karena SPP sudah lunas. Kosongkan tanggal lunas dulu bila perlu dikoreksi.']);
        $this->assertSame(1000000.0, (float) $p->fresh()->dp);

        // DP sama: boleh
        $this->sebagai($this->admin)->put($url, [...$data, 'dp' => '1.000.000'])->assertSessionHasNoErrors();

        // Tanggal lunas dikosongkan: DP boleh dikoreksi
        $this->sebagai($this->admin)->put($url, [...$data, 'tanggal_lunas' => '', 'dp' => '1.200.000', 'metode_dp' => 'transfer_bca'])
            ->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(1200000.0, (float) $p->dp);
        $this->assertNull($p->tanggal_lunas);
    }

    // ── Temuan QA akhir: lunas otomatis, cache PDF, token panjang, posisi blok bawah ──

    public function test_lunas_otomatis_saat_catat_dp_menutup_tagihan(): void
    {
        $p = $this->buat(['ongkir' => '25.000']); // total tagihan 2.375.000
        $this->catatDp($p, ['dp' => '2.375.000', 'metode_dp' => 'qris', 'tanggal_dp' => '2026-11-22'])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('2026-11-22', $p->tanggal_lunas->toDateString(), 'Tanggal lunas = tanggal DP.');
        $this->assertSame(MetodeBayar::Qris, $p->metode_lunas, 'Metode lunas = metode DP.');
        $this->assertSame('lunas', $p->status_pembayaran);
        $this->assertSame('lunas', $p->status_bayar_invoice);
        $this->assertSame(0.0, $p->sisa);
        $this->assertSame(1, $p->riwayat()->where('catatan', 'Lunas otomatis: DP menutup seluruh tagihan')->count());
        $this->assertStringContainsString('✅ Lunas: DP menutup seluruh tagihan (QRIS, 22 Nov)', $p->teks_spp);

        // DP sebagian: tidak lunas otomatis
        $q = $this->buat(['nama_customer' => 'Ismun']);
        $this->catatDp($q, ['nomor_spp' => '177'])->assertSessionHasNoErrors();
        $this->assertNull($q->fresh()->tanggal_lunas);
        $this->assertSame('belum_lunas', $q->fresh()->status_bayar_invoice);
    }

    public function test_lunas_otomatis_saat_dp_diubah_lewat_edit_atau_harga_turun(): void
    {
        $p    = $this->buatProduksi(); // tagihan 2.375.000, DP 1.000.000 (Transfer BCA, 23 Nov)
        $data = $this->data(['nomor_spp' => '176', 'ongkir' => '25.000']);
        $url  = route('admin.pesanan.update', $p);

        $this->sebagai($this->admin)->put($url, [...$data, 'dp' => '2.375.000', 'metode_dp' => 'transfer_bri'])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame('2026-11-23', $p->tanggal_lunas->toDateString());
        $this->assertSame(MetodeBayar::TransferBri, $p->metode_lunas);
        $this->assertSame('lunas', $p->status_bayar_invoice);
        $this->assertSame(1, $p->riwayat()->where('catatan', 'Lunas otomatis: DP menutup seluruh tagihan')->count());

        // Tanggal lunas sengaja dikosongkan tanpa mengubah DP/total: tidak diisi ulang
        $this->sebagai($this->admin)->put($url, [...$data, 'dp' => '2.375.000', 'tanggal_lunas' => ''])->assertSessionHasNoErrors();
        $this->assertNull($p->fresh()->tanggal_lunas);
        $this->assertSame('belum_lunas', $p->fresh()->status_bayar_invoice);

        // Isi Harga menurunkan total tagihan sampai sama dengan DP → lunas otomatis
        $q = $this->buat(['nama_customer' => 'Ismun', 'ongkir' => '25.000']);
        $this->catatDp($q, ['nomor_spp' => '177'])->assertSessionHasNoErrors();
        $ids = $q->detailPesanan()->pluck('id')->all();
        $this->sebagai($this->admin)->post(route('admin.pesanan.harga', $q), ['harga' => [$ids[0] => '1.500', $ids[1] => '2.250']])
            ->assertSessionHasNoErrors();
        $q->refresh();
        $this->assertSame(1000000.0, $q->total_tagihan);
        $this->assertSame('lunas', $q->status_bayar_invoice);
        $this->assertSame(MetodeBayar::TransferBca, $q->metode_lunas);
    }

    public function test_cache_pdf_dipakai_ulang_dan_dibuang_saat_data_berubah(): void
    {
        $p       = $this->buatProduksi();
        $invoice = app(InvoicePdf::class);
        $disk    = Storage::disk(PengaturanInvoice::DISK);
        $folder  = InvoicePdf::folderCache($p);

        $pertama = $invoice->isi($p->fresh());
        $this->assertStringStartsWith('%PDF', $pertama);
        $this->assertCount(1, $disk->files($folder));
        $path = $disk->files($folder)[0];

        // Tanpa perubahan: isi cache dikirim apa adanya (tidak dirender ulang)
        $disk->put($path, '%PDF-dari-cache');
        $this->assertSame('%PDF-dari-cache', $invoice->isi($p->fresh()));
        $res = $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $p))->assertOk();
        $this->assertSame('%PDF-dari-cache', $res->getContent());
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));

        // Data pesanan berubah (catat lunas): kunci baru, cache lama dihapus
        $kunciLama = $invoice->kunciCache($p->fresh());
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $p), ['metode_lunas' => 'qris'])->assertSessionHasNoErrors();
        $this->assertNotSame($kunciLama, $invoice->kunciCache($p->fresh()));
        $baru = $invoice->isi($p->fresh());
        $this->assertNotSame('%PDF-dari-cache', $baru);
        $this->assertSame([$folder . '/' . $invoice->kunciCache($p->fresh()) . '.pdf'], $disk->files($folder));

        // Item berubah, pengaturan teks berubah, gambar baru: masing-masing kunci baru
        $kunci = $invoice->kunciCache($p->fresh());
        $p->detailPesanan()->first()->update(['catatan' => 'Logo emas']);
        $this->assertNotSame($kunci, $kunci = $invoice->kunciCache($p->fresh()));
        Pengaturan::simpan('invoice.keterangan_bawah', 'Terima kasih.');
        $this->assertNotSame($kunci, $kunci = $invoice->kunciCache($p->fresh()));
        app(PengaturanInvoice::class)->simpanGambar('stempel', UploadedFile::fake()->image('stempel.png', 533, 123));
        $this->assertNotSame($kunci, $kunci = $invoice->kunciCache($p->fresh()));
        $this->sebagai($this->admin)->post(route('admin.pesanan.catatan', $p), ['catatan' => 'Pembayaran sudah dicek'])->assertSessionHasNoErrors();
        $this->assertNotSame($kunci, $invoice->kunciCache($p->fresh()), 'Riwayat baru ikut mengubah kunci.');

        // Data contoh (belum tersimpan) tidak di-cache
        $this->assertNull($invoice->kunciCache(new Pesanan(['nama_customer' => 'Contoh'])));
    }

    public function test_token_panjang_tidak_melebarkan_tabel_dan_blok_bawah_di_batas(): void
    {
        app(PengaturanInvoice::class)->simpanGambar('stempel', UploadedFile::fake()->image('stempel.png', 533, 123));

        $item = fn (int $n, array $u = []) => array_map(fn ($i) => [
            'jenis_packaging' => 'Paperbag', 'jenis_bahan' => 'Kraft', 'jumlah' => '500', 'harga_satuan' => '1.000', ...$u,
        ], range(1, $n));

        $panjang = [
            'jenis_bahan' => 'DuplexTripleCoatedPremiumGlossyLaminasiDoffSpotUV',
            'aksesori'    => 'TaliKurSatinMerahMarunPanjang40cmDobelSimpulMati',
            'catatan'     => 'https://drive.google.com/drive/folders/1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-desain-final-revisi',
        ];

        $invoice = app(InvoicePdf::class);
        $kasus = [
            'link panjang' => [$item(1, $panjang), 1],
            '1 item'       => [$item(1), 1],
            '6 item'       => [$item(6), null],
            '12 item'      => [$item(12), null],
        ];

        foreach ($kasus as $nama => [$baris, $halaman]) {
            $data = $this->data();
            $data['item'] = $baris;
            $data['nama_customer'] = 'NamaCustomerSangatPanjangTanpaSpasiSamaSekaliUntukUji';
            $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $data)->assertSessionHasNoErrors();
            $p = Pesanan::latest('id')->first();

            $pdf = $invoice->buat($p);
            $ukur = $invoice->ukuranTerakhir;

            // Tabel tidak melebar: tepi kanan baris tetap di dalam margin kanan (210 − 14 mm)
            $this->assertLessThanOrEqual(196.5, $ukur['kanan_tabel'], "Tepi kanan tabel ({$nama}).");

            // Blok bawah selalu berakhir di ±252 mm pada halaman terakhir
            $this->assertEqualsWithDelta(InvoicePdf::BATAS_BAWAH_BLOK, $ukur['bawah'], 1.5, "Tepi bawah blok bawah ({$nama}).");
            $jumlahHalaman = $pdf->getDomPDF()->getCanvas()->get_page_count();
            $this->assertSame($jumlahHalaman, $ukur['halaman'], "Blok bawah di halaman terakhir ({$nama}).");
            if ($halaman) {
                $this->assertSame($halaman, $jumlahHalaman, "Jumlah halaman ({$nama}).");
            }
        }
    }

    public function test_url_invoice_ditolak_dialihkan_ke_detail_spp(): void
    {
        $hitung = $this->buat(['item' => [['harga_satuan' => ''], ['harga_satuan' => '']]]);
        $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $hitung))
            ->assertRedirect(route('admin.pesanan.show', $hitung))
            ->assertSessionHas('error', 'Invoice tersedia setelah semua harga terisi.');

        foreach (['tidak_jadi', 'dibatalkan'] as $status) {
            $x = Pesanan::factory()->status($status)->create(['total' => 100000]);
            $this->sebagai($this->admin)->get(route('admin.pesanan.invoice', $x))
                ->assertRedirect(route('admin.pesanan.show', $x))
                ->assertSessionHas('error', 'Invoice tidak tersedia untuk SPP berstatus ' . $x->status_produksi->label() . ' karena tidak ada tagihan.');
        }

        // Produksi tetap dialihkan ke daftar SPP
        $this->sebagai($this->produksi)->get(route('admin.pesanan.invoice', $hitung))
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'Invoice hanya untuk admin.');
    }
}

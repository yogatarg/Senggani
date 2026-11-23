<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Pesanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Perbaikan hasil QA modul Pesanan/SPP: lepas customer saat edit, SPP terhapus/tidak ada,
 * 403 di panel, format nomor SPP, tanggal lunas, file bukan gambar, ukuran berkoma, label "SPP".
 */
class PesananPerbaikanQaTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Admin $produksi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(9, 0));

        $this->admin    = Admin::factory()->create();
        $this->produksi = Admin::factory()->produksi()->create();
    }

    private function sebagai(Admin $pengguna): static
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_id' => $pengguna->id]);
    }

    private function data(array $ubah = []): array
    {
        return array_replace_recursive([
            'nomor_spp'      => (string) Pesanan::saranNomorSpp(),
            'nama_customer'  => 'Ismun',
            'nomor_whatsapp' => '0812-3456-7890',
            'alamat'         => 'Jl. Melati 5',
            'kota'           => 'Surakarta',
            'tanggal_order'  => '2026-11-10',
            'deadline'       => '2026-11-30',
            'jenis_deadline' => 'kirim',
            'item'           => [[
                'jenis_packaging' => 'Paperbag', 'panjang' => '33', 'lebar' => '29', 'tinggi' => '15',
                'jenis_bahan' => 'Kraft', 'jumlah' => '500', 'harga_satuan' => '4.300',
            ]],
        ], $ubah);
    }

    private function buat(array $ubah = []): Pesanan
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data($ubah))
            ->assertSessionHasNoErrors()->assertRedirect();

        return Pesanan::latest('id')->firstOrFail();
    }

    private function dataEdit(Pesanan $pesanan, array $ubah = []): array
    {
        return array_replace_recursive($this->data(['nomor_spp' => (string) $pesanan->nomor_spp]), $ubah);
    }

    /** Catat DP & masukkan ke produksi (admin). */
    private function masukProduksi(Pesanan $pesanan, int $nomor = 1): Pesanan
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.dp', $pesanan), [
            'dp' => '100.000', 'nomor_spp' => (string) $nomor, 'konfirmasi_nomor' => '1', 'metode_dp' => 'transfer_bri',
        ])->assertSessionHasNoErrors();

        return $pesanan->fresh();
    }

    // ── 1. Lepas customer saat edit ────────────────────

    public function test_edit_dengan_lepas_customer_membuat_customer_baru_dari_isian(): void
    {
        $pesanan = $this->buat();
        $lama    = $pesanan->customer_id;

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $this->dataEdit($pesanan, [
            'customer_id' => '', 'lepas_customer' => '1',
            'nama_customer' => 'Budi Baru', 'nomor_whatsapp' => '0813 1111 2222',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.pesanan.show', $pesanan));

        $pesanan->refresh();
        $this->assertNotSame($lama, $pesanan->customer_id);
        $this->assertSame('Budi Baru', $pesanan->customer->nama);
        $this->assertSame('6281311112222', $pesanan->customer->nomor_whatsapp);
        $this->assertSame('Ismun', Customer::find($lama)->nama); // customer lama tidak diubah
    }

    public function test_edit_dengan_lepas_customer_memakai_pemilik_wa_bila_ada(): void
    {
        $pemilik = Customer::create(['nama' => 'Toko Sebelah', 'nomor_whatsapp' => '6281399990000']);
        $pesanan = $this->buat();

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $this->dataEdit($pesanan, [
            'customer_id' => '', 'lepas_customer' => '1', 'nomor_whatsapp' => '0813-9999-0000',
        ]))->assertSessionHasNoErrors()->assertSessionHas('info_customer');

        $this->assertSame($pemilik->id, $pesanan->fresh()->customer_id);
    }

    public function test_edit_tanpa_lepas_customer_tetap_customer_lama(): void
    {
        $pesanan = $this->buat();
        $lama    = $pesanan->customer_id;

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $this->dataEdit($pesanan, [
            'customer_id' => '', 'nama_customer' => 'Budi Baru', 'nomor_whatsapp' => '0813 1111 2222',
        ]))->assertSessionHasNoErrors();

        $this->assertSame($lama, $pesanan->fresh()->customer_id);
        $this->assertSame(1, Customer::count());
    }

    // ── 2. SPP terhapus / tidak ada ────────────────────

    public function test_aksi_pada_spp_terhapus_diarahkan_ke_daftar_dengan_pesan(): void
    {
        $pesanan = Pesanan::factory()->create(['nomor_spp' => 176]);
        $pesanan->delete();
        $pesan = 'SPP #176 sudah dihapus oleh admin.';

        $html = [
            ['get', route('admin.pesanan.show', $pesanan)],
            ['get', route('admin.pesanan.edit', $pesanan)],
            ['post', route('admin.pesanan.status', $pesanan)],
            ['post', route('admin.pesanan.catatan', $pesanan)],
            ['post', route('admin.pesanan.batal', $pesanan)],
            ['post', route('admin.pesanan.pulihkan', $pesanan)],
            ['post', route('admin.pesanan.lunas', $pesanan)],
            ['post', route('admin.pesanan.harga', $pesanan)],
            ['post', route('admin.pesanan.dp', $pesanan)],
            ['post', route('admin.pesanan.tidak_jadi', $pesanan)],
            ['put', route('admin.pesanan.update', $pesanan)],
        ];
        foreach ($html as [$metode, $url]) {
            $this->sebagai($this->produksi)->{$metode}($url, [])
                ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', $pesan);
        }

        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.status', $pesanan), ['status_lama' => 'antrian', 'status_baru' => 'proses'])
            ->assertStatus(410)->assertExactJson(['pesan' => $pesan]);
        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.catatan', $pesanan), ['catatan' => 'x'])
            ->assertStatus(410)->assertJson(['pesan' => $pesan]);
    }

    public function test_file_spp_terhapus_juga_ramah(): void
    {
        $pesanan = Pesanan::factory()->create(['nomor_spp' => 9]);
        $pesanan->delete();

        $this->sebagai($this->admin)->get(route('admin.pesanan.file.lihat', [$pesanan->id, 1]))
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'SPP #9 sudah dihapus oleh admin.');
    }

    public function test_id_spp_tidak_ada_diarahkan_ke_daftar(): void
    {
        $this->sebagai($this->produksi)->get('/admin/pesanan/999999')
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'SPP tidak ditemukan.');
        $this->sebagai($this->produksi)->get('/admin/pesanan/abc')
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'SPP tidak ditemukan.');
        $this->sebagai($this->produksi)->postJson('/admin/pesanan/999999/catatan', ['catatan' => 'x'])
            ->assertNotFound()->assertExactJson(['pesan' => 'SPP tidak ditemukan.']);
    }

    public function test_halaman_error_publik_tidak_berubah(): void
    {
        $this->get('/produk/tidak-ada')->assertNotFound();
        // Tamu tidak mendapat info SPP apa pun
        $this->get('/admin/pesanan/999999')->assertSessionMissing('error');
    }

    // ── 3. 403 di panel ────────────────────────────────

    public function test_produksi_ke_halaman_khusus_admin_diarahkan_dan_aksi_403_berbahasa_indonesia(): void
    {
        $pesanan = Pesanan::factory()->create();

        $this->sebagai($this->produksi)->get(route('admin.pesanan.create'))
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'Halaman ini khusus admin.');
        $this->sebagai($this->produksi)->get(route('admin.pesanan.edit', $pesanan))
            ->assertRedirect(route('admin.pesanan.index'))->assertSessionHas('error', 'Halaman ini khusus admin.');

        // Form biasa (bukan JSON): redirect back dengan flash, bukan JSON mentah
        $this->sebagai($this->produksi)->from(route('admin.pesanan.create'))->post(route('admin.pesanan.store'), [])
            ->assertRedirect(route('admin.pesanan.create'))->assertSessionHas('error', 'Aksi ini khusus admin.');
        $this->sebagai($this->produksi)->from(route('admin.pesanan.show', $pesanan))->delete(route('admin.pesanan.destroy', $pesanan))
            ->assertRedirect(route('admin.pesanan.show', $pesanan))->assertSessionHas('error', 'Aksi ini khusus admin.');
        $this->sebagai($this->produksi)->postJson(route('admin.pesanan.store'), [])
            ->assertForbidden()->assertExactJson(['pesan' => 'Aksi ini khusus admin.']);
        $this->sebagai($this->produksi)->putJson(route('admin.pesanan.update', $pesanan), [])
            ->assertForbidden()->assertExactJson(['pesan' => 'Aksi ini khusus admin.']);
        $this->sebagai($this->produksi)->getJson(route('admin.pesanan.create'))
            ->assertForbidden()->assertExactJson(['pesan' => 'Halaman ini khusus admin.']);
    }

    // ── 4. Nomor SPP ───────────────────────────────────

    public function test_nomor_spp_format_aneh_ditolak(): void
    {
        $dp = fn (Pesanan $p, string $nomor) => $this->sebagai($this->admin)
            ->post(route('admin.pesanan.dp', $p), ['dp' => '100.000', 'nomor_spp' => $nomor, 'metode_dp' => 'cash']);

        $a = $this->buat();
        foreach (['12.5', '1e3', '1.000', '17a', '-5x'] as $nomor) {
            $dp($a, $nomor)->assertSessionHasErrors(['nomor_spp' => 'Nomor SPP harus angka bulat, mis. 176.']);
        }
        $this->assertNull($a->fresh()->nomor_spp);

        $dp($a, 'SPP #1')->assertSessionHasNoErrors();
        $this->assertSame(1, $a->fresh()->nomor_spp);
        $b = $this->buat();
        $dp($b, '#2')->assertSessionHasNoErrors();
        $this->assertSame(2, $b->fresh()->nomor_spp);
        $c = $this->buat();
        $dp($c, ' 3 ')->assertSessionHasNoErrors();
        $this->assertSame(3, $c->fresh()->nomor_spp);
    }

    // ── 5. Tanggal lunas ───────────────────────────────

    public function test_tanggal_lunas_tidak_boleh_sebelum_tanggal_spp(): void
    {
        // Lewat form edit: hanya untuk SPP yang sudah masuk produksi
        $pesanan = $this->masukProduksi($this->buat());
        $edit = fn (string $tgl) => $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan),
            $this->dataEdit($pesanan, ['dp' => '100.000', 'tanggal_lunas' => $tgl, 'metode_lunas' => 'cash']));

        $edit('2026-11-09')->assertSessionHasErrors(['tanggal_lunas' => 'Tanggal lunas tidak boleh sebelum tanggal SPP.']);
        $edit('2026-11-19')->assertSessionHasErrors(['tanggal_lunas' => 'Tanggal lunas tidak boleh setelah hari ini.']);
        $edit('2026-11-10')->assertSessionHasNoErrors();
        $this->assertSame('2026-11-10', $pesanan->fresh()->tanggal_lunas->toDateString());

        $lain = Pesanan::factory()->create(['tanggal_order' => '2026-11-15', 'total' => 100000]);
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $lain), ['tanggal_lunas' => '2026-11-14', 'metode_lunas' => 'cash'])
            ->assertSessionHasErrors(['tanggal_lunas' => 'Tanggal lunas tidak boleh sebelum tanggal SPP (15 November 2026).']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.lunas', $lain), ['tanggal_lunas' => '2026-11-15', 'metode_lunas' => 'cash'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-11-15', $lain->fresh()->tanggal_lunas->toDateString());
    }

    // ── 6. File bukan gambar ───────────────────────────

    public function test_file_bukan_gambar_satu_galat_saja(): void
    {
        $respons = $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data([
            'mockup'    => [UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf')],
            'referensi' => [UploadedFile::fake()->create('catatan.txt', 1, 'text/plain')],
        ]));

        $respons->assertSessionHasErrors([
            'mockup.0'    => 'File harus berupa gambar JPG, PNG, atau WebP.',
            'referensi.0' => 'File harus berupa gambar JPG, PNG, atau WebP.',
        ]);
        $galat = session('errors')->getBag('default');
        $this->assertCount(1, $galat->get('mockup.0'));
        $this->assertCount(1, $galat->get('referensi.0'));
    }

    // ── 7. Ukuran berkoma ──────────────────────────────

    public function test_ukuran_dengan_koma_diterima_dan_bulat_tanpa_desimal(): void
    {
        $pesanan = $this->masukProduksi($this->buat(['item' => [['panjang' => '12,5', 'lebar' => ' 10 ', 'tinggi' => '7.25']]]));
        $item    = $pesanan->detailPesanan->first();

        $this->assertEquals(12.5, (float) $item->panjang);
        $this->assertSame('12,5 × 10 × 7,25 cm', $item->ukuran_teks);
        $this->assertStringContainsString('Ukuran: 12,5 x 10 x 7,25', $pesanan->teks_spp);

        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data(['item' => [['panjang' => '1,2,3']]]))
            ->assertSessionHasErrors('item.0.panjang');
    }

    // ── 8. Label "SPP" ─────────────────────────────────

    public function test_label_memakai_istilah_spp(): void
    {
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data(['tanggal_order' => '']))
            ->assertSessionHasErrors('tanggal_order');
        $this->assertStringContainsString('Tanggal SPP', session('errors')->first('tanggal_order'));

        $this->assertSame('Semua SPP', Pesanan::TAB['semua']);
        $this->assertSame('History SPP', Pesanan::TAB['history']);
        $this->assertSame('SPP terbaru', Pesanan::URUT['terbaru']);
    }
}

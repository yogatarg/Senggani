<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Pesanan;
use App\Services\PesananService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Nomor SPP, tabel customer, nama brand & jenis deadline, dan teks salin SPP.
 */
class SppCustomerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;
    private Admin $produksi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->setDate(2026, 11, 18)->setTime(9, 0));

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
            'nomor_spp'      => (string) Pesanan::saranNomorSpp(),
            'nama_customer'  => 'Ismun',
            'nama_brand'     => 'paperbag alea',
            'nomor_whatsapp' => '0812-3456-7890',
            'alamat'         => 'Jl. Melati 5',
            'kota'           => 'Surakarta',
            'tanggal_order'  => '2026-11-18',
            'deadline'       => '2026-11-30',
            'jenis_deadline' => 'kirim',
            'link_desain'    => 'https://drive.google.com/drive/folders/abc',
            'item'           => [[
                'jenis_packaging' => 'Paperbag', 'panjang' => '33', 'lebar' => '29', 'tinggi' => '15',
                'jenis_bahan' => 'kertas kraft putih', 'gramasi' => '150gr', 'finishing' => ['tanpa laminasi'],
                'jenis_cetak' => 'cetak 2 sisi sama', 'aksesori' => 'tali lilit',
                'jumlah' => '500', 'harga_satuan' => '4.300',
            ]],
        ], $ubah);
    }

    private function simpan(array $ubah = [])
    {
        return $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $this->data($ubah));
    }

    private function buat(array $ubah = []): Pesanan
    {
        $this->simpan($ubah)->assertSessionHasNoErrors()->assertRedirect();

        return Pesanan::latest('id')->firstOrFail();
    }

    // ── Nomor SPP: saran ───────────────────────────────

    public function test_saran_nomor_1_bila_belum_ada_pesanan_dan_terbesar_plus_1_termasuk_yang_dihapus(): void
    {
        $this->sebagai($this->admin)->get(route('admin.pesanan.create'))
            ->assertOk()->assertViewHas('saranNomor', 1)
            ->assertSee('name="nomor_spp" value="1"', false);

        $a = $this->buat(['nomor_spp' => '176']); // SPP pertama boleh langsung melanjutkan buku lama
        $this->assertSame(176, $a->nomor_spp);
        $this->assertSame('SPP #176', $a->nomor_spp_teks);
        $this->buat(['nomor_spp' => '177']);

        $this->sebagai($this->admin)->delete(route('admin.pesanan.destroy', Pesanan::where('nomor_spp', 177)->first()));

        $this->assertSame(178, Pesanan::saranNomorSpp());
        $this->sebagai($this->admin)->get(route('admin.pesanan.create'))->assertViewHas('saranNomor', 178);
    }

    public function test_nomor_boleh_diketik_dengan_pagar_dan_wajib_angka_minimal_1(): void
    {
        $this->buat(['nomor_spp' => '176']);
        $this->assertSame(177, $this->buat(['nomor_spp' => '#177'])->nomor_spp);

        $this->simpan(['nomor_spp' => ''])->assertSessionHasErrors(['nomor_spp' => 'Nomor SPP wajib diisi.']);
        $this->simpan(['nomor_spp' => '0'])->assertSessionHasErrors(['nomor_spp' => 'Nomor SPP minimal 1.']);
        $this->simpan(['nomor_spp' => 'abc'])->assertSessionHasErrors('nomor_spp');
    }

    // ── Nomor SPP: unik & loncatan ─────────────────────

    public function test_nomor_yang_sudah_dipakai_ditolak_dengan_nama_customer_termasuk_yang_dihapus(): void
    {
        $this->buat(['nomor_spp' => '176', 'nama_customer' => 'Bu Rina']);

        $this->simpan(['nomor_spp' => '176'])
            ->assertSessionHasErrors(['nomor_spp' => 'SPP #176 sudah dipakai oleh Bu Rina.']);

        $hapus = $this->buat(['nomor_spp' => '177', 'nama_customer' => 'Pak Budi', 'nomor_whatsapp' => '0813 1111 2222']);
        $hapus->delete();

        $this->simpan(['nomor_spp' => '177'])
            ->assertSessionHasErrors(['nomor_spp' => 'SPP #177 sudah dipakai oleh Pak Budi (SPP itu sudah dihapus).']);

        $this->assertSame(2, Pesanan::withTrashed()->count());
    }

    public function test_nomor_loncat_ditolak_tanpa_konfirmasi_dan_diterima_dengan_konfirmasi(): void
    {
        $this->buat(['nomor_spp' => '176']);

        // Saran 177; ambang 10 → 187 masih wajar, 188 loncat
        $this->simpan(['nomor_spp' => '1776'])
            ->assertSessionHasErrors(['konfirmasi_nomor' => 'Nomor SPP #1776 jauh dari nomor berikutnya (#177). Periksa lagi; bila memang benar, centang konfirmasi lalu simpan lagi.'])
            ->assertSessionDoesntHaveErrors('nomor_spp');
        $this->simpan(['nomor_spp' => '188'])->assertSessionHasErrors('konfirmasi_nomor');
        $this->simpan(['nomor_spp' => '17'])->assertSessionHasErrors('konfirmasi_nomor'); // terlalu jauh ke bawah
        $this->assertSame(1, Pesanan::count());

        $this->assertSame(187, $this->buat(['nomor_spp' => '187'])->nomor_spp);
        $this->assertSame(1776, $this->buat(['nomor_spp' => '1776', 'konfirmasi_nomor' => '1'])->nomor_spp);
        $this->assertSame(1777, Pesanan::saranNomorSpp());
    }

    public function test_peringatan_loncat_tetap_menampilkan_galat_lain_sekaligus(): void
    {
        $this->buat(['nomor_spp' => '176']);

        $this->simpan(['nomor_spp' => '999', 'dp' => '99.999.999'])
            ->assertSessionHasErrors(['konfirmasi_nomor', 'dp']);
    }

    public function test_form_menampilkan_kotak_konfirmasi_setelah_peringatan(): void
    {
        $this->buat(['nomor_spp' => '176']);

        $this->from(route('admin.pesanan.create'))->simpan(['nomor_spp' => '999'])->assertRedirect(route('admin.pesanan.create'));

        $this->sebagai($this->admin)->get(route('admin.pesanan.create'))
            ->assertSee('name="konfirmasi_nomor"', false)
            ->assertSee('jauh dari nomor berikutnya');
    }

    // ── Nomor SPP: ganti setelah tersimpan ─────────────

    public function test_ganti_nomor_wajib_konfirmasi_dan_tercatat_di_riwayat(): void
    {
        $pesanan = $this->buat(['nomor_spp' => '176']);
        $data = fn (array $u = []) => $this->data(['nomor_spp' => '177', ...$u]);

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $data())
            ->assertSessionHasErrors(['konfirmasi_ganti_nomor' => 'Nomor SPP akan diganti dari #176 menjadi #177. Produksi mungkin sudah menerima nomor lama lewat WhatsApp, jadi kabari mereka setelah menyimpan. Centang konfirmasi lalu simpan lagi bila yakin.']);
        $this->assertSame(176, $pesanan->fresh()->nomor_spp);

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $data(['konfirmasi_ganti_nomor' => '1']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'SPP #177 berhasil diperbarui.');

        $this->assertSame(177, $pesanan->fresh()->nomor_spp);
        $this->assertDatabaseHas('pesanan_riwayat', [
            'pesanan_id' => $pesanan->id,
            'catatan'    => 'Nomor SPP diubah dari #176 menjadi #177 oleh Rina CS',
            'admin_id'   => $this->admin->id,
        ]);

        // Edit tanpa mengganti nomor tidak butuh konfirmasi & tidak menambah riwayat nomor
        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $pesanan), $data())->assertSessionHasNoErrors();
        $this->assertSame(1, $pesanan->riwayat()->where('catatan', 'like', 'Nomor SPP diubah%')->count());
    }

    public function test_ganti_nomor_ke_nomor_terpakai_ditolak(): void
    {
        $a = $this->buat(['nomor_spp' => '176', 'nama_customer' => 'Bu Rina']);
        $b = $this->buat(['nomor_spp' => '177', 'nama_customer' => 'Pak Budi', 'nomor_whatsapp' => '0813 1111 2222']);

        $this->sebagai($this->admin)->put(route('admin.pesanan.update', $b), $this->data(['nomor_spp' => '176', 'konfirmasi_ganti_nomor' => '1']))
            ->assertSessionHasErrors(['nomor_spp' => 'SPP #176 sudah dipakai oleh Bu Rina.']);
        $this->assertSame(177, $b->fresh()->nomor_spp);
    }

    public function test_hanya_admin_yang_bisa_mengganti_nomor(): void
    {
        $pesanan = $this->buat(['nomor_spp' => '176']);

        $this->sebagai($this->produksi)->put(route('admin.pesanan.update', $pesanan), $this->data(['nomor_spp' => '177', 'konfirmasi_ganti_nomor' => '1']))
            ->assertForbidden();
        $this->assertSame(176, $pesanan->fresh()->nomor_spp);
    }

    // ── Nomor SPP: dua admin bersamaan ─────────────────

    public function test_duplikat_saat_simpan_bersamaan_menjadi_pesan_validasi_bukan_500(): void
    {
        $lain = Customer::factory()->create(['nama' => 'Toko Sebelah']);

        // Admin lain menyimpan #176 tepat setelah validasi lolos, sebelum insert kita
        Pesanan::creating(function (Pesanan $p) use ($lain) {
            static $sekali = false;
            if ($sekali) {
                return;
            }
            $sekali = true;
            DB::table('pesanan')->insert([
                'nomor_spp' => $p->nomor_spp, 'customer_id' => $lain->id, 'nama_customer' => 'Toko Sebelah',
                'nomor_whatsapp' => $lain->nomor_whatsapp, 'alamat' => '-', 'kota' => '-',
                'tanggal_order' => '2026-11-18', 'deadline' => '2026-11-20', 'jenis_deadline' => 'kirim',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        $this->simpan(['nomor_spp' => '176'])
            ->assertRedirect()
            ->assertSessionHasErrors('nomor_spp');

        Pesanan::flushEventListeners();
        $this->assertSame(0, Pesanan::where('nama_customer', 'Ismun')->count());
    }

    public function test_service_menerjemahkan_pelanggaran_indeks_unik_dengan_nama_pemilik(): void
    {
        Pesanan::factory()->create(['nomor_spp' => 176, 'nama_customer' => 'Toko Sebelah']);

        $data = [
            'nomor_spp' => 176, 'nama_customer' => 'Ismun', 'nomor_whatsapp' => '6281234567890', 'alamat' => 'x', 'kota' => 'y',
            'tanggal_order' => '2026-11-18', 'deadline' => '2026-11-30', 'jenis_deadline' => 'kirim', 'dp' => 0,
        ];
        $item = [['jenis_packaging' => 'Box', 'jenis_bahan' => 'Duplex', 'jumlah' => 1, 'harga_satuan' => 1000]];

        try {
            app(PesananService::class)->buat($data, $item, [], $this->admin);
            $this->fail('Seharusnya ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(['nomor_spp' => ['SPP #176 sudah dipakai oleh Toko Sebelah.']], $e->errors());
        }

        $this->assertSame(1, Pesanan::count());
        $this->assertSame(0, Customer::where('nomor_whatsapp', '6281234567890')->count()); // ikut di-rollback
    }

    // ── Customer ───────────────────────────────────────

    public function test_customer_dibuat_otomatis_dari_isian_spp(): void
    {
        $pesanan = $this->buat();

        $customer = $pesanan->customer;
        $this->assertNotNull($customer);
        $this->assertSame('Ismun', $customer->nama);
        $this->assertSame('6281234567890', $customer->nomor_whatsapp);
        $this->assertSame('Surakarta', $customer->kota);
        $this->assertSame('C-' . str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT), $customer->kode);
        $this->assertSame(1, Customer::count());
    }

    public function test_customer_dipakai_ulang_lewat_nomor_wa_dan_admin_diberi_tahu(): void
    {
        $lama = Customer::factory()->create(['nama' => 'Ismun Lama', 'nomor_whatsapp' => '6281234567890', 'kota' => 'Solo']);

        $res = $this->simpan(['nomor_whatsapp' => '+62 812-3456-7890']);
        $pesanan = Pesanan::latest('id')->first();

        $this->assertSame($lama->id, $pesanan->customer_id);
        $this->assertSame(1, Customer::count());
        $res->assertSessionHas('info_customer', fn ($p) => str_contains($p, $lama->kode) && str_contains($p, 'Ismun Lama'));
        $res->assertSessionHas('success', fn ($p) => str_starts_with($p, 'SPP #1 berhasil disimpan.') && str_contains($p, 'sudah terdaftar'));

        // Tanpa opsi perbarui, data customer tidak berubah; snapshot SPP memakai isian
        $this->assertSame('Ismun Lama', $lama->fresh()->nama);
        $this->assertSame('Ismun', $pesanan->nama_customer);

        // Dengan opsi perbarui
        $this->buat(['nama_customer' => 'Ismun Baru', 'kota' => 'Klaten', 'perbarui_customer' => '1']);
        $this->assertSame('Ismun Baru', $lama->fresh()->nama);
        $this->assertSame('Klaten', $lama->fresh()->kota);
    }

    public function test_memilih_customer_yang_ada_lewat_customer_id(): void
    {
        $c = Customer::factory()->create(['nama' => 'CV Alea', 'nomor_whatsapp' => '6285700001111']);

        // WA isian berbeda & belum dipakai siapa pun → tetap customer yang dipilih
        $pesanan = $this->buat(['customer_id' => (string) $c->id, 'nomor_whatsapp' => '0812 9999 0000']);
        $this->assertSame($c->id, $pesanan->customer_id);
        $this->assertSame('6285700001111', $c->fresh()->nomor_whatsapp);

        // perbarui_customer ikut mengganti WA customer bila nomornya bebas
        $this->buat(['customer_id' => (string) $c->id, 'nomor_whatsapp' => '0812 9999 0000', 'perbarui_customer' => '1']);
        $this->assertSame('6281299990000', $c->fresh()->nomor_whatsapp);

        $this->simpan(['customer_id' => '999999'])->assertSessionHasErrors(['customer_id' => 'Customer yang dipilih tidak ditemukan. Pilih ulang atau kosongkan.']);
    }

    public function test_snapshot_spp_tidak_berubah_saat_customer_diedit(): void
    {
        $pesanan = $this->buat();
        $customer = $pesanan->customer;

        $this->sebagai($this->admin)->put(route('admin.customer.update', $customer), [
            'nama' => 'Ismun Ganti', 'nomor_whatsapp' => '0899 1234 5678', 'alamat' => 'Jl. Baru', 'kota' => 'Klaten',
        ])->assertRedirect(route('admin.customer.show', $customer))->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('Ismun Ganti', $customer->nama);
        $this->assertSame('6289912345678', $customer->nomor_whatsapp);

        $pesanan->refresh();
        $this->assertSame('Ismun', $pesanan->nama_customer);
        $this->assertSame('6281234567890', $pesanan->nomor_whatsapp);
        $this->assertSame('Jl. Melati 5', $pesanan->alamat);
        $this->assertSame('Surakarta', $pesanan->kota);
    }

    public function test_edit_customer_validasi_wa_unik_setelah_normalisasi(): void
    {
        $a = Customer::factory()->create(['nomor_whatsapp' => '6281234567890', 'nama' => 'A']);
        $b = Customer::factory()->create(['nomor_whatsapp' => '6281111111111', 'nama' => 'B']);

        $this->sebagai($this->admin)->put(route('admin.customer.update', $b), ['nama' => 'B', 'nomor_whatsapp' => '0812-3456-7890'])
            ->assertSessionHasErrors(['nomor_whatsapp' => "Nomor WhatsApp ini sudah dipakai customer {$a->kode} (A)."]);

        // Nomor sendiri boleh
        $this->sebagai($this->admin)->put(route('admin.customer.update', $b), ['nama' => 'B2', 'nomor_whatsapp' => '081111111111'])
            ->assertSessionHasNoErrors();
    }

    public function test_autocomplete_customer_khusus_admin_dan_maksimal_10(): void
    {
        $rina = Customer::factory()->create(['nama' => 'Bu Rina', 'nomor_whatsapp' => '6281234567890']);
        Customer::factory()->count(12)->sequence(fn ($s) => ['nama' => 'Toko Rina ' . $s->index])->create();

        $url = fn ($q) => route('admin.customer.cari', ['q' => $q]);

        $this->sebagai($this->admin)->getJson($url('rina'))->assertOk()->assertJsonCount(10, 'data');

        $this->sebagai($this->admin)->getJson($url('0812 3456'))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $rina->id)
            ->assertJsonPath('data.0.kode', $rina->kode)
            ->assertJsonPath('data.0.nomor_whatsapp', '6281234567890')
            ->assertJsonPath('data.0.jumlah_spp', 0);

        $this->sebagai($this->admin)->getJson($url($rina->kode))->assertJsonPath('data.0.id', $rina->id);
        $this->sebagai($this->admin)->getJson($url(''))->assertExactJson(['data' => []]);

        $this->sebagai($this->produksi)->getJson($url('rina'))->assertForbidden();
    }

    public function test_halaman_customer_khusus_admin(): void
    {
        $pesanan = $this->buat(['nomor_spp' => '176']);
        $c = $pesanan->customer;

        foreach ([route('admin.customer.index'), route('admin.customer.show', $c), route('admin.customer.edit', $c)] as $url) {
            $this->sebagai($this->produksi)->get($url)->assertRedirect(route('admin.pesanan.index'));
        }
        $this->sebagai($this->produksi)->put(route('admin.customer.update', $c), ['nama' => 'X', 'nomor_whatsapp' => '081234567890'])->assertForbidden();

        $this->sebagai($this->admin)->get(route('admin.customer.index', ['q' => 'ismun']))
            ->assertOk()->assertSee($c->kode)->assertSee('#176');
        $this->sebagai($this->admin)->get(route('admin.customer.show', $c))
            ->assertOk()->assertSee('SPP #176')->assertViewHas('bisaHapus', false);
        $this->sebagai($this->admin)->get(route('admin.customer.edit', $c))->assertOk();
    }

    public function test_customer_hanya_bisa_dihapus_bila_belum_punya_spp(): void
    {
        $pesanan = $this->buat();
        $pesanan->delete(); // soft delete tetap dihitung

        $this->sebagai($this->admin)->delete(route('admin.customer.destroy', $pesanan->customer_id))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('customer', ['id' => $pesanan->customer_id]);

        $kosong = Customer::factory()->create();
        $this->sebagai($this->admin)->delete(route('admin.customer.destroy', $kosong))->assertRedirect(route('admin.customer.index'));
        $this->assertDatabaseMissing('customer', ['id' => $kosong->id]);
    }

    // ── Brand & jenis deadline ─────────────────────────

    public function test_nama_brand_dan_jenis_deadline_disimpan_dan_divalidasi(): void
    {
        $p = $this->buat(['nama_brand' => '  Alea Wedding  ', 'jenis_deadline' => 'siap']);
        $this->assertSame('Alea Wedding', $p->nama_brand);
        $this->assertSame('siap', $p->jenis_deadline);
        $this->assertSame('Siap', $p->label_jenis_deadline);

        $this->assertNull($this->buat(['nama_brand' => ''])->nama_brand);

        $this->simpan(['jenis_deadline' => 'besok'])->assertSessionHasErrors(['jenis_deadline' => 'Pilih jenis deadline: Siap atau Kirim.']);
        $tanpa = $this->data();
        unset($tanpa['jenis_deadline']);
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $tanpa)->assertSessionHasErrors('jenis_deadline');

        // Kolom default "kirim"
        $this->assertSame('kirim', Pesanan::factory()->create()->fresh()->jenis_deadline);
    }

    // ── Teks salin SPP ─────────────────────────────────

    public function test_teks_spp_satu_item_sesuai_format(): void
    {
        $p = $this->buat(['nomor_spp' => '175']);

        $harapan = "📋 SPP | #175\n\n"
            . "Nama Customer: Ismun / paperbag alea\n"
            . "Produk: Paperbag\n"
            . "Ukuran: 33 x 29 x 15\n"
            . "Jumlah: 500 pcs\n"
            . "Bahan: kertas kraft putih 150gr + tanpa laminasi + cetak 2 sisi sama + tali lilit\n\n"
            . "📌Harga : Rp4.300 /pcs\n"
            . "📅 Deadline: 30 nov kirim\n\n"
            . "File Desain: https://drive.google.com/drive/folders/abc\n"
            . 'Detail SPP: ' . route('admin.pesanan.show', $p);

        $this->assertSame($harapan, $p->teks_spp);
        $this->assertSame('https://wa.me/?text=' . rawurlencode($harapan), $p->link_whatsapp_spp);
    }

    public function test_teks_spp_banyak_item_jadi_blok_bernomor(): void
    {
        $p = $this->buat([
            'nomor_spp' => '176', 'jenis_deadline' => 'siap', 'nama_brand' => '',
            'item' => [1 => [
                'jenis_packaging' => 'Box', 'jenis_bahan' => 'Duplex', 'gramasi' => '', 'jumlah' => '1.000', 'harga_satuan' => '2500',
                'panjang' => '12.5', 'lebar' => '10',
            ]],
        ]);

        $harapan = "📋 SPP | #176\n\n"
            . "Nama Customer: Ismun\n\n"
            . "1. Produk: Paperbag\n"
            . "Ukuran: 33 x 29 x 15\n"
            . "Jumlah: 500 pcs\n"
            . "Bahan: kertas kraft putih 150gr + tanpa laminasi + cetak 2 sisi sama + tali lilit\n"
            . "📌Harga : Rp4.300 /pcs\n\n"
            . "2. Produk: Box\n"
            . "Ukuran: 12,5 x 10\n"
            . "Jumlah: 1.000 pcs\n"
            . "Bahan: Duplex\n"
            . "📌Harga : Rp2.500 /pcs\n\n"
            . "📅 Deadline: 30 nov siap\n\n"
            . "File Desain: https://drive.google.com/drive/folders/abc\n"
            . 'Detail SPP: ' . route('admin.pesanan.show', $p);

        $this->assertSame($harapan, $p->teks_spp);
    }

    public function test_teks_spp_field_kosong_tidak_menyisakan_baris_kosong(): void
    {
        $data = $this->data(['nama_brand' => '', 'link_desain' => '', 'deadline' => '2027-01-05']);
        $data['item'] = [[
            'jenis_packaging' => 'Paperbag', 'jenis_bahan' => 'kertas kraft putih', 'panjang' => '', 'lebar' => '', 'tinggi' => '',
            'gramasi' => '', 'jenis_cetak' => '', 'aksesori' => '', 'jumlah' => '500', 'harga_satuan' => '0',
        ]];
        $this->sebagai($this->admin)->post(route('admin.pesanan.store'), $data)->assertSessionHasNoErrors();
        $p = Pesanan::latest('id')->firstOrFail();

        $harapan = "📋 SPP | #1\n\n"
            . "Nama Customer: Ismun\n"
            . "Produk: Paperbag\n"
            . "Jumlah: 500 pcs\n"
            . "Bahan: kertas kraft putih\n\n"
            . "📅 Deadline: 5 jan 2027 kirim\n\n"
            . 'Detail SPP: ' . route('admin.pesanan.show', $p);

        $this->assertSame($harapan, $p->teks_spp);
        $this->assertStringNotContainsString("\n\n\n", $p->teks_spp);
    }

    public function test_teks_spp_tersedia_di_halaman_detail_untuk_admin_dan_produksi(): void
    {
        $p = $this->buat(['nomor_spp' => '175']);

        foreach ([$this->admin, $this->produksi] as $pengguna) {
            $this->sebagai($pengguna)->get(route('admin.pesanan.show', $p))
                ->assertOk()
                ->assertViewHas('teksSpp', $p->teks_spp)
                ->assertViewHas('linkWhatsappSpp', $p->link_whatsapp_spp)
                ->assertSee('📋 SPP | #175');
        }
    }
}

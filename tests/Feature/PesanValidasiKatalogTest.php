<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Bahan;
use App\Models\Banner;
use App\Models\Cetak;
use App\Models\Finishing;
use App\Models\KategoriProduk;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pesan validasi form katalog harus berbahasa Indonesia dengan label yang ramah,
 * walaupun APP_LOCALE masih "en".
 */
class PesanValidasiKatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = Admin::factory()->create();
        $this->withSession(['admin_logged_in' => true, 'admin_id' => $admin->id]);
    }

    public function test_produk_pesan_wajib_isi_berbahasa_indonesia(): void
    {
        $this->post(route('admin.produk.store'), ['minimal_pesan' => 0])
            ->assertSessionHasErrors([
                'nama_produk'   => 'Nama produk wajib diisi.',
                'kategori_id'   => 'Kategori wajib dipilih.',
                'minimal_pesan' => 'Minimal pesan minimal 1.',
            ]);

        $kategori = KategoriProduk::create(['nama_kategori' => 'Brosur', 'slug' => 'brosur']);
        $produk   = Produk::create([
            'nama_produk' => 'Brosur A4', 'slug' => 'brosur-a4',
            'kategori_id' => $kategori->id, 'minimal_pesan' => 1,
        ]);

        $this->post(route('admin.produk.ukuran.tambah', $produk), ['panjang' => 'abc'])
            ->assertSessionHasErrors([
                'nama_ukuran' => 'Nama ukuran wajib diisi.',
                'panjang'     => 'Panjang harus berupa angka.',
            ]);
    }

    public function test_kategori_pesan_wajib_isi_berbahasa_indonesia(): void
    {
        $this->post(route('admin.kategori-produk.store'), [])
            ->assertSessionHasErrors(['nama_kategori' => 'Nama kategori wajib diisi.']);
    }

    public function test_banner_pesan_indonesia_dan_urutan_galat_mengikuti_form(): void
    {
        $this->post(route('admin.banner.store'), ['tautan' => 'bukan url'])
            ->assertSessionHasErrors([
                'judul'         => 'Judul wajib diisi.',
                'tautan'        => 'Tautan harus berupa URL yang valid.',
                'gambar'        => 'Gambar desktop wajib diunggah.',
                'gambar_mobile' => 'Gambar HP wajib diunggah.',
            ]);

        // Ringkasan galat berurutan sama dengan form: judul, tautan, gambar desktop, gambar HP
        $this->assertSame(
            ['judul', 'tautan', 'gambar', 'gambar_mobile'],
            session('errors')->getBag('default')->keys()
        );

        $banner = Banner::create(['judul' => 'Lama', 'gambar' => 'banner/a.webp', 'aktif' => true]);
        $this->put(route('admin.banner.update', $banner), [])
            ->assertSessionHasErrors([
                'judul'         => 'Judul wajib diisi.',
                'gambar_mobile' => 'Gambar HP wajib diunggah.',
            ]);
    }

    public function test_bahan_pesan_wajib_isi_berbahasa_indonesia(): void
    {
        $this->post(route('admin.bahan.store'), [])
            ->assertSessionHasErrors(['nama_bahan' => 'Nama bahan wajib diisi.']);

        $bahan = Bahan::create(['nama_bahan' => 'Art Paper']);
        $this->put(route('admin.bahan.update', $bahan), ['nama_bahan' => str_repeat('a', 256)])
            ->assertSessionHasErrors(['nama_bahan' => 'Nama bahan maksimal 255 karakter.']);
    }

    public function test_finishing_pesan_wajib_isi_berbahasa_indonesia(): void
    {
        $this->post(route('admin.finishing.store'), [])
            ->assertSessionHasErrors(['nama_finishing' => 'Nama finishing wajib diisi.']);

        $finishing = Finishing::create(['nama_finishing' => 'Laminasi Doff']);
        $this->put(route('admin.finishing.update', $finishing), [])
            ->assertSessionHasErrors(['nama_finishing' => 'Nama finishing wajib diisi.']);
    }

    public function test_cetak_pesan_wajib_isi_berbahasa_indonesia(): void
    {
        $this->post(route('admin.cetak.store'), [])
            ->assertSessionHasErrors(['nama_cetak' => 'Nama jenis cetak wajib diisi.']);

        $cetak = Cetak::create(['nama_cetak' => 'Full Color']);
        $this->put(route('admin.cetak.update', $cetak), [])
            ->assertSessionHasErrors(['nama_cetak' => 'Nama jenis cetak wajib diisi.']);
    }
}
